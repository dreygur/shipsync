<?php
/**
 * Courier Interface
 * Defines the contract that all courier integrations must implement
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

interface ShipSync_Courier_Interface {

    /**
     * Get unique courier identifier
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Get human-readable courier name
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Check if courier is enabled and configured
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Validate API credentials
     *
     * @return true|WP_Error True if valid, WP_Error on failure
     */
    public function validate_credentials();

    /**
     * Create a shipment/consignment
     *
     * @param WC_Order $order WooCommerce order object
     * @param array    $params Additional parameters
     * @return array Response array with 'success', 'message', and optional 'data' keys
     */
    public function create_order(WC_Order $order, array $params = []): array;

    /**
     * Create multiple shipments in bulk
     *
     * @param WC_Order[] $orders Array of WooCommerce order objects
     * @return array Response array with results for each order
     */
    public function create_bulk_orders(array $orders): array;

    /**
     * Get delivery status for a shipment
     *
     * @param string $identifier Order identifier (tracking code, invoice, etc.)
     * @param string $type Identifier type ('tracking_code', 'invoice', 'consignment_id')
     * @return array Response array with 'success', 'status', and optional 'data' keys
     */
    public function get_delivery_status(string $identifier, string $type = 'tracking_code'): array;

    /**
     * Get account balance
     *
     * @return array Response array with 'success', 'balance', and optional 'currency' keys
     */
    public function get_balance(): array;

    /**
     * Handle webhook callback from courier
     *
     * @param array $payload Webhook payload data
     * @return array Response array indicating webhook processing result
     */
    public function handle_webhook(array $payload): array;

    /**
     * Get tracking URL for customer
     *
     * @param string      $tracking_code Tracking code
     * @param string|null $consignment_id Optional consignment ID
     * @return string|null Tracking URL or null if not available
     */
    public function get_tracking_url(string $tracking_code, ?string $consignment_id = null): ?string;

    /**
     * Get admin settings fields configuration
     *
     * @return array Settings fields array
     */
    public function get_settings_fields(): array;
}
