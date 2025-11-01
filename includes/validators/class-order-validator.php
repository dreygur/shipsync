<?php
/**
 * Order Validator
 * Validates order data and business rules
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Order_Validator {

    /**
     * Validation errors
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Validate order for courier shipment
     *
     * @param WC_Order $order Order to validate
     * @return bool Validation result
     */
    public function validate_for_shipment(WC_Order $order): bool {
        $this->errors = [];

        // Check if order exists
        if (!$order || !$order->get_id()) {
            $this->add_error('invalid_order', __('Invalid order', 'shipsync'));
            return false;
        }

        // Check order status
        $valid_statuses = ['processing', 'on-hold', 'pending'];
        if (!in_array($order->get_status(), $valid_statuses, true)) {
            $this->add_error(
                'invalid_status',
                __('Order must be in pending, processing, or on-hold status', 'shipsync')
            );
        }

        // Check if order has items
        if (count($order->get_items()) === 0) {
            $this->add_error('no_items', __('Order has no items', 'shipsync'));
        }

        // Check billing information
        if (empty($order->get_billing_first_name()) && empty($order->get_billing_last_name())) {
            $this->add_error('no_customer_name', __('Customer name is required', 'shipsync'));
        }

        if (empty($order->get_billing_phone())) {
            $this->add_error('no_phone', __('Customer phone number is required', 'shipsync'));
        }

        if (empty($order->get_billing_address_1())) {
            $this->add_error('no_address', __('Customer address is required', 'shipsync'));
        }

        // Check order total
        if ($order->get_total() <= 0) {
            $this->add_error('invalid_total', __('Order total must be greater than zero', 'shipsync'));
        }

        // Allow extensions via filter
        $this->errors = apply_filters('shipsync_validate_order_for_shipment', $this->errors, $order);

        return empty($this->errors);
    }

    /**
     * Validate order status transition
     *
     * @param WC_Order $order Order
     * @param string   $new_status New status
     * @return bool Validation result
     */
    public function validate_status_transition(WC_Order $order, string $new_status): bool {
        $this->errors = [];

        $current_status = $order->get_status();

        // Check if status is valid
        if (!ShipSync_Order_Status::is_valid($new_status)) {
            $this->add_error('invalid_status', __('Invalid order status', 'shipsync'));
            return false;
        }

        // Validate specific transitions
        if ($new_status === ShipSync_Order_Status::COMPLETED && $current_status === ShipSync_Order_Status::CANCELLED) {
            $this->add_error(
                'invalid_transition',
                __('Cannot change cancelled order to completed', 'shipsync')
            );
        }

        // Allow extensions via filter
        $this->errors = apply_filters(
            'shipsync_validate_status_transition',
            $this->errors,
            $order,
            $current_status,
            $new_status
        );

        return empty($this->errors);
    }

    /**
     * Validate order for tracking
     *
     * @param WC_Order $order Order
     * @return bool Validation result
     */
    public function validate_for_tracking(WC_Order $order): bool {
        $this->errors = [];

        if (!$order || !$order->get_id()) {
            $this->add_error('invalid_order', __('Invalid order', 'shipsync'));
            return false;
        }

        // Check if order has tracking code
        $has_tracking = false;

        if ($order->get_meta(ShipSync_Meta_Keys::STEADFAST_TRACKING_CODE)) {
            $has_tracking = true;
        } elseif ($order->get_meta(ShipSync_Meta_Keys::PATHAO_TRACKING_ID)) {
            $has_tracking = true;
        } elseif ($order->get_meta(ShipSync_Meta_Keys::REDX_TRACKING_ID)) {
            $has_tracking = true;
        }

        if (!$has_tracking) {
            $this->add_error('no_tracking', __('Order has no tracking information', 'shipsync'));
        }

        return empty($this->errors);
    }

    /**
     * Add validation error
     *
     * @param string $code Error code
     * @param string $message Error message
     * @return void
     */
    private function add_error(string $code, string $message): void {
        $this->errors[$code] = $message;
    }

    /**
     * Get validation errors
     *
     * @return array Errors array
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Get first validation error
     *
     * @return string|null First error message or null
     */
    public function get_first_error(): ?string {
        if (empty($this->errors)) {
            return null;
        }

        return reset($this->errors);
    }

    /**
     * Check if has errors
     *
     * @return bool
     */
    public function has_errors(): bool {
        return !empty($this->errors);
    }

    /**
     * Clear errors
     *
     * @return void
     */
    public function clear_errors(): void {
        $this->errors = [];
    }
}
