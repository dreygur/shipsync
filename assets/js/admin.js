/**
 * Admin JavaScript for Order & Courier Manager
 */

jQuery(document).ready(function ($) {

  // Modal functionality
  function openModal(modalId) {
    $('#' + modalId).fadeIn(300);
    $('body').addClass('modal-open');
  }

  function closeModal(modalId) {
    $('#' + modalId).fadeOut(300);
    $('body').removeClass('modal-open');
  }

  // Close modal when clicking outside
  $(document).on('click', '.ocm-modal', function (e) {
    if (e.target === this) {
      closeModal($(this).attr('id'));
    }
  });

  // Close modal buttons
  $(document).on('click', '.ocm-modal-close', function () {
    const modalId = $(this).closest('.ocm-modal').attr('id');
    closeModal(modalId);
  });

  // Update order status
  $(document).on('click', '.ocm-update-status', function () {
    const orderId = $(this).data('order-id');
    $('#update-status-order-id').val(orderId);
    openModal('ocm-update-status-modal');
  });

  // Assign courier
  $(document).on('click', '.ocm-assign-courier', function () {
    const orderId = $(this).data('order-id');
    $('#assign-courier-order-id').val(orderId);
    openModal('ocm-assign-courier-modal');
  });

  // Update status form submission
  $('#ocm-update-status-form').on('submit', function (e) {
    e.preventDefault();

    const formData = {
      action: 'shipsync_update_order_status',
      order_id: $('#update-status-order-id').val(),
      status: $('#status').val(),
      notes: $('#notes').val(),
      nonce: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.nonce : ocm_ajax.nonce)
    };

    const $form = $(this);
    const $submitBtn = $form.find('button[type="submit"]');

    $submitBtn.prop('disabled', true).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).loading);

    $.ajax({
      url: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.ajax_url : ocm_ajax.ajax_url),
      type: 'POST',
      data: formData,
      success: function (response) {
        if (response.success) {
          showMessage(response.data.message, 'success');
          closeModal('ocm-update-status-modal');
          setTimeout(function () {
            location.reload();
          }, 1500);
        } else {
          showMessage(response.data.message, 'error');
        }
      },
      error: function () {
        showMessage((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).error, 'error');
      },
      complete: function () {
        $submitBtn.prop('disabled', false).text('Update Status');
      }
    });
  });

  // Assign courier form submission
  $('#ocm-assign-courier-form').on('submit', function (e) {
    e.preventDefault();

    const formData = {
      action: 'shipsync_assign_courier',
      order_id: $('#assign-courier-order-id').val(),
      courier_id: $('#courier_id').val(),
      nonce: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.nonce : ocm_ajax.nonce)
    };

    const $form = $(this);
    const $submitBtn = $form.find('button[type="submit"]');

    $submitBtn.prop('disabled', true).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).loading);

    $.ajax({
      url: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.ajax_url : ocm_ajax.ajax_url),
      type: 'POST',
      data: formData,
      success: function (response) {
        if (response.success) {
          showMessage(response.data.message, 'success');
          closeModal('ocm-assign-courier-modal');
          setTimeout(function () {
            location.reload();
          }, 1500);
        } else {
          showMessage(response.data.message, 'error');
        }
      },
      error: function () {
        showMessage((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).error, 'error');
      },
      complete: function () {
        $submitBtn.prop('disabled', false).text('Assign Courier');
      }
    });
  });

  // Delete courier
  $(document).on('click', '.ocm-delete-courier', function () {
    if (!confirm((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).confirm_delete)) {
      return;
    }

    const courierId = $(this).data('courier-id');
    const $btn = $(this);

    $btn.prop('disabled', true).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).loading);

    $.ajax({
      url: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.ajax_url : ocm_ajax.ajax_url),
      type: 'POST',
      data: {
        action: 'shipsync_delete_courier',
        courier_id: courierId,
        nonce: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.nonce : ocm_ajax.nonce)
      },
      success: function (response) {
        if (response.success) {
          showMessage(response.data.message, 'success');
          $btn.closest('tr').fadeOut(300, function () {
            $(this).remove();
          });
        } else {
          showMessage(response.data.message, 'error');
        }
      },
      error: function () {
        showMessage((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).error, 'error');
      },
      complete: function () {
        $btn.prop('disabled', false).text('Delete');
      }
    });
  });

  // Edit courier (placeholder for future functionality)
  $(document).on('click', '.ocm-edit-courier', function () {
    const courierId = $(this).data('courier-id');
    // Redirect to edit page or open edit modal
    window.location.href = 'admin.php?page=ocm-edit-courier&id=' + courierId;
  });

  // Show message function
  function showMessage(message, type) {
    const messageClass = 'ocm-message ' + type;
    const $message = $('<div class="' + messageClass + '">' + message + '</div>');

    $('.wrap h1').after($message);

    setTimeout(function () {
      $message.fadeOut(300, function () {
        $(this).remove();
      });
    }, 5000);
  }

  // Auto-refresh orders every 60 seconds (only if user is inactive and no modals)
  // This is less disruptive than 30 seconds
  if ($('.ocm-orders-container').length > 0) {
    var lastUserActivity = Date.now();
    var refreshInterval = 60000; // 60 seconds

    // Track user activity
    $(document).on('mousemove keypress scroll click', function() {
      lastUserActivity = Date.now();
    });

    setInterval(function () {
      // Only refresh if:
      // 1. No modals are open
      // 2. User has been inactive for at least 30 seconds
      // 3. Page has been visible for at least 60 seconds
      var timeSinceActivity = Date.now() - lastUserActivity;
      var isPageVisible = !document.hidden;

      if ($('.ocm-modal:visible').length === 0 &&
          timeSinceActivity > 30000 &&
          isPageVisible) {
        location.reload();
      }
    }, refreshInterval);

    // Add manual refresh button
    if ($('.ocm-auto-refresh-toggle').length === 0) {
      var $refreshBtn = $('<button>', {
        class: 'button ocm-auto-refresh-toggle',
        style: 'margin-left: 10px;',
        html: '<span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle;"></span> ' +
              '<span class="ocm-refresh-text">' + (typeof shipsyncAjax !== 'undefined' && shipsyncAjax.strings && shipsyncAjax.strings.refresh ? shipsyncAjax.strings.refresh : 'Refresh') + '</span>'
      }).on('click', function() {
        var $btn = $(this);
        $btn.find('.dashicons').addClass('ocm-spinning');
        setTimeout(function() {
          location.reload();
        }, 500);
      });
      $('.page-title-action').after($refreshBtn);
    }
  }

  // Form validation with better UX
  $('.ocm-form').on('submit', function (e) {
    const $form = $(this);
    let isValid = true;
    let firstError = null;

    // Remove previous error messages
    $form.find('.ocm-field-error').remove();

    // Check required fields
    $form.find('input[required], select[required], textarea[required]').each(function () {
      const $field = $(this);
      if (!$field.val().trim()) {
        $field.addClass('error');
        if (!firstError) firstError = $field;
        isValid = false;

        // Add inline error message
        const fieldLabel = $field.closest('.ocm-form-group').find('label').text() || $field.attr('placeholder') || 'This field';
        $field.after('<span class="ocm-field-error" style="color: #d63638; font-size: 12px; display: block; margin-top: 5px;"><span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span> ' + fieldLabel.replace('*', '').trim() + ' is required.</span>');
      } else {
        $field.removeClass('error');
      }
    });

    // Check email format
    $form.find('input[type="email"]').each(function () {
      const $field = $(this);
      const email = $field.val();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (email && !emailRegex.test(email)) {
        $field.addClass('error');
        if (!firstError) firstError = $field;
        isValid = false;
        $field.after('<span class="ocm-field-error" style="color: #d63638; font-size: 12px; display: block; margin-top: 5px;"><span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span> Please enter a valid email address.</span>');
      }
    });

    if (!isValid) {
      e.preventDefault();
      showMessage('Please correct the errors below and try again.', 'error');

      // Scroll to first error
      if (firstError) {
        $('html, body').animate({
          scrollTop: firstError.offset().top - 100
        }, 500);
        firstError.focus();
      }
    }
  });

  // Remove error class on input
  $(document).on('input', '.ocm-form input, .ocm-form select, .ocm-form textarea', function () {
    $(this).removeClass('error');
  });

  // Add loading state to buttons
  $(document).on('click', '.button-primary', function () {
    const $btn = $(this);
    if (!$btn.prop('disabled')) {
      $btn.addClass('ocm-loading');
    }
  });

  // Keyboard shortcuts
  $(document).on('keydown', function (e) {
    // ESC to close modals
    if (e.keyCode === 27) {
      $('.ocm-modal:visible').each(function () {
        closeModal($(this).attr('id'));
      });
    }
  });

  // Initialize tooltips (if using a tooltip library)
  if (typeof $.fn.tooltip !== 'undefined') {
    $('[data-tooltip]').tooltip();
  }

  // Table sorting (basic implementation)
  $('.wp-list-table th').on('click', function () {
    const $th = $(this);
    const $table = $th.closest('table');
    const column = $th.index();
    const $rows = $table.find('tbody tr').toArray();

    // Simple alphabetical sorting
    $rows.sort(function (a, b) {
      const aText = $(a).find('td').eq(column).text().toLowerCase();
      const bText = $(b).find('td').eq(column).text().toLowerCase();
      return aText.localeCompare(bText);
    });

    $table.find('tbody').empty().append($rows);
  });

  // Search functionality
  if ($('#ocm-search').length > 0) {
    $('#ocm-search').on('input', function () {
      const searchTerm = $(this).val().toLowerCase();
      $('.wp-list-table tbody tr').each(function () {
        const $row = $(this);
        const text = $row.text().toLowerCase();
        if (text.includes(searchTerm)) {
          $row.show();
        } else {
          $row.hide();
        }
      });
    });
  }

  // Bulk actions
  $('.ocm-bulk-action').on('click', function () {
    const action = $(this).data('action');
    const selectedItems = $('.ocm-checkbox:checked').map(function () {
      return $(this).val();
    }).get();

    if (selectedItems.length === 0) {
      showMessage('Please select items to perform bulk action.', 'error');
      return;
    }

    if (!confirm('Are you sure you want to perform this action on ' + selectedItems.length + ' items?')) {
      return;
    }

    // Perform bulk action
    $.ajax({
      url: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.ajax_url : ocm_ajax.ajax_url),
      type: 'POST',
      data: {
        action: 'shipsync_bulk_action',
        bulk_action: action,
        items: selectedItems,
        nonce: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.nonce : ocm_ajax.nonce)
      },
      success: function (response) {
        if (response.success) {
          showMessage(response.data.message, 'success');
          setTimeout(function () {
            location.reload();
          }, 1500);
        } else {
          showMessage(response.data.message, 'error');
        }
      },
      error: function () {
        showMessage((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).error, 'error');
      }
    });
  });

  // Select all checkbox
  $('.ocm-select-all').on('change', function () {
    const isChecked = $(this).is(':checked');
    $('.ocm-checkbox').prop('checked', isChecked);
  });

  // Individual checkbox change
  $(document).on('change', '.ocm-checkbox', function () {
    const totalCheckboxes = $('.ocm-checkbox').length;
    const checkedCheckboxes = $('.ocm-checkbox:checked').length;

    $('.ocm-select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
  });

  // Courier meta box functionality
  // Send to courier
  $(document).on('click', '.ocm-send-order', function () {
    const $btn = $(this);
    const orderId = $btn.data('order-id');
    const courier = $('#ocm_courier_service').val();
    const note = $('#ocm_delivery_note').val();

    if (!courier) {
      showMessage((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).select_courier || 'Please select a courier service', 'error');
      return;
    }

    $btn.prop('disabled', true).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).sending || 'Sending...');
    $('.ocm-courier-response').html('');

    $.ajax({
      url: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.ajax_url : ocm_ajax.ajax_url),
      type: 'POST',
      data: {
        action: 'shipsync_send_to_courier',
        order_id: orderId,
        courier: courier,
        note: note,
        nonce: ($('#shipsync_courier_nonce').length ? $('#shipsync_courier_nonce').val() : $('#ocm_courier_nonce').val())
      },
      success: function (response) {
        if (response.success) {
          $('.ocm-courier-response').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          $('.ocm-courier-response').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
          $btn.prop('disabled', false).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).send_to_courier || 'Send to Courier');
        }
      },
      error: function () {
        $('.ocm-courier-response').html('<div class="notice notice-error"><p>' + ((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).error || 'An error occurred') + '</p></div>');
        $btn.prop('disabled', false).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).send_to_courier || 'Send to Courier');
      }
    });
  });

  // Check courier status
  $(document).on('click', '.ocm-check-status', function () {
    const $btn = $(this);
    const orderId = $btn.data('order-id');
    const courier = $btn.data('courier');

    $btn.prop('disabled', true).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).checking || 'Checking...');

    $.ajax({
      url: (typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.ajax_url : ocm_ajax.ajax_url),
      type: 'POST',
      data: {
        action: 'shipsync_check_courier_status',
        order_id: orderId,
        courier: courier,
        nonce: ($('#shipsync_courier_nonce').length ? $('#shipsync_courier_nonce').val() : $('#ocm_courier_nonce').val())
      },
      success: function (response) {
        if (response.success) {
          showMessage(((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).status || 'Status: ') + response.data.status, 'success');
          setTimeout(function () {
            location.reload();
          }, 2000);
        } else {
          showMessage(response.data.message, 'error');
        }
        $btn.prop('disabled', false).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).check_status || 'Check Status');
      },
      error: function () {
        showMessage((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).error || 'An error occurred', 'error');
        $btn.prop('disabled', false).text((typeof shipsyncAjax !== 'undefined' ? shipsyncAjax.strings : ocm_ajax.strings).check_status || 'Check Status');
      }
    });
  });

});
