/**
 * RFID Attendance System - Bootstrap Initialization
 * This file initializes Bootstrap components and provides error handling
 */

document.addEventListener('DOMContentLoaded', function() {
    // Make sure Bootstrap is properly loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded!');
        displayBootstrapError();
        return;
    }
    
    console.log('Bootstrap is loaded successfully');

    // Initialize Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize Popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    const popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Initialize Toasts
    const toastElList = [].slice.call(document.querySelectorAll('.toast'));
    const toastList = toastElList.map(function (toastEl) {
        return new bootstrap.Toast(toastEl);
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Initialize any collapse components
    const collapseElementList = [].slice.call(document.querySelectorAll('.collapse'));
    const collapseList = collapseElementList.map(function (collapseEl) {
        return new bootstrap.Collapse(collapseEl, {
            toggle: false
        });
    });
});

/**
 * Display an error message if Bootstrap fails to load
 */
function displayBootstrapError() {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; background: #ff5252; color: white; padding: 1rem; text-align: center; z-index: 9999;';
    errorDiv.innerHTML = `
        <strong>Error:</strong> Bootstrap failed to load. Some features may not work correctly. 
        Please check your internet connection and refresh the page.
    `;
    document.body.insertBefore(errorDiv, document.body.firstChild);
}
