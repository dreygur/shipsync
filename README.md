# ShipSync - Bangladesh Courier Integration for WooCommerce

A WooCommerce plugin that integrates Bangladesh courier services (Steadfast, Pathao, RedX, etc.) for order delivery tracking and management.

## Overview

**ShipSync** is a WooCommerce plugin specifically designed for Bangladesh e-commerce businesses. It bridges the gap between your WooCommerce store and local courier services, enabling automated delivery management.

### Key Concept

- **WooCommerce Order Status** = Your business workflow (Processing, Completed, etc.)
- **Courier Delivery Status** = Real delivery tracking from courier companies (In Transit, Delivered, etc.)

The plugin **does not replace** WooCommerce's order management. Instead, it adds a layer of courier integration and delivery tracking on top of your existing WooCommerce orders.

## Features

### WooCommerce Integration
- Works seamlessly with WooCommerce orders
- Uses WooCommerce's native order status system
- HPOS (High-Performance Order Storage) compatible
- View and manage WooCommerce orders from ShipSync dashboard
- Assign internal couriers or send to courier services

### Bangladesh Courier Services
- **Steadfast Courier** - Full API integration with automated tracking
- **Pathao Courier** - Full API integration with bundled plugin support
- **RedX Courier** - Full API integration with WooCommerce plugin support
- **SA Paribahan** - Coming soon
- Send orders to courier APIs with one click
- Automatic tracking code and consignment ID storage
- Real-time delivery status updates via webhooks
- Support for multiple courier services simultaneously

### Delivery Tracking Dashboard
- Separate "Courier Orders" view for delivery tracking
- Track delivery status independent of order status
- Status types: In Review, Pending, On Hold, In Transit, Delivered, Cancelled
- Bulk status updates from courier APIs
- Delivery charge tracking
- Customer delivery tracking interface

### Frontend Widget
- Order card widget for homepage display
- Recent orders display
- Order tracking functionality
- Responsive design
- Customizable display options

### Admin Interface
- Intuitive admin dashboard
- Bulk actions support
- Real-time order updates
- AJAX-powered interactions
- Modal-based forms

## Installation

1. Ensure WooCommerce is installed and activated
2. Upload the plugin files to `/wp-content/plugins/order-courier-manager/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. The plugin will automatically create necessary database tables
5. Navigate to ShipSync > Courier Integration to configure your courier services

### Requirements
- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- cURL enabled for API communications

## Usage

### Admin Panel

1. **All Orders**: Navigate to `ShipSync > All Orders`
   - View all WooCommerce orders with delivery tracking
   - Change order status (uses WooCommerce status)
   - Assign internal couriers to orders
   - View tracking codes and delivery status
   - Quick actions: Copy order details, Assign courier, Edit order

2. **Courier Orders**: Navigate to `ShipSync > Courier Orders`
   - View orders sent to courier services (Steadfast, etc.)
   - Track delivery status from courier APIs
   - Monitor: In Review, Pending, Hold, Delivered, Cancelled
   - View tracking codes, consignment IDs, and delivery charges
   - Filter by delivery status
   - Search by tracking code or order number

3. **Courier Integration**: Navigate to `ShipSync > Courier Integration`
   - Configure Steadfast, Pathao, and RedX API credentials
   - Enable/disable individual courier services
   - Test API connections for each service
   - View integration status and plugin dependencies
   - Manage courier settings per service
   - Configure webhook authentication

4. **Settings**: Navigate to `ShipSync > Settings`
   - Configure general plugin options
   - Set notification preferences
   - Customize display settings

### Frontend Widget

1. **Widget Setup**:
   - Go to `Appearance > Widgets`
   - Add "Order Card Widget" to your desired widget area
   - Configure display options

2. **Shortcode Usage - Order Cards**:
   ```
   [ocm_order_card limit="5" show_status="true" show_courier="true" title="Recent Orders"]
   ```

   Parameters:
   - `limit`: Number of orders to display (default: 5)
   - `show_status`: Display order status badges (default: true)
   - `show_courier`: Display courier information (default: true)
   - `title`: Widget title (default: "Recent Orders")

3. **Shortcode Usage - Order Tracking**:
   ```
   [ocm_track_order title="Track Your Order" placeholder="Enter your order number" button_text="Track Order"]
   ```

   Parameters:
   - `title`: Form title (default: "Track Your Order")
   - `placeholder`: Input field placeholder (default: "Enter your order number")
   - `button_text`: Submit button text (default: "Track Order")

   Customers can track orders by order number and view:
   - Current order status
   - Order details
   - Status history
   - Assigned courier information

## How It Works

### Order Flow

1. **Customer places order** → WooCommerce creates order
2. **You process order** → Change WooCommerce order status (Processing, etc.)
3. **Send to courier** → Use ShipSync to send order to Steadfast/other courier
4. **Track delivery** → Monitor delivery status in "Courier Orders" dashboard
5. **Auto-update** → Webhook updates delivery status automatically

### Two Status Systems

**WooCommerce Order Status (Your Business)**
- Pending Payment
- Processing (order being prepared)
- On Hold
- Completed (order fulfilled)
- Cancelled

**Courier Delivery Status (Logistics)**
- In Review (courier reviewing)
- Pending (awaiting pickup)
- On Hold (delivery issue)
- In Transit (out for delivery)
- Delivered (successfully delivered)
- Cancelled (return to sender)

### Database Structure

**Tables Created:**
1. **wp_ocm_couriers**: Stores internal courier information (for manual assignment)

**WooCommerce Order Meta Used:**
- `_ocm_courier_id` / `_shipsync_courier_id`: Assigned internal courier
- `_shipsync_courier_service`: Which courier service was used (steadfast, pathao, redx)
- **Steadfast:**
  - `_steadfast_tracking_code`: Steadfast tracking number
  - `_steadfast_consignment_id`: Steadfast consignment ID
  - `_steadfast_status`: Current delivery status from Steadfast
  - `_steadfast_delivery_charge`: Delivery cost
- **Pathao:**
  - `_pathao_tracking_id`: Pathao tracking ID
  - `_pathao_consignment_id`: Pathao consignment ID
  - `_pathao_status`: Current delivery status from Pathao
  - `_pathao_delivery_charge`: Delivery cost
- **RedX:**
  - `_redx_tracking_id`: RedX tracking ID
  - `_redx_status`: Current delivery status from RedX
  - `_redx_delivery_fee`: Delivery cost

## Customization

### CSS Customization

The plugin includes comprehensive CSS files:
- `assets/css/admin.css` - Admin interface styling
- `assets/css/frontend.css` - Frontend widget styling

### JavaScript Functionality

- `assets/js/admin.js` - Admin interface interactions
- `assets/js/frontend.js` - Frontend widget functionality

### Hooks and Filters

The plugin provides various WordPress hooks for customization:

**Action Hooks:**
```php
// Fires when a new order is created
add_action('ocm_order_created', 'custom_order_handler', 10, 2);

