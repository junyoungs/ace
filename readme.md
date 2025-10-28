# ACE

**API framework with authentication built-in.**

```
database/schema.dbml → ./ace api → Complete API with Auth
```

Every project starts with user registration, login, roles, 2FA, and token management. No setup required.

---

## What You Get

✅ **Ready-to-use Authentication**
- User registration (member/admin roles)
- Login with token (access + refresh)
- 2FA (Google Authenticator compatible)
- Password hashing, token management
- Login history logging

✅ **Auto-Generated from Schema**
- Complete CRUD APIs
- Relationship endpoints
- Database migrations
- Input validation structure

✅ **Zero Configuration**
- Auto-routing (method names → URLs)
- JSON responses
- Middleware support

---

## Quick Start (2 Minutes)

### 1. Install

```bash
git clone <this-repo>
cp .env.example .env
chmod +x ace.php
```

**Edit `.env`**: Set database credentials and generate `APP_KEY`

```bash
# Generate APP_KEY
openssl rand -base64 32
# Copy output to .env: APP_KEY=<your-generated-key>
```

### 2. Generate & Run

```bash
./ace api      # Generate API from schema
./ace migrate  # Create database tables
./ace serve    # Start server on :8080
```

### 3. Test Authentication

Your API is now running with complete authentication. Test it:

**Register a user:**
```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "john@example.com",
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
    "email": "john@example.com",
    "password": "password123"
  }'
```

**Response:**
```json
{
  "access_token": "eyJ1c2VyX2lkIjoxLCJ1...",
  "refresh_token": "eyJ1c2VyX2lkIjoxLCJ1...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "email": "john@example.com",
    "user_type": "member"
  }
}
```

**Use the API:**
```bash
curl http://localhost:8080/api/auth/me \
  -H "Authorization: Bearer eyJ1c2VyX2lkIjoxLCJ1..."
```

**Done.** You have a working API with authentication in 2 minutes.

---

## Authentication Endpoints

These are **available immediately** without writing any code:

### User Management
```
POST   /api/auth/register        Register new user
POST   /api/auth/login           Login and get tokens
POST   /api/auth/logout          Logout (revoke token)
POST   /api/auth/refresh         Refresh access token
GET    /api/auth/me              Get current user info
```

### Two-Factor Authentication (2FA)
```
POST   /api/auth/enable2fa       Enable 2FA (get QR code)
POST   /api/auth/disable2fa      Disable 2FA
POST   /api/auth/verify2fa       Verify 2FA code
```

### User CRUD (Protected)
```
GET    /api/users                List users
GET    /api/users/show/{id}      Get user details
PUT    /api/users/update/{id}    Update user
DELETE /api/users/destroy/{id}   Delete user
```

### Profiles
```
GET    /api/members              List member profiles
POST   /api/members/store        Create member profile
PUT    /api/members/update/{id}  Update member profile

GET    /api/admins               List admin profiles
POST   /api/admins/store         Create admin profile
```

### Audit Logs
```
GET    /api/login-logs           Get login history
```

---

## User Structure

ACE uses a split authentication design:

```
┌─────────────┐
│   users     │  Login credentials & type
│   (auth)    │  - email, password
└──────┬──────┘  - user_type (member/admin)
       │         - status (active/inactive/suspended)
       │
   ┌───┴────┬──────────────┐
   │        │              │
┌──▼───┐ ┌──▼────┐  ┌──────▼──────┐
│member│ │ admin │  │   tokens    │
│(info)│ │(info) │  │ (sessions)  │
└──────┘ └───────┘  └─────────────┘
```

**Why this design?**
- One login system (`users` table)
- Multiple user types with different data needs
- Clean separation: auth vs user data
- Easy to extend (add more roles)

**Example flow:**
1. Register → Creates `users` row + `members` or `admins` row
2. Login → Validates `users` credentials, creates `tokens` row
3. API call → Validates token, loads user + profile data

---

## User Types & Registration

### Register as Member (Default)

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "name": "John Doe",
    "nickname": "johndoe",
    "phone": "010-1234-5678",
    "bio": "Hello world"
  }'
```

Creates:
- `users` row: email, password, user_type='member'
- `members` row: name, nickname, phone, bio, etc.

### Register as Admin

```bash
curl -X POST http://localhost:8080/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "admin123",
    "name": "Admin User",
    "user_type": "admin",
    "role": "super_admin",
    "permissions": "all"
  }'
