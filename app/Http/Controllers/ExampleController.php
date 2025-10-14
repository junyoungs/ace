<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Attributes\Route;
use APP\Attributes\Summary;
use APP\Attributes\Description;
use APP\Attributes\Param;
use APP\Attributes\Response;

class ExampleController extends \APP\Control
{
    #[Route('/hello/{name}', method: 'GET')]
    #[Summary('Greets a specific user')]
    #[Description('This endpoint returns a personalized greeting to the user.')]
    #[Param('name', 'string', 'path', true, 'The name of the user to greet.')]
    #[Response(200, 'A successful greeting.', exampleJson: '{ "message": "Hello, [name]!" }')]
    public function hello(string $name): array
    {
        return ['message' => "Hello, " . htmlspecialchars($name) . "!"];
    }

    #[Route('/users/create', method: 'POST')]
    #[Summary('Creates a new user')]
    #[Description('This endpoint simulates creating a new user and then broadcasts a `user.created` event via Redis Pub/Sub, which can be picked up by a WebSocket server.')]
    #[Param('name', 'string', 'body', true, 'The name of the new user.')]
    #[Param('email', 'string', 'body', true, 'The email of the new user.')]
    #[Response(201, 'User created and event broadcasted.', exampleJson: '{ "status": "success", "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" } }')]
    public function createUser(): array
    {
        // In a real app, you would get this from the request body, e.g., $this->input->post('name');
        $name = 'Jane Doe';
        $email = 'jane@example.com';

        // 1. Create user in the database (simulation)
        $newUser = ['id' => rand(1, 1000), 'name' => $name, 'email' => $email];
        // \APP\Models\User::insert($newUser);

        // 2. Publish an event
        \CORE\Event::publish('user.created', $newUser);

        // 3. Return a response
        return ['status' => 'success', 'user' => $newUser];
    }
}