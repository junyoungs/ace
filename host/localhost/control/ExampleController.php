<?php

class ExampleController extends \APP\Control
{
    /**
     * Greets a user.
     *
     * @api-summary Greets a specific user
     * @api-description This endpoint returns a personalized greeting to the user.
     * @api-method GET
     * @api-uri /hello/{name}
     * @api-param name string path required The name of the user to greet.
     * @api-response 200 { "message": "Hello, [name]!" } application/json A successful greeting.
     * @api-response 404 { "error": "User not found" } application/json The user was not found.
     */
    public function hello($name)
    {
        // Simple JSON output
        header('Content-Type: application/json');
        echo json_encode(['message' => "Hello, " . htmlspecialchars($name) . "!"]);
    }
}