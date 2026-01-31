/**
 * WashMate Main JavaScript File
 */

// Global variables
const WashMate = {
    apiBase: window.location.origin,
    currency: '৳',
    currentUser: null,
    
    // Initialize
    init: function() {
        this.setupEventListeners();
        this.checkNotifications();
        this.setupAutoRefresh();
        this.setupPrintButton();
    },
    
    // Setup event listeners
    setupEventListeners: function() {
        // Mobile menu toggle
        const menuToggle = document.querySelector('.menu-toggle');
        if (menuToggle) {
            menuToggle.addEventListener('click', this.toggleMobileMenu);
        }
        
        // Form validation
        this.setupFormValidation();
        
        // Copy OTP buttons
        document.querySelectorAll('.copy-otp').forEach(btn => {
            btn.addEventListener('click', this.copyOTP);
        });
        
        // Auto-submit forms with enter key
        document.querySelectorAll('input[type="text"], input[type="password"]').forEach(input => {
            input.addEventListener('keypress', e => {
                if (e.key === 'Enter' && e.target.form) {
                    const submitBtn = e.target.form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.click();
                    }
                }
            });
        });
    },
    
    // Toggle mobile menu
    toggleMobileMenu: function() {
        const nav = document.querySelector('.dashboard-nav');
        if (nav) {
            nav.classList.toggle('active');
        }
    },
    
    // Setup form validation
    setupFormValidation: function() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', e => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
        
        // Mobile number validation
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value && !/^01[3-9]\d{8}$/.test(value)) {
                    this.classList.add('error');
                    this.nextElementSibling?.classList.add('show');
                } else {
                    this.classList.remove('error');
                    this.nextElementSibling?.classList.remove('show');
                }
            });
        });
        
        // PIN validation
        document.querySelectorAll('input[data-pin]').forEach(input => {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
            });
        });
    },
    
    // Validate form
    validateForm: function(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], select[required]');
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.showError(input, 'This field is required');
                isValid = false;
            } else {
                this.hideError(input);
                
                // Additional validation
                if (input.type === 'tel' && !/^01[3-9]\d{8}$/.test(input.value)) {
                    this.showError(input, 'Please enter a valid Bangladeshi mobile number');
                    isValid = false;
                }
                
                if (input.hasAttribute('data-pin') && !/^\d{4}$/.test(input.value)) {
                    this.showError(input, 'PIN must be 4 digits');
                    isValid = false;
                }
                
                if (input.type === 'email' && !this.isValidEmail(input.value)) {
                    this.showError(input, 'Please enter a valid email');
                    isValid = false;
                }
            }
        });
        
        return isValid;
    },
    
    // Show error message
    showError: function(input, message) {
        input.classList.add('error');
        
        let errorElement = input.nextElementSibling;
        if (!errorElement || !errorElement.classList.contains('error-message')) {
            errorElement = document.createElement('div');
            errorElement.className = 'error-message';
            input.parentNode.insertBefore(errorElement, input.nextSibling);
        }
        
        errorElement.textContent = message;
        errorElement.classList.add('show');
    },
    
    // Hide error message
    hideError: function(input) {
        input.classList.remove('error');
        const errorElement = input.nextElementSibling;
        if (errorElement && errorElement.classList.contains('error-message')) {
            errorElement.classList.remove('show');
        }
    },
    
    // Validate email
    isValidEmail: function(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    },
    
    // Copy OTP to clipboard
    copyOTP: function(e) {
        const otpElement = e.target.closest('.otp-display').querySelector('.otp-code');
        const otp = otpElement.textContent.trim();
        
        navigator.clipboard.writeText(otp).then(() => {
            const originalText = e.target.textContent;
            e.target.innerHTML = '<i class="fas fa-check"></i> Copied!';
            e.target.classList.add('success');
            
            setTimeout(() => {
                e.target.innerHTML = originalText;
                e.target.classList.remove('success');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy OTP:', err);
            alert('Failed to copy OTP. Please copy manually.');
        });
    },
    
    // Check for notifications
    checkNotifications: function() {
        if ('Notification' in window && Notification.permission === 'granted') {
            // Check for new notifications
            setInterval(() => {
                this.fetchNotifications();
            }, 30000); // Check every 30 seconds
        }
    },
    
    // Fetch notifications from server
    fetchNotifications: function() {
        fetch('api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    data.notifications.forEach(notification => {
                        this.showNotification(notification);
                    });
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    },
    
    // Show browser notification
    showNotification: function(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(notification.title, {
                body: notification.message,
                icon: '/assets/images/logo.png'
            });
        }
    },
    
    // Setup auto-refresh for booking pages
    setupAutoRefresh: function() {
        const bookingPages = ['booking.php', 'dashboard.php'];
        const currentPage = window.location.pathname.split('/').pop();
        
        if (bookingPages.includes(currentPage)) {
            // Refresh every 30 seconds to update booking status
            setInterval(() => {
                this.refreshBookingStatus();
            }, 30000);
        }
    },
    
    // Refresh booking status without full page reload
    refreshBookingStatus: function() {
        fetch('api/booking_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update booking cards
                    data.bookings.forEach(booking => {
                        this.updateBookingCard(booking);
                    });
                    
                    // Update balance if changed
                    if (data.balance !== undefined) {
                        this.updateBalanceDisplay(data.balance);
                    }
                }
            })
            .catch(error => console.error('Error refreshing booking status:', error));
    },
    
    // Update booking card
    updateBookingCard: function(booking) {
        const card = document.querySelector(`.booking-card[data-booking-id="${booking.id}"]`);
        if (card) {
            const statusBadge = card.querySelector('.booking-status');
            const timeRemaining = card.querySelector('.time-remaining');
            
            if (statusBadge) {
                statusBadge.className = `status-badge ${booking.status}`;
                statusBadge.innerHTML = `<i class="fas fa-${this.getStatusIcon(booking.status)}"></i> ${this.capitalize(booking.status)}`;
            }
            
            if (timeRemaining && booking.time_remaining) {
                timeRemaining.textContent = booking.time_remaining;
            }
        }
    },
    
    // Update balance display
    updateBalanceDisplay: function(balance) {
        const balanceElements = document.querySelectorAll('.balance-info span, .balance-amount');
        balanceElements.forEach(element => {
            if (element.classList.contains('balance-amount')) {
                element.textContent = `${this.currency}${parseFloat(balance).toFixed(2)}`;
            } else {
                element.textContent = `${this.currency}${parseFloat(balance).toFixed(2)}`;
            }
        });
    },
    
    // Get status icon
    getStatusIcon: function(status) {
        const icons = {
            'active': 'check-circle',
            'used': 'check-double',
            'expired': 'times-circle',
            'pending': 'clock'
        };
        return icons[status] || 'circle';
    },
    
    // Capitalize first letter
    capitalize: function(string) {
        return string.charAt(0).toUpperCase() + string.slice(1);
    },
    
    // Setup print button
    setupPrintButton: function() {
        const printBtn = document.querySelector('.print-btn');
        if (printBtn) {
            printBtn.addEventListener('click', () => {
                window.print();
            });
        }
    },
    
    // AJAX helper function
    ajax: function(url, method = 'GET', data = null) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject({
                        status: xhr.status,
                        statusText: xhr.statusText
                    });
                }
            };
            
            xhr.onerror = function() {
                reject({
                    status: xhr.status,
                    statusText: xhr.statusText
                });
            };
            
            if (data && method === 'POST') {
                const params = new URLSearchParams(data);
                xhr.send(params.toString());
            } else {
                xhr.send();
            }
        });
    },
    
    // Show loading spinner
    showLoading: function(element) {
        const spinner = document.createElement('div');
        spinner.className = 'loading-spinner';
        spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        element.appendChild(spinner);
        return spinner;
    },
    
    // Hide loading spinner
    hideLoading: function(spinner) {
        if (spinner && spinner.parentNode) {
            spinner.parentNode.removeChild(spinner);
        }
    },
    
    // Show toast notification
    showToast: function(message, type = 'success', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
        }, 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, duration);
    },
    
    // Format currency
    formatCurrency: function(amount) {
        return `${this.currency}${parseFloat(amount).toFixed(2)}`;
    },
    
    // Format date
    formatDate: function(dateString, format = 'relative') {
        const date = new Date(dateString);
        
        if (format === 'relative') {
            const now = new Date();
            const diff = now - date;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            
            if (minutes < 1) return 'Just now';
            if (minutes < 60) return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
            if (hours < 24) return `${hours} hour${hours === 1 ? '' : 's'} ago`;
            if (days < 7) return `${days} day${days === 1 ? '' : 's'} ago`;
        }
        
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },
    
    // Confirm dialog
    confirm: function(message, title = 'Confirm') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'confirm-modal';
            modal.innerHTML = `
                <div class="confirm-content">
                    <h3>${title}</h3>
                    <p>${message}</p>
                    <div class="confirm-buttons">
                        <button class="btn btn-secondary cancel-btn">Cancel</button>
                        <button class="btn btn-primary confirm-btn">Confirm</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.querySelector('.cancel-btn').addEventListener('click', () => {
                document.body.removeChild(modal);
                resolve(false);
            });
            
            modal.querySelector('.confirm-btn').addEventListener('click', () => {
                document.body.removeChild(modal);
                resolve(true);
            });
        });
    },
    
    // Countdown timer
    startCountdown: function(elementId, endTime) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const end = new Date(endTime).getTime();
        
        const timer = setInterval(() => {
            const now = new Date().getTime();
            const distance = end - now;
            
            if (distance < 0) {
                clearInterval(timer);
                element.textContent = 'Expired';
                element.classList.add('expired');
                return;
            }
            
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            element.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }, 1000);
        
        return timer;
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    WashMate.init();
    
    // Add CSS for dynamic elements
    const style = document.createElement('style');
    style.textContent = `
        .error {
            border-color: #ef4444 !important;
        }
        
        .error-message {
            color: #ef4444;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-success {
            border-left: 4px solid #10b981;
        }
        
        .toast-error {
            border-left: 4px solid #ef4444;
        }
        
        .toast-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .confirm-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 400px;
            width: 90%;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            justify-content: flex-end;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .print-only {
                display: block !important;
            }
        }
    `;
    document.head.appendChild(style);
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + S to save (where applicable)
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const saveBtn = document.querySelector('button[type="submit"]');
        if (saveBtn) saveBtn.click();
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modal = document.querySelector('.confirm-modal');
        if (modal) modal.remove();
    }
});