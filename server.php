<?php declare(strict_types=1);

// This is the entry point for a high-performance application server like RoadRunner.

ini_set('display_errors', 'stderr');

// --- Bootstrap The Application ---
// The container is created once when the server worker starts.
require __DIR__.'/bootstrap/app.php';

use App\App;
use Spiral\RoadRunner;

$worker = RoadRunner\Worker::create();
$psrFactory = new RoadRunner\Http\PSR7Worker(
    $worker,
    new \Laminas\Diactoros\ServerRequestFactory(),
    new \Laminas\Diactoros\StreamFactory(),
    new \Laminas\Diactoros\UploadedFileFactory()
);

// --- Request Handling Loop ---
while ($request = $psrFactory->waitRequest()) {
    try {
        // Pass the request to the application kernel and get a response.
        $response = \ACE\Foundation\App::getInstance()->handle($request);

        // Send the response back to the server.
        $psrFactory->respond($response);
    } catch (\Throwable $e) {
        // If something catastrophic happens, send an error response.
        $psrFactory->getWorker()->error((string)$e);
    }
}