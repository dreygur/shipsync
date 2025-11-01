<?php
/**
 * Order Repository
 * Handles all database operations for orders
 * Implements Repository pattern to separate data access from business logic
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Order_Repository {

    /**
     * Database instance
     *
     * @var ShipSync_Database
     */
    private ShipSync_Database $database;

    /**
     * Constructor
     *
     * @param ShipSync_Database $database Database instance
     */
    public function __construct(ShipSync_Database $database) {
        $this->database = $database;
    }

    /**
     * Find order by ID
     *
     * @param int $order_id Order ID
     * @return WC_Order|null Order object or null if not found
     */
    public function find(int $order_id): ?WC_Order {
        $order = wc_get_order($order_id);
        return $order ? $order : null;
    }

    /**
     * Find orders with filtering and pagination
     *
     * @param array $criteria Search criteria
     * @return array Array of WC_Order objects
     */
    public function find_by(array $criteria = []): array {
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ];

        $args = wp_parse_args($criteria, $defaults);

        return wc_get_orders($args);
    }

    /**
     * Count orders matching criteria
     *
     * @param array $criteria Search criteria
     * @return int Order count
     */
    public function count(array $criteria = []): int {
        $args = array_merge($criteria, [
            'limit' => -1,
            'return' => 'ids',
        ]);

        return count(wc_get_orders($args));
    }

    /**
     * Find orders by courier ID
     *
     * @param int   $courier_id Courier ID
     * @param array $criteria Additional criteria
     * @return array Array of WC_Order objects
     */
    public function find_by_courier(int $courier_id, array $criteria = []): array {
        $defaults = [
            'limit' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ];

        $args = wp_parse_args($criteria, $defaults);
        $args['limit'] = -1; // Get all first, then filter

        $all_orders = wc_get_orders($args);
        $filtered = [];

        foreach ($all_orders as $order) {
            $order_courier_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_ID);
            if ((int) $order_courier_id === $courier_id) {
                $filtered[] = $order;
            }
        }

        // Apply limit if specified in original criteria
        if (isset($criteria['limit']) && $criteria['limit'] > 0) {
            $filtered = array_slice($filtered, 0, $criteria['limit']);
        }

        return $filtered;
    }

    /**
     * Find orders with courier tracking
     *
     * @param array $criteria Search criteria
     * @return array Array of orders with tracking information
     */
    public function find_with_tracking(array $criteria = []): array {
        $defaults = [
            'limit' => 20,
            'offset' => 0,
            'status_filter' => 'all',
            'search' => '',
        ];

        $args = wp_parse_args($criteria, $defaults);

        $query_args = [
            'limit' => -1, // Get all first for filtering
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ];

        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        $all_orders = wc_get_orders($query_args);
        $filtered = [];

        foreach ($all_orders as $order) {
            // Check for any courier tracking code
            $has_tracking = false;

            if ($order->get_meta(ShipSync_Meta_Keys::STEADFAST_TRACKING_CODE)) {
                $has_tracking = true;
            } elseif ($order->get_meta(ShipSync_Meta_Keys::PATHAO_TRACKING_ID)) {
                $has_tracking = true;
            } elseif ($order->get_meta(ShipSync_Meta_Keys::REDX_TRACKING_ID)) {
                $has_tracking = true;
            }

            if ($has_tracking) {
                // Apply status filter
                if ($args['status_filter'] !== 'all') {
                    $status_data = $this->database->get_delivery_status_from_order($order);
                    $delivery_status = $status_data ? $status_data['status'] : null;

                    if (!$this->matches_status_filter($delivery_status, $args['status_filter'])) {
                        continue;
                    }
                }

                $filtered[] = $order;
            }
        }

        // Apply pagination
        $total = count($filtered);
        $offset = $args['offset'];
        $limit = $args['limit'];

        return array_slice($filtered, $offset, $limit);
    }

    /**
     * Check if status matches filter
     *
     * @param string|null $status Delivery status
     * @param string      $filter Status filter
     * @return bool
     */
    private function matches_status_filter(?string $status, string $filter): bool {
        switch ($filter) {
            case 'delivered':
                return in_array($status, ['delivered', 'delivered_approval_pending'], true);
            case 'cancelled':
                return in_array($status, ['cancelled', 'cancelled_approval_pending'], true);
            case 'pending':
                return $status === 'pending';
            case 'hold':
                return $status === 'hold';
            case 'in_review':
                return $status === 'in_review';
            default:
                return true;
        }
    }

    /**
     * Count orders with tracking
     *
     * @param array $criteria Search criteria
     * @return int Count
     */
    public function count_with_tracking(array $criteria = []): int {
        $criteria['limit'] = -1;
        $criteria['offset'] = 0;
        return count($this->find_with_tracking($criteria));
    }

    /**
     * Update order meta
     *
     * @param int    $order_id Order ID
     * @param string $key Meta key
     * @param mixed  $value Meta value
     * @return bool Success status
     */
    public function update_meta(int $order_id, string $key, $value): bool {
        $order = $this->find($order_id);

        if (!$order) {
            return false;
        }

        $order->update_meta_data($key, $value);
        $order->save();

        return true;
    }

    /**
     * Get order meta
     *
     * @param int    $order_id Order ID
     * @param string $key Meta key
     * @param mixed  $default Default value
     * @return mixed Meta value
     */
    public function get_meta(int $order_id, string $key, $default = '') {
        $order = $this->find($order_id);

        if (!$order) {
            return $default;
        }

        $value = $order->get_meta($key);
        return $value !== '' ? $value : $default;
    }

    /**
     * Delete order meta
     *
     * @param int    $order_id Order ID
     * @param string $key Meta key
     * @return bool Success status
     */
    public function delete_meta(int $order_id, string $key): bool {
        $order = $this->find($order_id);

        if (!$order) {
            return false;
        }

        $order->delete_meta_data($key);
        $order->save();

        return true;
    }

    /**
     * Update order status
     *
     * @param int    $order_id Order ID
     * @param string $status New status
     * @param string $note Optional note
     * @return bool Success status
     */
    public function update_status(int $order_id, string $status, string $note = ''): bool {
        $order = $this->find($order_id);

        if (!$order) {
            return false;
        }

        $order->update_status($status, $note);

        return true;
    }

    /**
     * Get tracking information for order
     *
     * @param int $order_id Order ID
     * @return array|null Tracking data or null if not found
     */
    public function get_tracking_data(int $order_id): ?array {
        $order = $this->find($order_id);

        if (!$order) {
            return null;
        }

        return $this->database->get_tracking_code_from_order($order);
    }

    /**
     * Get delivery status for order
     *
     * @param int $order_id Order ID
     * @return array|null Status data or null if not found
     */
    public function get_delivery_status(int $order_id): ?array {
        $order = $this->find($order_id);

        if (!$order) {
            return null;
        }

        return $this->database->get_delivery_status_from_order($order);
    }

    /**
     * Assign courier to order
     *
     * @param int $order_id Order ID
     * @param int $courier_id Courier ID
     * @return bool Success status
     */
    public function assign_courier(int $order_id, int $courier_id): bool {
        return $this->database->assign_courier($order_id, $courier_id);
    }
}
