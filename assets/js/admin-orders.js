/**
 * Admin Orders JavaScript for ShipSync
 */

jQuery(document).ready(function($) {
    // Global variables for courier selection
    var pendingOrderId = null;
    var pendingStatus = null;
    var selectedCourierId = null;

    // Change order status
    $('.ocm-order-status-select').on('change', function() {
        var $select = $(this);
        var orderId = $select.data('order-id');
        var newStatus = $select.val();

        if (!confirm(shipSyncOrders.i18n.confirmStatusChange)) {
            // Reset to original value
            location.reload();
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_update_wc_order_status',
                order_id: orderId,
                status: newStatus,
                nonce: shipSyncOrders.nonce
            },
            success: function(response) {
                if (response.success) {
                    var courierAction = response.data.courier_action;

                    if (courierAction === 'select_required') {
                        // Show courier selection modal
                        pendingOrderId = orderId;
                        pendingStatus = newStatus;
                        showCourierSelectionModal(response.data.couriers);
                    } else if (courierAction === 'auto_sent' || courierAction === 'default_sent') {
                        // Show success message with courier name
                        showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        // Regular status change or no courier integration
                        if (response.data.message) {
                            showNotice('success', response.data.message);
                        }
                        location.reload();
                    }
                } else {
                    showNotice('error', response.data.message || shipSyncOrders.i18n.error);
                    location.reload();
                }
            },
            error: function() {
                showNotice('error', shipSyncOrders.i18n.error);
                location.reload();
            }
        });
    });

    // Show courier selection modal
    function showCourierSelectionModal(couriers) {
        var $options = $('#ocm-courier-options');
        $options.empty();

        couriers.forEach(function(courier, index) {
            var option = $('<div class="ocm-courier-option">' +
                '<input type="radio" name="ocm_courier" id="courier_' + courier.id + '" value="' + courier.id + '"' + (index === 0 ? ' checked' : '') + '>' +
                '<label for="courier_' + courier.id + '">' + courier.name + '</label>' +
                '</div>');

            option.on('click', function() {
                $(this).find('input[type="radio"]').prop('checked', true);
            });

            $options.append(option);
        });

        selectedCourierId = couriers[0].id; // Pre-select first courier
        $('#ocm-courier-selection-modal').fadeIn();
    }

    // Handle courier selection change
    $(document).on('change', 'input[name="ocm_courier"]', function() {
        selectedCourierId = $(this).val();
    });

    // Confirm courier selection
    $('#ocm-confirm-courier').on('click', function() {
        if (!selectedCourierId) {
            showNotice('error', shipSyncOrders.i18n.selectCourier);
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text(shipSyncOrders.i18n.sending);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_send_to_selected_courier',
                order_id: pendingOrderId,
                courier_id: selectedCourierId,
                nonce: shipSyncOrders.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    location.reload();
                } else {
                    showNotice('error', response.data.message);
                    $btn.prop('disabled', false).text(shipSyncOrders.i18n.confirmAndShip);
                }
            },
            error: function() {
                showNotice('error', shipSyncOrders.i18n.error);
                $btn.prop('disabled', false).text(shipSyncOrders.i18n.confirmAndShip);
            }
        });
    });

    // Close modal
    $('.ocm-courier-modal-close').on('click', function() {
        $('#ocm-courier-selection-modal').fadeOut();
        location.reload(); // Reload to reset status dropdown
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('ocm-courier-modal')) {
            $('#ocm-courier-selection-modal').fadeOut();
            location.reload(); // Reload to reset status dropdown
        }
    });

    // Copy order details to clipboard
    $('.ocm-copy-order').on('click', function() {
        var orderDetails = $(this).data('order-details');

        var text = '📦 Order Details\n\n';
        text += 'Order ID: ' + orderDetails.order_id + '\n';
        text += 'Customer: ' + orderDetails.customer_name + '\n';
        text += 'Phone: ' + orderDetails.phone + '\n';
        text += 'Quantity: ' + orderDetails.quantity + '\n';
        text += 'Address: ' + orderDetails.address + '\n';
        if (orderDetails.notes) {
            text += 'Notes: ' + orderDetails.notes + '\n';
        }

        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success feedback
                var $btn = $('.ocm-copy-order').filter(function() {
                    return JSON.stringify($(this).data('order-details')) === JSON.stringify(orderDetails);
                });
                var $icon = $btn.find('.dashicons');
                $icon.removeClass('dashicons-admin-page').addClass('dashicons-yes-alt');
                $btn.css('background', '#00a32a');

                setTimeout(function() {
                    $icon.removeClass('dashicons-yes-alt').addClass('dashicons-admin-page');
                    $btn.css('background', '');
                }, 1500);
            }).catch(function(err) {
                showNotice('error', shipSyncOrders.i18n.copyFailed);
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            showNotice('success', shipSyncOrders.i18n.copied);
        }
    });

    // Send order details to WhatsApp
    $('.ocm-whatsapp-order').on('click', function() {
        var orderDetails = $(this).data('order-details');

        var text = '📦 *Order Details*\n\n';
        text += '*Order ID:* ' + orderDetails.order_id + '\n';
        text += '*Customer:* ' + orderDetails.customer_name + '\n';
        text += '*Phone:* ' + orderDetails.phone + '\n';
        text += '*Quantity:* ' + orderDetails.quantity + '\n';
        text += '*Address:* ' + orderDetails.address + '\n';
        if (orderDetails.notes) {
            text += '*Notes:* ' + orderDetails.notes + '\n';
        }

        // Encode text for URL
        var encodedText = encodeURIComponent(text);

        // Open WhatsApp with pre-filled message
        var whatsappUrl = 'https://wa.me/?text=' + encodedText;
        window.open(whatsappUrl, '_blank');
    });

    // Copy tracking URL to clipboard when clicking on tracking code
    $(document).on('click', '.ocm-tracking-code[data-tracking-url], .dashicons[data-tracking-url]', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var trackingUrl = $(this).data('tracking-url');
        if (!trackingUrl) {
            // Try to get from parent or sibling
            trackingUrl = $(this).closest('.ocm-tracking-code').data('tracking-url') ||
                         $(this).siblings('.ocm-tracking-code').data('tracking-url') ||
                         $(this).parent().find('.ocm-tracking-code').data('tracking-url');
        }

        if (!trackingUrl) {
            return;
        }

        var $clickedElement = $(this);
        var isIcon = $clickedElement.hasClass('dashicons');

        // Copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(trackingUrl).then(function() {
                // Show success feedback
                if (isIcon) {
                    // Change icon to checkmark
                    $clickedElement.removeClass('dashicons-admin-page').addClass('dashicons-yes-alt');
                    $clickedElement.css('color', '#00a32a');

                    setTimeout(function() {
                        $clickedElement.removeClass('dashicons-yes-alt').addClass('dashicons-admin-page');
                        $clickedElement.css('color', '#2271b1');
                    }, 2000);
                } else {
                    // Change code background to green
                    var originalBg = $clickedElement.css('background-color');
                    $clickedElement.css({
                        'background-color': '#00a32a',
                        'color': '#fff'
                    });

                    setTimeout(function() {
                        $clickedElement.css({
                            'background-color': originalBg,
                            'color': '#2271b1'
                        });
                    }, 2000);
                }

                // Show brief notification
                showNotice('success', 'Tracking URL copied to clipboard!');
            }).catch(function(err) {
                showNotice('error', 'Failed to copy tracking URL');
            });
        } else {
            // Fallback for older browsers
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(trackingUrl).select();
            document.execCommand('copy');
            $temp.remove();
            showNotice('success', 'Tracking URL copied to clipboard!');
        }
    });

    /**
     * Show WordPress-style notice
     * @param {string} type - 'success', 'error', 'warning', 'info'
     * @param {string} message - Message to display
     */
    function showNotice(type, message) {
        var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        setTimeout(function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
});

