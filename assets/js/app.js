/**
 * Library Seat Reservation and Monitoring System
 * Main JavaScript Application Script
 */

// Toast Notification System
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '❌';
    if (type === 'warning') icon = '⚠️';

    toast.innerHTML = `
        <span style="font-size: 18px;">${icon}</span>
        <div style="flex: 1; font-size: 14px; line-height: 1.4;">${message}</div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

// Confirmation Dialog Modal
function confirmModal(message, confirmCallback) {
    let overlay = document.getElementById('system-confirm-modal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'system-confirm-modal';
        overlay.className = 'modal-overlay';
        overlay.innerHTML = `
            <div class="modal-container">
                <div class="modal-header">
                    <h3>Confirmation Required</h3>
                    <button type="button" class="modal-close" onclick="closeConfirmModal()">&times;</button>
                </div>
                <div class="modal-body" id="system-confirm-message" style="font-size: 15px; color: var(--gray-700);">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeConfirmModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" id="system-confirm-btn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    document.getElementById('system-confirm-message').textContent = message;
    const confirmBtn = document.getElementById('system-confirm-btn');
    
    // Replace with fresh clone to clear previous onclick listeners
    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    newBtn.addEventListener('click', () => {
        closeConfirmModal();
        if (typeof confirmCallback === 'function') {
            confirmCallback();
        }
    });

    overlay.classList.add('active');
}

function closeConfirmModal() {
    const overlay = document.getElementById('system-confirm-modal');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

// Generic Modal Opener / Closer
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
}

// Sidebar Mobile Toggle
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('.sidebar-toggle-btn');
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            if (sidebar) sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // Login page tab switching if present
    const studentTabBtn = document.getElementById('tab-student-btn');
    const adminTabBtn = document.getElementById('tab-admin-btn');
    const studentForm = document.getElementById('student-login-form');
    const adminForm = document.getElementById('admin-login-form');

    if (studentTabBtn && adminTabBtn && studentForm && adminForm) {
        studentTabBtn.addEventListener('click', () => {
            studentTabBtn.classList.add('active');
            adminTabBtn.classList.remove('active');
            studentForm.style.display = 'block';
            adminForm.style.display = 'none';
        });

        adminTabBtn.addEventListener('click', () => {
            adminTabBtn.classList.add('active');
            studentTabBtn.classList.remove('active');
            adminForm.style.display = 'block';
            studentForm.style.display = 'none';
        });
    }
});

// Periodic Automatic Seat Availability Refresher (every 5 seconds)
let availabilityInterval = null;

function fetchSeatAvailability(apiUrl) {
    fetch(apiUrl, {
        headers: { 'Accept': 'application/json' }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.json();
    })
    .then(data => {
        if (data.success && data.counts) {
            updateSeatCountElements(data.counts);
        }
    })
    .catch(err => {
        // Log quietly without disturbing user
        console.warn('Seat availability polling paused or failed:', err.message);
    });
}

function updateSeatCountElements(counts) {
    const totalEl = document.getElementById('stat-total');
    const availEl = document.getElementById('stat-available');
    const resEl = document.getElementById('stat-reserved');
    const occEl = document.getElementById('stat-occupied');

    if (totalEl && counts.total !== undefined) totalEl.textContent = counts.total;
    if (availEl && counts.available !== undefined) availEl.textContent = counts.available;
    if (resEl && counts.reserved !== undefined) resEl.textContent = counts.reserved;
    if (occEl && counts.occupied !== undefined) occEl.textContent = counts.occupied;

    // Also update any inline availability text if present
    const inlineAvail = document.getElementById('live-available-count');
    if (inlineAvail && counts.available !== undefined) {
        inlineAvail.textContent = counts.available;
    }
}

function startSeatPolling(apiUrl) {
    // Initial fetch
    fetchSeatAvailability(apiUrl);

    // Repeat every 5 seconds
    if (!availabilityInterval) {
        availabilityInterval = setInterval(() => {
            fetchSeatAvailability(apiUrl);
        }, 5000);
    }
}

