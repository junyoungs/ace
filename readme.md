# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. We believe that creating an API should be an elegant and straightforward process, free from unnecessary complexity and boilerplate.

Our core philosophy is **Absolute Simplicity**.

## Core Concepts & Folder Structure

The entire framework is designed to be intuitive and easy to understand.

- **`/ace`**: Contains all the core framework files. You'll probably never need to touch this.
- **`/app`**: This is where your application code lives.
  - `Http/Controllers/`: Handles incoming HTTP requests and returns responses.
  - `Models/`: Represents your database tables and handles data interaction.
  - `Services/`: Contains your core business logic, keeping your controllers thin.
- **`/config`**: Holds simple configuration files, like `database.php`.
- **`/database/migrations`**: Where your database schema changes are stored.
- **`/public`**: The web server's document root and your application's entry point (`index.php`).
- **`ace.php`**: The command-line interface for the framework.

## Key Features

- **Zero-Config Routing**: No route files. No route attributes. Just name your controller methods (e.g., `getIndex`, `postStore`) and your API endpoints are live instantly.
- **Automatic Code Generation**: Create full CRUD API resources (Model, Service, Controller, Migration) with a single command.
- **Intelligent Models**: Your models come with built-in CRUD methods (`getAll`, `find`, `create`, `update`, `delete`). No need to write basic SQL for common tasks.
- **Service Layer Architecture**: A clean architecture that separates business logic (`Services`) from HTTP logic (`Controllers`).
- **Effortless JSON Responses**: Just return an array or object from your controller, and ACE automatically converts it to a JSON response.
- **Explicit & Safe SQL**: For complex queries, write clear, explicit SQL that is easy to debug and automatically protected from SQL injection.

## Quick Start: Your First API in 90 Seconds

### 1. Initial Setup
Make the `ace` command-line tool executable (you only need to do this once):
`chmod +x ace.php`

### 2. Generate the API Resource
This command creates the migration, model, service, and controller for a "Post" resource. It will ask for the table name.
```bash
./ace make:api Post
```

### 3. Customize and Run Migration
Edit the new migration file in `database/migrations/` to add your columns, then run it.
```bash
./ace migrate
```

### 4. Implement Your Logic
Open `app/Services/PostService.php`. The controller is already wired up to call the service. You only need to fill in the business logic.
```php
// app/Services/PostService.php
public function getAllPosts(): array
{
    // You can add caching, complex validation, etc. here.
    return Post::getAll();
}
```

### 5. Start the Server & View Docs
Start the development server, then generate your API documentation.
```bash
./ace serve
./ace docs:generate
```
Open your browser and navigate to `http://localhost:8080/api/docs` to see your fully documented, functional CRUD API.

---
*Built with simplicity by ED.*