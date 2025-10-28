# ACE Framework

**Schema-First API Framework for PHP**

Write your database schema in DBML, generate a complete REST API automatically. Focus only on complex business logic.

---

## Philosophy

Stop writing repetitive CRUD code. Define your data structure once, get a production-ready API instantly.

```
DBML Schema → Complete API (Models, Services, Controllers, Migrations)
```

---

## Quick Start

### 1. Install & Configure
```bash
cp .env.example .env
# Edit .env with your database credentials
chmod +x ace.php
```

### 2. Define Your Schema

Create or edit `database/schema.dbml`:

```dbml
Table products {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [not null, note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=name']
  price decimal(10,2) [note: 'input:required|min:0']
  category_id int [ref: > categories.id, note: 'input:required']
  created_at timestamp [note: 'auto:db']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [not null, note: 'input:required']
  created_at timestamp [note: 'auto:db']
}
```

### 3. Generate Everything

```bash
./ace api
```

This creates:
- ✅ Migrations with foreign keys
- ✅ Models with relationships
- ✅ Services with CRUD + business logic hooks
- ✅ Controllers with REST endpoints
- ✅ Automatic input validation structure

### 4. Run & Test

```bash
./ace migrate
./ace serve
```

Your API is now live:
- `GET    /api/products` - List all products
- `POST   /api/products/store` - Create product
- `GET    /api/products/show/1` - Get product #1
- `PUT    /api/products/update/1` - Update product
- `DELETE /api/products/destroy/1` - Delete product
- `GET    /api/products/category/1` - Get product's category
- `GET    /api/categories/products/1` - Get category's products

---

## DBML Metadata System

Control how each field behaves with `note:` annotations:

### Input Fields (from API requests)
```dbml
name varchar(255) [note: 'input:required']        # Required from user
description text [note: 'input:optional']         # Optional from user
price decimal(10,2) [note: 'input:required|min:0'] # With validation
```

### Auto-Generated Fields (Database)
```dbml
id int [pk, increment, note: 'auto:db']           # Auto-increment
created_at timestamp [note: 'auto:db']            # DB timestamp
updated_at timestamp [note: 'auto:db']            # Auto-update
```

### Auto-Generated Fields (Server)
```dbml
slug varchar(255) [note: 'auto:server:from=name'] # From another field
user_id int [note: 'auto:server:from=auth']       # From auth session
order_number varchar(50) [note: 'auto:server:uuid'] # UUID generation
deleted_at timestamp [note: 'auto:server:soft_delete'] # Soft delete
```

---

## Automatic Relationships

Foreign keys automatically generate relationship methods:

```dbml
Table reviews {
  id int [pk, increment, note: 'auto:db']
  product_id int [ref: > products.id, note: 'input:required']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  rating int [note: 'input:required|min:1|max:5']
}
```

Auto-generated methods:
```php
// In Review model
$review->product($id);  // Get related product
$review->user($id);     // Get related user

// In Product model
$product->reviews($id); // Get all reviews for product

// In User model
$user->reviews($id);    // Get all reviews by user
```

Auto-generated endpoints:
- `GET /api/reviews/product/1` - Get review's product
- `GET /api/reviews/user/1` - Get review's user
- `GET /api/products/reviews/1` - Get product's reviews
- `GET /api/users/reviews/1` - Get user's reviews

---

## Project Structure

```
/ace                    # Framework core (don't touch)
/app
  /Http
    /Controllers        # Auto-generated REST controllers
    /Middleware         # Your custom middleware
  /Models               # Auto-generated with relationships
  /Services             # Auto-generated + your business logic
/database
  /migrations           # Auto-generated from DBML
  schema.dbml           # Single source of truth
/public
  index.php             # Entry point
ace.php                 # CLI tool
.env                    # Configuration
```

---

## CLI Commands

```bash
./ace api [path]    # Generate complete API from DBML (default: database/schema.dbml)
./ace migrate       # Run database migrations
./ace serve         # Start development server
```

---

## Example: E-Commerce in 5 Minutes

**1. Define schema** (`database/schema.dbml`):