// Fires when order status is updated
add_action('ocm_order_status_updated', 'custom_status_update_handler', 10, 4);

// Fires when a courier is assigned to an order
add_action('ocm_courier_assigned', 'custom_courier_assignment', 10, 2);

// Fires when a new courier is created
add_action('ocm_courier_created', 'custom_courier_handler', 10, 2);

// Fires after status update email is sent
add_action('ocm_status_update_email_sent', 'after_email_sent', 10, 4);
```

**Filter Hooks:**
```php
// Filter order display data
add_filter('ocm_order_display_data', 'custom_order_display');

// Filter order card shortcode attributes
add_filter('ocm_order_card_atts', 'custom_order_card_atts');

// Filter track order shortcode attributes
add_filter('ocm_track_order_atts', 'custom_track_order_atts');

// Customize email messages
add_filter('ocm_status_update_email_message', 'custom_email_message', 10, 5);
add_filter('ocm_status_update_email_subject', 'custom_email_subject', 10, 3);
```

## API Endpoints

### AJAX Actions

- `ocm_update_order_status` - Update order status
- `ocm_assign_courier` - Assign courier to order
- `ocm_get_order_details` - Get order details
- `ocm_delete_courier` - Delete courier
- `ocm_track_order` - Track order (frontend)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Security Features

- Nonce verification for all AJAX requests
- Capability checks for admin functions
- Data sanitization and validation
- SQL injection prevention

## Performance

- Optimized database queries
- Lazy loading for large order lists
- Caching for frequently accessed data
- Responsive design for mobile devices

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Troubleshooting

### Common Issues

1. **Plugin not activating**: Check file permissions and WordPress version
2. **Database errors**: Ensure MySQL version compatibility
3. **AJAX not working**: Check nonce configuration and user permissions
4. **Widget not displaying**: Verify widget area configuration

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### Version 2.0.0
- Rebranded as ShipSync
- Focused on Bangladesh courier integration
- Full WooCommerce HPOS compatibility
- Steadfast Courier API integration with webhooks
- Pathao Courier API integration (bundled plugin support)
- RedX Courier API integration (plugin dependency support)
- Separated order status from delivery status
- Removed legacy custom order system
- Streamlined admin interface
- Added dedicated Courier Orders dashboard
- Modern UI/UX improvements with responsive design
- Webhook authentication (Bearer token, API token, header, query parameter)
- Tracking URL copy functionality
- Multi-courier support with service detection

### Version 1.0.0
- Initial release
- Order management system
- Courier management system
- Frontend widget
- Admin interface
- AJAX functionality
- Responsive design

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed as a comprehensive WordPress plugin for order and courier management.
