# AI Agent Development Guide for ACE Framework

This guide provides clear rules and patterns for AI agents working with ACE Framework.

## Core Principles

1. **DO NOT** modify ACE framework core code (`ace/` directory)
2. **ONLY** work in designated areas: `app/`, `database/schema.dbml`, `.ace/config.php`
3. Keep code **simple and verifiable** (humans must review your code)
4. Follow ACE patterns for **consistency**
5. Use DBML for schema, let ACE generate boilerplate

---

## Directory Structure

```
ace/                    ⛔ READ-ONLY - DO NOT MODIFY
├── Database/           Framework core
├── Http/
├── Service/
└── ...

app/                    ✅ YOUR WORK AREA
├── Models/             Auto-generated + custom methods
├── Services/           Auto-generated + business logic
├── Http/Controllers/   Auto-generated + custom endpoints
└── ...

database/
├── schema.dbml         ✅ Define your schema here
└── migrations/         Auto-generated

.ace/
└── config.php          ✅ Configuration
```

---

## DBML Rules (Only 3 Simple Rules)

### Rule 1: `input` - User provides this data
```dbml
email varchar(255) [not null, note: 'input:required|email']
phone varchar(20) [null, note: 'input:optional']
```

### Rule 2: `auto:db` - Database generates this
```dbml
id int [pk, increment, note: 'auto:db']
created_at timestamp [note: 'auto:db']
updated_at timestamp [note: 'auto:db']
```

### Rule 3: `auto:server` - Server generates this
```dbml
user_id int [note: 'auto:server:from=auth']
order_number varchar(50) [note: 'auto:server']
slug varchar(255) [note: 'auto:server:from=title']
```

**That's it! Just 3 rules.**

---

## Generated Code - What ACE Provides

When you define a table in DBML, ACE auto-generates:

### 1. Model (app/Models/Post.php)
```php
class Post extends Model
{
    protected static string $table = 'posts';
    protected static array $fillable = ['title', 'content']; // auto-detected

    // Relationship methods auto-generated
    public function user(int $id): ?array { ... }
}
```

### 2. Service (app/Services/PostService.php)
```php
class PostService extends BaseService
{
    public function getAll(array $filters = []): array { ... }
    public function findById(int $id): ?array { ... }
    public function create(array $data): array { ... }
    public function update(int $id, array $data): int { ... }
    public function delete(int $id): int { ... }

    // ADD YOUR CUSTOM BUSINESS LOGIC BELOW
}
```

### 3. Controller (app/Http/Controllers/PostController.php)
```php
class PostController extends Control
{
    // Auto-generated CRUD endpoints
    public function getIndex(): array { ... }     // GET /api/post
    public function postStore(): array { ... }    // POST /api/post/store
    public function getShow(int $id): ?array { ... }
    public function putUpdate(int $id): array { ... }
    public function deleteDestroy(int $id): ?array { ... }

    // ADD YOUR CUSTOM ENDPOINTS BELOW
}
```

### 4. Migration
```php
class CreatePostsTable
{
    public function up($db): void { /* CREATE TABLE ... */ }
    public function down($db): void { /* DROP TABLE ... */ }
}
```

---

## Your Job: Add Custom Logic

### When to Add Custom Logic?

✅ **Use auto-generated code** for:
- Simple CRUD operations
- Basic filtering
- Standard relationships

✅ **Add custom code** for:
- Multi-table transactions (10+ tables)
- Complex business logic
- Conditional validation
- External API calls

### Pattern 1: Custom Service Method

When you need multi-table operations, use `transaction()`:

```php
// app/Services/OrderService.php
class OrderService extends BaseService
{
    public function createFromCart(array $data): array
    {
        // Validate input
        $this->validate($data, [
            'user_id' => 'required',
            'address' => 'required',
        ]);

        // Transaction for multi-table operation
        return $this->transaction(function() use ($data) {
            // 1. Create order
            $orderId = Order::create([
                'user_id' => $data['user_id'],
                'total' => $this->calculateTotal($data),
            ]);

            // 2. Create order items
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            // 3. Update stock
            $this->updateStock($data['items']);

            // 4. Return result
            return Order::find($orderId);
        });
    }

    private function calculateTotal(array $data): float
    {
        // Helper method (keep it simple!)
        return array_sum(array_column($data['items'], 'price'));
    }

    private function updateStock(array $items): void
    {
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            Product::update($product['id'], [
                'stock' => $product['stock'] - $item['quantity']
            ]);
        }
    }
}
```

### Pattern 2: Custom Controller Endpoint

```php
// app/Http/Controllers/OrderController.php
class OrderController extends Control
{
    public function postCheckout(): array
    {
        try {
            // 1. Get input
            $data = $this->request->getParsedBody();
            $authUser = $this->request->getAttribute('auth_user');
            $data['user_id'] = $authUser['user_id'];

            // 2. Simple validation
            if (empty($data['address'])) {
                http_response_code(400);
                return ['error' => 'Address is required'];
            }

            // 3. Delegate to Service
            $order = $this->orderService->createFromCart($data);

            // 4. Return response
            http_response_code(201);
            return $order;

        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }
}
```

