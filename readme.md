# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. We believe that creating an API should be an elegant and straightforward process, free from unnecessary complexity and boilerplate.

Our core philosophy is **Absolute Simplicity**.

## Core Concepts & Folder Structure

The entire framework is designed to be intuitive and easy to understand.

- **`/ace`**: Contains all the core framework files. You'll probably never need to touch this.
- **`/app`**: This is where your application code lives.
  - `Http/Controllers/`: Handles incoming HTTP requests and returns responses.
  - `Models/`: Represents your database tables and handles data interaction.
- **`/database/migrations`**: Where your database schema changes are stored.
- **`/public`**: The web server's document root and your application's entry point (`index.php`).
- **`ace.php`**: The command-line interface for the framework.
- **`.env`**: All your application's configuration, like database credentials, is stored here.

## Key Features

- **Zero-Config Routing**: Just name your controller methods (e.g., `getIndex`) and your API endpoints are live instantly.
- **Automatic Code Generation**: A powerful CLI (`./ace`) to create full CRUD API resources with a single command.
- **Intelligent Models**: Your models come with built-in CRUD methods (`getAll`, `find`, `create`, `update`, `delete`).
- **Effortless JSON Responses**: The framework automatically converts controller return values into JSON responses.
- **Explicit & Safe SQL**: For complex queries, write clear SQL that is easy to debug and automatically protected from SQL injection.

## Quick Start: Your First API in 90 Seconds

### 1. Configure Your Environment
Copy `.env.example` to `.env` and fill in your database credentials.
```bash
cp .env.example .env
nano .env
```

### 2. Make CLI Tool Executable
You only need to do this once:
`chmod +x ace.php`

### 3. Generate the API Resource
This command creates the migration, model, and controller for a "Post" resource.
```bash
./ace make:api Post
```

### 4. Run the Migration
Execute the migration to create the table in your database.
```bash
./ace migrate
```

### 5. Start the Server & View Docs
Start the development server, then generate your API documentation.
```bash
./ace serve
./ace docs:generate
```
Open your browser and navigate to `http://localhost:8080/api/docs` to see your fully documented, functional CRUD API.

## Database Usage: Explicit & Safe

All database interactions are handled through simple, static methods on your Model.

### Fetching Data
```php
// Get all posts
$posts = Post::getAll();
// Get a single post
$post = Post::find($id);
```

### Modifying Data
```php
// Create a new post
Post::create(['title' => 'New Post', 'body' => '...']);
// Update a post
$affected = Post::update($id, ['title' => 'Updated Title']);
```

### Automatic SQL Commenting for Debugging
A key feature of ACE is that every query is automatically commented with the file and line number of its origin, making debugging incredibly easy.
```sql
SELECT * FROM posts WHERE id = ? /*/app/Http/Controllers/PostController.php:25*/
```

---
*Built with simplicity by ED.*