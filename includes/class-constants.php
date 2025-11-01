<?php
/**
 * Constants class for ShipSync
 * Contains all magic strings and meta keys
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constants class for ShipSync
 * Contains all magic strings and meta keys
 */
class ShipSync_Meta_Keys {
    // SteadFast
    const STEADFAST_TRACKING_CODE = '_steadfast_tracking_code';
    const STEADFAST_CONSIGNMENT_ID = '_steadfast_consignment_id';
    const STEADFAST_STATUS = '_steadfast_status';
    const STEADFAST_DELIVERY_CHARGE = '_steadfast_delivery_charge';

    // Pathao
    const PATHAO_TRACKING_ID = '_pathao_tracking_id';
    const PATHAO_CONSIGNMENT_ID = '_pathao_consignment_id';
    const PATHAO_STATUS = '_pathao_status';
    const PATHAO_DELIVERY_CHARGE = '_pathao_delivery_charge';

    // RedX
    const REDX_TRACKING_ID = '_redx_tracking_id';
    const REDX_STATUS = '_redx_status';
    const REDX_DELIVERY_CHARGE = '_redx_delivery_fee';

    // Generic
    const COURIER_ID = '_ocm_courier_id';
    const SHIPSYNC_COURIER_ID = '_shipsync_courier_id';
    const COURIER_SERVICE = '_shipsync_courier_service'; // Which service was used (steadfast, pathao, redx)
}

/**
 * Valid WooCommerce order statuses
 */
class ShipSync_Order_Status {
    const PENDING = 'pending';
    const PROCESSING = 'processing';
    const ON_HOLD = 'on-hold';
    const OUT_SHIPPING = 'out-shipping';
    const COMPLETED = 'completed';
    const CANCELLED = 'cancelled';
    const REFUNDED = 'refunded';
    const FAILED = 'failed';

    /**
     * Get all valid statuses
     * @return array
     */
    public static function get_all(): array {
        return [
            self::PENDING,
            self::PROCESSING,
            self::ON_HOLD,
            self::OUT_SHIPPING,
            self::COMPLETED,
            self::CANCELLED,
            self::REFUNDED,
            self::FAILED
        ];
    }

    /**
     * Check if status is valid
     * @param string $status
     * @return bool
     */
    public static function is_valid(string $status): bool {
        return in_array($status, self::get_all(), true);
    }
}

/**
 * Courier delivery statuses
 */
class ShipSync_Courier_Status {
    const IN_REVIEW = 'in_review';
    const PENDING = 'pending';
    const HOLD = 'hold';
    const DELIVERED = 'delivered';
    const DELIVERED_APPROVAL_PENDING = 'delivered_approval_pending';
    const CANCELLED = 'cancelled';
    const CANCELLED_APPROVAL_PENDING = 'cancelled_approval_pending';
    const PARTIAL_DELIVERED = 'partial_delivered';
    const PARTIAL_DELIVERED_APPROVAL_PENDING = 'partial_delivered_approval_pending';

    /**
     * Get all valid statuses
     * @return array
     */
    public static function get_all(): array {
        return [
            self::IN_REVIEW,
            self::PENDING,
            self::HOLD,
            self::DELIVERED,
            self::DELIVERED_APPROVAL_PENDING,
            self::CANCELLED,
            self::CANCELLED_APPROVAL_PENDING,
            self::PARTIAL_DELIVERED,
            self::PARTIAL_DELIVERED_APPROVAL_PENDING
        ];
    }
}

/**
 * Transient keys
 */
class ShipSync_Transients {
    const NEEDS_TABLE_CREATION = 'shipsync_needs_table_creation';
    const COURIERS_CACHE = 'shipsync_couriers_';
}

/**
 * Option names (for consistency and to prevent hardcoded strings)
 */
class ShipSync_Options {
    const COURIER_SETTINGS = 'ocm_courier_settings';
    const SETTINGS = 'ocm_settings';
    const VERSION = 'ocm_version';
    const ENABLE_COURIER_LOGS = 'ocm_enable_courier_logs';
    const ENABLE_WEBHOOK_LOGS = 'ocm_enable_webhook_logs';
    const WEBHOOK_AUTH_ENABLED = 'ocm_webhook_auth_enabled';
    const WEBHOOK_AUTH_TOKEN = 'ocm_webhook_auth_token';
    const WEBHOOK_AUTH_METHOD = 'ocm_webhook_auth_method';
}

/**
 * Default values
 */
class ShipSync_Defaults {
    const ORDERS_PER_PAGE = 20;
    const COURIER_ORDERS_PER_PAGE = 10;
    const DEFAULT_DELIVERY_CHARGE = 150;
    const CACHE_EXPIRATION = HOUR_IN_SECONDS;
    const RATE_LIMIT_WINDOW = 60; // seconds
    const RATE_LIMIT_MAX_REQUESTS = 10; // max requests per window
}