```

Creates:
- `users` row: email, password, user_type='admin'
- `admins` row: name, role, permissions

---

## Two-Factor Authentication (2FA)

### Enable 2FA

**1. Request 2FA setup:**
```bash
curl -X POST http://localhost:8080/api/auth/enable2fa \
  -H "Authorization: Bearer <your_access_token>"
```

**2. Response:**
```json
{
  "message": "2FA enabled successfully",
  "qr_code_url": "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=...",
  "backup_codes": [
    "12345678",
    "87654321",
    "11223344",
    ...
  ],
  "instructions": "Scan the QR code with Google Authenticator or Authy app"
}
```

**3. Scan QR code** with Google Authenticator, Authy, or any TOTP app

**4. Login with 2FA:**
```bash
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "two_factor_code": "123456"
  }'
```

**Backup codes:** Save them! Use if you lose your phone.

---

## Login History & Security

Every login attempt is logged in `login_logs` table:

- User ID
- Email
- IP address
- User agent (browser/device)
- Success/failure
- Failure reason
- Timestamp

**View login history:**
```bash
curl http://localhost:8080/api/login-logs \
  -H "Authorization: Bearer <admin_token>"
```

**Use cases:**
- Detect suspicious activity
- Track failed login attempts
- Monitor account security
- Compliance/audit requirements

---

## Token Management

### How Tokens Work

**Login** → Server generates 2 tokens:
- `access_token` - Short-lived (1 hour), for API calls
- `refresh_token` - Long-lived (30 days), to get new access token

**Access token expires** → Use refresh token to get new one:
```bash
curl -X POST http://localhost:8080/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refresh_token": "<your_refresh_token>"
  }'
```

**Logout** → Revokes tokens:
```bash
curl -X POST http://localhost:8080/api/auth/logout \
  -H "Authorization: Bearer <your_access_token>"
