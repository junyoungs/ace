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

    /**
     * Creates a new user and broadcasts an event.
     *
     * @api-summary Creates a new user
     * @api-description This endpoint simulates creating a new user and then broadcasts a `user.created` event via Redis Pub/Sub, which can be picked up by a WebSocket server.
     * @api-method POST
     * @api-uri /users/create
     * @api-param name string body required The name of the new user.
     * @api-param email string body required The email of the new user.
     * @api-response 201 { "status": "success", "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" } } application/json User created and event broadcasted.
     */
    public function createUser()
    {
        // In a real app, you would get this from the request body, e.g., $this->input->post('name');
        $name = 'Jane Doe';
        $email = 'jane@example.com';

        // 1. Create user in the database (simulation)
        $newUser = ['id' => rand(1, 1000), 'name' => $name, 'email' => $email];
        // \PROJECT\MODEL\User::insert($newUser);

        // 2. Publish an event
        \CORE\Event::publish('user.created', $newUser);

        // 3. Return a response
        header('Content-Type: application/json', true, 201);
        echo json_encode(['status' => 'success', 'user' => $newUser]);
    }
}