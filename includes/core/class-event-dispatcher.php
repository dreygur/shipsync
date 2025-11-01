<?php
/**
 * Event Dispatcher
 * Type-safe event system built on top of WordPress hooks
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Event_Dispatcher {

    /**
     * Registered event listeners
     *
     * @var array
     */
    private static array $listeners = [];

    /**
     * Event constants
     */
    const EVENT_ORDER_CREATED = 'shipsync.order.created';
    const EVENT_ORDER_UPDATED = 'shipsync.order.updated';
    const EVENT_ORDER_STATUS_CHANGED = 'shipsync.order.status_changed';
    const EVENT_COURIER_ASSIGNED = 'shipsync.courier.assigned';
    const EVENT_SHIPMENT_CREATED = 'shipsync.shipment.created';
    const EVENT_SHIPMENT_STATUS_UPDATED = 'shipsync.shipment.status_updated';
    const EVENT_TRACKING_UPDATED = 'shipsync.tracking.updated';
    const EVENT_WEBHOOK_RECEIVED = 'shipsync.webhook.received';
    const EVENT_API_ERROR = 'shipsync.api.error';

    /**
     * Dispatch an event
     *
     * @param string $event Event name
     * @param array  $data Event data
     * @return void
     */
    public static function dispatch(string $event, array $data = []): void {
        // Call WordPress action
        do_action($event, $data);

        // Call registered type-safe listeners
        if (isset(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $listener) {
                call_user_func($listener, $data);
            }
        }

        // Log event if debug enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('SHIPSYNC_DEBUG_EVENTS') && SHIPSYNC_DEBUG_EVENTS) {
            error_log(sprintf(
                '[ShipSync Event] %s | Data: %s',
                $event,
                wp_json_encode($data)
            ));
        }
    }

    /**
     * Listen to an event
     *
     * @param string   $event Event name
     * @param callable $callback Callback function
     * @param int      $priority Priority (default 10)
     * @return void
     */
    public static function listen(string $event, callable $callback, int $priority = 10): void {
        // Register in internal listeners
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }

        self::$listeners[$event][] = $callback;

        // Also register as WordPress action for compatibility
        add_action($event, $callback, $priority, 1);
    }

    /**
     * Dispatch order created event
     *
     * @param WC_Order $order Order object
     * @return void
     */
    public static function order_created(WC_Order $order): void {
        self::dispatch(self::EVENT_ORDER_CREATED, [
            'order_id' => $order->get_id(),
            'order' => $order,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch order status changed event
     *
     * @param int    $order_id Order ID
     * @param string $old_status Old status
     * @param string $new_status New status
     * @return void
     */
    public static function order_status_changed(int $order_id, string $old_status, string $new_status): void {
        self::dispatch(self::EVENT_ORDER_STATUS_CHANGED, [
            'order_id' => $order_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch courier assigned event
     *
     * @param int $order_id Order ID
     * @param int $courier_id Courier ID
     * @return void
     */
    public static function courier_assigned(int $order_id, int $courier_id): void {
        self::dispatch(self::EVENT_COURIER_ASSIGNED, [
            'order_id' => $order_id,
            'courier_id' => $courier_id,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch shipment created event
     *
     * @param int    $order_id Order ID
     * @param string $courier_service Courier service ID
     * @param string $tracking_code Tracking code
     * @param array  $shipment_data Shipment data
     * @return void
     */
    public static function shipment_created(
        int $order_id,
        string $courier_service,
        string $tracking_code,
        array $shipment_data = []
    ): void {
        self::dispatch(self::EVENT_SHIPMENT_CREATED, [
            'order_id' => $order_id,
            'courier_service' => $courier_service,
            'tracking_code' => $tracking_code,
            'shipment_data' => $shipment_data,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch shipment status updated event
     *
     * @param int    $order_id Order ID
     * @param string $courier_service Courier service ID
     * @param string $old_status Old delivery status
     * @param string $new_status New delivery status
     * @return void
     */
    public static function shipment_status_updated(
        int $order_id,
        string $courier_service,
        string $old_status,
        string $new_status
    ): void {
        self::dispatch(self::EVENT_SHIPMENT_STATUS_UPDATED, [
            'order_id' => $order_id,
            'courier_service' => $courier_service,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch tracking updated event
     *
     * @param int    $order_id Order ID
     * @param string $tracking_code Tracking code
     * @param array  $tracking_data Tracking data
     * @return void
     */
    public static function tracking_updated(int $order_id, string $tracking_code, array $tracking_data): void {
        self::dispatch(self::EVENT_TRACKING_UPDATED, [
            'order_id' => $order_id,
            'tracking_code' => $tracking_code,
            'tracking_data' => $tracking_data,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch webhook received event
     *
     * @param string $courier_service Courier service ID
     * @param array  $payload Webhook payload
     * @return void
     */
    public static function webhook_received(string $courier_service, array $payload): void {
        self::dispatch(self::EVENT_WEBHOOK_RECEIVED, [
            'courier_service' => $courier_service,
            'payload' => $payload,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Dispatch API error event
     *
     * @param string $courier_service Courier service ID
     * @param string $error_message Error message
     * @param array  $context Error context
     * @return void
     */
    public static function api_error(string $courier_service, string $error_message, array $context = []): void {
        self::dispatch(self::EVENT_API_ERROR, [
            'courier_service' => $courier_service,
            'error_message' => $error_message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
        ]);
    }

    /**
     * Get all registered events
     *
     * @return array Event names
     */
    public static function get_registered_events(): array {
        return array_keys(self::$listeners);
    }
}
