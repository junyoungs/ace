<?php declare(strict_types=1);

namespace APP\Http\Controllers;

use APP\Services\OrderService;

/**
 * 전자상거래 주문 컨트롤러 - 커스텀 엔드포인트 예제
 *
 * 자동 생성된 CRUD 엔드포인트 외에 복잡한 비즈니스 로직을 처리하는
 * 커스텀 엔드포인트 예제입니다.
 */
class OrderController extends \ACE\Http\Control
{
    public function __construct(
        private OrderService $orderService
    ) {}

    // ========================================
    // Auto-generated CRUD Endpoints
    // ========================================

    /**
     * GET /api/order
     * List all orders (관리자 전용)
     */
    public function getIndex(): array
    {
        // TODO: 관리자 권한 확인
        return $this->orderService->getAll();
    }

    /**
     * GET /api/order/show/{id}
     * Get order details (5개 이상 테이블 조인)
     */
    public function getShow(int $id): ?array
    {
        $authUser = $this->request->getAttribute('auth_user');

        $order = $this->orderService->getOrderWithDetails($id);

        if (!$order) {
            http_response_code(404);
            return ['error' => 'Order not found'];
        }

        // 권한 확인: 본인 주문이거나 관리자만 조회 가능
        if ($order['customer']['user_id'] !== $authUser['user_id'] &&
            $authUser['user_type'] !== 'admin') {
            http_response_code(403);
            return ['error' => 'Access denied'];
        }

        return $order;
    }

    // ========================================
    // Custom Endpoints (복잡한 비즈니스 로직)
    // ========================================

    /**
     * POST /api/order/checkout
     * 장바구니에서 주문 생성 (복잡한 조건부 로직)
     *
     * Request Body:
     * {
     *   "recipient_name": "홍길동",
     *   "phone": "010-1234-5678",
     *   "postal_code": "12345",
     *   "address": "서울시 강남구 테헤란로 123",
     *   "address_detail": "401호",
     *   "city": "서울",
     *   "state": null,
     *   "country": "South Korea",
     *   "delivery_memo": "문 앞에 놔주세요",
     *   "coupon_code": "WELCOME10",
     *   "customer_notes": "빠른 배송 부탁드립니다"
     * }
     *
     * Response:
     * {
     *   "id": 1,
     *   "order_number": "ORD-64F8B2A3C1E5D",
     *   "subtotal_amount": 100000,
     *   "discount_amount": 10000,
     *   "shipping_fee": 0,
     *   "tax_amount": 9000,
     *   "total_amount": 99000,
     *   "status": "pending",
     *   "customer": { ... },
     *   "items": [ ... ],
     *   "shipping_address": { ... }
     * }
     */
    public function postCheckout(): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');
            $data = $this->request->getParsedBody();

            // 인증된 사용자 정보 추가
            $data['user_id'] = $authUser['user_id'];

