<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Attributes\Summary;
use APP\Attributes\Description;
use APP\Attributes\Param;
use APP\Attributes\Response;

class ExampleController extends \APP\Control
{
    // This controller is now just an example and doesn't map to any
    // automatic routes because its methods don't follow the standard
    // index, show, store, update, destroy naming convention.

    /**
     * This is an example of a non-CRUD method.
     * It won't be automatically routed.
     */
    public function hello(string $name): array
    {
        return ['message' => "Hello, " . htmlspecialchars($name) . "!"];
    }
}