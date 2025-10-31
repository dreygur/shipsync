=== ShipSync ===
Contributors: rakibulyeasin
Donate link: https://example.com
Tags: woocommerce, courier, delivery, shipping, tracking, bangladesh, steadfast, pathao, redx
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ShipSync is a comprehensive WooCommerce plugin that integrates Bangladesh courier services (Steadfast, Pathao, RedX) for order delivery tracking and management.

== Description ==

ShipSync seamlessly integrates multiple Bangladesh courier services into your WooCommerce store, allowing you to:

* **Multi-Courier Support**: Integrate Steadfast, Pathao, RedX, and custom courier services
* **Order Management**: Automatically send orders to courier services and track their delivery status
* **Real-Time Tracking**: Receive webhook updates from courier services for automatic status updates
* **Customer Tracking**: Allow customers to track their orders using a tracking widget
* **Bulk Operations**: Send multiple orders to courier services at once
* **Email Notifications**: Automatic email notifications for order status changes
* **Admin Dashboard**: Comprehensive admin interface for managing orders and couriers
* **Webhook Authentication**: Secure webhook endpoints with multiple authentication methods

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/shipsync` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure WooCommerce is installed and activated (required dependency).
4. Navigate to ShipSync > Settings to configure your courier services.
5. Configure each courier service with your API credentials.
6. Start sending orders to courier services from the Orders page.

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No, WooCommerce is required for this plugin to function. ShipSync integrates directly with WooCommerce orders.

= Which courier services are supported? =

ShipSync currently supports:
* Steadfast Courier
* Pathao Courier
* RedX Courier
* Custom/manual courier services

= Can I use multiple courier services at the same time? =

Yes! You can enable multiple courier services and choose which one to use for each order.

= How do webhooks work? =

When enabled, courier services can send status updates directly to your WordPress site via webhooks. You can configure webhook authentication for added security.

= Do I need to install separate plugins for courier services? =

For Steadfast and RedX, the respective WordPress plugins are recommended (but optional). Pathao plugin is bundled with ShipSync.

== Screenshots ==

1. Orders management dashboard
2. Courier service settings and configuration
3. Order tracking interface
4. Webhook configuration panel

== Changelog ==

= 2.0.0 =
* Initial release
* Multi-courier support (Steadfast, Pathao, RedX)
* Webhook integration with authentication
* Order tracking and status management
* Admin dashboard with responsive design
* Customer tracking widget

== Upgrade Notice ==

= 2.0.0 =
Initial release of ShipSync.

== Development ==

Development is hosted on GitHub: https://github.com/rakibulyeasin/shipsync

== Credits ==

Developed by Rakibul Yeasin