```

**Token storage:**
- Stored in `tokens` table
- Includes: user_id, token, type (access/refresh), expires_at
- Validates on every API call
- Expired tokens automatically rejected

---

## Default Schema

The included `database/schema.dbml` provides a complete authentication system:

### Authentication Tables

**`users`** - Login credentials
- email, password (bcrypt hashed)
- user_type (member, admin, or custom)
- status (active, inactive, suspended)
- email_verified_at
- created_at, updated_at

**`members`** - Member profiles
- user_id (→ users.id)
- name, nickname (unique)
- phone, avatar_url, bio
- birth_date
- created_at, updated_at

**`admins`** - Admin profiles
- user_id (→ users.id)
- name, role (admin, super_admin, etc.)
- permissions (JSON)
- last_login_at
- created_at, updated_at

**`tokens`** - Session management
- user_id (→ users.id)
- token (unique)
- type (access, refresh)
- expires_at
- created_at

**`login_logs`** - Security audit
- user_id (→ users.id)
- email, ip_address, user_agent
- success (boolean)
- failure_reason
- created_at

**`two_factor_auth`** - 2FA settings
- user_id (→ users.id)
- secret (TOTP secret)
- is_enabled (boolean)
- backup_codes (JSON array)
- last_used_at
- created_at, updated_at

### Example Tables (Optional)

**`posts`, `categories`, `comments`** - Blog/forum example

You can remove these and add your own tables.

---

## Customizing the Schema

Edit `database/schema.dbml` and run `./ace api` to regenerate.

### Add Your Own Tables

```dbml
Table products {
  id int [pk, increment, note: 'auto:db']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  name varchar(255) [not null, note: 'input:required']
  price decimal(10,2) [note: 'input:required|min:0']
  category_id int [ref: > categories.id, note: 'input:required']
  status enum('draft', 'published') [default: 'draft', note: 'input:optional']
  created_at timestamp [note: 'auto:db']
  updated_at timestamp [note: 'auto:db']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [not null, note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=name']
  created_at timestamp [note: 'auto:db']
}
```

Run `./ace api` → Automatically generates:
- Migrations
- Models with relationships
- Services (CRUD + business logic hooks)
- Controllers (REST endpoints)

### DBML Annotations

**Input Fields** (from API requests):
```dbml
name varchar(255) [note: 'input:required']         # Required
email varchar(255) [note: 'input:required|email']  # With validation
bio text [note: 'input:optional']                  # Optional
```

**Auto-Generated (Database)**:
```dbml
id int [pk, increment, note: 'auto:db']
created_at timestamp [note: 'auto:db']
updated_at timestamp [note: 'auto:db']
```

**Auto-Generated (Server)**:
```dbml
slug varchar(255) [note: 'auto:server:from=name']       # From name field
user_id int [note: 'auto:server:from=auth']             # From logged-in user
order_number varchar(50) [note: 'auto:server:uuid']     # UUID
deleted_at timestamp [note: 'auto:server:soft_delete']  # Soft delete
```

**📖 Complete DBML Guide:** For complex scenarios (5+ table joins, conditional logic, e-commerce examples), see **[docs/DBML_GUIDE.md](docs/DBML_GUIDE.md)**

**📦 Ready-to-Use Examples:**
- `examples/ecommerce-schema.dbml` - Full e-commerce system (orders, payments, shipping, reviews)
- `examples/OrderService_example.php` - Complex business logic with transactions
- `examples/OrderController_example.php` - Custom API endpoints

---

## Protecting Routes

### Protect All API Routes

Edit `app/Http/Kernel.php`:

```php
public array $middlewareGroups = [
    'api' => [
        \APP\Http\Middleware\AuthMiddleware::class,
    ],
];
```

Now all `/api/*` routes require authentication.

### Protect Specific Subdomains

```php
public array $middlewareGroups = [
    'admin' => [
        \APP\Http\Middleware\AuthMiddleware::class,
    ],
];
```

Routes on `admin.yourdomain.com` require auth, but `api.yourdomain.com` doesn't.

### Public Routes

Don't add middleware to routes you want public:

```php
public array $middlewareGroups = [
    'api' => [], // No middleware = public
];
```

Or create a custom `PublicController` and don't apply middleware to it.

---

## Adding Custom Logic

Generated services have hooks for your code:

```php
// app/Services/ProductService.php

class ProductService
{
    // ========================================
    // Auto-generated CRUD (don't modify)
    // ========================================

    public function getAll(array $filters = []): array { /* ... */ }
    public function findById(int $id): ?array { /* ... */ }
    public function create(array $data): array { /* ... */ }
    public function update(int $id, array $data): int { /* ... */ }
    public function delete(int $id): int { /* ... */ }

    // ========================================
    // Your custom business logic below
    // ========================================

    public function getFeaturedProducts(): array {
        return Product::where('is_featured', 1);
    }

    public function applyDiscount(int $id, float $percent): void {
        $product = Product::find($id);
        $newPrice = $product['price'] * (1 - $percent / 100);
        Product::update($id, ['price' => $newPrice]);
    }

    public function getByCategory(int $categoryId): array {
        return Product::where('category_id', $categoryId);
    }
}
```

Add corresponding controller methods:

```php
// app/Http/Controllers/ProductController.php

public function getFeatured(): array {
    return $this->productService->getFeaturedProducts();
}

public function postApplyDiscount(int $id): array {
    $data = $this->request->getParsedBody();
    $this->productService->applyDiscount($id, $data['percent']);
    return ['message' => 'Discount applied'];
}
```

New endpoints:
- `GET /api/products/featured`
- `POST /api/products/apply-discount/{id}`

---

## Routing Convention

Controller method names automatically map to URLs:

```php
class ProductController {
    // Standard CRUD
    public function getIndex() {}              // GET    /api/product
    public function postStore() {}             // POST   /api/product/store
    public function getShow(int $id) {}        // GET    /api/product/show/{id}
    public function putUpdate(int $id) {}      // PUT    /api/product/update/{id}
    public function deleteDestroy(int $id) {}  // DELETE /api/product/destroy/{id}

    // Custom endpoints
    public function getFeatured() {}           // GET    /api/product/featured
    public function postSearch() {}            // POST   /api/product/search
    public function getByCategory(int $id) {}  // GET    /api/product/by-category/{id}
}
```

**Pattern:** `{httpMethod}{ActionName}` → `{METHOD} /api/{resource}/{action}`

---

## Model Methods

All models inherit these methods:

```php
// Read
Product::getAll();                     // Get all records
Product::find($id);                    // Find by ID
Product::where('status', 'active');    // Find by column

// Write
Product::create($data);                // Insert (auto-filters to fillable)
Product::update($id, $data);           // Update
Product::delete($id);                  // Delete

