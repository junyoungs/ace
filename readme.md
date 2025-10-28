# ACE

**Define database schema. Get complete REST API.**

```
database/schema.dbml → ./ace api → Ready-to-use API
```

Stop writing CRUD code. Write schema once, API is done.

---

## Install

```bash
git clone <this-repo>
cp .env.example .env
# Edit .env with your database credentials
chmod +x ace.php
```

---

## Usage

### 1. Write Schema

`database/schema.dbml`:

```dbml
Table products {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
  price decimal(10,2) [note: 'input:required']
  category_id int [ref: > categories.id, note: 'input:required']
  created_at timestamp [note: 'auto:db']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
  created_at timestamp [note: 'auto:db']
}
```

### 2. Generate

```bash
./ace api
./ace migrate
./ace serve
```

### 3. Use

Your API is live at `http://localhost:8080`:

```bash
# Products
GET    /api/products              # List all
POST   /api/products/store        # Create
GET    /api/products/show/1       # Get one
PUT    /api/products/update/1     # Update
DELETE /api/products/destroy/1    # Delete

# Relationships (auto-generated)
GET    /api/products/category/1   # Get product's category
GET    /api/categories/products/1 # Get category's products
```

**Done.**

---

## DBML Annotations

Control field behavior with `note:` attribute:

### Input Fields (from API)

```dbml
name varchar(255) [note: 'input:required']      # Required from user
email varchar(255) [note: 'input:required|email'] # With validation
description text [note: 'input:optional']        # Optional
```

### Auto-Generated (Database)

```dbml
id int [pk, increment, note: 'auto:db']
created_at timestamp [note: 'auto:db']
updated_at timestamp [note: 'auto:db']
```

### Auto-Generated (Server)

```dbml
slug varchar(255) [note: 'auto:server:from=name']      # Generate from name
user_id int [note: 'auto:server:from=auth']            # From logged-in user
order_number varchar(50) [note: 'auto:server:uuid']    # UUID
deleted_at timestamp [note: 'auto:server:soft_delete'] # Soft delete
```

---

## Relationships

Define with `ref:`:

```dbml
Table posts {
  id int [pk, increment, note: 'auto:db']
  title varchar(255) [note: 'input:required']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  category_id int [ref: > categories.id, note: 'input:required']
}

Table users {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
}
```

**Auto-generated API endpoints:**

```
GET /api/posts/user/1       # Get post's user
GET /api/posts/category/1   # Get post's category
GET /api/users/posts/1      # Get user's posts
GET /api/categories/posts/1 # Get category's posts
```

**Auto-generated model methods:**

```php
Post::find(1);           // Get post
$post = new Post();
$post->user(1);          // Get post's user
$post->category(1);      // Get post's category

$user = new User();
$user->posts(1);         // Get user's posts
```

---

## What Gets Generated

From one DBML file, you get:

**Migrations**
- Tables with correct column types
- Primary keys, foreign keys, indexes
- Constraints and relationships

**Models**
- CRUD methods: `find()`, `getAll()`, `where()`, `create()`, `update()`, `delete()`
- Relationship methods: `category()`, `posts()`, etc.
- Automatic fillable fields (only input fields)

**Services**
- Basic CRUD operations
- Input/auto field separation logic
- Relationship loaders
- Hooks for custom business logic

**Controllers**
- REST endpoints (list, show, store, update, destroy)
- Relationship endpoints
- Automatic JSON responses
- Zero-config routing

---

## Add Custom Logic

Generated files have sections for your code:

```php
// app/Services/ProductService.php

class ProductService
{
    // Auto-generated CRUD (don't touch)
    public function getAll(): array { /* ... */ }
    public function create(array $data): array { /* ... */ }

    // Your custom logic below
    public function getFeatured(): array {
        return Product::where('featured', 1);
    }

    public function applyDiscount(int $id, float $percent): void {
        $product = Product::find($id);
        $newPrice = $product['price'] * (1 - $percent / 100);
        Product::update($id, ['price' => $newPrice]);
    }
}
```

Add controller method:

```php
// app/Http/Controllers/ProductController.php

public function getFeatured(): array {
    return $this->productService->getFeatured();
}
```

New endpoint: `GET /api/products/featured`

---

## Routing

Controller method names = routes:

```php
class ProductController {
    public function getIndex() {}        // GET    /api/product
    public function postStore() {}       // POST   /api/product/store
    public function getShow($id) {}      // GET    /api/product/show/{id}
    public function putUpdate($id) {}    // PUT    /api/product/update/{id}
    public function deleteDestroy($id){} // DELETE /api/product/destroy/{id}
    public function getFeatured() {}     // GET    /api/product/featured
    public function postSearch() {}      // POST   /api/product/search
}
```

Pattern: `{method}{Action}` → `{METHOD} /api/{resource}/{action}`

---

## Commands

```bash
./ace api         # Generate API from database/schema.dbml
./ace migrate     # Run migrations
./ace serve       # Start server (localhost:8080)
```

---

## Example: Blog in 2 Minutes

**schema.dbml:**

```dbml
Table posts {
  id int [pk, increment, note: 'auto:db']
  title varchar(255) [note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=title']
  content text [note: 'input:required']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  category_id int [ref: > categories.id, note: 'input:required']
  views int [default: 0, note: 'auto:server']
  created_at timestamp [note: 'auto:db']
  updated_at timestamp [note: 'auto:db']
}

Table users {
  id int [pk, increment, note: 'auto:db']
  email varchar(255) [unique, note: 'input:required|email']
  password varchar(255) [note: 'input:required|min:8']
  name varchar(255) [note: 'input:required']
  created_at timestamp [note: 'auto:db']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [unique, note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=name']
  created_at timestamp [note: 'auto:db']
}

Table comments {
  id int [pk, increment, note: 'auto:db']
  post_id int [ref: > posts.id, note: 'input:required']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  content text [note: 'input:required']
  created_at timestamp [note: 'auto:db']
}
```

**Generate:**

```bash
./ace api
./ace migrate
./ace serve
```

**Result: 20+ endpoints, all relationships working.**

```
Posts:    GET/POST/PUT/DELETE /api/posts/...
Users:    GET/POST/PUT/DELETE /api/users/...
Category: GET/POST/PUT/DELETE /api/categories/...
Comments: GET/POST/PUT/DELETE /api/comments/...

Relationships:
GET /api/posts/user/1
GET /api/posts/category/1
GET /api/posts/comments/1
GET /api/users/posts/1
GET /api/categories/posts/1
... and more
```

---

## Philosophy

**90% of API work is repetitive:**
- Define tables
- Write CRUD
- Handle relationships
- Create endpoints
- Filter input vs auto-generated fields

**ACE automates all of it.**

You focus on the 10% that matters:
- Business logic
- Custom features
- Third-party integrations

---

## Model Methods

Every model inherits:

```php
Product::getAll();                  // Get all records
Product::find($id);                 // Find by ID
Product::where('status', 'active'); // Find by column
Product::create($data);             // Insert (auto-filters to fillable)
Product::update($id, $data);        // Update
Product::delete($id);               // Delete
Product::query($sql, $bindings);    // Raw SQL
```

---

## When to Use ACE

**Perfect for:**
- REST APIs
- CRUD-heavy apps
- Prototypes
- Internal tools
- Microservices

**Not for:**
- Server-rendered websites
- GraphQL APIs
- Non-database-driven apps

---

## Requirements

- PHP 8.1+
- MySQL or SQLite
- Composer

---

## License

LGPL-3.0-or-later

---

**ACE = Absolute Simplicity**

One schema file. Three commands. Complete API.
