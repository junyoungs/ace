<?php declare(strict_types=1);

namespace ACE\Database;

/**
 * Code Generator - Generates Models, Controllers, Services, and Migrations from DBML
 */
class CodeGenerator
{
    private array $tables = [];
    private array $relationships = [];
    private string $basePath;

    public function __construct(string $basePath = BASE_PATH)
    {
        $this->basePath = $basePath;
    }

    public function generate(array $schema, DbmlParser $parser): void
    {
        $this->tables = $schema['tables'];
        $this->relationships = $schema['relationships'];

        $analyzedRelationships = $parser->analyzeRelationships();

        foreach ($this->tables as $tableName => $tableData) {
            $modelName = $this->tableNameToModelName($tableName);
            $relations = $analyzedRelationships[$tableName] ?? [];

            echo "Generating resources for table '{$tableName}'...\n";

            $this->generateMigration($tableName, $tableData);
            $this->generateModel($modelName, $tableName, $tableData, $relations);
            $this->generateService($modelName, $tableName, $tableData, $relations);
            $this->generateController($modelName, $tableName, $tableData, $relations);

            echo "✓ Generated: {$modelName} (Model, Service, Controller, Migration)\n\n";
        }

        echo "All resources generated successfully!\n";
    }

    private function generateMigration(string $tableName, array $tableData): void
    {
        $className = 'Create' . str_replace(' ', '', ucwords(str_replace('_', ' ', $tableName))) . 'Table';
        $columns = $tableData['columns'];
        $indexes = $tableData['indexes'];

        $columnsCode = $this->generateMigrationColumns($columns);
        $indexesCode = $this->generateMigrationIndexes($indexes);
        $foreignKeysCode = $this->generateMigrationForeignKeys($columns);

        $content = <<<PHP
<?php declare(strict_types=1);

/**
 * Migration: {$className}
 * Auto-generated from DBML schema
 * @generated
 */
class {$className}
{
    public function up(\$db): void
    {
        \$sql = "CREATE TABLE {$tableName} (
{$columnsCode}
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        \$db->exec(\$sql);
{$indexesCode}{$foreignKeysCode}
    }

    public function down(\$db): void
    {
        \$db->exec("DROP TABLE IF EXISTS {$tableName}");
    }
}
PHP;

        $filename = date('Y_m_d_His') . "_{$className}.php";
        $path = "{$this->basePath}/database/migrations/{$filename}";

        file_put_contents($path, $content);
        echo "  ✓ Migration: {$filename}\n";

        // Small delay to ensure unique timestamps
        usleep(100000);
    }

    private function generateMigrationColumns(array $columns): string
    {
        $lines = [];

        foreach ($columns as $column) {
            $line = "            {$column['name']} ";
            $line .= $this->getMySQLType($column);

            if (!$column['nullable']) {
                $line .= ' NOT NULL';
            } else {
                $line .= ' NULL';
            }

            if ($column['auto_increment']) {
                $line .= ' AUTO_INCREMENT';
            }

            if ($column['default'] !== null && !$column['auto_increment']) {
                $default = $this->formatDefault($column['default'], $column['type']);
                $line .= " DEFAULT {$default}";
            }

            if ($column['primary_key']) {
                $line .= ',';
                $lines[] = $line;
                $lines[] = "            PRIMARY KEY ({$column['name']})";
                continue;
            }

            $lines[] = $line;
        }

        return implode(",\n", $lines);
    }

