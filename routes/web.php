<?php

use CORE\Route;

/**
 * --------------------------------------------------------------------
 * Web Routes
 * --------------------------------------------------------------------
 *
 * Here is where you can register web routes for your application.
 *
 * Route::get('/', 'HomeController@index');
 * Route::post('/users', 'UserController@store');
 */

// Example Route
Route::get('/', function() {
    echo "<h1>Welcome to the framework!</h1>";
    echo "<p>This is the default route.</p>";
});

Route::get('/hello/{name}', 'ExampleController@hello');

Route::get('/api/docs', function() {
    // This provides a simple way to view the API documentation.
    header('Content-Type: text/html');
    $docPath = __DIR__ . '/../public/api-docs.html';
    if (file_exists($docPath)) {
        readfile($docPath);
    } else {
        http_response_code(404);
        echo "API documentation file not found. Please run 'php apidoc.php' to generate it.";
    }
});