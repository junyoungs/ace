# ACE

**Define database schema. Get complete REST API with authentication.**

```
database/schema.dbml → ./ace api → API + Auth + 2FA
```

Authentication, user management, role-based access, and 2FA included by default.

---

## Features

✅ **Authentication System** (ready to use)
- User registration & login
- Token-based auth (Bearer tokens)
- Role management (member, admin, custom)
- Two-factor authentication (2FA/TOTP)
- Login logging
- Password hashing

✅ **Auto-Generated from DBML**
- Complete CRUD APIs
- Relationship endpoints
- Input validation structure
- Database migrations

✅ **Zero Configuration**
- Auto-routing (method names → URLs)
- JSON responses
- Middleware support

---

## Quick Start

### 1. Install & Configure

```bash
git clone <this-repo>
cp .env.example .env
# Edit .env: Set DB credentials and generate APP_KEY
chmod +x ace.php
```

**Important**: Generate a random `APP_KEY` in `.env`:
```bash
openssl rand -base64 32
```

### 2. Generate & Run

```bash
./ace api      # Generate API from schema
./ace migrate  # Create database tables
./ace serve    # Start server
```

### 3. Test Authentication

The default schema includes a complete auth system. Try it:

**Register:**
```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "name": "John Doe",
    "nickname": "johndoe"
  }'
```

**Login:**
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123"
  }'
```

You'll get:
```json
{
  "access_token": "eyJ1c2VyX2lkIjo...",
  "refresh_token": "eyJ1c2VyX2lkIjo...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "user_type": "member"
  }
}
```

**Use token:**
```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer <your_access_token>"
```

---

## Authentication Endpoints

All these are **ready to use** without writing any code:

```
POST   /api/auth/register       Register new user
POST   /api/auth/login          Login and get token
POST   /api/auth/logout         Logout (revoke token)
POST   /api/auth/refresh        Refresh access token
GET    /api/auth/me             Get current user info

# 2FA (Two-Factor Authentication)
POST   /api/auth/enable2fa      Enable 2FA (returns QR code)
POST   /api/auth/disable2fa     Disable 2FA
POST   /api/auth/verify2fa      Verify 2FA code

# User Management
GET    /api/users               List users (admin only)
GET    /api/users/show/{id}     Get user details
PUT    /api/users/update/{id}   Update user
DELETE /api/users/destroy/{id}  Delete user

# Members & Admins
GET    /api/members             List member profiles
POST   /api/members/store       Create member profile
GET    /api/admins              List admins (admin only)

# Login History
GET    /api/login-logs          Get login history
```

---

## Enable 2FA (Two-Factor Authentication)

**1. Enable 2FA:**
```bash
curl -X POST http://localhost:8080/api/auth/enable2fa \
  -H "Authorization: Bearer <token>"
```

**Response:**
```json
{
  "message": "2FA enabled successfully",
  "qr_code_url": "https://api.qrserver.com/v1/create-qr-code/?...",
  "backup_codes": ["12345678", "87654321", ...],
  "instructions": "Scan the QR code with Google Authenticator"
}
```

**2. Scan QR code** with Google Authenticator or Authy

**3. Login with 2FA code:**
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "two_factor_code": "123456"
  }'
```

---

## User Roles & Types

The default schema supports multiple user types:

**Schema structure:**
```
users (login info)
  ├─ members (general users)
  └─ admins (administrators)
```

**Register as admin:**
```bash
curl -X POST http://localhost:8080/api/auth/register \
  -d '{
    "email": "admin@example.com",
    "password": "admin123",
    "name": "Admin User",
    "user_type": "admin",
    "role": "super_admin"
  }'
```

---

## Default Schema

The included `database/schema.dbml` provides:

**Authentication Tables:**
- `users` - Login credentials, user type
- `members` - Member profiles (name, nickname, bio, etc.)
- `admins` - Admin profiles (role, permissions)
- `tokens` - Token storage (access & refresh)
- `login_logs` - Login attempt history
- `two_factor_auth` - 2FA secrets and backup codes

**Example Tables** (can be removed):
- `posts`, `categories`, `comments` - Blog/forum example

---

## Custom Schema

You can modify `database/schema.dbml` or create your own:

