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
Open `app/Services/PostService.php` and `app/Http/Controllers/PostController.php`. The controller methods already call the service methods. You only need to fill in the business logic in the service.

**Controller:** `app/Http/Controllers/PostController.php`
```php
// This is auto-generated. You don't need to change it.
public function getIndex(): array
{
    return $this->postService->getAllPosts();
}
```

**Service:** `app/Services/PostService.php`
```php
// This is where you write your logic.
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
Middleware can be assigned to groups in `app/Http/Kernel.php`. The `api` group is applied to all routes starting with `/api/`. You can create new groups for different subdomains (e.g., `admin`).

**Kernel:** `app/Http/Kernel.php`
```php
protected array $middlewareGroups = [
    'api' => [
        \APP\Http\Middleware\AuthenticateApi::class,
    ],
    'admin' => [
        // \APP\Http\Middleware\AuthenticateAdmin::class,
    ],
];
```
The router will automatically apply the middleware group that matches the subdomain of the request.

### Custom SQL
For complex queries not covered by the built-in Model methods, you can always fall back to writing explicit, safe SQL.
```php
Post::select("SELECT * FROM posts WHERE published_at < ? AND is_active = ?", [$now, true]);
```

---
*Built with simplicity by ED.*