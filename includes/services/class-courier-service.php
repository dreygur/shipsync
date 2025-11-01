<?php
/**
 * Courier Service
 * Handles courier-related business logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Courier_Service {

    /**
     * @var ShipSync_Courier_Manager
     */
    private $courier_manager;

    /**
     * @var ShipSync_Database
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->courier_manager = ShipSync_Courier_Manager::instance();
        $this->database = ShipSync_Database::instance();
    }

    /**
     * Get enabled couriers
     */
    public function get_enabled_couriers() {
        return $this->courier_manager->get_enabled_couriers();
    }

    /**
     * Get all couriers (enabled and disabled)
     */
    public function get_all_couriers() {
        return $this->courier_manager->get_couriers();
    }

    /**
     * Get courier by ID
     */
    public function get_courier($courier_id) {
        return $this->courier_manager->get_courier($courier_id);
    }

    /**
     * Get courier orders statistics
     */
    public function get_courier_orders_stats() {
        return $this->database->get_courier_orders_stats();
    }

    /**
     * Get filtered courier orders
     */
    public function get_filtered_courier_orders($args = array()) {
        $defaults = array(
            'status_filter' => 'all',
            'search' => '',
            'paged' => 1,
            'per_page' => 10
        );

        $args = wp_parse_args($args, $defaults);

        return $this->database->get_courier_orders(
            $args['status_filter'],
            $args['search'],
            $args['paged'],
            $args['per_page']
        );
    }

    /**
     * Get total filtered courier orders count
     */
    public function get_filtered_courier_orders_count($args = array()) {
        $defaults = array(
            'status_filter' => 'all',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        return $this->database->count_courier_orders(
            $args['status_filter'],
            $args['search']
        );
    }

    /**
     * Check if any couriers are enabled
     */
    public function has_enabled_couriers() {
        $enabled = $this->get_enabled_couriers();
        return !empty($enabled);
    }
}