// Raw SQL
Product::query($sql, $bindings);       // Custom query
Product::execute($sql, $bindings);     // Custom statement
```

---

## Commands

```bash
./ace api         # Generate API from database/schema.dbml
./ace migrate     # Run database migrations
./ace serve       # Start development server (localhost:8080)
```

---

## Security Best Practices

1. **Always generate APP_KEY**: `openssl rand -base64 32` (don't use default!)
2. **Use HTTPS in production** (tokens over HTTP = insecure)
3. **Enable 2FA for admin accounts** (mandatory for sensitive operations)
4. **Monitor `login_logs` table** (detect brute force, suspicious IPs)
5. **Set appropriate token expiration** (balance security vs UX)
6. **Validate input** (use DBML validation rules)
7. **Rate limit auth endpoints** (prevent brute force)
8. **Rotate tokens on password change** (invalidate all existing tokens)

---

## Example Project: E-Commerce

**Goal:** Build a product catalog with user reviews.

**1. Modify `database/schema.dbml`:**

```dbml
// Keep default auth tables (users, members, admins, tokens, login_logs, two_factor_auth)

// Add your tables
Table products {
  id int [pk, increment, note: 'auto:db']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  name varchar(255) [not null, note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=name']
  price decimal(10,2) [note: 'input:required|min:0']
  stock int [default: 0, note: 'input:optional']
  category_id int [ref: > categories.id, note: 'input:required']
  created_at timestamp [note: 'auto:db']
}

Table categories {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [not null, note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=name']
  created_at timestamp [note: 'auto:db']
}

Table reviews {
  id int [pk, increment, note: 'auto:db']
  product_id int [ref: > products.id, note: 'input:required']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  rating int [note: 'input:required|min:1|max:5']
  comment text [note: 'input:optional']
  created_at timestamp [note: 'auto:db']
}
```

**2. Generate:**
```bash
./ace api
./ace migrate
./ace serve
```

**3. You now have:**
- User registration & login
- Product CRUD API
- Category management
- Review system (users can review products)
- All relationships working
- Token authentication on all endpoints

**Example API calls:**

```bash
# Register and login (get token)
TOKEN=$(curl -s -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"pass123"}' \
  | jq -r '.access_token')

# Create a product
curl -X POST http://localhost:8080/api/products/store \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"iPhone 15","price":999.99,"stock":100,"category_id":1}'

# Add a review
curl -X POST http://localhost:8080/api/reviews/store \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"product_id":1,"rating":5,"comment":"Great product!"}'

# Get product with reviews
curl http://localhost:8080/api/products/reviews/1 \
  -H "Authorization: Bearer $TOKEN"
```

**Done.** Full e-commerce API in minutes.

---

## When to Use ACE

**Perfect for:**
- REST APIs with authentication needs
- Admin panels & dashboards
- Membership/subscription sites
- Multi-tenant applications
- Internal tools & utilities
- MVPs & prototypes
- Microservices

**Not ideal for:**
- Server-side rendered websites
- GraphQL APIs
- Real-time apps (WebSocket-heavy)
- Non-database-driven applications

---

## Requirements

- PHP 8.1+
- MySQL 5.7+ or SQLite 3
- Composer
- OpenSSL (for key generation)

Optional:
- Google Authenticator or Authy (for 2FA)

---

## Troubleshooting

**"Invalid or expired token"**
- Token expired (1 hour for access token) → Use refresh token
- Token revoked (logged out) → Login again
- Wrong APP_KEY in .env → Regenerate tokens

**"APP_KEY not set"**
- Generate key: `openssl rand -base64 32`
- Add to .env: `APP_KEY=<your_key>`

**"2FA code invalid"**
- Check time sync on your device (TOTP is time-based)
- Use backup code if phone is unavailable
- Disable and re-enable 2FA

**"Database connection failed"**
- Check DB credentials in .env
- Ensure database exists
- Check DB server is running

---

## License

LGPL-3.0-or-later

---

## Philosophy

**ACE = Absolute Simplicity + Complete Authentication**

Most APIs need the same things:
- User accounts
- Login/logout
- Protected endpoints
- Role-based access
- Audit logs

ACE gives you all of this **by default**. No configuration, no packages, no decisions.

**One schema file. Three commands. Production-ready API.**

Stop building auth systems. Start building features.
