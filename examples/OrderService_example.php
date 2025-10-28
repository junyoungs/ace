<?php declare(strict_types=1);

namespace APP\Services;

use APP\Models\Order;
use APP\Models\OrderItem;
use APP\Models\Customer;
use APP\Models\Product;
use APP\Models\ProductOption;
use APP\Models\Coupon;
use APP\Models\ShippingAddress;
use APP\Models\Payment;
use APP\Models\CartItem;
use APP\Models\InventoryTransaction;

/**
 * 전자상거래 주문 서비스 - 복잡한 조건부 로직 예제
 *
 * 이 파일은 5개 이상 테이블 조인과 조건부 등록/수정 로직을 보여줍니다.
 * 실제 프로젝트에서는 이 코드를 app/Services/OrderService.php에 복사해서 사용하세요.
 */
class OrderService
{
    private \ACE\Database\DB $db;

    public function __construct()
    {
        $this->db = \ACE\Database\DB::getInstance();
    }

    // ========================================
    // 복잡한 주문 생성 (조건부 로직 포함)
    // ========================================

    /**
     * 장바구니에서 주문 생성
     *
     * 조건부 처리:
     * 1. 재고 확인 및 차감
     * 2. 쿠폰 검증 및 할인 계산
     * 3. 회원 등급별 할인
     * 4. 배송비 계산 (조건부)
     * 5. 포인트 적립
     * 6. 재고 트랜잭션 로그
     * 7. 주문 완료 후 장바구니 비우기
     */
    public function createFromCart(array $data): array
    {
        try {
            $this->db->beginTransaction();

            // 1. 고객 정보 가져오기
            $customer = $this->getAuthenticatedCustomer($data['user_id']);

            // 2. 장바구니 항목 가져오기
            $cartItems = CartItem::where('customer_id', $customer['id']);
            if (empty($cartItems)) {
                throw new \Exception('Cart is empty');
            }

            // 3. 재고 확인 및 주문 항목 준비
            $orderItems = $this->validateAndPrepareOrderItems($cartItems);

            // 4. 금액 계산
            $amounts = $this->calculateOrderAmounts(
                $orderItems,
                $customer,
                $data['coupon_code'] ?? null
            );

            // 5. 주문 생성
            $orderId = Order::create([
                'customer_id' => $customer['id'],
                'order_number' => $this->generateOrderNumber(),
                'subtotal_amount' => $amounts['subtotal'],
                'discount_amount' => $amounts['discount'],
                'shipping_fee' => $amounts['shipping_fee'],
                'tax_amount' => $amounts['tax'],
                'total_amount' => $amounts['total'],
                'coupon_code' => $data['coupon_code'] ?? null,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'customer_notes' => $data['customer_notes'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);

            // 6. 주문 항목 생성 및 재고 차감
            foreach ($orderItems as $item) {
                $this->createOrderItemAndUpdateStock($orderId, $item);
            }

            // 7. 쿠폰 사용 횟수 증가
            if (!empty($data['coupon_code'])) {
                $this->incrementCouponUsage($data['coupon_code']);
            }

            // 8. 배송지 정보 저장
            ShippingAddress::create([
                'order_id' => $orderId,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'postal_code' => $data['postal_code'],
                'address' => $data['address'],
                'address_detail' => $data['address_detail'] ?? null,
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'country' => $data['country'] ?? 'South Korea',
                'delivery_memo' => $data['delivery_memo'] ?? null,
            ]);

            // 9. 장바구니 비우기
            $this->clearCart($customer['id']);

            // 10. 포인트 적립 (조건부: 결제 완료 후에만)
            // 이 부분은 결제 완료 시 처리됩니다

            $this->db->commit();

            return $this->getOrderWithDetails($orderId);

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 재고 확인 및 주문 항목 준비
     */
    private function validateAndPrepareOrderItems(array $cartItems): array
    {
        $orderItems = [];

        foreach ($cartItems as $cartItem) {
            $product = Product::find($cartItem['product_id']);
            if (!$product) {
                throw new \Exception("Product not found: {$cartItem['product_id']}");
            }

            // 상품 상태 확인
            if ($product['status'] !== 'active') {
                throw new \Exception("Product is not available: {$product['name']}");
            }

            $unitPrice = $product['sale_price'] ?? $product['base_price'];
            $optionDetails = null;
            $sku = $product['sku'];

            // 옵션이 있는 경우
            if (!empty($cartItem['product_option_id'])) {
                $option = ProductOption::find($cartItem['product_option_id']);
                if (!$option) {
                    throw new \Exception("Product option not found");
                }

                // 옵션 재고 확인
                if ($option['stock_quantity'] < $cartItem['quantity']) {
                    throw new \Exception(
                        "Insufficient option stock: {$product['name']} - " .
                        "{$option['option_name']}: {$option['option_value']}"
                    );
                }

                if (!$option['is_available']) {
                    throw new \Exception("Product option is not available");
                }

                $unitPrice += $option['price_adjustment'];
                $optionDetails = "{$option['option_name']}: {$option['option_value']}";
                $sku = $option['sku'] ?? $sku;
            } else {
                // 기본 상품 재고 확인
                if ($product['stock_quantity'] < $cartItem['quantity']) {
                    throw new \Exception("Insufficient stock: {$product['name']}");
                }
            }

            $orderItems[] = [
                'product_id' => $product['id'],
                'product_option_id' => $cartItem['product_option_id'] ?? null,
                'product_name' => $product['name'],
                'product_sku' => $sku,
                'option_details' => $optionDetails,
                'quantity' => $cartItem['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $cartItem['quantity'],
            ];
        }

        return $orderItems;
    }

    /**
     * 주문 금액 계산 (조건부 할인 및 배송비)
     */
    private function calculateOrderAmounts(array $orderItems, array $customer, ?string $couponCode): array
    {
        // 1. 소계 (상품 금액 합계)
        $subtotal = array_sum(array_column($orderItems, 'total_price'));

        // 2. 회원 등급별 할인
        $memberDiscount = $this->calculateMemberDiscount($subtotal, $customer['customer_level']);

        // 3. 쿠폰 할인
        $couponDiscount = 0;
        if ($couponCode) {
            $couponDiscount = $this->calculateCouponDiscount($couponCode, $subtotal);
        }

        // 4. 총 할인 금액
        $totalDiscount = $memberDiscount + $couponDiscount;

        // 5. 조건부 배송비 계산
        $amountAfterDiscount = $subtotal - $totalDiscount;
        $shippingFee = $this->calculateShippingFee($amountAfterDiscount);

        // 6. 세금 (10%)
        $tax = ($amountAfterDiscount + $shippingFee) * 0.1;

        // 7. 최종 금액
        $total = $amountAfterDiscount + $shippingFee + $tax;

        return [
            'subtotal' => $subtotal,
            'discount' => $totalDiscount,
            'member_discount' => $memberDiscount,
            'coupon_discount' => $couponDiscount,
            'shipping_fee' => $shippingFee,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * 회원 등급별 할인 계산
     */
    private function calculateMemberDiscount(float $subtotal, string $customerLevel): float
    {
        $discountRates = [
            'bronze' => 0.00,    // 0%
            'silver' => 0.05,    // 5%
            'gold' => 0.10,      // 10%
            'platinum' => 0.15,  // 15%
        ];

        return $subtotal * ($discountRates[$customerLevel] ?? 0);
    }

    /**
     * 쿠폰 할인 계산 (조건부 검증)
     */
    private function calculateCouponDiscount(string $couponCode, float $subtotal): float
    {
        $coupons = Coupon::where('code', $couponCode);
        if (empty($coupons)) {
            throw new \Exception('Invalid coupon code');
        }

        $coupon = $coupons[0];

        // 조건 1: 쿠폰 활성화 여부
        if (!$coupon['is_active']) {
            throw new \Exception('Coupon is not active');
        }

        // 조건 2: 유효 기간
        $now = time();
        $validFrom = strtotime($coupon['valid_from']);
        $validUntil = strtotime($coupon['valid_until']);

        if ($now < $validFrom || $now > $validUntil) {
            throw new \Exception('Coupon has expired or not yet valid');
        }

        // 조건 3: 사용 횟수 제한
        if ($coupon['usage_limit'] !== null && $coupon['used_count'] >= $coupon['usage_limit']) {
            throw new \Exception('Coupon usage limit exceeded');
        }

        // 조건 4: 최소 구매 금액
        if ($subtotal < $coupon['min_purchase_amount']) {
            throw new \Exception(
                "Minimum purchase amount is {$coupon['min_purchase_amount']}"
            );
        }

        // 할인 금액 계산
        $discount = 0;

        if ($coupon['discount_type'] === 'percentage') {
            $discount = $subtotal * ($coupon['discount_value'] / 100);
        } else {
            $discount = $coupon['discount_value'];
        }

        // 최대 할인 금액 제한
        if ($coupon['max_discount_amount'] !== null) {
            $discount = min($discount, $coupon['max_discount_amount']);
        }

        return $discount;
    }

    /**
     * 조건부 배송비 계산
     * - 10만원 이상: 무료
     * - 그 외: 3,000원
     */
    private function calculateShippingFee(float $amount): float
    {
        if ($amount >= 100000) {
            return 0;
        }

        return 3000;
    }

    /**
     * 주문 항목 생성 및 재고 차감
     */
    private function createOrderItemAndUpdateStock(int $orderId, array $item): void
    {
        // 1. 주문 항목 생성
        OrderItem::create([
            'order_id' => $orderId,
            ...$item
        ]);

        // 2. 재고 차감
        if ($item['product_option_id']) {
            // 옵션 재고 차감
            $option = ProductOption::find($item['product_option_id']);
            $newStock = $option['stock_quantity'] - $item['quantity'];

            ProductOption::update($item['product_option_id'], [
                'stock_quantity' => $newStock,
                'is_available' => $newStock > 0,
            ]);

            // 재고 트랜잭션 로그
            InventoryTransaction::create([
                'product_id' => $item['product_id'],
                'product_option_id' => $item['product_option_id'],
                'transaction_type' => 'out',
                'quantity' => $item['quantity'],
                'quantity_before' => $option['stock_quantity'],
                'quantity_after' => $newStock,
                'reason' => 'Order placed',
                'reference_type' => 'order',
                'reference_id' => $orderId,
            ]);
        } else {
            // 기본 상품 재고 차감
            $product = Product::find($item['product_id']);
            $newStock = $product['stock_quantity'] - $item['quantity'];

            Product::update($item['product_id'], [
                'stock_quantity' => $newStock,
                'status' => $newStock === 0 ? 'out_of_stock' : $product['status'],
            ]);

            // 재고 트랜잭션 로그
            InventoryTransaction::create([
                'product_id' => $item['product_id'],
                'product_option_id' => null,
                'transaction_type' => 'out',
                'quantity' => $item['quantity'],
                'quantity_before' => $product['stock_quantity'],
                'quantity_after' => $newStock,
                'reason' => 'Order placed',
                'reference_type' => 'order',
                'reference_id' => $orderId,
            ]);
        }
    }

    // ========================================
    // 주문 취소 (조건부 재고 복원)
    // ========================================

    /**
     * 주문 취소 (재고 복원 포함)
     */
    public function cancel(int $orderId): array
    {
        try {
            $this->db->beginTransaction();

            $order = Order::find($orderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // 조건 1: 취소 가능 상태 확인
            if (in_array($order['status'], ['shipped', 'delivered', 'cancelled', 'refunded'])) {
                throw new \Exception('Cannot cancel order in current status');
            }

            // 조건 2: 결제 완료된 경우만 환불 처리
            if ($order['payment_status'] === 'paid') {
                $this->refundPayment($orderId);
            }

            // 3. 재고 복원
            $this->restoreStock($orderId);

            // 4. 쿠폰 사용 횟수 감소
            if ($order['coupon_code']) {
                $this->decrementCouponUsage($order['coupon_code']);
            }

            // 5. 주문 상태 변경
            Order::update($orderId, [
                'status' => 'cancelled',
                'payment_status' => 'refunded',
            ]);

            // 6. 고객 총 구매액 차감 (결제 완료된 경우에만)
            if ($order['payment_status'] === 'paid') {
                $customer = Customer::find($order['customer_id']);
                Customer::update($customer['id'], [
                    'total_purchases' => max(0, $customer['total_purchases'] - $order['total_amount'])
                ]);

                // 등급 재계산
                $newLevel = $this->calculateCustomerLevel($customer['total_purchases'] - $order['total_amount']);
                if ($newLevel !== $customer['customer_level']) {
                    Customer::update($customer['id'], [
                        'customer_level' => $newLevel
                    ]);
                }
            }

            $this->db->commit();

            return Order::find($orderId);

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 재고 복원
     */
    private function restoreStock(int $orderId): void
    {
        $items = OrderItem::where('order_id', $orderId);

        foreach ($items as $item) {
            if ($item['product_option_id']) {
                // 옵션 재고 복원
                $option = ProductOption::find($item['product_option_id']);
                $newStock = $option['stock_quantity'] + $item['quantity'];

                ProductOption::update($item['product_option_id'], [
                    'stock_quantity' => $newStock,
                    'is_available' => true,
                ]);

                // 재고 트랜잭션 로그
                InventoryTransaction::create([
                    'product_id' => $item['product_id'],
                    'product_option_id' => $item['product_option_id'],
                    'transaction_type' => 'return',
                    'quantity' => $item['quantity'],
                    'quantity_before' => $option['stock_quantity'],
                    'quantity_after' => $newStock,
                    'reason' => 'Order cancelled',
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                ]);
            } else {
                // 기본 상품 재고 복원
                $product = Product::find($item['product_id']);
                $newStock = $product['stock_quantity'] + $item['quantity'];

                Product::update($item['product_id'], [
                    'stock_quantity' => $newStock,
                    'status' => $newStock > 0 ? 'active' : $product['status'],
                ]);

                // 재고 트랜잭션 로그
                InventoryTransaction::create([
                    'product_id' => $item['product_id'],
                    'product_option_id' => null,
                    'transaction_type' => 'return',
                    'quantity' => $item['quantity'],
                    'quantity_before' => $product['stock_quantity'],
                    'quantity_after' => $newStock,
                    'reason' => 'Order cancelled',
                    'reference_type' => 'order',
                    'reference_id' => $orderId,
                ]);
            }
        }
    }

    /**
     * 결제 환불
     */
    private function refundPayment(int $orderId): void
    {
        $payments = Payment::where('order_id', $orderId);

        foreach ($payments as $payment) {
            if ($payment['status'] === 'completed') {
                Payment::update($payment['id'], [
                    'status' => 'refunded',
                    'refunded_at' => date('Y-m-d H:i:s'),
                ]);

                // TODO: 실제 PG사 환불 API 호출
                // $this->callPGRefundAPI($payment['transaction_id'], $payment['amount']);
            }
        }
    }

    // ========================================
    // 결제 완료 처리 (조건부 포인트 적립)
    // ========================================

    /**
     * 결제 완료 후 처리
     */
    public function completePayment(int $orderId, string $transactionId): array
    {
        try {
            $this->db->beginTransaction();

            $order = Order::find($orderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // 1. 결제 정보 업데이트
            $payments = Payment::where('order_id', $orderId);
            foreach ($payments as $payment) {
                Payment::update($payment['id'], [
                    'status' => 'completed',
                    'transaction_id' => $transactionId,
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // 2. 주문 상태 변경
            Order::update($orderId, [
                'status' => 'confirmed',
                'payment_status' => 'paid',
            ]);

            // 3. 고객 총 구매액 업데이트
            $customer = Customer::find($order['customer_id']);
            $newTotalPurchases = $customer['total_purchases'] + $order['total_amount'];

            Customer::update($customer['id'], [
                'total_purchases' => $newTotalPurchases
            ]);

            // 4. 조건부: 등급 자동 상승
            $newLevel = $this->calculateCustomerLevel($newTotalPurchases);
            if ($newLevel !== $customer['customer_level']) {
                Customer::update($customer['id'], [
                    'customer_level' => $newLevel
                ]);
            }

            // 5. 조건부: 포인트 적립 (구매 금액의 1%)
            $points = (int)($order['total_amount'] * 0.01);
            Customer::update($customer['id'], [
                'points' => $customer['points'] + $points
            ]);

            // 6. 조건부: 첫 구매 고객에게 보너스 포인트
            $customerOrders = Order::where('customer_id', $customer['id']);
            $paidOrders = array_filter($customerOrders, function($o) {
                return $o['payment_status'] === 'paid';
            });

            if (count($paidOrders) === 1) {
                Customer::update($customer['id'], [
                    'points' => $customer['points'] + 5000  // 첫 구매 보너스 5,000 포인트
                ]);
            }

            $this->db->commit();

            return $this->getOrderWithDetails($orderId);

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * 회원 등급 계산
     */
    private function calculateCustomerLevel(float $totalPurchases): string
    {
        if ($totalPurchases >= 1000000) return 'platinum';  // 100만원 이상
        if ($totalPurchases >= 500000) return 'gold';       // 50만원 이상
        if ($totalPurchases >= 200000) return 'silver';     // 20만원 이상
        return 'bronze';
    }

    // ========================================
    // 복잡한 조인 쿼리 (5개 이상 테이블)
    // ========================================

    /**
     * 주문 상세 정보 조회 (7개 테이블 조인)
     */
    public function getOrderWithDetails(int $orderId): array
    {
        // 기본 주문 정보
        $order = Order::find($orderId);
        if (!$order) {
            return null;
        }

        // 고객 정보
        $order['customer'] = Customer::find($order['customer_id']);

        // 주문 항목 (products, product_options 포함)
        $orderItems = OrderItem::where('order_id', $orderId);
        foreach ($orderItems as &$item) {
            $item['product'] = Product::find($item['product_id']);

            if ($item['product_option_id']) {
                $item['option'] = ProductOption::find($item['product_option_id']);
            }
        }
        $order['items'] = $orderItems;

        // 결제 정보
        $payments = Payment::where('order_id', $orderId);
        $order['payment'] = $payments[0] ?? null;

        // 배송지 정보
        $addresses = ShippingAddress::where('order_id', $orderId);
        $order['shipping_address'] = $addresses[0] ?? null;

        // 배송 정보 (있는 경우)
        $shipments = Shipment::where('order_id', $orderId);
        $order['shipment'] = $shipments[0] ?? null;

        return $order;
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function getAuthenticatedCustomer(int $userId): array
    {
        $customers = Customer::where('user_id', $userId);
        if (empty($customers)) {
            throw new \Exception('Customer profile not found');
        }
        return $customers[0];
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    private function clearCart(int $customerId): void
    {
        $cartItems = CartItem::where('customer_id', $customerId);
        foreach ($cartItems as $item) {
            CartItem::delete($item['id']);
        }
    }

    private function incrementCouponUsage(string $couponCode): void
    {
        $coupons = Coupon::where('code', $couponCode);
        if (!empty($coupons)) {
            $coupon = $coupons[0];
            Coupon::update($coupon['id'], [
                'used_count' => $coupon['used_count'] + 1
            ]);
        }
    }

    private function decrementCouponUsage(string $couponCode): void
    {
        $coupons = Coupon::where('code', $couponCode);
        if (!empty($coupons)) {
            $coupon = $coupons[0];
            Coupon::update($coupon['id'], [
                'used_count' => max(0, $coupon['used_count'] - 1)
            ]);
        }
    }
}