```dbml
Table products {
  id int [pk, increment, note: 'auto:db']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  name varchar(255) [note: 'input:required']
  price decimal(10,2) [note: 'input:required|min:0']
  created_at timestamp [note: 'auto:db']
}
```

Run `./ace api` to regenerate everything.

---

## DBML Annotations

Control how each field works:

### Input Fields (from API)
```dbml
name varchar(255) [note: 'input:required']        # Required
email varchar(255) [note: 'input:required|email'] # With validation
bio text [note: 'input:optional']                 # Optional
```

### Auto-Generated (Database)
```dbml
id int [pk, increment, note: 'auto:db']
created_at timestamp [note: 'auto:db']
updated_at timestamp [note: 'auto:db']
```

### Auto-Generated (Server)
```dbml
slug varchar(255) [note: 'auto:server:from=name']       # From another field
user_id int [note: 'auto:server:from=auth']             # From logged-in user
token varchar(255) [note: 'auto:server']                # Server-generated
deleted_at timestamp [note: 'auto:server:soft_delete']  # Soft delete
```

---

## Protecting Routes

Use `AuthMiddleware` in `app/Http/Kernel.php`:

```php
public array $middlewareGroups = [
    'api' => [
        \APP\Http\Middleware\AuthMiddleware::class,  // All /api/* requires auth
    ],
];
```

Or apply to specific subdomains:
```php
public array $middlewareGroups = [
    'admin' => [
        \APP\Http\Middleware\AuthMiddleware::class,  // admin.* requires auth
    ],
];
```

---

## Adding Custom Logic

Generated services have hooks for your code:

```php
// app/Services/ProductService.php

class ProductService
{
    // Auto-generated CRUD (don't modify)
    public function getAll(): array { /* ... */ }
    public function create(array $data): array { /* ... */ }

    // Your custom logic below
    public function getFeatured(): array {
        return Product::where('is_featured', 1);
    }

    public function applyDiscount(int $id, float $percent): void {
        $product = Product::find($id);
        Product::update($id, [
            'price' => $product['price'] * (1 - $percent / 100)
        ]);
    }
}
```

Add controller endpoint:
```php
// app/Http/Controllers/ProductController.php

public function getFeatured(): array {
    return $this->productService->getFeatured();
}
```

New endpoint: `GET /api/products/featured`

---

## Routing

Controller method names automatically map to URLs:

```php
class ProductController {
    public function getIndex() {}        // GET    /api/product
    public function postStore() {}       // POST   /api/product/store
    public function getShow($id) {}      // GET    /api/product/show/{id}
    public function putUpdate($id) {}    // PUT    /api/product/update/{id}
    public function deleteDestroy($id){} // DELETE /api/product/destroy/{id}

    public function getFeatured() {}     // GET    /api/product/featured
}
```

Pattern: `{httpMethod}{Action}` → `{METHOD} /api/{resource}/{action}`

---

## Commands

```bash
./ace api         # Generate API from database/schema.dbml
./ace migrate     # Run database migrations
./ace serve       # Start development server
```

---

## Model Methods

Every model inherits:

```php
User::getAll();                    // Get all
User::find($id);                   // Find by ID
User::where('status', 'active');   // Find by column
User::create($data);               // Insert
User::update($id, $data);          // Update
User::delete($id);                 // Delete
User::query($sql, $bindings);      // Raw SQL
```

---

## Security Best Practices

1. **Generate strong APP_KEY**: `openssl rand -base64 32`
2. **Use HTTPS** in production
3. **Enable 2FA** for admin accounts
4. **Set token expiration** appropriately
5. **Monitor login_logs** table for suspicious activity
6. **Hash passwords** (done automatically)
7. **Validate input** (use DBML validation rules)

---

## When to Use ACE

**Perfect for:**
- REST APIs with authentication
- Admin panels & dashboards
- Multi-tenant apps
- Membership sites
- Internal tools
- Prototypes & MVPs

**Not for:**
- Server-rendered websites
- GraphQL APIs
- Non-database apps

---

## Requirements

- PHP 8.1+
- MySQL or SQLite
- Composer
- OpenSSL (for key generation)

---

## License

LGPL-3.0-or-later

---

**ACE = Absolute Simplicity**

One schema file. Authentication included. Start building features, not boilerplate.
