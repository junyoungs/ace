<?php declare(strict_types=1);

namespace APP\Services;

use APP\Models\Order;
use APP\Models\OrderItem;
use APP\Models\Customer;
use APP\Models\Product;
use APP\Models\CartItem;
use APP\Models\ShippingAddress;
use ACE\Service\BaseService;

/**
 * OrderService Example - Simple multi-table operations
 *
 * This demonstrates ACE patterns for complex business logic.
 * Key principles:
 * - Extend BaseService
 * - Use transaction() for multi-table operations
 * - Keep functions under 100 lines
 * - Max 3 nesting levels
 * - Simple and verifiable
 */
class OrderService extends BaseService
{
    /**
     * Create order from cart (10+ table operation)
     *
     * Simple workflow:
     * 1. Validate cart items
     * 2. Calculate amounts
     * 3. Create order + items + shipping
     * 4. Update stock
     * 5. Clear cart
     */
    public function createFromCart(array $data): array
    {
        // Validate required fields
        $this->validate($data, [
            'user_id' => 'required',
            'recipient_name' => 'required',
            'phone' => 'required',
            'address' => 'required',
        ]);

        return $this->transaction(function() use ($data) {
            // 1. Get cart items
            $customer = $this->getCustomer($data['user_id']);
            $cartItems = CartItem::where('customer_id', $customer['id']);

            if (empty($cartItems)) {
                throw new \Exception('Cart is empty');
            }

            // 2. Prepare order data
            $orderItems = $this->prepareOrderItems($cartItems);
            $amounts = $this->calculateAmounts($orderItems);

            // 3. Create order
            $orderId = Order::create([
                'customer_id' => $customer['id'],
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'subtotal_amount' => $amounts['subtotal'],
                'total_amount' => $amounts['total'],
                'status' => 'pending',
                'payment_status' => 'unpaid',
            ]);

            // 4. Create order items and update stock
            foreach ($orderItems as $item) {
                $this->createOrderItem($orderId, $item);
                $this->updateStock($item['product_id'], -$item['quantity']);
            }

            // 5. Create shipping address
            ShippingAddress::create([
                'order_id' => $orderId,
                'recipient_name' => $data['recipient_name'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'postal_code' => $data['postal_code'] ?? null,
            ]);

            // 6. Clear cart
            $this->clearCart($customer['id']);

            return Order::find($orderId);
        });
    }

    /**
     * Cancel order (restore stock, refund payment)
     */
    public function cancel(int $orderId): array
    {
        return $this->transaction(function() use ($orderId) {
            $order = Order::find($orderId);

            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Check if cancellable
            if (in_array($order['status'], ['shipped', 'cancelled'])) {
                throw new \Exception('Cannot cancel order');
            }

            // Restore stock
            $items = OrderItem::where('order_id', $orderId);
            foreach ($items as $item) {
                $this->updateStock($item['product_id'], $item['quantity']);
            }

            // Update order status
            Order::update($orderId, [
                'status' => 'cancelled',
                'payment_status' => 'refunded',
            ]);

            return Order::find($orderId);
        });
    }

    // ========================================
    // Helper Methods (simple, focused)
    // ========================================

    private function getCustomer(int $userId): array
    {
        $customers = Customer::where('user_id', $userId);

        if (empty($customers)) {
            throw new \Exception('Customer not found');
        }

        return $customers[0];
    }

    private function prepareOrderItems(array $cartItems): array
    {
        $items = [];

        foreach ($cartItems as $cart) {
            $product = Product::find($cart['product_id']);

            if (!$product) {
                throw new \Exception("Product not found: {$cart['product_id']}");
            }

            // Check stock
            if ($product['stock_quantity'] < $cart['quantity']) {
                throw new \Exception("Insufficient stock: {$product['name']}");
            }

            $items[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'quantity' => $cart['quantity'],
                'unit_price' => $product['price'],
                'total_price' => $product['price'] * $cart['quantity'],
            ];
        }

        return $items;
    }

    private function calculateAmounts(array $items): array
    {
        $subtotal = array_sum(array_column($items, 'total_price'));

        return [
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ];
    }

    private function createOrderItem(int $orderId, array $item): void
    {
        OrderItem::create([
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_price' => $item['total_price'],
        ]);
    }

    private function updateStock(int $productId, int $quantity): void
    {
        $product = Product::find($productId);
        $newStock = $product['stock_quantity'] + $quantity;

        Product::update($productId, [
            'stock_quantity' => $newStock,
            'status' => $newStock > 0 ? 'active' : 'out_of_stock',
        ]);
    }

    private function clearCart(int $customerId): void
    {
        $items = CartItem::where('customer_id', $customerId);

        foreach ($items as $item) {
            CartItem::delete($item['id']);
        }
    }
}
