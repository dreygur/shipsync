/**
 * Modern Admin JavaScript for ShipSync
 * ES6+ with modular architecture
 *
 * @package ShipSync
 * @since 3.0.0
 */

class ShipSyncAdmin {
  constructor() {
    this.config = window.shipsyncConfig || {};
    this.modals = new Map();
    this.init();
  }

  /**
   * Initialize the application
   */
  init() {
    this.setupEventListeners();
    this.initModals();
    this.initTooltips();
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    // Delegate modal triggers
    document.addEventListener('click', (e) => {
      if (e.target.matches('[data-shipsync-modal]')) {
        e.preventDefault();
        const modalId = e.target.dataset.shipsyncModal;
        this.openModal(modalId, e.target.dataset);
      }

      if (e.target.matches('.shipsync-modal-close') || e.target.matches('.shipsync-modal-backdrop')) {
        e.preventDefault();
        this.closeModal(e.target.closest('.shipsync-modal')?.id);
      }
    });

    // Form submissions
    document.addEventListener('submit', (e) => {
      if (e.target.matches('[data-shipsync-form]')) {
        e.preventDefault();
        this.handleFormSubmit(e.target);
      }
    });

    // ESC key to close modals
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeAllModals();
      }
    });
  }

  /**
   * Initialize modals
   */
  initModals() {
    document.querySelectorAll('.shipsync-modal').forEach(modal => {
      this.modals.set(modal.id, modal);
    });
  }

  /**
   * Open modal
   */
  openModal(modalId, data = {}) {
    const modal = this.modals.get(modalId);

    if (!modal) {
      console.error(`Modal ${modalId} not found`);
      return;
    }

    // Populate modal data
    this.populateModalData(modal, data);

    // Show modal with animation
    modal.style.display = 'flex';
    requestAnimationFrame(() => {
      modal.classList.add('active');
      document.body.classList.add('modal-open');
    });

    // Fire event
    this.dispatchEvent('modal:opened', { modalId, data });
  }

  /**
   * Close modal
   */
  closeModal(modalId) {
    const modal = this.modals.get(modalId);

    if (!modal) return;

    modal.classList.remove('active');

    setTimeout(() => {
      modal.style.display = 'none';
      document.body.classList.remove('modal-open');
    }, 300);

    // Fire event
    this.dispatchEvent('modal:closed', { modalId });
  }

  /**
   * Close all modals
   */
  closeAllModals() {
    this.modals.forEach((modal, id) => this.closeModal(id));
  }

  /**
   * Populate modal with data
   */
  populateModalData(modal, data) {
    Object.entries(data).forEach(([key, value]) => {
      const input = modal.querySelector(`[name="${key}"]`);
      if (input) {
        input.value = value;
      }

      const display = modal.querySelector(`[data-field="${key}"]`);
      if (display) {
        display.textContent = value;
      }
    });
  }

  /**
   * Handle form submission
   */
  async handleFormSubmit(form) {
    const formData = new FormData(form);
    const action = form.dataset.shipsyncForm;
    const submitBtn = form.querySelector('[type="submit"]');

    // Add action and nonce
    formData.append('action', action);
    formData.append('nonce', this.config.nonce);

    // Disable submit button
    this.setButtonLoading(submitBtn, true);

    try {
      const response = await this.ajaxRequest(formData);

      if (response.success) {
        this.showNotification(response.data.message, 'success');

        // Close modal if form is in modal
        const modal = form.closest('.shipsync-modal');
        if (modal) {
          this.closeModal(modal.id);
        }

        // Reload if needed
        if (form.dataset.reload === 'true') {
          setTimeout(() => location.reload(), 1500);
        }

        // Fire event
        this.dispatchEvent('form:success', { form, response });
      } else {
        this.showNotification(response.data.message, 'error');
        this.dispatchEvent('form:error', { form, response });
      }
    } catch (error) {
      this.showNotification(error.message, 'error');
      console.error('Form submission error:', error);
    } finally {
      this.setButtonLoading(submitBtn, false);
    }
  }

  /**
   * Make AJAX request
   */
  async ajaxRequest(formData) {
    const response = await fetch(this.config.ajaxUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    return await response.json();
  }

  /**
   * Set button loading state
   */
  setButtonLoading(button, isLoading) {
    if (!button) return;

    if (isLoading) {
      button.disabled = true;
      button.dataset.originalText = button.textContent;
      button.innerHTML = `
        <span class="spinner"></span>
        ${this.config.strings?.loading || 'Loading...'}
      `;
    } else {
      button.disabled = false;
      button.textContent = button.dataset.originalText;
    }
  }

  /**
   * Show notification
   */
  showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `shipsync-notification shipsync-notification-${type}`;
    notification.textContent = message;
    notification.setAttribute('role', 'alert');

    document.body.appendChild(notification);

    // Trigger animation
    requestAnimationFrame(() => {
      notification.classList.add('active');
    });

    // Auto remove after 5 seconds
    setTimeout(() => {
      notification.classList.remove('active');
      setTimeout(() => notification.remove(), 300);
    }, 5000);
  }

  /**
   * Initialize tooltips
   */
  initTooltips() {
    document.querySelectorAll('[data-tooltip]').forEach(element => {
      element.addEventListener('mouseenter', (e) => {
        this.showTooltip(e.target, e.target.dataset.tooltip);
      });

      element.addEventListener('mouseleave', () => {
        this.hideTooltip();
      });
    });
  }

  /**
   * Show tooltip
   */
  showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'shipsync-tooltip';
    tooltip.textContent = text;
    tooltip.id = 'shipsync-active-tooltip';

    document.body.appendChild(tooltip);

    const rect = element.getBoundingClientRect();
    tooltip.style.top = `${rect.top - tooltip.offsetHeight - 10}px`;
    tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px`;

    requestAnimationFrame(() => tooltip.classList.add('active'));
  }

  /**
   * Hide tooltip
   */
  hideTooltip() {
    const tooltip = document.getElementById('shipsync-active-tooltip');
    if (tooltip) {
      tooltip.classList.remove('active');
      setTimeout(() => tooltip.remove(), 200);
    }
  }

  /**
   * Dispatch custom event
   */
  dispatchEvent(eventName, detail = {}) {
    const event = new CustomEvent(`shipsync:${eventName}`, {
      bubbles: true,
      detail,
    });
    document.dispatchEvent(event);
  }
}

// Courier Status Handler
class CourierStatusHandler {
  constructor(admin) {
    this.admin = admin;
    this.init();
  }

  init() {
    document.addEventListener('click', (e) => {
      if (e.target.matches('.shipsync-check-status')) {
        e.preventDefault();
        this.checkStatus(e.target);
      }

      if (e.target.matches('.shipsync-send-courier')) {
        e.preventDefault();
        this.sendToCourier(e.target);
      }
    });
  }

  async checkStatus(button) {
    const orderId = button.dataset.orderId;
    const courierId = button.dataset.courierId;

    this.admin.setButtonLoading(button, true);

    try {
      const formData = new FormData();
      formData.append('action', 'shipsync_check_courier_status');
      formData.append('order_id', orderId);
      formData.append('courier', courierId);
      formData.append('nonce', this.admin.config.nonce);

      const response = await this.admin.ajaxRequest(formData);

      if (response.success) {
        this.updateStatusDisplay(orderId, response.data.status);
        this.admin.showNotification('Status updated successfully', 'success');
      } else {
        this.admin.showNotification(response.data.message, 'error');
      }
    } catch (error) {
      this.admin.showNotification(error.message, 'error');
    } finally {
      this.admin.setButtonLoading(button, false);
    }
  }

  async sendToCourier(button) {
    const orderId = button.dataset.orderId;
    const courierId = document.getElementById('courier-select')?.value;

    if (!courierId) {
      this.admin.showNotification('Please select a courier', 'error');
      return;
    }

    this.admin.setButtonLoading(button, true);

    try {
      const formData = new FormData();
      formData.append('action', 'shipsync_send_to_courier');
      formData.append('order_id', orderId);
      formData.append('courier', courierId);
      formData.append('nonce', this.admin.config.nonce);

      const response = await this.admin.ajaxRequest(formData);

      if (response.success) {
        this.admin.showNotification(response.data.message, 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        this.admin.showNotification(response.data.message, 'error');
      }
    } catch (error) {
      this.admin.showNotification(error.message, 'error');
    } finally {
      this.admin.setButtonLoading(button, false);
    }
  }

  updateStatusDisplay(orderId, status) {
    const statusElement = document.querySelector(`[data-order-status="${orderId}"]`);
    if (statusElement) {
      statusElement.textContent = status;
      statusElement.className = `status-badge status-${status.toLowerCase().replace('_', '-')}`;
    }
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.shipSyncAdmin = new ShipSyncAdmin();
    new CourierStatusHandler(window.shipSyncAdmin);
  });
} else {
  window.shipSyncAdmin = new ShipSyncAdmin();
  new CourierStatusHandler(window.shipSyncAdmin);
}

// Export for use in other modules
export { ShipSyncAdmin, CourierStatusHandler };