    private function getMySQLType(array $column): string
    {
        $type = $column['raw_type'];

        // Handle timestamp
        if ($column['name'] === 'created_at' || $column['name'] === 'updated_at' || $column['name'] === 'deleted_at') {
            return 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' .
                   ($column['name'] === 'updated_at' ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
        }

        return strtoupper($type);
    }

    private function formatDefault($value, string $type): string
    {
        if ($value === 'null') return 'NULL';
        if ($value === 'true') return '1';
        if ($value === 'false') return '0';
        if (is_numeric($value)) return $value;

        return "'{$value}'";
    }

    private function generateMigrationIndexes(array $indexes): string
    {
        if (empty($indexes)) return '';

        $code = "\n";
        foreach ($indexes as $index) {
            $columns = implode(', ', $index['columns']);
            $code .= "        \$db->exec(\"CREATE INDEX {$index['name']} ON {\$tableName} ({$columns})\");\n";
        }

        return $code;
    }

    private function generateMigrationForeignKeys(array $columns): string
    {
        $code = '';

        foreach ($columns as $column) {
            if (isset($column['reference'])) {
                $ref = $column['reference'];
                $fkName = "fk_{$ref['from_table']}_{$ref['from_column']}";
                $code .= "\n        \$db->exec(\"ALTER TABLE {$ref['from_table']} ADD CONSTRAINT {$fkName} ";
                $code .= "FOREIGN KEY ({$ref['from_column']}) REFERENCES {$ref['to_table']}({$ref['to_column']}) ";
                $code .= "ON DELETE CASCADE\");\n";
            }
        }

        return $code;
    }

    private function generateModel(string $modelName, string $tableName, array $tableData, array $relations): void
    {
        $relationMethods = $this->generateRelationshipMethods($relations, $tableName);
        $fillableFields = $this->getFillableFields($tableData['columns']);

        $content = <<<PHP
<?php declare(strict_types=1);

namespace APP\Models;

use ACE\Database\Model;

/**
 * Model: {$modelName}
 * Table: {$tableName}
 * Auto-generated from DBML schema
 * @generated
 */
class {$modelName} extends Model
{
    protected static string \$table = '{$tableName}';

    /**
     * Fields that can be mass-assigned (auto-detected from DBML)
     */
    protected static array \$fillable = [
{$fillableFields}
    ];

    // ========================================
    // Auto-generated Relationship Methods
    // ========================================
{$relationMethods}
    // ========================================
    // Custom Methods (add your logic below)
    // ========================================
}
PHP;

        $path = "{$this->basePath}/app/Models/{$modelName}.php";
        file_put_contents($path, $content);
        echo "  ✓ Model: app/Models/{$modelName}.php\n";
    }

    private function getFillableFields(array $columns): string
    {
        $fillable = [];

        foreach ($columns as $column) {
            $meta = $column['metadata'];

            // Include if it's input mode
            if ($meta['mode'] === 'input') {
                $fillable[] = "        '{$column['name']}'";
            }
        }

        return implode(",\n", $fillable);
    }

    private function generateRelationshipMethods(array $relations, string $tableName): string
    {
        $methods = [];

        // belongsTo relationships
        if (isset($relations['belongsTo'])) {
            foreach ($relations['belongsTo'] as $rel) {
                $methodName = $rel['name'];
                $relatedModel = $this->tableNameToModelName($rel['table']);

                $methods[] = <<<PHP
    /**
     * Belongs to relationship: {$methodName}
     * @return ?array
     */
    public function {$methodName}(int \$id): ?array
    {
        \$row = static::find(\$id);
        if (!\$row || !\$row['{$rel['foreign_key']}']) return null;

        return {$relatedModel}::find(\$row['{$rel['foreign_key']}']);
    }
PHP;
            }
        }

        // hasMany relationships
        if (isset($relations['hasMany'])) {
            foreach ($relations['hasMany'] as $rel) {
                $methodName = $rel['name'];
                $relatedModel = $this->tableNameToModelName($rel['table']);

                $methods[] = <<<PHP
    /**
     * Has many relationship: {$methodName}
     * @return array
     */
    public function {$methodName}(int \$id): array
    {
        return {$relatedModel}::where('{$rel['foreign_key']}', \$id);
    }
PHP;
            }
        }

        return empty($methods) ? "    // No relationships defined\n" : implode("\n\n", $methods);
    }

    private function generateService(string $modelName, string $tableName, array $tableData, array $relations): void
    {
        $serviceName = "{$modelName}Service";
        $varName = lcfirst($modelName);
        $columns = $tableData['columns'];

        $createMethod = $this->generateServiceCreateMethod($modelName, $columns);
        $updateMethod = $this->generateServiceUpdateMethod($modelName, $columns);
        $deleteMethod = $this->generateServiceDeleteMethod($modelName, $columns);
        $relationMethods = $this->generateServiceRelationMethods($modelName, $relations);

        $content = <<<PHP
<?php declare(strict_types=1);

namespace APP\Services;

use APP\Models\\{$modelName};
use ACE\Service\BaseService;

/**
 * Service: {$serviceName}
 * Handles business logic for {$tableName}
 * Auto-generated from DBML schema
 * @generated
 */
class {$serviceName} extends BaseService
{
    // ========================================
    // Auto-generated CRUD Methods
    // ========================================

    public function getAll(array \$filters = []): array
    {
        // TODO: Implement filtering logic
        return {$modelName}::getAll();
    }

    public function findById(int \$id): ?array
    {
        return {$modelName}::find(\$id);
    }

{$createMethod}

{$updateMethod}

{$deleteMethod}

    // ========================================
    // Auto-generated Relationship Methods
    // ========================================
{$relationMethods}

    // ========================================
    // Custom Business Logic (add below)
    // ========================================

    /**
     * Example: Multi-table operation with transaction
     *
     * public function customOperation(array \$data): array
     * {
     *     return \$this->transaction(function() use (\$data) {
     *         // Validate input
     *         \$this->validate(\$data, [
     *             'field1' => 'required',
     *             'field2' => 'required'
     *         ]);
     *
     *         // Create main record
     *         \$mainId = {$modelName}::create(\$data);
     *
     *         // Create related records
     *         // RelatedModel::create([...]);
     *
     *         // Return result
     *         return {$modelName}::find(\$mainId);
     *     });
     * }
     */
}
PHP;

        $path = "{$this->basePath}/app/Services/{$serviceName}.php";
        file_put_contents($path, $content);
        echo "  ✓ Service: app/Services/{$serviceName}.php\n";
    }

    private function generateServiceCreateMethod(string $modelName, array $columns): string
    {
        $autoFields = [];
        $inputFields = [];

        foreach ($columns as $column) {
            $meta = $column['metadata'];

            if ($meta['mode'] === 'auto') {
                if ($meta['auto_type'] === 'server') {
                    $autoFields[$column['name']] = $meta;
                }
            } elseif ($meta['mode'] === 'input') {
                $inputFields[] = $column['name'];
            }
        }

        $autoFieldsCode = '';
        foreach ($autoFields as $fieldName => $meta) {
            if ($meta['auto_source'] === 'uuid') {
                $autoFieldsCode .= "        \$data['{$fieldName}'] = uniqid('', true);\n";
            } elseif (isset($meta['auto_source']) && str_starts_with($meta['auto_source'], 'auth')) {
                $autoFieldsCode .= "        // TODO: Set {$fieldName} from authenticated user\n";
                $autoFieldsCode .= "        // \$data['{$fieldName}'] = \$_SESSION['user_id'] ?? null;\n";
            } elseif ($fieldName === 'slug' && isset($meta['auto_source'])) {
                $sourceField = $meta['auto_source'];
                $autoFieldsCode .= "        \$data['slug'] = \$this->generateSlug(\$data['{$sourceField}'] ?? '');\n";
            }
        }

        return <<<PHP
    public function create(array \$data): array
    {
        // Auto-generate server-side fields
{$autoFieldsCode}
        // Validate required fields
        // TODO: Add validation logic

        \$id = {$modelName}::create(\$data);
        return {$modelName}::find(\$id);
    }
PHP;
    }

    private function generateServiceUpdateMethod(string $modelName, array $columns): string
    {
        return <<<PHP
    public function update(int \$id, array \$data): int
    {
        // TODO: Add validation logic
        return {$modelName}::update(\$id, \$data);
    }
PHP;
    }

    private function generateServiceDeleteMethod(string $modelName, array $columns): string
    {
        // Check if soft delete is enabled
        $hasSoftDelete = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'deleted_at') {
                $hasSoftDelete = true;
                break;
            }
        }

