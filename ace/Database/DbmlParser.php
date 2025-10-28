<?php declare(strict_types=1);

namespace ACE\Database;

/**
 * DBML Parser - Parses DBML files and extracts schema information
 */
class DbmlParser
{
    private array $tables = [];
    private array $relationships = [];

    public function parse(string $dbmlContent): array
    {
        $this->tables = [];
        $this->relationships = [];

        // Remove comments
        $dbmlContent = preg_replace('/\/\/.*$/m', '', $dbmlContent);
        $dbmlContent = preg_replace('/\/\*.*?\*\//s', '', $dbmlContent);

        // Extract tables
        preg_match_all('/Table\s+(\w+)\s*\{([^}]+)\}/s', $dbmlContent, $tableMatches, PREG_SET_ORDER);

        foreach ($tableMatches as $tableMatch) {
            $tableName = $tableMatch[1];
            $tableBody = $tableMatch[2];

            $this->tables[$tableName] = [
                'name' => $tableName,
                'columns' => $this->parseColumns($tableBody, $tableName),
                'indexes' => $this->parseIndexes($tableBody),
            ];
        }

        return [
            'tables' => $this->tables,
            'relationships' => $this->relationships,
        ];
    }

    private function parseColumns(string $tableBody, string $tableName): array
    {
        $columns = [];
        $lines = explode("\n", $tableBody);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'indexes')) continue;

            // Match column definition: name type [attributes]
            if (preg_match('/^(\w+)\s+(\w+(?:\([^)]+\))?)\s*(\[.*?\])?/s', $line, $match)) {
                $columnName = $match[1];
                $columnType = $match[2];
                $attributes = $match[3] ?? '';

                $column = [
                    'name' => $columnName,
                    'type' => $this->normalizeType($columnType),
                    'raw_type' => $columnType,
                    'nullable' => !str_contains($attributes, 'not null'),
                    'primary_key' => str_contains($attributes, 'pk'),
                    'auto_increment' => str_contains($attributes, 'increment'),
                    'unique' => str_contains($attributes, 'unique'),
                    'default' => $this->extractDefault($attributes),
                    'metadata' => $this->extractMetadata($attributes),
                    'reference' => $this->extractReference($attributes, $tableName, $columnName),
                ];

                $columns[$columnName] = $column;
            }
        }

        return $columns;
    }

    private function normalizeType(string $type): string
    {
        // Remove size specifications for comparison
        $baseType = preg_replace('/\(.*?\)/', '', $type);

        $typeMap = [
            'int' => 'integer',
            'varchar' => 'string',
            'text' => 'text',
            'decimal' => 'decimal',
            'bool' => 'boolean',
            'boolean' => 'boolean',
            'timestamp' => 'timestamp',
            'datetime' => 'datetime',
            'date' => 'date',
            'enum' => 'enum',
        ];

        return $typeMap[$baseType] ?? $type;
    }

    private function extractDefault(string $attributes): ?string
    {
        if (preg_match('/default:\s*[\'"]?([^\],\'"]+)[\'"]?/i', $attributes, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    private function extractMetadata(string $attributes): array
    {
        $metadata = [
            'mode' => 'input', // default
            'required' => false,
            'auto_type' => null,
            'auto_source' => null,
            'validations' => [],
        ];

        // Extract note content
        if (preg_match('/note:\s*[\'"]([^\'"]+)[\'"]/i', $attributes, $match)) {
            $note = $match[1];

            // Parse note directives
            $parts = explode('|', $note);

            foreach ($parts as $part) {
                $part = trim($part);

                // input:required or input:optional
                if (preg_match('/^input:(required|optional)$/i', $part, $m)) {
                    $metadata['mode'] = 'input';
                    $metadata['required'] = strtolower($m[1]) === 'required';
                }
                // auto:db or auto:server
                elseif (preg_match('/^auto:(db|server)(?::(.+))?$/i', $part, $m)) {
                    $metadata['mode'] = 'auto';
                    $metadata['auto_type'] = strtolower($m[1]);

                    if (!empty($m[2])) {
                        // Parse auto source: from=field, uuid, calculated, soft_delete
                        if (str_starts_with($m[2], 'from=')) {
                            $metadata['auto_source'] = substr($m[2], 5);
                        } else {
                            $metadata['auto_source'] = $m[2];
                        }
                    }
                }
                // Validations: min:1, max:5, email, url, etc.
                else {
                    $metadata['validations'][] = $part;
                }
            }
        }

        return $metadata;
    }

    private function extractReference(string $attributes, string $fromTable, string $fromColumn): ?array
    {
        // Match: ref: > table.column or ref: - table.column
        if (preg_match('/ref:\s*([>-])\s*(\w+)\.(\w+)/i', $attributes, $match)) {
            $relationship = [
                'type' => $match[1] === '>' ? 'many_to_one' : 'one_to_many',
                'from_table' => $fromTable,
                'from_column' => $fromColumn,
                'to_table' => $match[2],
                'to_column' => $match[3],
            ];

            // Store for later processing
            $this->relationships[] = $relationship;

            return $relationship;
        }

        return null;
    }

    private function parseIndexes(string $tableBody): array
    {
        $indexes = [];

        // Match indexes block
        if (preg_match('/indexes\s*\{([^}]+)\}/s', $tableBody, $match)) {
            $indexBody = $match[1];
            $lines = explode("\n", $indexBody);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Match: (column1, column2) [name: 'idx_name']
                if (preg_match('/\(([^)]+)\)\s*(?:\[.*?name:\s*[\'"]([^\'"]+)[\'"].*?\])?/', $line, $m)) {
                    $columns = array_map('trim', explode(',', $m[1]));
                    $name = $m[2] ?? 'idx_' . implode('_', $columns);

                    $indexes[] = [
                        'name' => $name,
                        'columns' => $columns,
                    ];
                }
            }
        }

        return $indexes;
    }

    public function getTables(): array
    {
        return $this->tables;
    }

    public function getRelationships(): array
    {
        return $this->relationships;
    }

    public function getTable(string $name): ?array
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * Analyze relationships and generate helper methods
     */
    public function analyzeRelationships(): array
    {
        $analyzed = [];

        if (empty($this->tables)) {
            return $analyzed;
        }

        foreach ($this->relationships as $rel) {
            $fromTable = $rel['from_table'];
            $toTable = $rel['to_table'];

            // Many-to-One (belongsTo): product.category_id -> categories.id
            if ($rel['type'] === 'many_to_one') {
                $analyzed[$fromTable]['belongsTo'][] = [
                    'name' => $this->singularize($toTable),
                    'table' => $toTable,
                    'foreign_key' => $rel['from_column'],
                    'owner_key' => $rel['to_column'],
                ];

                // Inverse: One-to-Many (hasMany): categories.id <- product.category_id
                $analyzed[$toTable]['hasMany'][] = [
                    'name' => $toTable === $fromTable ? 'children' : $fromTable,
                    'table' => $fromTable,
                    'foreign_key' => $rel['from_column'],
                    'local_key' => $rel['to_column'],
                ];
            }
        }

        return $analyzed;
    }

    private function singularize(string $word): string
    {
        // Simple singularization (can be improved)
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }
        return $word;
    }
}