```dbml
Table products {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
  price decimal(10,2) [note: 'input:required|min:0']
  stock int [default: 0, note: 'input:optional']
  category_id int [ref: > categories.id, note: 'input:required']
  created_at timestamp [note: 'auto:db']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
}

Table orders {
  id int [pk, increment, note: 'auto:db']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  total decimal(10,2) [note: 'auto:server:calculated']
  status enum('pending','paid','shipped') [default: 'pending']
  created_at timestamp [note: 'auto:db']
}

Table order_items {
  id int [pk, increment, note: 'auto:db']
  order_id int [ref: > orders.id, note: 'input:required']
  product_id int [ref: > products.id, note: 'input:required']
  quantity int [note: 'input:required|min:1']
  price decimal(10,2) [note: 'auto:server:from=products.price']
}

Table users {
  id int [pk, increment, note: 'auto:db']
  email varchar(255) [unique, note: 'input:required|email']
  password varchar(255) [note: 'input:required|min:8']
  name varchar(255) [note: 'input:required']
  created_at timestamp [note: 'auto:db']
}
```

**2. Generate**:
```bash
./ace api
./ace migrate
./ace serve
```

**3. Done!** You now have:
- Complete product catalog API
- Category management
- Order system with items
- User management
- All relationships working
- **30+ REST endpoints ready to use**

---

## Adding Custom Business Logic

Generated services have hooks for your custom logic:

```php
// app/Services/ProductService.php (auto-generated)

class ProductService
{
    // ✅ Auto-generated CRUD (don't modify)
    public function getAll(array $filters = []): array { /* ... */ }
    public function findById(int $id): ?array { /* ... */ }
    public function create(array $data): array { /* ... */ }

    // 👇 Add your custom logic below

    public function getFeatured(): array {
        return Product::where('is_featured', 1);
    }

    public function applyDiscount(int $id, float $percent): void {
        $product = Product::find($id);
        $newPrice = $product['price'] * (1 - $percent / 100);
        Product::update($id, ['price' => $newPrice]);
    }
}
```

Then add controller method:
```php
// app/Http/Controllers/ProductController.php

public function getFeatured(): array {
    return $this->productService->getFeatured();
}

public function postDiscount(int $id): array {
    $data = $this->request->getParsedBody();
    $this->productService->applyDiscount($id, $data['percent']);
    return ['message' => 'Discount applied'];
}
```

Now you have:
- `GET /api/products/featured` - Custom endpoint
- `POST /api/products/discount/1` - Custom endpoint

---

## Model Methods (Auto-Available)

All models inherit these methods:

```php
Product::getAll();                    // Get all records
Product::find($id);                   // Find by ID
Product::where('status', 'active');   // Find by column
Product::create($data);               // Insert (respects fillable)
Product::update($id, $data);          // Update (respects fillable)
Product::delete($id);                 // Delete
Product::query($sql, $bindings);      // Raw query
```

---

## Zero-Config Routing

Controller method names automatically map to routes:

```php
class ProductController {
    public function getIndex() {}        // GET    /api/product
    public function postStore() {}       // POST   /api/product/store
    public function getShow($id) {}      // GET    /api/product/show/{id}
    public function putUpdate($id) {}    // PUT    /api/product/update/{id}
    public function deleteDestroy($id){} // DELETE /api/product/destroy/{id}

    // Custom routes
    public function getFeatured() {}     // GET    /api/product/featured
    public function postSearch() {}      // POST   /api/product/search
}
```

Pattern: `{httpMethod}{ActionName}` → `{METHOD} /api/{resource}/{action}`

---

## Middleware

Protect routes by subdomain in `app/Http/Kernel.php`:

```php
public array $middlewareGroups = [
    'api' => [
        Authenticate::class,  // All api.* requests require auth
    ],
    'admin' => [
        Authenticate::class,
        AdminOnly::class,     // All admin.* requests require admin
    ],
];
```

---

## When to Use ACE

✅ **Perfect for:**
- REST APIs with clear data models
- CRUD-heavy applications
- Rapid prototyping
- Internal tools and dashboards
- Microservices

❌ **Not ideal for:**
- Server-side rendered websites
- GraphQL APIs (use dedicated GraphQL frameworks)
- Non-CRUD focused applications

---

## Philosophy: 90/10 Rule

ACE automates 90% of typical API work:
- Database schema
- CRUD operations
- Relationships
- REST endpoints
- Input filtering

You focus on the 10% that matters:
- Complex business logic
- Custom queries
- Third-party integrations
- Unique features

---

## Requirements

- PHP 8.1+
- MySQL or SQLite
- Composer

---

## License

LGPL-3.0-or-later

---

**Built with simplicity.** Stop writing boilerplate. Start shipping features.
