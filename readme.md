# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. Our core philosophy is **Absolute Simplicity**.

## Core Concepts & Folder Structure

The entire framework is designed to be intuitive and easy to understand.

- **`/ace`**: Contains all the core framework files. You'll probably never need to touch this.
- **`/app`**: This is where your application code lives.
  - `Controllers/`: Handles incoming HTTP requests and returns responses.
  - `Models/`: Represents your database tables and handles data interaction.
  - `Services/`: Contains your core business logic, keeping your controllers thin.
  - `Middleware/`: Contains HTTP middleware for tasks like authentication.
- **`/database/migrations`**: Where your database schema changes are stored.
- **`/public`**: The web server's document root and your application's entry point (`index.php`).
- **`ace.php`**: The command-line interface for the framework.
- **`.env`**: All your application's configuration, like database credentials, is stored here.

## Key Features

- **Zero-Config Routing**: Just name your controller methods (e.g., `getIndex`) and your API endpoints are live instantly.
- **Automatic Code Generation**: A powerful CLI (`./ace`) to create full CRUD API resources (Model, Service, Controller, Migration) with a single command.
- **Intelligent Models**: Your models come with built-in CRUD methods (`getAll`, `find`, `create`, `update`, `delete`).
- **Service Layer Architecture**: A clean architecture that separates business logic (`Services`) from HTTP logic (`Controllers`).
- **Middleware Support**: Protect your routes with middleware, perfect for handling authentication and other cross-cutting concerns.
- **Effortless JSON Responses**: The framework automatically converts controller return values into JSON responses.

## Quick Start: Your First API in 90 Seconds

### 1. Configure Environment & Make CLI Executable
Copy `.env.example` to `.env` and fill in your DB credentials. Then, run `chmod +x ace.php`.

### 2. Generate the API Resource
This command creates the migration, model, service, and controller for a "Post" resource. It will ask for the table name.
```bash
./ace make:api Post
```

### 3. Run the Migration
```bash
./ace migrate
```

### 4. Implement Your Logic
Open `app/Services/PostService.php`. The controller is already wired up to call this service. You only need to fill in the business logic.
```php
// app/Services/PostService.php
public function getAllPosts(): array
{
    // You can add validation, caching, etc. here.
    return Post::getAll();
}
```

### 5. Start the Server & View Docs
Start the development server, then generate your API documentation.
```bash
./ace serve
./ace docs:generate
```
Open your browser and navigate to `http://localhost:8080/api/docs` to see your API.

## Middleware
Middleware can be assigned to groups in `app/Http/Kernel.php`. The router automatically applies the middleware group that matches the subdomain of the request (e.g., `api.domain.com` -> `api` group).

---
*Built with simplicity by ED.*