<?php
/**
 * Database management class for ShipSync
 * Now works with WooCommerce orders instead of custom orders table
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Database {

    /**
     * @var wpdb WordPress database instance
     */
    private $wpdb;

    /**
     * @var ShipSync_Database Singleton instance
     */
    private static $instance = null;

    public function __construct($wpdb = null) {
        if ($wpdb === null) {
            global $wpdb;
            $this->wpdb = $wpdb;
        } else {
            $this->wpdb = $wpdb;
        }
        add_action('init', array($this, 'check_version'));
        self::$instance = $this;
    }

    /**
     * Get singleton instance for backward compatibility
     * @return ShipSync_Database
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        // Couriers table (only table we need now)
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';
        $couriers_sql = "CREATE TABLE $couriers_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            vehicle_type varchar(50) DEFAULT NULL,
            license_number varchar(50) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($couriers_sql);

        // Insert sample data
        $this->insert_sample_data();
    }

    private function insert_sample_data() {
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';

        // Check if data already exists (table names must use esc_sql, not prepare)
        $courier_count = $this->wpdb->get_var("SELECT COUNT(*) FROM " . esc_sql($couriers_table));

        if ($courier_count == 0) {
            // Insert sample couriers
            $sample_couriers = array(
                array(
                    'name' => 'John Smith',
                    'email' => 'john.smith@example.com',
                    'phone' => '+1234567890',
                    'vehicle_type' => 'Motorcycle',
                    'license_number' => 'MC123456',
                    'status' => 'active'
                ),
                array(
                    'name' => 'Sarah Johnson',
                    'email' => 'sarah.johnson@example.com',
                    'phone' => '+1234567891',
                    'vehicle_type' => 'Car',
                    'license_number' => 'CAR789012',
                    'status' => 'active'
                ),
                array(
                    'name' => 'Mike Wilson',
                    'email' => 'mike.wilson@example.com',
                    'phone' => '+1234567892',
                    'vehicle_type' => 'Bicycle',
                    'license_number' => 'BIKE345678',
                    'status' => 'active'
                )
            );

            foreach ($sample_couriers as $courier) {
                $this->wpdb->insert($couriers_table, $courier);
            }
        }
    }

    public function check_version() {
        $installed_version = get_option(ShipSync_Options::VERSION);

        if ($installed_version != SHIPSYNC_VERSION) {
            $this->create_tables();
            update_option(ShipSync_Options::VERSION, SHIPSYNC_VERSION);
        }
    }

    /**
     * Get WooCommerce orders with courier information
     * @param int $limit
     * @param int $offset
     * @param string $status
     * @return array
     */
    public function get_orders($limit = 20, $offset = 0, $status = '') {
        $args = array(
            'limit' => $limit,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        );

        if (!empty($status)) {
            $args['status'] = $status;
        }

        $orders = wc_get_orders($args);
        $results = array();

        foreach ($orders as $order) {
            $courier_id = $order->get_meta('_ocm_courier_id');
            $courier_name = '';

            if ($courier_id) {
                $courier = $this->get_courier_by_id($courier_id);
                if ($courier) {
                    $courier_name = $courier->name;
                }
            }

            $order_data = (object) array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'customer_address' => $order->get_formatted_billing_address(),
                'total_amount' => $order->get_total(),
                'order_status' => $order->get_status(),
                'courier_id' => $courier_id,
                'courier_name' => $courier_name,
                'created_at' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'order_items' => $this->get_order_items_formatted($order)
            );

            $results[] = $order_data;
        }

        return $results;
    }

    /**
     * Get formatted order items
     * @param WC_Order $order
     * @return string JSON encoded items
     */
    private function get_order_items_formatted($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'item' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $item->get_total()
            );
        }
        return json_encode($items);
    }

    /**
     * Get couriers by status
     * @param string $status
     * @return array
     */
    public function get_couriers($status = 'active') {
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';
        $cache_key = ShipSync_Transients::COURIERS_CACHE . $status;

        // Try to get from cache
        $couriers = get_transient($cache_key);
        if (false !== $couriers) {
            return $couriers;
        }

        $sql = "SELECT * FROM $couriers_table WHERE status = %s ORDER BY name ASC";
        $couriers = $this->wpdb->get_results($this->wpdb->prepare($sql, $status));

        // Cache for 1 hour
        set_transient($cache_key, $couriers, ShipSync_Defaults::CACHE_EXPIRATION);

        return $couriers;
    }

    /**
     * Get courier by ID
     * @param int $courier_id
     * @return object|null
     */
    public function get_courier_by_id($courier_id) {
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';
        $sql = "SELECT * FROM $couriers_table WHERE id = %d";
        return $this->wpdb->get_row($this->wpdb->prepare($sql, $courier_id));
    }

    /**
     * Get order by ID
     * @param int $order_id
     * @return object|null
     */
    public function get_order_by_id($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        $courier_id = $order->get_meta('_ocm_courier_id');
        $courier_name = '';

        if ($courier_id) {
            $courier = $this->get_courier_by_id($courier_id);
            if ($courier) {
                $courier_name = $courier->name;
            }
        }

        return (object) array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_address' => $order->get_formatted_billing_address(),
            'total_amount' => $order->get_total(),
            'order_status' => $order->get_status(),
            'courier_id' => $courier_id,
            'courier_name' => $courier_name,
            'created_at' => $order->get_date_created()->date('Y-m-d H:i:s'),
            'order_items' => $this->get_order_items_formatted($order)
        );
    }

    /**
     * Update order status
     * @param int $order_id
     * @param string $status
     * @param string $notes
     * @return bool
     */
    public function update_order_status($order_id, $status, $notes = '') {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        $old_status = $order->get_status();

        // Update order status
        $order->update_status($status, $notes);

        // Fire action hook for status update
        do_action('shipsync_order_status_updated', $order_id, $status, $old_status, $notes);
        // Backward compatibility
        do_action('ocm_order_status_updated', $order_id, $status, $old_status, $notes);

        return true;
    }

    /**
     * Assign courier to order
     * @param int $order_id
     * @param int $courier_id
     * @return bool
     */
    public function assign_courier($order_id, $courier_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }

        // Store courier ID in order meta
        $order->update_meta_data('_ocm_courier_id', $courier_id);
        $order->save();

        // Add order note
        $courier = $this->get_courier_by_id($courier_id);
        if ($courier) {
            $order->add_order_note(
                sprintf(__('Courier assigned: %s (%s)', 'shipsync'),
                $courier->name,
                $courier->vehicle_type)
            );
        }

        // Fire action hook for courier assignment
        do_action('shipsync_courier_assigned', $order_id, $courier_id);
        // Backward compatibility
        do_action('ocm_courier_assigned', $order_id, $courier_id);

        return true;
    }

    /**
     * Add new courier
     * @param array $data
     * @return int|false Courier ID or false on failure
     */
    public function add_courier($data) {
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';

        // Clear cache
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'active');
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'inactive');

        $result = $this->wpdb->insert($couriers_table, $data);

        if ($result) {
            $courier_id = $this->wpdb->insert_id;
            do_action('shipsync_courier_created', $courier_id, $data);
            // Backward compatibility
            do_action('ocm_courier_created', $courier_id, $data);
            return $courier_id;
        }

        return false;
    }

    /**
     * Update courier
     * @param int $courier_id
     * @param array $data
     * @return int|false Number of rows updated or false on failure
     */
    public function update_courier($courier_id, $data) {
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';

        // Clear cache
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'active');
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'inactive');

        return $this->wpdb->update(
            $couriers_table,
            $data,
            array('id' => $courier_id),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
    }

    /**
     * Delete courier
     * @param int $courier_id
     * @return int|false Number of rows deleted or false on failure
     */
    public function delete_courier($courier_id) {
        $couriers_table = $this->wpdb->prefix . 'ocm_couriers';

        // Check if courier has assigned orders (HPOS compatible)
        $args = array(
            'return' => 'objects',
            'limit' => 100 // Check first 100 orders
        );

        $all_orders = wc_get_orders($args);

        // Check if any order has this courier assigned
        foreach ($all_orders as $order) {
            $order_courier_id = $order->get_meta('_ocm_courier_id');
            if ($order_courier_id == $courier_id) {
                return false; // Cannot delete courier with assigned orders
            }
        }

        // Clear cache
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'active');
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'inactive');

        return $this->wpdb->delete($couriers_table, array('id' => $courier_id), array('%d'));
    }

    /**
     * Get order count by courier
     * @param int $courier_id
     * @param array $statuses Optional status filter
     * @return int
     */
    public function get_orders_count_by_courier($courier_id, $statuses = array()) {
        // Get all orders (HPOS compatible)
        $args = array(
            'return' => 'objects',
            'limit' => -1
        );

        if (!empty($statuses)) {
            $args['status'] = $statuses;
        }

        $all_orders = wc_get_orders($args);

        // Filter by courier ID
        $count = 0;
        foreach ($all_orders as $order) {
            $order_courier_id = $order->get_meta('_ocm_courier_id');
            if ($order_courier_id == $courier_id) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get orders by courier
     * @param int $courier_id
     * @param int $limit
     * @param array $statuses Optional status filter
     * @return array
     */
    public function get_orders_by_courier($courier_id, $limit = 20, $statuses = array()) {
        // Get all orders (HPOS compatible)
        $args = array(
            'return' => 'objects',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        if (!empty($statuses)) {
            $args['status'] = $statuses;
        }

        $all_orders = wc_get_orders($args);

        // Filter by courier ID and apply limit
        $filtered_orders = array();
        foreach ($all_orders as $order) {
            $order_courier_id = $order->get_meta('_ocm_courier_id');
            if ($order_courier_id == $courier_id) {
                $filtered_orders[] = $order;
                if (count($filtered_orders) >= $limit) {
                    break;
                }
            }
        }

        $results = array();

        foreach ($filtered_orders as $order) {
            $tracking_data = $this->get_tracking_code_from_order($order);
            $status_data = $this->get_delivery_status_from_order($order);

            $order_data = (object) array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email' => $order->get_billing_email(),
                'customer_phone' => $order->get_billing_phone(),
                'customer_address' => $order->get_formatted_billing_address(),
                'total_amount' => $order->get_total(),
                'order_status' => $order->get_status(),
                'courier_id' => $courier_id,
                'created_at' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'tracking_code' => $tracking_data ? $tracking_data['tracking_code'] : null,
                'delivery_status' => $status_data ? $status_data['status'] : null,
                'courier_service' => $tracking_data ? $tracking_data['courier_service'] : null
            );

            $results[] = $order_data;
        }

        return $results;
    }

    /**
     * Get tracking code from order (from any courier integration)
     * @param WC_Order $order
     * @return array|null Array with 'tracking_code', 'courier_service', and 'consignment_id' keys, or null
     */
    public function get_tracking_code_from_order($order) {
        // Check Steadfast
        $tracking = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_TRACKING_CODE);
        if ($tracking) {
            return array(
                'tracking_code' => $tracking,
                'courier_service' => 'steadfast',
                'consignment_id' => $order->get_meta(ShipSync_Meta_Keys::STEADFAST_CONSIGNMENT_ID)
            );
        }

        // Check Pathao
        $tracking = $order->get_meta(ShipSync_Meta_Keys::PATHAO_TRACKING_ID);
        if ($tracking) {
            return array(
                'tracking_code' => $tracking,
                'courier_service' => 'pathao',
                'consignment_id' => $order->get_meta(ShipSync_Meta_Keys::PATHAO_CONSIGNMENT_ID)
            );
        }

        // Check RedX
        $tracking = $order->get_meta(ShipSync_Meta_Keys::REDX_TRACKING_ID);
        if ($tracking) {
            return array(
                'tracking_code' => $tracking,
                'courier_service' => 'redx',
                'consignment_id' => null
            );
        }

        return null;
    }

    /**
     * Get delivery status from order (from any courier integration)
     * @param WC_Order $order
     * @return array|null Array with 'status' and 'courier_service' keys, or null
     */
    public function get_delivery_status_from_order($order) {
        // Check Steadfast
        $status = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_STATUS);
        if ($status) {
            return array(
                'status' => $status,
                'courier_service' => 'steadfast'
            );
        }

        // Check Pathao
        $status = $order->get_meta(ShipSync_Meta_Keys::PATHAO_STATUS);
        if ($status) {
            return array(
                'status' => $status,
                'courier_service' => 'pathao'
            );
        }

        // Check RedX
        $status = $order->get_meta(ShipSync_Meta_Keys::REDX_STATUS);
        if ($status) {
            return array(
                'status' => $status,
                'courier_service' => 'redx'
            );
        }

        return null;
    }

    /**
     * Get courier orders/consignments (orders sent to courier services)
     * @param string $status_filter
     * @param string $search
     * @param int $paged
     * @param int $per_page
     * @return array
     */
    public function get_courier_orders($status_filter = 'all', $search = '', $paged = 1, $per_page = 10) {
        // Get all orders first (HPOS compatible)
        $args = array(
            'limit' => -1, // Get all first, then filter
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        );

        // Add search
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $all_orders = wc_get_orders($args);

        // Filter orders that have tracking codes from any courier service
        $filtered_orders = array();
        foreach ($all_orders as $order) {
            // Check all courier services
            $tracking_code = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_TRACKING_CODE);
            if (!$tracking_code) {
                $tracking_code = $order->get_meta(ShipSync_Meta_Keys::PATHAO_TRACKING_ID);
            }
            if (!$tracking_code) {
                $tracking_code = $order->get_meta(ShipSync_Meta_Keys::REDX_TRACKING_ID);
            }

            if ($tracking_code) {
                $filtered_orders[] = $order;
            }
        }

        // Apply pagination
        $total_filtered = count($filtered_orders);
        $offset = ($paged - 1) * $per_page;
        $orders = array_slice($filtered_orders, $offset, $per_page);

        $results = array();

        foreach ($orders as $order) {
            $tracking_data = $this->get_tracking_code_from_order($order);
            $status_data = $this->get_delivery_status_from_order($order);

            if (!$tracking_data) {
                continue;
            }

            $tracking_code = $tracking_data['tracking_code'];
            $consignment_id = $tracking_data['consignment_id'];
            $courier_service = $tracking_data['courier_service'];
            $delivery_status = $status_data ? $status_data['status'] : null;

            // Get delivery charge based on courier service
            $delivery_charge = null;
            if ($courier_service === 'steadfast') {
                $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_DELIVERY_CHARGE);
            } elseif ($courier_service === 'pathao') {
                $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::PATHAO_DELIVERY_CHARGE);
            } elseif ($courier_service === 'redx') {
                $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::REDX_DELIVERY_CHARGE);
            }

            // Filter by status
            if ($status_filter !== 'all') {
                if ($status_filter === 'delivered' && !in_array($delivery_status, array('delivered', 'delivered_approval_pending'))) {
                    continue;
                }
                if ($status_filter === 'cancelled' && !in_array($delivery_status, array('cancelled', 'cancelled_approval_pending'))) {
                    continue;
                }
                if ($status_filter === 'pending' && $delivery_status !== 'pending') {
                    continue;
                }
                if ($status_filter === 'hold' && $delivery_status !== 'hold') {
                    continue;
                }
                if ($status_filter === 'in_review' && $delivery_status !== 'in_review') {
                    continue;
                }
            }

            $order_data = (object) array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'consignment_id' => $consignment_id,
                'tracking_code' => $tracking_code,
                'courier_service' => $courier_service,
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_phone' => $order->get_billing_phone(),
                'cod_amount' => $order->get_total(),
                'delivery_charge' => $delivery_charge,
                'default_delivery_charge' => ShipSync_Defaults::DEFAULT_DELIVERY_CHARGE,
                'delivery_status' => $delivery_status ? $delivery_status : ShipSync_Courier_Status::IN_REVIEW,
                'status_message' => '',
                'created_at' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'updated_at' => $order->get_date_modified()->date('Y-m-d H:i:s')
            );

            $results[] = $order_data;
        }

        return $results;
    }

    /**
     * Count courier orders
     * @param string $status_filter
     * @param string $search
     * @return int
     */
    public function count_courier_orders($status_filter = 'all', $search = '') {
        // Get all orders (HPOS compatible)
        $args = array(
            'limit' => -1,
            'return' => 'objects'
        );

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $all_orders = wc_get_orders($args);

        // Filter orders that have tracking codes from any courier service
        $orders = array();
        foreach ($all_orders as $order) {
            // Check all courier services
            $tracking_code = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_TRACKING_CODE);
            if (!$tracking_code) {
                $tracking_code = $order->get_meta(ShipSync_Meta_Keys::PATHAO_TRACKING_ID);
            }
            if (!$tracking_code) {
                $tracking_code = $order->get_meta(ShipSync_Meta_Keys::REDX_TRACKING_ID);
            }

            if ($tracking_code) {
                $orders[] = $order;
            }
        }

        if ($status_filter === 'all') {
            return count($orders);
        }

        // Count by status
        $count = 0;
        foreach ($orders as $order) {
            $status_data = $this->get_delivery_status_from_order($order);
            $delivery_status = $status_data ? $status_data['status'] : null;

            if ($status_filter === 'delivered' && in_array($delivery_status, array('delivered', 'delivered_approval_pending'))) {
                $count++;
            } elseif ($status_filter === 'cancelled' && in_array($delivery_status, array('cancelled', 'cancelled_approval_pending'))) {
                $count++;
            } elseif ($status_filter === 'pending' && $delivery_status === 'pending') {
                $count++;
            } elseif ($status_filter === 'hold' && $delivery_status === 'hold') {
                $count++;
            } elseif ($status_filter === 'in_review' && $delivery_status === 'in_review') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get courier orders statistics
     * @return array
     */
    public function get_courier_orders_stats() {
        // Get all orders (HPOS compatible)
        $args = array(
            'limit' => -1,
            'return' => 'objects'
        );

        $all_orders = wc_get_orders($args);

        // Filter orders that have tracking codes from any courier service
        $orders = array();
        foreach ($all_orders as $order) {
            // Check all courier services
            $tracking_code = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_TRACKING_CODE);
            if (!$tracking_code) {
                $tracking_code = $order->get_meta(ShipSync_Meta_Keys::PATHAO_TRACKING_ID);
            }
            if (!$tracking_code) {
                $tracking_code = $order->get_meta(ShipSync_Meta_Keys::REDX_TRACKING_ID);
            }

            if ($tracking_code) {
                $orders[] = $order;
            }
        }

        $stats = array(
            'total' => count($orders),
            'in_review' => 0,
            'pending' => 0,
            'hold' => 0,
            'delivered' => 0,
            'cancelled' => 0
        );

        foreach ($orders as $order) {
            $status_data = $this->get_delivery_status_from_order($order);
            $status = $status_data ? $status_data['status'] : null;

            switch ($status) {
                case ShipSync_Courier_Status::IN_REVIEW:
                    $stats['in_review']++;
                    break;
                case ShipSync_Courier_Status::PENDING:
                    $stats['pending']++;
                    break;
                case ShipSync_Courier_Status::HOLD:
                    $stats['hold']++;
                    break;
                case ShipSync_Courier_Status::DELIVERED:
                case ShipSync_Courier_Status::DELIVERED_APPROVAL_PENDING:
                case ShipSync_Courier_Status::PARTIAL_DELIVERED:
                case ShipSync_Courier_Status::PARTIAL_DELIVERED_APPROVAL_PENDING:
                    $stats['delivered']++;
                    break;
                case ShipSync_Courier_Status::CANCELLED:
                case ShipSync_Courier_Status::CANCELLED_APPROVAL_PENDING:
                    $stats['cancelled']++;
                    break;
            }
        }

        return $stats;
    }
}
