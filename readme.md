# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. We believe that creating an API should be an elegant and straightforward process, free from unnecessary complexity and boilerplate.

Our core philosophy is **Absolute Simplicity**. ACE strips away non-essential features to provide a clean, intuitive, and highly focused development experience. If you need to build a pure API with MySQL or SQLite, ACE is the fastest way to get it done.

## Key Features

- **Zero-Config Routing**: No route files. No route attributes. Just name your controller methods with a `get`, `post`, `put`, or `delete` prefix, and your API endpoints are live instantly.
- **Automatic Code Generation**: Create full CRUD API resources with a single command.
- **Attribute-Based API Docs**: Define your API documentation with simple PHP 8 attributes right above your controller methods.
- **Effortless JSON Responses**: Just return an array or object from your controller, and ACE will automatically convert it to a JSON response with the correct headers and status codes.
- **Explicit & Safe SQL**: No complex query builders. Write clear, explicit SQL queries that are easy to debug and tune. All queries are safely executed using prepared statements to prevent SQL injection.
- **Simple Migrations**: A straightforward CLI-based migration system to manage your database schema.

## Quick Start: Your First API in 90 Seconds

Let's create a complete CRUD API for "posts".

### 1. Generate the API Resource

```bash
./ace make:api Post
```
This command creates the migration, model, and controller for your Post resource.

### 2. Customize and Run Migration

Edit the generated migration file in `database/migrations/` to add your columns, then run it.
```bash
./ace migrate
```

### 3. Review Your Controller

Open `app/Http/Controllers/PostController.php`. You will see that all the database queries are written in plain, easy-to-read SQL.

### 4. Generate and View API Docs

```bash
./ace docs:generate
```
Open your browser and navigate to `/api/docs` to see your fully documented API.

## Database Usage: Explicit & Safe

ACE encourages writing explicit SQL for clarity and performance. All database interactions are handled through two simple, static methods on your Model.

### Fetching Data (`select`)

To run a `SELECT` query and get results, use the `select` method.

```php
// Get all posts
$posts = Post::select("SELECT * FROM posts");

// Get a single post with a binding
$post = Post::select("SELECT * FROM posts WHERE id = ?", [$id]);
```

### Modifying Data (`statement`)

To run `INSERT`, `UPDATE`, or `DELETE` queries, use the `statement` method. It returns the number of affected rows.

```php
// Create a new post
Post::statement(
    "INSERT INTO posts (title, body) VALUES (?, ?)",
    ['New Post', 'Content here...']
);

// Update a post
$affected = Post::statement(
    "UPDATE posts SET title = ? WHERE id = ?",
    ['Updated Title', $id]
);
```

### Automatic SQL Commenting for Debugging

**A key feature of ACE is that it automatically adds a comment to every SQL query, indicating the exact file and line number where the query was executed.** This makes debugging and performance tuning incredibly easy. When you check your database logs, you'll see queries like this:

```sql
SELECT * FROM posts WHERE id = ? /*/app/Http/Controllers/PostController.php:25*/
```

This tells you instantly which line of code generated the query, eliminating guesswork.

## The `ace` Console Tool

For easier use, make the script executable once: `chmod +x ace.php`

- `./ace make:api [Name]`
- `./ace migrate`
- `./ace docs:generate`

---
*Built with simplicity by ED.*