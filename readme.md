# ACE Framework: The Art of Simplicity

**ACE** is a minimalist PHP framework designed for one thing: building powerful, simple, and fast API backends. Our core philosophy is **Absolute Simplicity**. If you need to build a pure API with MySQL or SQLite, ACE is the fastest way to get it done.

## Core Concepts

- **Simple Folder Structure**: The framework lives in `ace/`. Your code lives in `app/`. That's it.
- **Zero-Config Routing**: No route files. No route attributes. Just name your controller methods (e.g., `getIndex`, `postStore`) and your API endpoints are live instantly.
- **Intelligent Models**: Your models come with built-in CRUD methods (`getAll`, `find`, `create`, `update`, `delete`). No need to write basic SQL.
- **Service Layer Architecture**: Keep your controllers thin. Place your business logic in `Service` classes for better organization and reusability.
- **Middleware for Everything Else**: Handle authentication, logging, and more with a simple, powerful middleware system.

## Quick Start: Your First API in 90 Seconds

### 1. Generate the API Resource
This single command creates the migration, model, service, and controller for a "Post" resource. It will ask for the table name.
```bash
./ace make:api Post
```

### 2. Customize and Run Migration
Edit the new migration file in `database/migrations/` to add columns like `title` and `body`, then run it.
```bash
./ace migrate
```

### 3. Implement Your Logic
Open `app/Services/PostService.php`. The controller is already wired up to call the service. You only need to fill in the business logic.
```php
// app/Services/PostService.php
public function getAllPosts(): array
{
    // You can add caching, complex logic, etc. here.
    return Post::getAll();
}
```

### 4. Generate and View API Docs
```bash
./ace docs:generate
```
Open your browser and navigate to `/api/docs` to see your fully documented, functional CRUD API.

## Advanced Usage

### Middleware
Middleware can be assigned to groups in `app/Http/Kernel.php`. The router automatically applies the middleware group that matches the subdomain of the request (e.g., `api.domain.com` -> `api` group).

### Custom SQL
For complex queries not covered by the built-in Model methods, you can always fall back to writing explicit, safe SQL.
```php
Post::select("SELECT * FROM posts WHERE published_at < ? AND is_active = ?", [$now, true]);
```
Every query automatically gets a comment with the file path and line number for easy debugging.

## AI-Driven Development

ACE's simplicity and strict conventions make it exceptionally well-suited for development with AI assistants. The AI can easily understand the framework's structure and generate high-quality, working code with simple prompts.

### Example Prompt
> "I'm using the ACE Framework. In `PostService`, create a new method `getFeaturedPosts` that returns all posts where the `is_featured` column is true."

The AI will understand the framework's conventions and should be able to generate the following method for you, ready to go in your service class. You would then just need to add the corresponding `getFeaturedPosts` method in your controller.

## The `ace` Console Tool
For easier use, make the script executable once: `chmod +x ace.php`
- `./ace make:api [Name]`
- `./ace migrate`
- `./ace docs:generate`
- `./ace serve`

---
*Built with simplicity by ED.*