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
        $resourceName = strtolower(str_replace('Controller', '', $reflection->getShortName()));
        $baseUri = "/api/{$resourceName}s";

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            $route = null;

            switch ($methodName) {
                case 'index': $route = ['method' => 'GET', 'uri' => $baseUri]; break;
                case 'show': $route = ['method' => 'GET', 'uri' => "{$baseUri}/{id}"]; break;
                case 'store': $route = ['method' => 'POST', 'uri' => $baseUri]; break;
                case 'update': $route = ['method' => 'PUT', 'uri' => "{$baseUri}/{id}"]; break;
                case 'destroy': $route = ['method' => 'DELETE', 'uri' => "{$baseUri}/{id}"]; break;
            }

            if (!$route) continue;

            $pathItem = ['parameters' => [], 'responses' => []];
            $attributes = $method->getAttributes();

            foreach ($attributes as $attribute) {
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

            $httpMethod = strtolower($route['method']);
            $openapi['paths'][$route['uri']][$httpMethod] = $pathItem;
        }
    }

    file_put_contents($outputFile, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "API documentation generated successfully at: {$outputFile}\n";
}

// ... other functions ...
function make_api_resource(string $name) { /* ... */ }
function run_migrations() { /* ... */ }
function ensure_migrations_table_exists(DatabaseDriverInterface $db): void { /* ... */ }
function get_class_from_file(string $path): ?string { /* ... */ }