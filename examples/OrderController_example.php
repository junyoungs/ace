<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Services\OrderService;

/**
 * OrderController Example - Custom endpoints
 *
 * Demonstrates custom endpoints beyond auto-generated CRUD.
 * Key principles:
 * - Simple validation
 * - Clear error handling
 * - Delegate complex logic to Service layer
 */
class OrderController extends \ACE\Http\Control
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * POST /api/order/checkout
     * Create order from cart
     */
    public function postCheckout(): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');
            $data = $this->request->getParsedBody();

            // Add authenticated user
            $data['user_id'] = $authUser['user_id'];

            // Simple validation
            $required = ['recipient_name', 'phone', 'address'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    return ['error' => "Field '{$field}' is required"];
                }
            }

            // Delegate to Service
            $order = $this->orderService->createFromCart($data);

            http_response_code(201);
            return $order;

        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * POST /api/order/cancel/{id}
     * Cancel order (restore stock)
     */
    public function postCancel(int $id): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');

            // Check permission
            $order = $this->orderService->findById($id);
            if (!$order) {
                http_response_code(404);
                return ['error' => 'Order not found'];
            }

            // Only owner or admin can cancel
            if (!$this->canAccessOrder($order, $authUser)) {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

            // Delegate to Service
            $result = $this->orderService->cancel($id);

            return [
                'message' => 'Order cancelled successfully',
                'order' => $result
            ];

        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * GET /api/order/my-orders
     * Get user's orders
     */
    public function getMyOrders(): array
    {
        $authUser = $this->request->getAttribute('auth_user');
        $queryParams = $this->request->getQueryParams();

        // Get customer
        $customers = \APP\Models\Customer::where('user_id', $authUser['user_id']);
        if (empty($customers)) {
            return ['orders' => [], 'total' => 0];
        }

        // Get orders
        $customer = $customers[0];
        $orders = \APP\Models\Order::where('customer_id', $customer['id']);

        // Filter by status
        if (!empty($queryParams['status'])) {
            $orders = array_filter($orders, function($order) use ($queryParams) {
                return $order['status'] === $queryParams['status'];
            });
        }

        // Sort by date (newest first)
        usort($orders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return [
            'orders' => array_values($orders),
            'total' => count($orders)
        ];
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function canAccessOrder(array $order, array $authUser): bool
    {
        // Check if user owns this order
        $customer = \APP\Models\Customer::find($order['customer_id']);

        if ($customer['user_id'] === $authUser['user_id']) {
            return true;
        }

        // Check if admin
        if ($authUser['user_type'] === 'admin') {
            return true;
        }

        return false;
    }
}
