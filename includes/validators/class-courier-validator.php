<?php
/**
 * Courier Validator
 * Validates courier data and business rules
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Courier_Validator {

    /**
     * Validation errors
     *
     * @var array
     */
    private array $errors = [];

    /**
     * Validate courier data
     *
     * @param array $data Courier data
     * @return bool Validation result
     */
    public function validate(array $data): bool {
        $this->errors = [];

        // Required fields
        if (empty($data['name'])) {
            $this->add_error('name', __('Courier name is required', 'shipsync'));
        }

        if (empty($data['email'])) {
            $this->add_error('email', __('Email is required', 'shipsync'));
        } elseif (!is_email($data['email'])) {
            $this->add_error('email', __('Invalid email address', 'shipsync'));
        }

        if (empty($data['phone'])) {
            $this->add_error('phone', __('Phone number is required', 'shipsync'));
        } elseif (!$this->validate_phone($data['phone'])) {
            $this->add_error('phone', __('Invalid phone number format', 'shipsync'));
        }

        if (empty($data['vehicle_type'])) {
            $this->add_error('vehicle_type', __('Vehicle type is required', 'shipsync'));
        }

        // Status validation
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive'], true)) {
            $this->add_error('status', __('Invalid courier status', 'shipsync'));
        }

        // Allow extensions via filter
        $this->errors = apply_filters('shipsync_validate_courier_data', $this->errors, $data);

        return empty($this->errors);
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number
     * @return bool Validation result
     */
    private function validate_phone(string $phone): bool {
        // Remove common separators
        $cleaned = preg_replace('/[\s\-\(\)\.]+/', '', $phone);

        // Check if it's a valid format (10-15 digits, optional + prefix)
        return preg_match('/^\+?[0-9]{10,15}$/', $cleaned) === 1;
    }

    /**
     * Validate courier deletion
     *
     * @param int $courier_id Courier ID
     * @return bool Validation result
     */
    public function validate_for_deletion(int $courier_id): bool {
        $this->errors = [];

        $container = ShipSync_Container::instance();
        $courier_repo = $container->get('courier_repository');

        // Check if courier exists
        if (!$courier_repo->exists($courier_id)) {
            $this->add_error('not_found', __('Courier not found', 'shipsync'));
            return false;
        }

        // Check for assigned orders
        $orders_count = $courier_repo->get_orders_count($courier_id);

        if ($orders_count > 0) {
            $this->add_error(
                'has_orders',
                sprintf(
                    __('Cannot delete courier with %d assigned orders', 'shipsync'),
                    $orders_count
                )
            );
        }

        return empty($this->errors);
    }

    /**
     * Validate courier service credentials
     *
     * @param string $courier_id Courier service ID
     * @param array  $credentials Credentials array
     * @return bool Validation result
     */
    public function validate_credentials(string $courier_id, array $credentials): bool {
        $this->errors = [];

        switch ($courier_id) {
            case 'steadfast':
                if (empty($credentials['api_key'])) {
                    $this->add_error('api_key', __('API Key is required', 'shipsync'));
                }
                if (empty($credentials['secret_key'])) {
                    $this->add_error('secret_key', __('Secret Key is required', 'shipsync'));
                }
                break;

            case 'pathao':
                if (empty($credentials['client_id'])) {
                    $this->add_error('client_id', __('Client ID is required', 'shipsync'));
                }
                if (empty($credentials['client_secret'])) {
                    $this->add_error('client_secret', __('Client Secret is required', 'shipsync'));
                }
                if (empty($credentials['username'])) {
                    $this->add_error('username', __('Username is required', 'shipsync'));
                }
                if (empty($credentials['password'])) {
                    $this->add_error('password', __('Password is required', 'shipsync'));
                }
                break;

            case 'redx':
                if (empty($credentials['api_key'])) {
                    $this->add_error('api_key', __('API Token is required', 'shipsync'));
                }
                break;
        }

        // Allow extensions via filter
        $this->errors = apply_filters(
            'shipsync_validate_courier_credentials',
            $this->errors,
            $courier_id,
            $credentials
        );

        return empty($this->errors);
    }

    /**
     * Add validation error
     *
     * @param string $field Field name
     * @param string $message Error message
     * @return void
     */
    private function add_error(string $field, string $message): void {
        $this->errors[$field] = $message;
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
