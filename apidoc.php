<?php declare(strict_types=1);

/**
 * API Documentation Generator using PHP 8 Attributes
 */

// Bootstrap the framework for CLI environment
require __DIR__ . '/bootstrap/app.php';

// --- Autoload Attribute Classes ---
// This is a temporary solution until a proper autoloader is in place.
spl_autoload_register(function ($class) {
    $prefix = 'APP\\Attributes\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/app/Attributes/' . str_replace($prefix, '', $class) . '.php';

    // This logic is a bit brittle, assumes one class per file for simplicity
    // or known multi-class files.
    if (file_exists($file)) {
        require_once $file;
    } else if (file_exists(__DIR__ . '/app/Attributes/ApiOperation.php')) {
        require_once __DIR__ . '/app/Attributes/ApiOperation.php';
    } else if (file_exists(__DIR__ . '/app/Attributes/ApiParameter.php')) {
        require_once __DIR__ . '/app/Attributes/ApiParameter.php';
    } else if (file_exists(__DIR__ . '/app/Attributes/ApiResponse.php')) {
        require_once __DIR__ . '/app/Attributes/ApiResponse.php';
    }
});
// --- End Autoload ---


echo "Starting API documentation generation...\n";

// We need to define HOST for HOSTPATH to work correctly.
if (!defined('HOST')) {
    define('HOST', 'localhost');
}

$controllerPath = HOSTPATH . DIRECTORY_SEPARATOR . 'control';
$outputDir = __DIR__ . '/public';
$outputFile = $outputDir . '/openapi.json';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

$openapi = [
    'openapi' => '3.0.0',
    'info' => [
        'title' => 'Custom Framework API',
        'version' => '1.0.0',
        'description' => 'API documentation for the custom PHP framework.'
    ],
    'paths' => []
];

if (!is_dir($controllerPath)) {
    echo "Controller path not found: {$controllerPath}\n";
    exit(1);
}

$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerPath));
$phpFiles = new \RegexIterator($files, '/\.php$/');

\APP\App::loadAbstract('control');

foreach ($phpFiles as $phpFile) {
    require_once $phpFile->getRealPath();
    $content = file_get_contents($phpFile->getRealPath());
    preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches);
    if (!isset($matches[1])) continue;

    $className = $matches[1];
    if (!class_exists($className, false)) continue;

    $reflection = new \ReflectionClass($className);

    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        $attributes = $method->getAttributes();
        if (empty($attributes)) continue;

        $pathItem = ['parameters' => [], 'responses' => []];
        $routeInfo = null;

        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            switch (get_class($instance)) {
                case 'APP\Attributes\Route':
                    $routeInfo = $instance;
                    break;
                case 'APP\Attributes\Summary':
                    $pathItem['summary'] = $instance->summary;
                    break;
                case 'APP\Attributes\Description':
                    $pathItem['description'] = $instance->description;
                    break;
                case 'APP\Attributes\Param':
                    $pathItem['parameters'][] = [
                        'name' => $instance->name,
                        'in' => $instance->in,
                        'description' => $instance->description,
                        'required' => $instance->required,
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

        if ($routeInfo) {
            $httpMethod = strtolower($routeInfo->method);
            $openapi['paths'][$routeInfo->uri][$httpMethod] = $pathItem;
        }
    }
}

file_put_contents($outputFile, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "API documentation generated successfully at: {$outputFile}\n";