#!/usr/bin/env php
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
    echo "  serve             Start the high-performance server (RoadRunner)\n";
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
    case 'serve':
        start_server();
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
        $baseUri = "/api/{$resourceName}";

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->getName();
            preg_match('/^(get|post|put|delete)(.*)$/i', $methodName, $matches);

            if (empty($matches)) continue;

            $httpMethod = strtolower($matches[1]);
            $actionPath = strtolower($matches[2]);

            $uri = "{$baseUri}/{$actionPath}";

            $methodParams = $method->getParameters();
            foreach($methodParams as $param) {
                $uri .= "/{{$param->getName()}}";
            }

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

            $openapi['paths'][$uri][$httpMethod] = $pathItem;
        }
    }

    file_put_contents($outputFile, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "API documentation generated successfully at: {$outputFile}\n";
}

// ... other functions ...
function make_api_resource(string $name) { /* ... */ }
function run_migrations() { /* ... */ }
function ensure_migrations_table_exists(DatabaseDriverInterface $db): void { /* ... */ }
function start_server()
{
    $rr_path = __DIR__ . '/rr';
    $config_path = __DIR__ . '/.roadrunner.yaml';

    if (!file_exists($rr_path) || !file_exists($config_path)) {
        echo "RoadRunner executable or config not found.\n";
        echo "Please install RoadRunner to use the 'serve' command:\n";
        echo "1. Download the server binary: https://github.com/roadrunner-server/roadrunner/releases\n";
        echo "2. Place it in the project root as 'rr'.\n";
        echo "3. Create a '.roadrunner.yaml' file in the project root (a default one will be created for you now).\n";

        if (!file_exists($config_path)) {
            $default_config = <<<YAML
rpc:
  listen: tcp://127.0.0.1:6001

server:
  command: "php server.php"
  relay: "pipes"

http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 1
YAML;
            file_put_contents($config_path, $default_config);
            echo "Created default '.roadrunner.yaml'. Please configure it if needed.\n";
        }
        exit(1);
    }

    echo "Starting RoadRunner server on http://127.0.0.1:8080\n";
    passthru(PHP_BINARY . " {$rr_path} serve");
}

function get_class_from_file(string $path): ?string { /* ... */ }