        if ($hasSoftDelete) {
            return <<<PHP
    public function delete(int \$id): int
    {
        // Soft delete
        return {$modelName}::update(\$id, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function forceDelete(int \$id): int
    {
        // Hard delete
        return {$modelName}::delete(\$id);
    }
PHP;
        } else {
            return <<<PHP
    public function delete(int \$id): int
    {
        return {$modelName}::delete(\$id);
    }
PHP;
        }
    }

    private function generateServiceRelationMethods(string $modelName, array $relations): string
    {
        $methods = [];

        if (isset($relations['belongsTo'])) {
            foreach ($relations['belongsTo'] as $rel) {
                $methodName = 'get' . ucfirst($rel['name']);
                $methods[] = <<<PHP
    public function {$methodName}(int \$id): ?array
    {
        \$model = new {$modelName}();
        return \$model->{$rel['name']}(\$id);
    }
PHP;
            }
        }

        if (isset($relations['hasMany'])) {
            foreach ($relations['hasMany'] as $rel) {
                $methodName = 'get' . ucfirst($rel['name']);
                $methods[] = <<<PHP
    public function {$methodName}(int \$id): array
    {
        \$model = new {$modelName}();
        return \$model->{$rel['name']}(\$id);
    }
PHP;
            }
        }

        return empty($methods) ? "    // No relationship methods\n" : implode("\n\n", $methods);
    }

    private function generateController(string $modelName, string $tableName, array $tableData, array $relations): void
    {
        $controllerName = "{$modelName}Controller";
        $serviceName = "{$modelName}Service";
        $serviceVar = lcfirst($serviceName);
        $varName = lcfirst($modelName);

        $relationshipEndpoints = $this->generateControllerRelationshipEndpoints($serviceVar, $relations);

        $content = <<<PHP
<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Services\\{$serviceName};

/**
 * Controller: {$controllerName}
 * Handles HTTP requests for {$tableName}
 * Auto-generated from DBML schema
 * @generated
 */
class {$controllerName} extends \ACE\Http\Control
{
    public function __construct(
        private {$serviceName} \${$serviceVar}
    ) {}

    // ========================================
    // Auto-generated CRUD Endpoints
    // ========================================

    /**
     * GET /api/{$varName}
     * List all {$tableName}
     */
    public function getIndex(): array
    {
        return \$this->{$serviceVar}->getAll();
    }

    /**
     * POST /api/{$varName}/store
     * Create a new {$varName}
     */
    public function postStore(): array
    {
        \$data = \$this->request->getParsedBody();
        return \$this->{$serviceVar}->create(\$data);
    }

    /**
     * GET /api/{$varName}/show/{id}
     * Get a single {$varName}
     */
    public function getShow(int \$id): ?array
    {
        \$result = \$this->{$serviceVar}->findById(\$id);
        if (!\$result) {
            http_response_code(404);
            return ['error' => '{$modelName} not found'];
        }
        return \$result;
    }

    /**
     * PUT /api/{$varName}/update/{id}
     * Update a {$varName}
     */
    public function putUpdate(int \$id): array
    {
        \$data = \$this->request->getParsedBody();
        \$affected = \$this->{$serviceVar}->update(\$id, \$data);

        if (\$affected === 0) {
            http_response_code(404);
            return ['error' => '{$modelName} not found or no changes made'];
        }

        return ['message' => '{$modelName} updated successfully'];
    }

    /**
     * DELETE /api/{$varName}/destroy/{id}
     * Delete a {$varName}
     */
    public function deleteDestroy(int \$id): ?array
    {
        \$affected = \$this->{$serviceVar}->delete(\$id);
        if (\$affected === 0) {
            http_response_code(404);
            return ['error' => '{$modelName} not found'];
        }

        http_response_code(204);
        return null;
    }

    // ========================================
    // Auto-generated Relationship Endpoints
    // ========================================
{$relationshipEndpoints}

    // ========================================
    // Custom Endpoints (add below)
    // ========================================
}
PHP;

        $path = "{$this->basePath}/app/Http/Controllers/{$controllerName}.php";
        file_put_contents($path, $content);
        echo "  ✓ Controller: app/Http/Controllers/{$controllerName}.php\n";
    }

    private function generateControllerRelationshipEndpoints(string $serviceVar, array $relations): string
    {
        $endpoints = [];

        if (isset($relations['belongsTo'])) {
            foreach ($relations['belongsTo'] as $rel) {
                $methodName = 'get' . ucfirst($rel['name']);
                $endpoints[] = <<<PHP
    /**
     * GET /api/{resource}/{$rel['name']}/{id}
     * Get related {$rel['name']}
     */
    public function {$methodName}(int \$id): ?array
    {
        return \$this->{$serviceVar}->{$methodName}(\$id);
    }
PHP;
            }
        }

        if (isset($relations['hasMany'])) {
            foreach ($relations['hasMany'] as $rel) {
                $methodName = 'get' . ucfirst($rel['name']);
                $endpoints[] = <<<PHP
    /**
     * GET /api/{resource}/{$rel['name']}/{id}
     * Get related {$rel['name']}
     */
    public function {$methodName}(int \$id): array
    {
        return \$this->{$serviceVar}->{$methodName}(\$id);
    }
PHP;
            }
        }

        return empty($endpoints) ? "    // No relationship endpoints\n" : implode("\n\n", $endpoints);
    }

    private function tableNameToModelName(string $tableName): string
    {
        // Remove trailing 's' for plural -> singular
        $singular = preg_replace('/s$/', '', $tableName);

        // Convert snake_case to PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $singular)));
    }
}
