<?php declare(strict_types=1);

// Bootstrap the framework for CLI environment
require __DIR__ . '/bootstrap/app.php';

use \DATABASE\DatabaseDriverInterface;
use \PDO;

$args = $argv;
array_shift($args);

if (empty($args)) {
    echo "ACE Console Tool\n\n";
    echo "Usage:\n";
    echo "  command [options] [arguments]\n\n";
    echo "Available Commands:\n";
    echo "  migrate           Run the database migrations\n";
    echo "  docs:generate     Generate API documentation\n";
    echo "  make:api [name]   Create a new API resource (Model, Migration, Controller)\n";
    exit(0);
}

$command = $args[0];

switch ($command) {
    case 'migrate':
        run_migrations();
        break;
    case 'docs:generate':
        generate_api_docs();
        break;
    case 'make:api':
        $name = $args[1] ?? null;
        if (!$name) {
            echo "Error: Missing resource name for make:api command.\n";
            exit(1);
        }
        make_api_resource($name);
        break;
    default:
        echo "Error: Command '{$command}' not found.\n";
        exit(1);
}

function make_api_resource(string $name)
{
    echo "Creating API resource for '{$name}'...\n";

    // 1. Prepare names
    $modelName = ucfirst($name);
    $controllerName = $modelName . 'Controller';
    $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
    $variableName = lcfirst($modelName);

    $replacements = [
        '{{className}}' => $controllerName,
        '{{modelName}}' => $modelName,
        '{{tableName}}' => $tableName,
        '{{variableName}}' => $variableName,
    ];

    // 2. Create Migration
    $migrationTimestamp = date('Y_m_d_His');
    $migrationClassName = 'Create' . str_replace(' ', '', ucwords(str_replace('_', ' ', $tableName))) . 'Table';
    $migrationContent = file_get_contents(__DIR__ . '/stubs/migration.create.stub');
    $migrationContent = str_replace('{{className}}', $migrationClassName, $migrationContent);
    $migrationContent = str_replace('{{tableName}}', $tableName, $migrationContent);
    $migrationFileName = "{$migrationTimestamp}_{$migrationClassName}.php";
    file_put_contents(__DIR__ . "/database/migrations/{$migrationFileName}", $migrationContent);
    echo "Created Migration: database/migrations/{$migrationFileName}\n";

    // 3. Create Model
    $modelContent = file_get_contents(__DIR__ . '/stubs/model.stub');
    $modelContent = str_replace('{{className}}', $modelName, $modelContent);
    $modelContent = str_replace('{{tableName}}', $tableName, $modelContent);
    if (!is_dir(__DIR__ . '/app/Models')) mkdir(__DIR__ . '/app/Models', 0755, true);
    file_put_contents(__DIR__ . "/app/Models/{$modelName}.php", $modelContent);
    echo "Created Model: app/Models/{$modelName}.php\n";

    // 4. Create Controller
    $controllerContent = file_get_contents(__DIR__ . '/stubs/controller.api.stub');
    $controllerContent = str_replace(array_keys($replacements), array_values($replacements), $controllerContent);
    if (!is_dir(__DIR__ . '/app/Http/Controllers')) mkdir(__DIR__ . '/app/Http/Controllers', 0755, true);
    file_put_contents(__DIR__ . "/app/Http/Controllers/{$controllerName}.php", $controllerContent);
    echo "Created Controller: app/Http/Controllers/{$controllerName}.php\n";

    echo "API resource for '{$name}' created successfully.\n";
}


function generate_api_docs()
{
    echo "Starting API documentation generation...\n";

    $controllerPath = __DIR__ . '/app/Http/Controllers';
    $outputDir = __DIR__ . '/public';
    $outputFile = $outputDir . '/openapi.json';

    if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

    $openapi = [
        'openapi' => '3.0.0',
        'info' => ['title' => 'ACE Framework API', 'version' => '1.0.0'],
        'paths' => []
    ];

    if (!is_dir($controllerPath)) {
        echo "Controller path not found: {$controllerPath}\n";
        exit(1);
    }

    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerPath));
    $phpFiles = new \RegexIterator($files, '/\.php$/');

    foreach ($phpFiles as $phpFile) {
        $className = get_class_from_file($phpFile->getRealPath());
        if (!$className) continue;

        $reflection = new \ReflectionClass($className);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(\APP\Attributes\Route::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (empty($attributes)) continue;

            $pathItem = ['parameters' => [], 'responses' => []];
            $routeInfo = $attributes[0]->newInstance();

            $otherAttributes = $method->getAttributes();
            foreach ($otherAttributes as $attribute) {
                $instance = $attribute->newInstance();
                switch (get_class($instance)) {
                    case 'APP\Attributes\Summary': $pathItem['summary'] = $instance->summary; break;
                    case 'APP\Attributes\Description': $pathItem['description'] = $instance->description; break;
                    case 'APP\Attributes\Param':
                        $pathItem['parameters'][] = [
                            'name' => $instance->name, 'in' => $instance->in,
                            'description' => $instance->description, 'required' => $instance->required,
                            'schema' => ['type' => $instance->type]
                        ];
                        break;
                    case 'APP\Attributes\Response':
                        $pathItem['responses'][(string)$instance->statusCode] = [
                            'description' => $instance->description,
                            'content' => [
                                $instance->contentType => [
                                    'schema' => ['type' => 'object'],
                                    'example' => $instance->exampleJson ? json_decode($instance->exampleJson) : null
                                ]
                            ]
                        ];
                        break;
                }
            }

            $httpMethod = strtolower($routeInfo->method);
            $openapi['paths'][$routeInfo->uri][$httpMethod] = $pathItem;
        }
    }

    file_put_contents($outputFile, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "API documentation generated successfully at: {$outputFile}\n";
}

function get_class_from_file(string $path): ?string
{
    $content = file_get_contents($path);
    $tokens = token_get_all($content);
    $namespace = '';
    for ($i = 0; $i < count($tokens); $i++) {
        if ($tokens[$i][0] === T_NAMESPACE) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if ($tokens[$j] === ';') {
                    break;
                }
                $namespace .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
            }
        }
        if ($tokens[$i][0] === T_CLASS) {
            for ($j = $i + 1; $j < count($tokens); $j++) {
                if ($tokens[$j] === '{') {
                    $className = $tokens[$i+2][1];
                    return trim($namespace) . '\\' . $className;
                }
            }
        }
    }
    return null;
}

function run_migrations() { /* ... existing code ... */ }
function ensure_migrations_table_exists(DatabaseDriverInterface $db): void { /* ... existing code ... */ }