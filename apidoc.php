<?php

/**
 * Simple API Documentation Generator
 * Scans controller files for @api-* annotations and generates an openapi.json file.
 */

// --- Bootstrap The Framework (minimal) ---
define('WORKSPATH', __DIR__);
define('PROJECTPATH', __DIR__);
$_SERVER['HTTP_HOST'] = 'localhost'; // Assume localhost for CLI
require_once WORKSPATH . DIRECTORY_SEPARATOR . 'func' . DIRECTORY_SEPARATOR . 'default.php';
\setRequire(WORKSPATH . DIRECTORY_SEPARATOR . 'boot' . DIRECTORY_SEPARATOR . 'boot.php');
\setRequire(WORKSPATH . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR . 'core.php');
\setRequire(WORKSPATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'app.php');
// --- End Bootstrap ---

echo "Starting API documentation generation...\n";

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

$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerPath));
$phpFiles = new \RegexIterator($files, '/\.php$/');

// Ensure the base Control class is loaded so that child controllers can be reflected.
\APP\App::loadAbstract('control');

foreach ($phpFiles as $phpFile) {
    // Need to include the file to make Reflection work on non-autoloaded classes
    require_once $phpFile->getRealPath();

    $content = file_get_contents($phpFile->getRealPath());
    preg_match('/class\s+([a-zA-Z0-9_]+)/', $content, $matches);
    if (!isset($matches[1])) continue;

    $className = $matches[1];
    if (!class_exists($className, false)) {
        continue; // Skip if class is not defined in the file
    }

    $reflection = new \ReflectionClass($className);

    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        $docComment = $method->getDocComment();
        if (!$docComment) continue;

        preg_match_all('/@api-([a-zA-Z]+)\s+(.*)/', $docComment, $apiMatches, PREG_SET_ORDER);
        if (empty($apiMatches)) continue;

        $pathItem = [];
        $uri = '';
        $httpMethod = '';

        foreach ($apiMatches as $match) {
            $key = strtolower($match[1]);
            $value = trim($match[2]);

            switch ($key) {
                case 'uri':
                    $uri = $value;
                    break;
                case 'method':
                    $httpMethod = strtolower($value);
                    break;
                case 'summary':
                    $pathItem['summary'] = $value;
                    break;
                case 'description':
                    $pathItem['description'] = $value;
                    break;
                case 'param':
                    // e.g., name string path required The name...
                    list($pName, $pType, $pIn, $pRequired, $pDesc) = preg_split('/\s+/', $value, 5);
                    $pathItem['parameters'][] = [
                        'name' => $pName,
                        'in' => $pIn,
                        'description' => $pDesc,
                        'required' => ($pRequired === 'required'),
                        'schema' => ['type' => $pType]
                    ];
                    break;
                case 'response':
                    // e.g., 200 { "message": "..." } application/json A successful response.
                    list($rCode, $rExample, $rType, $rDesc) = preg_split('/\s+/', $value, 4);
                     $pathItem['responses'][$rCode] = [
                        'description' => $rDesc,
                        'content' => [
                            $rType => [
                                'schema' => [ 'type' => 'object' ],
                                'example' => json_decode($rExample)
                            ]
                        ]
                    ];
                    break;
            }
        }

        if ($uri && $httpMethod) {
            $openapi['paths'][$uri][$httpMethod] = $pathItem;
        }
    }
}

file_put_contents($outputFile, json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "API documentation generated successfully at: {$outputFile}\n";