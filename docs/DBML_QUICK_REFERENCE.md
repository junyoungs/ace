# DBML 메타데이터 빠른 참조

## note: 속성 사용법

### 기본 형식
```dbml
field_name type [note: 'directive|validation1|validation2']
```

---

## 1. Input 필드 (사용자 입력)

```dbml
// 필수 입력
name varchar(255) [note: 'input:required']

// 선택 입력
bio text [note: 'input:optional']

// 검증 규칙 포함
email varchar(255) [note: 'input:required|email']
password varchar(255) [note: 'input:required|min:8']
age int [note: 'input:optional|min:18|max:120']
website varchar(500) [note: 'input:optional|url']
phone varchar(20) [note: 'input:required|phone']
```

---

## 2. Auto 필드 (자동 생성)

### auto:db (데이터베이스 생성)
```dbml
id int [pk, increment, note: 'auto:db']
created_at timestamp [note: 'auto:db']
updated_at timestamp [note: 'auto:db']
```

### auto:server (서버 생성)
```dbml
// 다른 필드에서 생성
slug varchar(255) [note: 'auto:server:from=title']

// 인증된 사용자
user_id int [note: 'auto:server:from=auth']

// UUID
uuid varchar(36) [note: 'auto:server:uuid']

// 기본값 또는 로직
status enum('active', 'inactive') [note: 'auto:server']
ip_address varchar(45) [note: 'auto:server']
```

---

## 3. 관계 (Relationships)

```dbml
// Many-to-One (belongsTo)
category_id int [ref: > categories.id]
// → Product.category() 메서드 생성
// → Category.products() 역관계 생성

// One-to-One
user_id int [ref: - users.id, unique]
// → Member.user() 메서드 생성

// Self-referencing
parent_id int [ref: > categories.id, null]
// → Category.parent() 메서드 생성
// → Category.children() 메서드 생성
```

---

## 4. 검증 규칙

| 규칙 | 예시 | 설명 |
|------|------|------|
| `email` | `note: 'input:required\|email'` | 이메일 형식 |
| `url` | `note: 'input:optional\|url'` | URL 형식 |
| `phone` | `note: 'input:required\|phone'` | 전화번호 형식 |
| `min:N` | `note: 'input:required\|min:8'` | 최소값/길이 |
| `max:N` | `note: 'input:optional\|max:120'` | 최대값/길이 |

---

## 5. 실전 예제

### 블로그 포스트
```dbml
Table posts {
  id int [pk, increment, note: 'auto:db']
  user_id int [ref: > users.id, note: 'auto:server:from=auth']
  category_id int [ref: > categories.id, note: 'input:required']
  title varchar(255) [not null, note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=title']
  content text [not null, note: 'input:required']
  status enum('draft', 'published') [default: 'draft', note: 'input:optional']
  views int [default: 0, note: 'auto:server']
  published_at timestamp [null, note: 'auto:server']
  created_at timestamp [note: 'auto:db']
  updated_at timestamp [note: 'auto:db']
}
```

**API Request:**
```json
POST /api/posts/store
Authorization: Bearer {token}
{
  "category_id": 1,
  "title": "My First Post",
  "content": "Hello world",
  "status": "published"
}
```

**자동 생성:**
- `id`: AUTO_INCREMENT
- `user_id`: 현재 로그인한 사용자
- `slug`: "my-first-post"
- `views`: 0
- `created_at`, `updated_at`: 현재 시각

### 전자상거래 주문
```dbml
Table orders {
  id int [pk, increment, note: 'auto:db']
  customer_id int [ref: > customers.id, note: 'auto:server:from=auth']
  order_number varchar(50) [unique, note: 'auto:server:uuid']
  total_amount decimal(12,2) [note: 'auto:server']
  discount_amount decimal(12,2) [default: 0, note: 'auto:server']
  status enum('pending', 'confirmed', 'shipped') [default: 'pending', note: 'auto:server']
  ip_address varchar(45) [note: 'auto:server']
  created_at timestamp [note: 'auto:db']
}

Table order_items {
  id int [pk, increment, note: 'auto:db']
  order_id int [ref: > orders.id, note: 'input:required']
  product_id int [ref: > products.id, note: 'input:required']
  quantity int [note: 'input:required|min:1']
  unit_price decimal(10,2) [note: 'auto:server']
  total_price decimal(10,2) [note: 'auto:server']
}
```

---

## 6. 생성되는 API 엔드포인트

### 기본 CRUD
```
GET    /api/{resource}              - List all
POST   /api/{resource}/store        - Create
GET    /api/{resource}/show/{id}    - Get one
PUT    /api/{resource}/update/{id}  - Update
DELETE /api/{resource}/destroy/{id} - Delete
```

### 관계 엔드포인트
```
GET /api/posts/category/1           - Get category of post #1
GET /api/categories/posts/1         - Get all posts in category #1
GET /api/posts/user/1               - Get author of post #1
GET /api/users/posts/1              - Get all posts by user #1
```

---

## 7. 조건부 로직 구현

복잡한 비즈니스 로직은 Service 레이어에서 구현:

```php
// app/Services/OrderService.php

public function create(array $data): array
{
    $db = \ACE\Database\DB::getInstance();

    try {
        $db->beginTransaction();

        // 1. 재고 확인
        $product = Product::find($data['product_id']);
        if ($product['stock_quantity'] < $data['quantity']) {
            throw new \Exception('Insufficient stock');
        }

        // 2. 금액 계산
        $unitPrice = $product['price'];
        $totalPrice = $unitPrice * $data['quantity'];

        // 3. 회원 등급별 할인
        $customer = Customer::find($data['customer_id']);
        $discount = $this->calculateDiscount($customer['level'], $totalPrice);

        // 4. 주문 생성
        $orderId = Order::create([
            'customer_id' => $data['customer_id'],
            'order_number' => uniqid('ORD-'),
            'total_amount' => $totalPrice,
            'discount_amount' => $discount,
            'status' => 'pending',
        ]);

        // 5. 재고 차감
        Product::update($data['product_id'], [
            'stock_quantity' => $product['stock_quantity'] - $data['quantity']
        ]);

        $db->commit();
        return Order::find($orderId);

    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
```

---

## 더 자세한 내용

- **전체 가이드**: [docs/DBML_GUIDE.md](DBML_GUIDE.md)
- **전자상거래 예제**: [../examples/ecommerce-schema.dbml](../examples/ecommerce-schema.dbml)
- **Service 예제**: [../examples/OrderService_example.php](../examples/OrderService_example.php)
- **Controller 예제**: [../examples/OrderController_example.php](../examples/OrderController_example.php)
