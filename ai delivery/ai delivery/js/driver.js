// Driver Dashboard JavaScript

let driverPackages = [];
let notifications = [];

// Initialize driver dashboard
document.addEventListener('DOMContentLoaded', async function() {
    // Check authentication
    try {
        const result = await apiCall('auth.php?action=check', 'GET');
        if (!result.success || result.data.user.role !== 'driver') {
            window.location.href = 'driver-login.html';
            return;
        }
        
        // Set driver name
        const driverName = result.data.user.full_name || 'Driver';
        document.getElementById('driverName').textContent = driverName;
    } catch (error) {
        window.location.href = 'driver-login.html';
        return;
    }

    // Load dashboard data
    await loadPackages();
    await loadStats();
    await loadNotifications();

    // Notification button handler
    const notificationBtn = document.getElementById('notificationBtn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('active');
        });
    }

    // Close notification dropdown on outside click
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown && !dropdown.contains(e.target) && !notificationBtn.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Refresh button
    const refreshBtn = document.getElementById('refreshPackages');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async function() {
            await loadPackages();
            await loadStats();
            await loadNotifications();
        });
    }

    // Package modal handlers
    setupPackageModal();

    // Auto-refresh notifications every 30 seconds
    setInterval(async () => {
        await loadNotifications();
    }, 30000);
});

async function loadPackages() {
    try {
        const result = await apiCall('packages.php?action=list', 'GET');
        driverPackages = result.data || [];
        
        const container = document.getElementById('packagesContainer');
        if (!container) return;

        if (driverPackages.length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-box-open"></i>
                    <h3>No Assigned Packages</h3>
                    <p>You don't have any packages assigned at the moment.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = driverPackages.map(pkg => `
            <div class="package-card" onclick="openPackageModal('${pkg.package_id}')">
                <div class="package-card-header">
                    <span class="package-id">${pkg.package_id}</span>
                    <span class="status-badge status-${pkg.status.replace('-', '-')}">${pkg.status.replace('-', ' ')}</span>
                </div>
                <div class="package-details-list">
                    <div class="detail-row">
                        <span class="detail-label">Destination:</span>
                        <span class="detail-value">${pkg.destination}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Recipient:</span>
                        <span class="detail-value">${pkg.recipient_name}</span>
                    </div>
                </div>
                <div class="package-actions" onclick="event.stopPropagation()">
                    <button class="btn btn-primary" onclick="openPackageModal('${pkg.package_id}')">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading packages:', error);
        const container = document.getElementById('packagesContainer');
        if (container) {
            container.innerHTML = `
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Error Loading Packages</h3>
                    <p>${error.message}</p>
                </div>
            `;
        }
    }
}

async function loadStats() {
    try {
        const assignedCount = driverPackages.length;
        const deliveredCount = driverPackages.filter(p => p.status === 'delivered').length;
        const transitCount = driverPackages.filter(p => p.status === 'in-transit').length;

        document.getElementById('assignedCount').textContent = assignedCount;
        document.getElementById('deliveredCount').textContent = deliveredCount;
        document.getElementById('transitCount').textContent = transitCount;
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadNotifications() {
    try {
        const result = await apiCall('notifications.php?action=list&limit=20', 'GET');
        notifications = result.data || [];
        
        const notificationList = document.getElementById('notificationList');
        const notificationBadge = document.getElementById('notificationBadge');
        
        if (!notificationList) return;

        const unreadCount = notifications.filter(n => !n.is_read).length;
        if (notificationBadge) {
            notificationBadge.textContent = unreadCount;
            notificationBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }

        if (notifications.length === 0) {
            notificationList.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No notifications</p>';
            return;
        }

        notificationList.innerHTML = notifications.map(notif => `
            <div class="notification-item ${notif.is_read ? '' : 'unread'}" onclick="markNotificationRead(${notif.id})">
                <strong>${notif.message}</strong>
                <p class="notification-time">${formatTime(notif.created_at)}</p>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

async function openPackageModal(packageId) {
    try {
        const result = await apiCall(`packages.php?action=get&id=${packageId}`, 'GET');
        const pkg = result.data;
        
        if (!pkg) {
            alert('Package not found');
            return;
        }

        document.getElementById('modalPackageId').textContent = pkg.package_id;
        document.getElementById('modalDestination').textContent = pkg.destination;
        document.getElementById('modalRecipient').textContent = pkg.recipient_name;
        
        const statusBadge = document.getElementById('modalStatus');
        statusBadge.textContent = pkg.status.replace('-', ' ');
        statusBadge.className = 'status-badge status-' + pkg.status.replace('-', '-');

        const statusUpdate = document.getElementById('statusUpdate');
        statusUpdate.value = pkg.status;

        document.getElementById('packageModal').classList.add('active');
        document.getElementById('packageModal').setAttribute('data-package-id', pkg.package_id);
    } catch (error) {
        alert('Failed to load package details: ' + error.message);
    }
}

function setupPackageModal() {
    const updateBtn = document.getElementById('updateStatusBtn');
    if (updateBtn) {
        updateBtn.addEventListener('click', async function() {
            const modal = document.getElementById('packageModal');
            const packageId = modal.getAttribute('data-package-id');
            const newStatus = document.getElementById('statusUpdate').value;

            if (packageId && newStatus) {
                try {
                    const result = await apiCall(`packages.php?action=update&id=${packageId}`, 'PUT', {
                        status: newStatus
                    });
                    
                    if (result.success) {
                        await loadPackages();
                        await loadStats();
                        
                        modal.classList.remove('active');
                        showNotification('Package status updated successfully!');
                    }
                } catch (error) {
                    alert('Failed to update package status: ' + error.message);
                }
            }
        });
    }
}

async function markNotificationRead(notificationId) {
    try {
        await apiCall('notifications.php?action=read', 'PUT', {
            id: notificationId
        });
        await loadNotifications();
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

function showNotification(message) {
    // Create a temporary notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #22c55e;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Make functions available globally
window.openPackageModal = openPackageModal;
window.markNotificationRead = markNotificationRead;