---

## Code Quality Rules

### Rule 1: Keep Functions Small
- **Max 100 lines per function**
- If longer, break into smaller helper methods
- Each function should do ONE thing

### Rule 2: Limit Nesting
- **Max 3 nesting levels**
- Use early returns to reduce nesting
- Extract complex conditions to helper methods

**Bad (4 levels):**
```php
if ($user) {
    if ($order) {
        if ($order['status'] === 'pending') {
            if ($user['id'] === $order['user_id']) {
                // do something
            }
        }
    }
}
```

**Good (2 levels):**
```php
if (!$user || !$order) {
    return ['error' => 'Not found'];
}

if ($order['status'] !== 'pending') {
    return ['error' => 'Cannot modify'];
}

if ($user['id'] !== $order['user_id']) {
    return ['error' => 'Access denied'];
}

// do something
```

### Rule 3: Clear Comments
- Explain **WHY**, not what
- Comment complex business logic
- Document expected input/output

```php
// Good comment
// Calculate discount: 10% for members, 5% for guests
$discount = $user['type'] === 'member' ? 0.10 : 0.05;

// Bad comment
// Set discount variable
$discount = $user['type'] === 'member' ? 0.10 : 0.05;
```

---

## Common Patterns

### Multi-table Create
```php
public function createOrder(array $data): array
{
    return $this->transaction(function() use ($data) {
        $orderId = Order::create($data['order']);

        foreach ($data['items'] as $item) {
            OrderItem::create(['order_id' => $orderId, ...$item]);
        }

        return Order::find($orderId);
    });
}
```

### Conditional Update
```php
public function updateStatus(int $id, string $status): array
{
    return $this->transaction(function() use ($id, $status) {
        $order = Order::find($id);

        // Validate state transition
        if (!$this->canTransitionTo($order['status'], $status)) {
            throw new \Exception('Invalid status transition');
        }

        // Update order
        Order::update($id, ['status' => $status]);

        // Side effects based on status
        if ($status === 'cancelled') {
            $this->restoreStock($id);
        }

        return Order::find($id);
    });
}
```

### Fetch with Relationships
```php
public function getOrderWithDetails(int $id): array
{
    $order = Order::find($id);
    $order['items'] = OrderItem::where('order_id', $id);
    $order['user'] = User::find($order['user_id']);

    return $order;
}
```

---

## What NOT to Do

### ❌ DON'T modify ACE core
```php
// NEVER modify ace/Database/Model.php
// NEVER modify ace/Service/BaseService.php
// NEVER modify ace/Http/Control.php
```

### ❌ DON'T write complex DBML
```dbml
// BAD - Too complex
field varchar(255) [note: 'input:required|min:3|max:50|alpha_numeric|unique:check_db|transform:uppercase|sanitize:html']
```

```dbml
// GOOD - Simple
field varchar(255) [note: 'input:required']
```
Handle complex validation in Service layer.

### ❌ DON'T write giant functions
```php
// BAD - 300 lines function
public function processOrder($data) {
    // 300 lines of code...
}
```

```php
// GOOD - Break into smaller pieces
public function processOrder($data) {
    $this->validateOrder($data);
    $order = $this->createOrder($data);
    $this->updateInventory($order);
    $this->sendNotifications($order);
    return $order;
}
```

### ❌ DON'T use complex queries in Controllers
```php
// BAD - Complex logic in Controller
public function getIndex() {
    $sql = "SELECT ... complex 50-line query ...";
    return $db->query($sql);
}
```

```php
// GOOD - Delegate to Service
public function getIndex() {
    return $this->orderService->getAll();
}
```

---

## Testing Your Code

### Manual Checklist
- [ ] Functions under 100 lines?
- [ ] Max 3 nesting levels?
- [ ] Used `transaction()` for multi-table operations?
- [ ] Validated user input?
- [ ] Clear error messages?
- [ ] Followed ACE patterns?
- [ ] Did NOT modify `ace/` directory?

### Run Code Generation
```bash
php ace generate
```

### Run Migrations
```bash
php ace migrate
```

### Test API Endpoints
```bash
curl -X POST http://localhost:8000/api/order/checkout \
  -H "Content-Type: application/json" \
  -d '{"address": "123 Main St", "items": [...]}'
```

---

## Quick Reference

| Task | Command |
|------|---------|
| Define schema | Edit `database/schema.dbml` |
| Generate code | `php ace generate` |
| Run migrations | `php ace migrate` |
| Add business logic | Edit `app/Services/` |
| Add custom endpoint | Edit `app/Http/Controllers/` |

## Questions?

If uncertain:
1. Check examples: `examples/OrderService_example.php`
2. Follow the 3 DBML rules
3. Keep it simple
4. Use `transaction()` for multi-table operations
5. When in doubt, ask the human developer!

---

**Remember: Simple, verifiable code is better than clever complex code.**
