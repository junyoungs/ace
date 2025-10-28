# DBML 메타데이터 완벽 가이드

ACE Framework는 DBML의 `note:` 속성을 통해 API 동작을 제어합니다. 이 가이드는 모든 기능과 복잡한 시나리오를 다룹니다.

## 목차
1. [기본 메타데이터](#1-기본-메타데이터)
2. [테이블 관계 정의](#2-테이블-관계-정의)
3. [복잡한 5개 이상 테이블 조인](#3-복잡한-5개-이상-테이블-조인)
4. [조건부 등록/수정](#4-조건부-등록수정)
5. [실전 예제: 전자상거래](#5-실전-예제-전자상거래)

---

## 1. 기본 메타데이터

### 1.1 Input Fields (API 입력 필드)

사용자가 직접 입력하는 필드입니다.

```dbml
Table products {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [not null, note: 'input:required']           // 필수 입력
  description text [null, note: 'input:optional']                // 선택 입력
  price decimal(10,2) [not null, note: 'input:required|min:0']   // 필수 + 검증
  email varchar(255) [note: 'input:required|email']              // 이메일 검증
  website varchar(500) [note: 'input:optional|url']              // URL 검증
}
```

**API Request 예시:**
```json
POST /api/products/store
{
  "name": "iPhone 15",
  "description": "Latest model",
  "price": 999.99,
  "email": "contact@example.com",
  "website": "https://example.com"
}
```

### 1.2 Auto Fields (자동 생성 필드)

서버나 데이터베이스가 자동으로 생성하는 필드입니다.

#### auto:db - 데이터베이스 자동 생성
```dbml
Table users {
  id int [pk, increment, note: 'auto:db']        // AUTO_INCREMENT
  created_at timestamp [note: 'auto:db']         // CURRENT_TIMESTAMP
  updated_at timestamp [note: 'auto:db']         // ON UPDATE CURRENT_TIMESTAMP
}
```

#### auto:server - 서버에서 생성
```dbml
Table posts {
  id int [pk, increment, note: 'auto:db']
  title varchar(255) [note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=title']     // title에서 slug 생성
  user_id int [note: 'auto:server:from=auth']                     // 인증된 사용자 ID
  ip_address varchar(45) [note: 'auto:server']                   // 요청 IP 주소
  status enum('active', 'inactive') [note: 'auto:server']        // 기본값 또는 로직
  uuid varchar(36) [unique, note: 'auto:server:uuid']            // UUID 생성
}
```

**API Request 예시:**
```json
POST /api/posts/store
{
  "title": "Hello World",
  "content": "First post"
}

// 서버가 자동 생성:
// - slug: "hello-world"
// - user_id: 123 (현재 로그인한 사용자)
// - ip_address: "192.168.1.1"
// - status: "active"
// - uuid: "550e8400-e29b-41d4-a716-446655440000"
```

### 1.3 Validation (검증 규칙)

```dbml
Table users {
  email varchar(255) [note: 'input:required|email']              // 이메일 형식
  password varchar(255) [note: 'input:required|min:8']           // 최소 8자
  age int [note: 'input:optional|min:18|max:120']                // 18-120 범위
  website varchar(500) [note: 'input:optional|url']              // URL 형식
  phone varchar(20) [note: 'input:optional|phone']               // 전화번호 형식
}
```

---

## 2. 테이블 관계 정의

### 2.1 Many-to-One (belongsTo)

```dbml
Table posts {
  id int [pk, increment]
  user_id int [ref: > users.id]      // posts > users (Many posts belong to one user)
  category_id int [ref: > categories.id]
}

Table users {
  id int [pk, increment]
  name varchar(255)
}
```

**생성되는 API:**
```
GET /api/posts/user/1           // Get user of post #1
GET /api/posts/category/1       // Get category of post #1
```

**사용 예시:**
```php
// PostController
public function getUser(int $id): ?array {
    return $this->postService->getUser($id);
}

// PostService
public function getUser(int $id): ?array {
    $model = new Post();
    return $model->user($id);  // belongsTo relationship
}
```

### 2.2 One-to-Many (hasMany)

```dbml
Table users {
  id int [pk, increment]
}

Table posts {
  id int [pk, increment]
  user_id int [ref: > users.id]      // 자동으로 users.posts() hasMany 관계 생성
}
```

**생성되는 API:**
```
GET /api/users/posts/1          // Get all posts by user #1
```

### 2.3 One-to-One

```dbml
Table users {
  id int [pk, increment]
}

Table members {
  id int [pk, increment]
  user_id int [ref: - users.id, unique]     // One-to-One (주의: - 사용)
}
```

### 2.4 Self-referencing (재귀 관계)

```dbml
Table categories {
  id int [pk, increment]
  name varchar(255)
  parent_id int [ref: > categories.id, null]    // 자기 자신 참조
}

Table comments {
  id int [pk, increment]
  post_id int [ref: > posts.id]
  parent_id int [ref: > comments.id, null]      // 대댓글 (replies)
}
```

**생성되는 메서드:**
```php
// Category model
public function parent(int $id): ?array { }        // belongsTo
public function children(int $id): array { }       // hasMany

// Comment model
public function parent(int $id): ?array { }        // 부모 댓글
public function children(int $id): array { }       // 대댓글들
```

---

## 3. 복잡한 5개 이상 테이블 조인

전자상거래 시스템에서 **주문 상세 정보 조회**는 최소 5개 이상의 테이블이 필요합니다:

```
orders → order_items → products → product_options
      → customers
      → payments
      → shipping_addresses
```

### 3.1 전체 스키마

```dbml
// 1. 고객
Table customers {
  id int [pk, increment, note: 'auto:db']
  user_id int [ref: - users.id, note: 'auto:server:from=auth']
  name varchar(255) [note: 'input:required']
  phone varchar(20) [note: 'input:required']
  customer_level enum('bronze', 'silver', 'gold', 'platinum') [default: 'bronze', note: 'auto:server']
  total_purchases decimal(12,2) [default: 0, note: 'auto:server']
  created_at timestamp [note: 'auto:db']
}

// 2. 상품
Table products {
  id int [pk, increment, note: 'auto:db']
  name varchar(255) [note: 'input:required']
  slug varchar(255) [unique, note: 'auto:server:from=name']
  price decimal(10,2) [note: 'input:required']
  stock_quantity int [default: 0, note: 'input:required']
  status enum('active', 'inactive', 'out_of_stock') [default: 'active', note: 'auto:server']
  created_at timestamp [note: 'auto:db']
}

// 3. 상품 옵션 (색상, 사이즈 등)
Table product_options {
  id int [pk, increment, note: 'auto:db']
  product_id int [ref: > products.id, note: 'input:required']
  option_name varchar(100) [note: 'input:required']           // "색상", "사이즈"
  option_value varchar(100) [note: 'input:required']          // "빨강", "L"
  price_adjustment decimal(10,2) [default: 0, note: 'input:optional']  // 추가 금액
  stock_quantity int [default: 0, note: 'input:required']
}

// 4. 주문
Table orders {
  id int [pk, increment, note: 'auto:db']
  customer_id int [ref: > customers.id, note: 'auto:server:from=auth']
  order_number varchar(50) [unique, note: 'auto:server:uuid']
  total_amount decimal(12,2) [note: 'auto:server']            // 계산됨
  discount_amount decimal(12,2) [default: 0, note: 'auto:server']
  final_amount decimal(12,2) [note: 'auto:server']            // total - discount
  status enum('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') [default: 'pending', note: 'auto:server']
  created_at timestamp [note: 'auto:db']
  updated_at timestamp [note: 'auto:db']

  indexes {
    (customer_id, status) [name: 'idx_customer_orders']
    (order_number) [name: 'idx_order_number']
  }
}

// 5. 주문 항목 (장바구니 항목)
Table order_items {
  id int [pk, increment, note: 'auto:db']
  order_id int [ref: > orders.id, note: 'input:required']
  product_id int [ref: > products.id, note: 'input:required']
  product_option_id int [ref: > product_options.id, null, note: 'input:optional']
  quantity int [note: 'input:required|min:1']
  unit_price decimal(10,2) [note: 'auto:server']              // 주문 시점의 가격
  total_price decimal(10,2) [note: 'auto:server']             // quantity * unit_price
  created_at timestamp [note: 'auto:db']

  indexes {
    (order_id) [name: 'idx_order_items']
    (product_id) [name: 'idx_product']
  }
}

// 6. 결제
Table payments {
  id int [pk, increment, note: 'auto:db']
  order_id int [ref: > orders.id, note: 'input:required']
  payment_method enum('card', 'bank_transfer', 'paypal', 'cash') [note: 'input:required']
  amount decimal(12,2) [note: 'input:required']
  status enum('pending', 'completed', 'failed', 'refunded') [default: 'pending', note: 'auto:server']
  transaction_id varchar(255) [unique, null, note: 'auto:server']
  paid_at timestamp [null, note: 'auto:server']
  created_at timestamp [note: 'auto:db']

  indexes {
    (order_id) [name: 'idx_order_payment']
    (transaction_id) [name: 'idx_transaction']
  }
}

// 7. 배송지
Table shipping_addresses {
  id int [pk, increment, note: 'auto:db']
  order_id int [ref: > orders.id, note: 'input:required']
  recipient_name varchar(255) [note: 'input:required']
  phone varchar(20) [note: 'input:required']
  address varchar(500) [note: 'input:required']
  city varchar(100) [note: 'input:required']
  postal_code varchar(20) [note: 'input:required']
  delivery_memo text [null, note: 'input:optional']
  created_at timestamp [note: 'auto:db']
}
```

### 3.2 복잡한 조인 쿼리 예시

**주문 상세 정보 조회 (7개 테이블 조인):**

```sql
SELECT
  o.id, o.order_number, o.total_amount, o.status,
  c.name as customer_name, c.phone as customer_phone, c.customer_level,
  oi.quantity, oi.unit_price, oi.total_price,
  p.name as product_name, p.slug,
  po.option_name, po.option_value,
  pay.payment_method, pay.status as payment_status,
  sa.recipient_name, sa.address, sa.city
FROM orders o
INNER JOIN customers c ON o.customer_id = c.id
INNER JOIN order_items oi ON o.id = oi.order_id
INNER JOIN products p ON oi.product_id = p.id
LEFT JOIN product_options po ON oi.product_option_id = po.id
LEFT JOIN payments pay ON o.id = pay.order_id
LEFT JOIN shipping_addresses sa ON o.id = sa.order_id
WHERE o.id = ?
```

---

## 4. 조건부 등록/수정

복잡한 비즈니스 로직은 Service 레이어에서 구현합니다.

### 4.1 조건부 등록 예시

#### 예시 1: 주문 생성 시 재고 확인 및 차감

```php
// app/Services/OrderService.php
public function create(array $data): array
{
    $db = \ACE\Database\DB::getInstance();

    try {
        $db->beginTransaction();

        // 1. 고객 정보 가져오기
        $customer = Customer::where('user_id', $data['user_id'])[0] ?? null;
        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        // 2. 주문 항목 검증 및 총액 계산
        $totalAmount = 0;
        $validatedItems = [];

        foreach ($data['items'] as $item) {
            $product = Product::find($item['product_id']);

            // 재고 확인
            if ($product['stock_quantity'] < $item['quantity']) {
                throw new \Exception("Insufficient stock for {$product['name']}");
            }

            // 옵션이 있는 경우
            $unitPrice = $product['price'];
            if (!empty($item['product_option_id'])) {
                $option = ProductOption::find($item['product_option_id']);

                // 옵션 재고 확인
                if ($option['stock_quantity'] < $item['quantity']) {
                    throw new \Exception("Insufficient stock for option");
                }

                $unitPrice += $option['price_adjustment'];
            }

            $itemTotal = $unitPrice * $item['quantity'];
            $totalAmount += $itemTotal;

            $validatedItems[] = [
                'product_id' => $item['product_id'],
                'product_option_id' => $item['product_option_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $itemTotal,
            ];
        }

        // 3. 회원 등급별 할인 적용
        $discountAmount = 0;
        switch ($customer['customer_level']) {
            case 'silver':
                $discountAmount = $totalAmount * 0.05;  // 5%
                break;
            case 'gold':
                $discountAmount = $totalAmount * 0.10;  // 10%
                break;
            case 'platinum':
                $discountAmount = $totalAmount * 0.15;  // 15%
                break;
        }

        // 4. 조건부: 10만원 이상 구매 시 추가 할인
        if ($totalAmount >= 100000) {
            $discountAmount += 5000;  // 5,000원 추가 할인
        }

        $finalAmount = $totalAmount - $discountAmount;

        // 5. 주문 생성
        $orderId = Order::create([
            'customer_id' => $customer['id'],
            'order_number' => uniqid('ORD-'),
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
            'status' => 'pending',
        ]);

        // 6. 주문 항목 생성 및 재고 차감
        foreach ($validatedItems as $item) {
            OrderItem::create([
                'order_id' => $orderId,
                ...$item
            ]);

            // 재고 차감
            Product::update($item['product_id'], [
                'stock_quantity' => Product::find($item['product_id'])['stock_quantity'] - $item['quantity']
            ]);

            // 옵션 재고 차감
            if ($item['product_option_id']) {
                $option = ProductOption::find($item['product_option_id']);
                ProductOption::update($item['product_option_id'], [
                    'stock_quantity' => $option['stock_quantity'] - $item['quantity']
                ]);
            }
        }

        // 7. 배송지 생성
        ShippingAddress::create([
            'order_id' => $orderId,
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'delivery_memo' => $data['delivery_memo'] ?? null,
        ]);

        // 8. 고객 총 구매액 업데이트
        Customer::update($customer['id'], [
            'total_purchases' => $customer['total_purchases'] + $finalAmount
        ]);

        // 9. 조건부: 총 구매액에 따라 등급 자동 상승
        $newLevel = $this->calculateCustomerLevel($customer['total_purchases'] + $finalAmount);
        if ($newLevel !== $customer['customer_level']) {
            Customer::update($customer['id'], [
                'customer_level' => $newLevel
            ]);
        }

        $db->commit();

        return Order::find($orderId);

    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

private function calculateCustomerLevel(float $totalPurchases): string
{
    if ($totalPurchases >= 1000000) return 'platinum';  // 100만원
    if ($totalPurchases >= 500000) return 'gold';       // 50만원
    if ($totalPurchases >= 200000) return 'silver';     // 20만원
    return 'bronze';
}
```

#### 예시 2: 결제 완료 시 자동 처리

```php
// app/Services/PaymentService.php
public function completePayment(int $paymentId, string $transactionId): array
{
    $db = \ACE\Database\DB::getInstance();

    try {
        $db->beginTransaction();

        // 1. 결제 정보 업데이트
        Payment::update($paymentId, [
            'status' => 'completed',
            'transaction_id' => $transactionId,
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        $payment = Payment::find($paymentId);

        // 2. 조건부: 결제 완료 시 주문 상태 변경
        Order::update($payment['order_id'], [
            'status' => 'confirmed'
        ]);

        // 3. 조건부: 첫 구매 고객에게 포인트 지급
        $order = Order::find($payment['order_id']);
        $customer = Customer::find($order['customer_id']);

        $customerOrders = Order::where('customer_id', $customer['id']);
        if (count($customerOrders) === 1) {  // 첫 구매
            // 포인트 지급 로직 (별도 테이블 필요)
            // Point::create(['customer_id' => $customer['id'], 'amount' => 5000]);
        }

        // 4. 조건부: 10만원 이상 구매 시 자동 무료 배송
        if ($order['final_amount'] >= 100000) {
            // 무료 배송 처리
        }

        $db->commit();

        return $payment;

    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}
```

### 4.2 조건부 업데이트 예시

#### 예시: 주문 취소 시 재고 복원

```php
// app/Services/OrderService.php
public function cancel(int $orderId): array
{
    $db = \ACE\Database\DB::getInstance();

    try {
        $db->beginTransaction();

        $order = Order::find($orderId);

        // 1. 주문 상태 확인
        if (in_array($order['status'], ['shipped', 'delivered'])) {
            throw new \Exception('Cannot cancel order after shipping');
        }

        // 2. 주문 항목 가져오기
        $items = OrderItem::where('order_id', $orderId);

        // 3. 재고 복원
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            Product::update($item['product_id'], [
                'stock_quantity' => $product['stock_quantity'] + $item['quantity']
            ]);

            // 옵션 재고 복원
            if ($item['product_option_id']) {
                $option = ProductOption::find($item['product_option_id']);
                ProductOption::update($item['product_option_id'], [
                    'stock_quantity' => $option['stock_quantity'] + $item['quantity']
                ]);
            }
        }

        // 4. 결제 환불 처리
        $payments = Payment::where('order_id', $orderId);
        foreach ($payments as $payment) {
            if ($payment['status'] === 'completed') {
                Payment::update($payment['id'], [
                    'status' => 'refunded'
                ]);
            }
        }

        // 5. 주문 상태 변경
        Order::update($orderId, [
            'status' => 'cancelled'
        ]);

        // 6. 고객 총 구매액 차감
        $customer = Customer::find($order['customer_id']);
        Customer::update($customer['id'], [
            'total_purchases' => max(0, $customer['total_purchases'] - $order['final_amount'])
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

## 5. 실전 예제: 전자상거래

### 5.1 API 호출 흐름

#### 주문 생성 전체 프로세스

```json
// 1. 상품 조회
GET /api/products
Response: [
  {
    "id": 1,
    "name": "iPhone 15 Pro",
    "price": 1299.99,
    "stock_quantity": 50
  }
]

// 2. 상품 옵션 조회
GET /api/product-options
?product_id=1
Response: [
  {
    "id": 1,
    "product_id": 1,
    "option_name": "색상",
    "option_value": "스페이스 블랙",
    "price_adjustment": 0,
    "stock_quantity": 20
  },
  {
    "id": 2,
    "product_id": 1,
    "option_name": "저장용량",
    "option_value": "256GB",
    "price_adjustment": 100,
    "stock_quantity": 15
  }
]

// 3. 주문 생성
POST /api/orders/store
Authorization: Bearer {token}
{
  "items": [
    {
      "product_id": 1,
      "product_option_id": 2,
      "quantity": 1
    }
  ],
  "recipient_name": "홍길동",
  "phone": "010-1234-5678",
  "address": "서울시 강남구 테헤란로 123",
  "city": "서울",
  "postal_code": "12345",
  "delivery_memo": "문 앞에 놔주세요"
}

Response: {
  "id": 1,
  "order_number": "ORD-64f8b2a3c1e5d",
  "total_amount": 1399.99,
  "discount_amount": 69.99,
  "final_amount": 1330.00,
  "status": "pending"
}

// 4. 결제 생성
POST /api/payments/store
{
  "order_id": 1,
  "payment_method": "card",
  "amount": 1330.00
}

// 5. 결제 완료 (커스텀 엔드포인트)
POST /api/payments/complete/1
{
  "transaction_id": "TXN-123456789"
}

// 6. 주문 상세 조회 (5개 이상 테이블 조인)
GET /api/orders/show/1
Response: {
  "id": 1,
  "order_number": "ORD-64f8b2a3c1e5d",
  "total_amount": 1399.99,
  "discount_amount": 69.99,
  "final_amount": 1330.00,
  "status": "confirmed",
  "customer": {
    "id": 1,
    "name": "김철수",
    "customer_level": "silver"
  },
  "items": [
    {
      "id": 1,
      "product": {
        "id": 1,
        "name": "iPhone 15 Pro"
      },
      "option": {
        "option_name": "저장용량",
        "option_value": "256GB"
      },
      "quantity": 1,
      "unit_price": 1399.99,
      "total_price": 1399.99
    }
  ],
  "payment": {
    "id": 1,
    "payment_method": "card",
    "status": "completed",
    "transaction_id": "TXN-123456789"
  },
  "shipping_address": {
    "recipient_name": "홍길동",
    "address": "서울시 강남구 테헤란로 123",
    "phone": "010-1234-5678"
  }
}
```

### 5.2 커스텀 엔드포인트 추가

자동 생성된 CRUD 외에 복잡한 로직은 커스텀 메서드로 추가합니다:

```php
// app/Http/Controllers/OrderController.php
class OrderController extends \ACE\Http\Control
{
    // ... 자동 생성된 CRUD 메서드들 ...

    // ========================================
    // Custom Endpoints (add below)
    // ========================================

    /**
     * POST /api/orders/checkout
     * 장바구니에서 주문 생성 (복잡한 로직)
     */
    public function postCheckout(): array
    {
        $data = $this->request->getParsedBody();

        // 인증 정보 가져오기
        $authUser = $this->request->getAttribute('auth_user');
        $data['user_id'] = $authUser['user_id'];

        return $this->orderService->create($data);
    }

    /**
     * POST /api/orders/cancel/{id}
     * 주문 취소 (재고 복원 포함)
     */
    public function postCancel(int $id): array
    {
        return $this->orderService->cancel($id);
    }

    /**
     * GET /api/orders/my-orders
     * 내 주문 목록
     */
    public function getMyOrders(): array
    {
        $authUser = $this->request->getAttribute('auth_user');

        // customer_id 찾기
        $customer = Customer::where('user_id', $authUser['user_id'])[0] ?? null;
        if (!$customer) {
            return [];
        }

        return Order::where('customer_id', $customer['id']);
    }

    /**
     * GET /api/orders/sales-report
     * 매출 리포트 (관리자 전용, 복잡한 집계)
     */
    public function getSalesReport(): array
    {
        // TODO: 권한 확인

        $sql = "
            SELECT
                DATE(o.created_at) as date,
                COUNT(o.id) as order_count,
                SUM(o.final_amount) as total_sales,
                AVG(o.final_amount) as avg_order_value
            FROM orders o
            WHERE o.status IN ('confirmed', 'shipped', 'delivered')
            AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(o.created_at)
            ORDER BY date DESC
        ";

        return Order::query($sql);
    }
}
```

---

## 요약

### Input vs Auto 결정 가이드

| 필드 | 메타데이터 | 설명 |
|------|-----------|------|
| 사용자 입력 | `input:required` | API로 반드시 받아야 함 |
| 선택 입력 | `input:optional` | API로 받을 수 있지만 필수 아님 |
| DB 자동 | `auto:db` | AUTO_INCREMENT, CURRENT_TIMESTAMP |
| 서버 생성 | `auto:server` | 서버 로직으로 생성 (IP, 상태 등) |
| 다른 필드에서 | `auto:server:from=field` | slug from title |
| 인증 정보 | `auto:server:from=auth` | 현재 로그인한 사용자 |
| UUID | `auto:server:uuid` | UUID 자동 생성 |

### 관계 정의

| 관계 유형 | DBML 문법 | 설명 |
|----------|----------|------|
| Many-to-One | `ref: > table.column` | belongsTo |
| One-to-One | `ref: - table.column` | unique 관계 |
| One-to-Many | (역방향 자동 생성) | hasMany |

### 복잡한 로직 구현 위치

1. **Service Layer**: 비즈니스 로직, 트랜잭션, 검증
2. **Controller**: 커스텀 엔드포인트, 권한 확인
3. **Model**: 간단한 관계 메서드 (자동 생성됨)

### 다음 단계

1. `database/schema.dbml`에 위 예제 복사
2. `./ace api` 실행하여 코드 생성
3. `app/Services/`에서 복잡한 로직 구현
4. `app/Http/Controllers/`에 커스텀 엔드포인트 추가
5. `./ace migrate` 실행
6. `./ace serve` 실행하여 테스트
