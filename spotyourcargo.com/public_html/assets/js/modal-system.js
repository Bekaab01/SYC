// Dashboard Modal System
// Handles AJAX loading and management of dashboard modals with improved state management

class DashboardModalSystem {
    constructor() {
        this.modalStack = []; // Stack to manage modal layers
        this.modalCache = new Map();
        this.closeTimeoutId = null;
        this.isLoading = false;
        this.init();
    }

    init() {
        // Bind modal triggers from dashboard KPI cards
        this.bindKPICardTriggers();

        // Handle modal close events
        this.bindModalCloseEvents();

        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modalStack.length > 0) {
                this.closeTopModal();
            }
        });
    }

    bindKPICardTriggers() {
        document.addEventListener('click', (e) => {
            const kpiCard = e.target.closest('.kpi-card');
            if (kpiCard && !e.target.closest('.quick-topup-btn')) {
                const dataModule = kpiCard.dataset.module;
                if (dataModule) {
                    this.loadModal(dataModule);
                }
            }
        });
    }

    bindModalCloseEvents() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.modal-close')) {
                this.closeTopModal();
            }
        });
    }

    async loadModal(modalType) {
        if (this.isLoading) return;

        try {
            this.isLoading = true;
            this.showLoadingModal();

            // Check cache first
            if (this.modalCache.has(modalType)) {
                this.showMainModal(this.modalCache.get(modalType));
                return;
            }

            // Determine modal file path
            const modalPaths = {
                'active_shipments': 'modals/active_list.php',
                'pending_bids': 'modals/bidding_center.php',
                'wallet_balance': 'modals/wallet_management.php',
                'critical_alerts': 'modals/alerts_center.php',
                'post_load': 'modals/post_load.php'
            };

            const modalPath = modalPaths[modalType];
            if (!modalPath) {
                throw new Error(`Unknown modal type: ${modalType}`);
            }

            // Load modal via AJAX
            const response = await fetch(modalPath, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const modalHTML = await response.text();

            // Cache the modal
            this.modalCache.set(modalType, modalHTML);

            // Show the modal
            this.showMainModal(modalHTML);

        } catch (error) {
            console.error('Error loading modal:', error);
            this.showErrorModal('Failed to load modal content. Please try again.');
        } finally {
            this.isLoading = false;
        }
    }

    showLoadingModal() {
        const loadingModal = `
            <div class="modal active" id="loading-modal">
                <div class="modal-content loading-modal">
                    <div class="loading-content">
                        <div class="loading-spinner"></div>
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        `;

        // Remove any existing loading modal
        const existingLoading = document.getElementById('loading-modal');
        if (existingLoading) {
            existingLoading.remove();
        }

        document.body.insertAdjacentHTML('beforeend', loadingModal);
        document.body.style.overflow = 'hidden';
    }

    showMainModal(modalHTML) {
        // Remove loading modal
        const loadingModal = document.getElementById('loading-modal');
        if (loadingModal) {
            loadingModal.remove();
        }

        // Close any existing popup modals before showing main modal
        this.closeAllPopupModals();

        // Insert new modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Find the modal element
        const modals = document.querySelectorAll('.modal');
        const newModal = modals[modals.length - 1];

        // Add to stack
        this.modalStack.push(newModal);

        // Prevent body scroll
        document.body.style.overflow = 'hidden';

        // Trigger modal open animation
        setTimeout(() => {
            if (newModal) {
                newModal.classList.add('active');
            }
        }, 10);

        // Initialize modal-specific functionality
        this.initializeModalFunctionality(newModal);
    }

    showErrorModal(message) {
        this.showPopupModal('error', message, 'Error', 'fas fa-exclamation-triangle');
    }

    showSuccessModal(message) {
        this.showPopupModal('success', message, 'Success', 'fas fa-check-circle');
    }

    showConfirmationModal(message, onConfirm, onCancel = null) {
        const confirmationModal = `
            <div class="modal active" id="confirmation-modal">
                <div class="modal-content confirmation-modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-question-circle"></i> Confirm Action</h3>
                        <button class="modal-close" onclick="dashboardModalSystem.closeTopModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="confirmation-content">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>${message}</p>
                            <div class="confirmation-actions">
                                <button class="btn btn-secondary" onclick="dashboardModalSystem.closeTopModal(); ${onCancel ? onCancel : ''}">Cancel</button>
                                <button class="btn btn-danger" onclick="dashboardModalSystem.closeTopModal(); ${onConfirm}">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showPopupModalHTML(confirmationModal);
    }

    showPopupModal(type, message, title, iconClass) {
        const modalId = `${type}-modal`;
        const modalHTML = `
            <div class="modal active" id="${modalId}">
                <div class="modal-content ${type}-modal">
                    <div class="modal-header">
                        <h3><i class="${iconClass}"></i> ${title}</h3>
                        <button class="modal-close" onclick="dashboardModalSystem.closeTopModal()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="${type}-content">
                            <i class="${iconClass}"></i>
                            <p>${message}</p>
                            <button class="btn btn-primary" onclick="dashboardModalSystem.closeTopModal()">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        this.showPopupModalHTML(modalHTML);
    }

    showPopupModalHTML(modalHTML) {
        // Remove loading modal
        const loadingModal = document.getElementById('loading-modal');
        if (loadingModal) {
            loadingModal.remove();
        }

        // Insert popup modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Find the modal element
        const modals = document.querySelectorAll('.modal');
        const newModal = modals[modals.length - 1];

        // Add to stack
        this.modalStack.push(newModal);

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    closeAllPopupModals() {
        // Close all popup modals (error, success, confirmation) but keep main modals
        const popupModalIds = ['error-modal', 'success-modal', 'confirmation-modal', 'loading-modal'];
        this.modalStack = this.modalStack.filter(modal => {
            if (popupModalIds.includes(modal.id)) {
                this.removeModal(modal);
                return false;
            }
            return true;
        });
    }

    closeTopModal() {
        if (this.modalStack.length === 0) return;

        const modalToClose = this.modalStack.pop();
        this.removeModal(modalToClose);

        // Restore body scroll if no modals remain
        if (this.modalStack.length === 0) {
            document.body.style.overflow = '';
        }
    }

    removeModal(modal) {
        if (!modal) return;

        modal.classList.remove('active');

        // Clear any existing close timeout
        if (this.closeTimeoutId) {
            clearTimeout(this.closeTimeoutId);
        }

        // Remove modal after animation
        this.closeTimeoutId = setTimeout(() => {
            if (modal && modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
            this.closeTimeoutId = null;
        }, 300);
    }

    initializeModalFunctionality(modal) {
        // Initialize modal-specific JavaScript
        if (modal) {
            // Execute any inline scripts in the modal
            const scripts = modal.querySelectorAll('script');
            scripts.forEach(script => {
                if (script.textContent) {
                    try {
                        eval(script.textContent);
                    } catch (error) {
                        console.error('Error executing modal script:', error);
                    }
                }
            });
        }
    }

    // Public methods for external use
    openModal(modalType) {
        this.loadModal(modalType);
    }

    refreshModal(modalType) {
        // Clear cache and reload
        this.modalCache.delete(modalType);
        this.loadModal(modalType);
    }

    // Legacy support - close modal by ID or top modal
    closeModal(modalId = null) {
        if (modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                this.removeModal(modal);
                // Remove from stack if present
                this.modalStack = this.modalStack.filter(m => m !== modal);
                if (this.modalStack.length === 0) {
                    document.body.style.overflow = '';
                }
            }
        } else {
            this.closeTopModal();
        }
    }
}

// Global modal system instance
const dashboardModalSystem = new DashboardModalSystem();

// Make it globally available
window.dashboardModalSystem = dashboardModalSystem;

// Legacy support functions
function openModal(modalType) {
    dashboardModalSystem.openModal(modalType);
}

function closeModal(modalId) {
    dashboardModalSystem.closeModal(modalId);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard Modal System initialized');
});
