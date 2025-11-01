<?php
/**
 * Courier Repository
 * Handles all database operations for courier personnel
 * Implements Repository pattern
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Courier_Repository {

    /**
     * Database instance
     *
     * @var ShipSync_Database
     */
    private ShipSync_Database $database;

    /**
     * Table name
     *
     * @var string
     */
    private string $table;

    /**
     * Constructor
     *
     * @param ShipSync_Database $database Database instance
     */
    public function __construct(ShipSync_Database $database) {
        global $wpdb;
        $this->database = $database;
        $this->table = $wpdb->prefix . 'ocm_couriers';
    }

    /**
     * Find courier by ID
     *
     * @param int $courier_id Courier ID
     * @return object|null Courier object or null if not found
     */
    public function find(int $courier_id): ?object {
        return $this->database->get_courier_by_id($courier_id);
    }

    /**
     * Find couriers by status
     *
     * @param string $status Status ('active' or 'inactive')
     * @return array Array of courier objects
     */
    public function find_by_status(string $status = 'active'): array {
        return $this->database->get_couriers($status);
    }

    /**
     * Find all couriers
     *
     * @return array Array of courier objects
     */
    public function find_all(): array {
        global $wpdb;

        $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
        $couriers = $wpdb->get_results($sql);

        return $couriers ?: [];
    }

    /**
     * Create new courier
     *
     * @param array $data Courier data
     * @return int|false Courier ID on success, false on failure
     */
    public function create(array $data) {
        return $this->database->add_courier($data);
    }

    /**
     * Update courier
     *
     * @param int   $courier_id Courier ID
     * @param array $data Courier data
     * @return int|false Number of rows updated or false on failure
     */
    public function update(int $courier_id, array $data) {
        return $this->database->update_courier($courier_id, $data);
    }

    /**
     * Delete courier
     *
     * @param int $courier_id Courier ID
     * @return bool Success status
     */
    public function delete(int $courier_id): bool {
        $result = $this->database->delete_courier($courier_id);
        return $result !== false;
    }

    /**
     * Check if courier exists
     *
     * @param int $courier_id Courier ID
     * @return bool
     */
    public function exists(int $courier_id): bool {
        return $this->find($courier_id) !== null;
    }

    /**
     * Count couriers by status
     *
     * @param string $status Status ('active' or 'inactive')
     * @return int Count
     */
    public function count_by_status(string $status = 'active'): int {
        return count($this->find_by_status($status));
    }

    /**
     * Get orders count for courier
     *
     * @param int   $courier_id Courier ID
     * @param array $statuses Optional status filter
     * @return int Order count
     */
    public function get_orders_count(int $courier_id, array $statuses = []): int {
        return $this->database->get_orders_count_by_courier($courier_id, $statuses);
    }

    /**
     * Search couriers by name or email
     *
     * @param string $search Search term
     * @return array Array of courier objects
     */
    public function search(string $search): array {
        global $wpdb;

        $search = '%' . $wpdb->esc_like($search) . '%';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE name LIKE %s OR email LIKE %s ORDER BY name ASC",
            $search,
            $search
        );

        $couriers = $wpdb->get_results($sql);

        return $couriers ?: [];
    }

    /**
     * Find couriers with orders
     *
     * @param array $statuses Optional order status filter
     * @return array Array of courier objects with order counts
     */
    public function find_with_orders(array $statuses = []): array {
        $couriers = $this->find_by_status('active');

        foreach ($couriers as $courier) {
            $courier->orders_count = $this->get_orders_count($courier->id, $statuses);
        }

        return $couriers;
    }

    /**
     * Update courier status
     *
     * @param int    $courier_id Courier ID
     * @param string $status New status
     * @return bool Success status
     */
    public function update_status(int $courier_id, string $status): bool {
        $result = $this->update($courier_id, ['status' => $status]);
        return $result !== false;
    }

    /**
     * Bulk update courier status
     *
     * @param array  $courier_ids Array of courier IDs
     * @param string $status New status
     * @return int Number of couriers updated
     */
    public function bulk_update_status(array $courier_ids, string $status): int {
        global $wpdb;

        $ids = implode(',', array_map('intval', $courier_ids));

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$this->table} SET status = %s WHERE id IN ({$ids})",
                $status
            )
        );

        // Clear cache
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'active');
        delete_transient(ShipSync_Transients::COURIERS_CACHE . 'inactive');

        return $result ?: 0;
    }
}
