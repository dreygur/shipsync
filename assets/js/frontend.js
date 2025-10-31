/**
 * Frontend JavaScript for Order & Courier Manager
 */

jQuery(document).ready(function ($) {

  // Order tracking functionality
  function initOrderTracking() {
    $('.ocm-track-form').on('submit', function (e) {
      e.preventDefault();

      const orderNumber = $(this).find('input[type="text"]').val().trim();
      if (!orderNumber) {
        showTrackMessage('Please enter an order number.', 'error');
        return;
      }

      const $form = $(this);
      const $submitBtn = $form.find('button');
      const $result = $('.ocm-track-result');

      $submitBtn.prop('disabled', true).text('Tracking...');
      $result.removeClass('show').hide();

      $.ajax({
        url: (typeof shipsyncFrontend !== 'undefined' ? shipsyncFrontend.ajax_url : ocm_frontend.ajax_url),
        type: 'POST',
        data: {
          action: 'shipsync_track_order',
          order_number: orderNumber,
          nonce: (typeof shipsyncFrontend !== 'undefined' ? shipsyncFrontend.nonce : ocm_frontend.nonce)
        },
        success: function (response) {
          if (response.success) {
            displayTrackResult(response.data.order, response.data.history);
            $result.addClass('show track-success').show();
          } else {
            showTrackMessage(response.data.message, 'error');
          }
        },
        error: function () {
          showTrackMessage('An error occurred while tracking the order.', 'error');
        },
        complete: function () {
          $submitBtn.prop('disabled', false).text('Track Order');
        }
      });
    });
  }

  function displayTrackResult(order, history) {
    const $result = $('.ocm-track-result');
    let html = '<h4>Order Details</h4>';
    html += '<div class="ocm-track-order-info">';
    html += '<p><strong>Order Number:</strong> ' + order.order_number + '</p>';
    html += '<p><strong>Customer:</strong> ' + order.customer_name + '</p>';
    html += '<p><strong>Status:</strong> <span class="ocm-status-badge status-' + order.order_status + '">' +
      order.order_status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</span></p>';
    html += '<p><strong>Total:</strong> $' + parseFloat(order.total_amount).toFixed(2) + '</p>';
    if (order.courier_name) {
      html += '<p><strong>Courier:</strong> ' + order.courier_name + '</p>';
    }
    html += '</div>';

    if (history && history.length > 0) {
      html += '<h4>Status History</h4>';
      html += '<div class="ocm-status-history">';
      history.forEach(function (entry) {
        html += '<div class="ocm-status-entry">';
        html += '<span class="ocm-status-date">' + formatDate(entry.created_at) + '</span>';
        html += '<span class="ocm-status-name">' + entry.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</span>';
        if (entry.notes) {
          html += '<span class="ocm-status-notes">' + entry.notes + '</span>';
        }
        html += '</div>';
      });
      html += '</div>';
    }

    $result.html(html);
  }

  function showTrackMessage(message, type) {
    const $result = $('.ocm-track-result');
    $result.removeClass('track-success track-error')
      .addClass('track-' + type)
      .html('<p>' + message + '</p>')
      .addClass('show')
      .show();
  }

  function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
  }

  // Initialize order tracking if element exists
  if ($('.ocm-track-form').length > 0) {
    initOrderTracking();
  }

  // Order card widget animations
  function initOrderCardAnimations() {
    $('.ocm-order-item').each(function (index) {
      $(this).css('animation-delay', (index * 0.1) + 's');
    });
  }

  // Initialize animations
  initOrderCardAnimations();

  // Refresh order cards every 60 seconds
  if ($('.ocm-order-card-widget').length > 0) {
    setInterval(function () {
      refreshOrderCards();
    }, 60000);
  }

  function refreshOrderCards() {
    $('.ocm-order-card-widget').each(function () {
      const $widget = $(this);
      const $ordersList = $widget.find('.ocm-orders-list');

      // Add loading state
      $ordersList.addClass('ocm-loading');

      // Simulate refresh (in a real implementation, you'd make an AJAX call)
      setTimeout(function () {
        $ordersList.removeClass('ocm-loading');
        // Re-initialize animations
        initOrderCardAnimations();
      }, 1000);
    });
  }

  // Order status color coding
  function updateStatusColors() {
    $('.ocm-status-badge').each(function () {
      const $badge = $(this);
      const status = $badge.text().toLowerCase().replace(/\s+/g, '_');
      $badge.removeClass().addClass('ocm-status-badge status-' + status);
    });
  }

  // Initialize status colors
  updateStatusColors();

  // Smooth scrolling for anchor links
  $('a[href^="#"]').on('click', function (e) {
    e.preventDefault();
    const target = $(this.getAttribute('href'));
    if (target.length) {
      $('html, body').animate({
        scrollTop: target.offset().top - 100
      }, 500);
    }
  });

  // Mobile menu toggle (if needed)
  $('.ocm-mobile-toggle').on('click', function () {
    $(this).toggleClass('active');
    $('.ocm-mobile-menu').slideToggle(300);
  });

  // Form validation
  $('.ocm-form').on('submit', function (e) {
    const $form = $(this);
    let isValid = true;

    // Check required fields
    $form.find('input[required], select[required], textarea[required]').each(function () {
      if (!$(this).val().trim()) {
        $(this).addClass('error');
        isValid = false;
      } else {
        $(this).removeClass('error');
      }
    });

    // Check email format
    $form.find('input[type="email"]').each(function () {
      const email = $(this).val();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (email && !emailRegex.test(email)) {
        $(this).addClass('error');
        isValid = false;
      }
    });

    if (!isValid) {
      e.preventDefault();
      showMessage('Please fill in all required fields correctly.', 'error');
    }
  });

  // Remove error class on input
  $(document).on('input', '.ocm-form input, .ocm-form select, .ocm-form textarea', function () {
    $(this).removeClass('error');
  });

  // Show message function
  function showMessage(message, type) {
    const messageClass = 'ocm-message ' + type;
    const $message = $('<div class="' + messageClass + '">' + message + '</div>');

    $('body').prepend($message);

    setTimeout(function () {
      $message.fadeOut(300, function () {
        $(this).remove();
      });
    }, 5000);
  }

  // Lazy loading for order items
  function initLazyLoading() {
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            const $item = $(entry.target);
            $item.addClass('loaded');
            observer.unobserve(entry.target);
          }
        });
      });

      $('.ocm-order-item').each(function () {
        observer.observe(this);
      });
    }
  }

  // Initialize lazy loading
  initLazyLoading();

  // Keyboard navigation
  $(document).on('keydown', function (e) {
    // Enter key on order items
    if (e.keyCode === 13 && $(e.target).hasClass('ocm-order-item')) {
      $(e.target).click();
    }
  });

  // Accessibility improvements
  $('.ocm-order-item').attr('tabindex', '0').on('keydown', function (e) {
    if (e.keyCode === 13 || e.keyCode === 32) {
      e.preventDefault();
      $(this).click();
    }
  });

  // Print functionality
  $('.ocm-print-btn').on('click', function () {
    window.print();
  });

  // Share functionality
  $('.ocm-share-btn').on('click', function () {
    if (navigator.share) {
      navigator.share({
        title: 'Order Status',
        text: 'Check out my order status',
        url: window.location.href
      });
    } else {
      // Fallback to copying URL to clipboard
      navigator.clipboard.writeText(window.location.href).then(function () {
        showMessage('Link copied to clipboard!', 'success');
      });
    }
  });

  // Dark mode toggle
  $('.ocm-dark-mode-toggle').on('click', function () {
    $('body').toggleClass('dark-mode');
    localStorage.setItem('ocm-dark-mode', $('body').hasClass('dark-mode'));
  });

  // Load dark mode preference
  if (localStorage.getItem('ocm-dark-mode') === 'true') {
    $('body').addClass('dark-mode');
  }

  // Responsive table handling
  function handleResponsiveTables() {
    $('.ocm-responsive-table').each(function () {
      const $table = $(this);
      const $wrapper = $table.wrap('<div class="ocm-table-wrapper"></div>').parent();

      if ($table.width() > $wrapper.width()) {
        $wrapper.addClass('scrollable');
      }
    });
  }

  // Initialize responsive tables
  handleResponsiveTables();

  // Window resize handler
  $(window).on('resize', function () {
    handleResponsiveTables();
  });

  // Search functionality
  $('.ocm-search-input').on('input', function () {
    const searchTerm = $(this).val().toLowerCase();
    $('.ocm-order-item').each(function () {
      const $item = $(this);
      const text = $item.text().toLowerCase();
      if (text.includes(searchTerm)) {
        $item.show();
      } else {
        $item.hide();
      }
    });
  });

  // Filter functionality
  $('.ocm-filter-btn').on('click', function () {
    const filter = $(this).data('filter');
    $('.ocm-filter-btn').removeClass('active');
    $(this).addClass('active');

    if (filter === 'all') {
      $('.ocm-order-item').show();
    } else {
      $('.ocm-order-item').hide();
      $('.ocm-order-item[data-status="' + filter + '"]').show();
    }
  });

  // Sort functionality
  $('.ocm-sort-btn').on('click', function () {
    const sortBy = $(this).data('sort');
    const $container = $('.ocm-orders-list');
    const $items = $container.find('.ocm-order-item').toArray();

    $items.sort(function (a, b) {
      let aVal, bVal;

      switch (sortBy) {
        case 'date':
          aVal = new Date($(a).find('.ocm-order-date').text());
          bVal = new Date($(b).find('.ocm-order-date').text());
          break;
        case 'amount':
          aVal = parseFloat($(a).find('.ocm-order-total').text().replace('$', ''));
          bVal = parseFloat($(b).find('.ocm-order-total').text().replace('$', ''));
          break;
        case 'status':
          aVal = $(a).find('.ocm-status-badge').text();
          bVal = $(b).find('.ocm-status-badge').text();
          break;
        default:
          aVal = $(a).find('.ocm-order-number').text();
          bVal = $(b).find('.ocm-order-number').text();
      }

      if (aVal < bVal) return -1;
      if (aVal > bVal) return 1;
      return 0;
    });

    $container.empty().append($items);
    initOrderCardAnimations();
  });

});