            // 필수 필드 검증
            $required = ['recipient_name', 'phone', 'postal_code', 'address', 'city'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    return ['error' => "Field '{$field}' is required"];
                }
            }

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
     * 주문 취소 (재고 복원 포함)
     *
     * Response:
     * {
     *   "id": 1,
     *   "status": "cancelled",
     *   "payment_status": "refunded",
     *   ...
     * }
     */
    public function postCancel(int $id): array
    {
        try {
            $authUser = $this->request->getAttribute('auth_user');

            // 권한 확인
            $order = $this->orderService->findById($id);
            if (!$order) {
                http_response_code(404);
                return ['error' => 'Order not found'];
            }

            // 본인 주문이거나 관리자만 취소 가능
            $customer = \APP\Models\Customer::find($order['customer_id']);
            if ($customer['user_id'] !== $authUser['user_id'] &&
                $authUser['user_type'] !== 'admin') {
                http_response_code(403);
                return ['error' => 'Access denied'];
            }

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
     * 내 주문 목록 조회
     *
     * Query Parameters:
     * - status: 주문 상태 필터 (optional)
     * - limit: 결과 개수 (default: 20)
     * - offset: 페이징 오프셋 (default: 0)
     *
     * Response:
     * {
     *   "orders": [ ... ],
     *   "total": 50,
     *   "limit": 20,
     *   "offset": 0
     * }
     */
    public function getMyOrders(): array
    {
        $authUser = $this->request->getAttribute('auth_user');
        $queryParams = $this->request->getQueryParams();

        // 고객 정보 가져오기
        $customers = \APP\Models\Customer::where('user_id', $authUser['user_id']);
        if (empty($customers)) {
            return ['orders' => [], 'total' => 0];
        }

        $customer = $customers[0];
        $orders = \APP\Models\Order::where('customer_id', $customer['id']);

        // 상태 필터
        if (!empty($queryParams['status'])) {
            $orders = array_filter($orders, function($order) use ($queryParams) {
                return $order['status'] === $queryParams['status'];
            });
        }

        // 정렬 (최신순)
        usort($orders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $total = count($orders);

        // 페이징
        $limit = (int)($queryParams['limit'] ?? 20);
        $offset = (int)($queryParams['offset'] ?? 0);
        $orders = array_slice($orders, $offset, $limit);

        // 각 주문에 상세 정보 추가
        foreach ($orders as &$order) {
            $order = $this->orderService->getOrderWithDetails($order['id']);
        }

        return [
            'orders' => array_values($orders),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * POST /api/order/complete-payment/{id}
     * 결제 완료 처리 (PG사 콜백)
     *
     * Request Body:
     * {
     *   "transaction_id": "TXN-123456789",
     *   "pg_provider": "kakaopay",
     *   "payment_method": "card",
     *   "card_number_masked": "1234-****-****-5678"
     * }
     *
     * Response:
     * {
     *   "message": "Payment completed successfully",
     *   "order": { ... },
     *   "points_earned": 1000
     * }
     */
    public function postCompletePayment(int $id): array
    {
        try {
            $data = $this->request->getParsedBody();

            if (empty($data['transaction_id'])) {
                http_response_code(400);
                return ['error' => 'Transaction ID is required'];
            }

            $order = $this->orderService->completePayment($id, $data['transaction_id']);

            // 포인트 계산
            $pointsEarned = (int)($order['total_amount'] * 0.01);

            // 첫 구매 보너스 확인
            $customerOrders = \APP\Models\Order::where('customer_id', $order['customer_id']);
            $paidOrders = array_filter($customerOrders, function($o) {
                return $o['payment_status'] === 'paid';
            });

            if (count($paidOrders) === 1) {
                $pointsEarned += 5000;
            }

            return [
                'message' => 'Payment completed successfully',
                'order' => $order,
                'points_earned' => $pointsEarned
            ];

        } catch (\Exception $e) {
            http_response_code(400);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * GET /api/order/sales-report
     * 매출 리포트 (관리자 전용, 복잡한 집계)
     *
     * Query Parameters:
     * - start_date: 시작일 (YYYY-MM-DD)
     * - end_date: 종료일 (YYYY-MM-DD)
     * - group_by: day|week|month (default: day)
     *
     * Response:
     * {
     *   "period": {
     *     "start": "2024-01-01",
     *     "end": "2024-01-31"
     *   },
     *   "summary": {
     *     "total_orders": 150,
     *     "total_sales": 15000000,
     *     "avg_order_value": 100000,
     *     "total_items_sold": 300
     *   },
     *   "data": [
     *     {
     *       "date": "2024-01-01",
     *       "order_count": 10,
     *       "total_sales": 1000000,
     *       "avg_order_value": 100000
     *     },
     *     ...
     *   ]
     * }
     */
    public function getSalesReport(): array
    {
        $authUser = $this->request->getAttribute('auth_user');

        // 관리자 권한 확인
        if ($authUser['user_type'] !== 'admin') {
            http_response_code(403);
            return ['error' => 'Admin access required'];
        }

        $queryParams = $this->request->getQueryParams();

        $startDate = $queryParams['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $queryParams['end_date'] ?? date('Y-m-d');
        $groupBy = $queryParams['group_by'] ?? 'day';

        // 날짜 형식 검증
        if (!strtotime($startDate) || !strtotime($endDate)) {
            http_response_code(400);
            return ['error' => 'Invalid date format. Use YYYY-MM-DD'];
        }

        // 날짜 그룹화 형식 결정
        $dateFormat = match($groupBy) {
            'week' => '%Y-W%U',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        // 복잡한 집계 쿼리
        $sql = "
            SELECT
                DATE_FORMAT(o.created_at, ?) as period,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_sales,
                AVG(o.total_amount) as avg_order_value,
                SUM(oi.quantity) as total_items_sold
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            WHERE o.status IN ('confirmed', 'processing', 'shipped', 'delivered')
            AND o.payment_status = 'paid'
            AND o.created_at >= ?
            AND o.created_at <= ?
            GROUP BY period
            ORDER BY period DESC
        ";

        $data = \APP\Models\Order::query($sql, [
            $dateFormat,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        ]);

        // 전체 요약 계산
        $summary = [
            'total_orders' => array_sum(array_column($data, 'order_count')),
            'total_sales' => array_sum(array_column($data, 'total_sales')),
            'avg_order_value' => 0,
            'total_items_sold' => array_sum(array_column($data, 'total_items_sold')),
        ];

        if ($summary['total_orders'] > 0) {
            $summary['avg_order_value'] = $summary['total_sales'] / $summary['total_orders'];
        }

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'group_by' => $groupBy
            ],
            'summary' => $summary,
            'data' => $data
        ];
    }

    /**
     * GET /api/order/status-summary
     * 주문 상태별 요약 (관리자 전용)
     *
     * Response:
     * {
     *   "pending": 10,
     *   "confirmed": 25,
     *   "processing": 15,
     *   "shipped": 30,
     *   "delivered": 100,
     *   "cancelled": 5,
     *   "refunded": 2
     * }
     */
    public function getStatusSummary(): array
    {
        $authUser = $this->request->getAttribute('auth_user');

        // 관리자 권한 확인
        if ($authUser['user_type'] !== 'admin') {
            http_response_code(403);
            return ['error' => 'Admin access required'];
        }

        $sql = "
            SELECT
                status,
                COUNT(*) as count
            FROM orders
            GROUP BY status
        ";

        $results = \APP\Models\Order::query($sql);

        // 결과를 키-값 형태로 변환
        $summary = [];
        foreach ($results as $row) {
            $summary[$row['status']] = (int)$row['count'];
        }

        return $summary;
    }

    /**
     * GET /api/order/track/{orderNumber}
     * 주문 추적 (주문번호로 조회, 인증 불필요)
     *
     * Query Parameters:
     * - email: 주문 시 사용한 이메일 (선택)
     *
     * Response:
     * {
     *   "order": { ... },
     *   "tracking": {
     *     "status": "shipped",
     *     "carrier": "CJ대한통운",
     *     "tracking_number": "123456789",
     *     "estimated_delivery": "2024-01-15 18:00:00",
     *     "history": [ ... ]
     *   }
     * }
     */
    public function getTrack(string $orderNumber): array
    {
        $orders = \APP\Models\Order::where('order_number', $orderNumber);

        if (empty($orders)) {
            http_response_code(404);
            return ['error' => 'Order not found'];
        }

        $order = $orders[0];

        // 이메일 검증 (선택사항)
        $queryParams = $this->request->getQueryParams();
        if (!empty($queryParams['email'])) {
            $customer = \APP\Models\Customer::find($order['customer_id']);
            $user = \APP\Models\User::find($customer['user_id']);

            if ($user['email'] !== $queryParams['email']) {
                http_response_code(403);
                return ['error' => 'Email does not match'];
            }
        }

        // 주문 상세 정보
        $orderDetails = $this->orderService->getOrderWithDetails($order['id']);

        // 배송 정보
        $tracking = null;
        if (!empty($orderDetails['shipment'])) {
            $tracking = [
                'status' => $orderDetails['shipment']['status'],
                'carrier' => $orderDetails['shipment']['carrier'],
                'tracking_number' => $orderDetails['shipment']['tracking_number'],
                'shipped_at' => $orderDetails['shipment']['shipped_at'],
                'estimated_delivery' => $orderDetails['shipment']['estimated_delivery'],
                'delivered_at' => $orderDetails['shipment']['delivered_at'],
            ];
        }

        return [
            'order' => $orderDetails,
            'tracking' => $tracking
        ];
    }

    // ========================================
    // Custom Endpoints (add below)
    // ========================================
}
