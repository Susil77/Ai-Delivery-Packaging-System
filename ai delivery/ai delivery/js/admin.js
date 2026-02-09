// Admin Dashboard JavaScript

let packages = [];
let drivers = [];
let notifications = [];

// Initialize dashboard
document.addEventListener('DOMContentLoaded', async function() {
    // Check authentication
    try {
        const result = await apiCall('auth.php?action=check', 'GET');
        if (!result.success || result.data.user.role !== 'admin') {
            window.location.href = 'admin-login.html';
            return;
        }
    } catch (error) {
        window.location.href = 'admin-login.html';
        return;
    }

    // Navigation
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.getAttribute('data-section');
            switchSection(section);
            
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Load initial data
    await loadOverview();
    await loadPackages();
    await loadDrivers();
    await loadAssignments();
    await loadNotifications();

    // Notification button handler
    const notificationBtn = document.getElementById('adminNotificationBtn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('adminNotificationDropdown');
            dropdown.classList.toggle('active');
        });
    }

    // Close notification dropdown on outside click
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('adminNotificationDropdown');
        const notificationBtn = document.getElementById('adminNotificationBtn');
        if (dropdown && !dropdown.contains(e.target) && notificationBtn && !notificationBtn.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Mark all as read button
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async function(e) {
            e.stopPropagation();
            await markAllNotificationsRead();
        });
    }

    // Filter handler
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            loadPackages();
        });
    }

    // Assignment modal handlers
    setupAssignmentModal();

    // Add Package button handler
    const addPackageBtn = document.getElementById('addPackageBtn');
    if (addPackageBtn) {
        addPackageBtn.addEventListener('click', function() {
            openAddPackageModal();
        });
    }

    // Add Package form handler
    const confirmAddPackageBtn = document.getElementById('confirmAddPackage');
    if (confirmAddPackageBtn) {
        confirmAddPackageBtn.addEventListener('click', async function() {
            await handleAddPackage();
        });
    }

    // Auto-refresh notifications every 30 seconds
    setInterval(async () => {
        await loadNotifications();
    }, 30000);
});

function switchSection(sectionId) {
    const sections = document.querySelectorAll('.dashboard-section');
    sections.forEach(section => {
        section.classList.remove('active');
    });
    
    const targetSection = document.getElementById(sectionId);
    if (targetSection) {
        targetSection.classList.add('active');
    }
}

async function loadOverview() {
    try {
        // Load stats
        const statsResult = await apiCall('packages.php?action=stats', 'GET');
        const stats = statsResult.data;
        
        // Update analytics cards
        const cardValues = document.querySelectorAll('.card-value');
        if (cardValues.length >= 4) {
            cardValues[0].textContent = stats.total || 0;
            cardValues[1].textContent = stats.delivered || 0;
            cardValues[2].textContent = await getActiveDriversCount();
            cardValues[3].textContent = stats.pending || 0;
        }

        // Load recent packages
        const packagesResult = await apiCall('packages.php?action=list', 'GET');
        packages = packagesResult.data || [];
        const recentPackages = packages.slice(0, 5);
        const recentPackagesContainer = document.getElementById('recentPackages');
        if (recentPackagesContainer) {
            recentPackagesContainer.innerHTML = recentPackages.map(pkg => `
                <div class="package-item">
                    <div class="package-info">
                        <strong>${pkg.package_id}</strong>
                        <span>${pkg.destination}</span>
                    </div>
                    <span class="status-badge status-${pkg.status}">${pkg.status}</span>
                </div>
            `).join('');
        }

        // Load active drivers
        const driversResult = await apiCall('drivers.php?action=list&status=available', 'GET');
        const activeDrivers = (driversResult.data || []).slice(0, 5);
        const activeDriversContainer = document.getElementById('activeDrivers');
        if (activeDriversContainer) {
            activeDriversContainer.innerHTML = activeDrivers.map(driver => `
                <div class="driver-item">
                    <div class="driver-info">
                        <i class="fas fa-truck"></i>
                        <div class="driver-details">
                            <h4>${driver.name}</h4>
                            <p>${driver.active_packages} packages assigned</p>
                        </div>
                    </div>
                    <span class="availability-badge available">Available</span>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Error loading overview:', error);
    }
}

async function getActiveDriversCount() {
    try {
        const result = await apiCall('drivers.php?action=list&status=available', 'GET');
        return (result.data || []).length;
    } catch (error) {
        return 0;
    }
}

async function loadPackages() {
    try {
        const statusFilter = document.getElementById('statusFilter');
        const filterValue = statusFilter ? statusFilter.value : 'all';
        
        const url = filterValue !== 'all' 
            ? `packages.php?action=list&status=${filterValue}`
            : 'packages.php?action=list';
        
        const result = await apiCall(url, 'GET');
        packages = result.data || [];

        const packagesTable = document.getElementById('packagesTable');
        if (packagesTable) {
            if (packages.length === 0) {
                packagesTable.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No packages found</td></tr>';
            } else {
                packagesTable.innerHTML = packages.map(pkg => `
                    <tr>
                        <td><strong>${pkg.package_id}</strong></td>
                        <td>${pkg.destination}</td>
                        <td><span class="status-badge status-${pkg.status}">${pkg.status}</span></td>
                        <td>${pkg.driver_name || 'Unassigned'}</td>
                        <td>
                            <div class="action-btns">
                                ${pkg.status === 'pending' ? `
                                    <button class="action-btn" onclick="openAssignmentModal('${pkg.package_id}')">
                                        <i class="fas fa-user-plus"></i> Assign
                                    </button>
                                ` : ''}
                                <button class="action-btn" onclick="viewPackage('${pkg.package_id}')">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading packages:', error);
        alert('Failed to load packages: ' + error.message);
    }
}

async function loadDrivers() {
    try {
        const result = await apiCall('drivers.php?action=list', 'GET');
        drivers = result.data || [];

        const driverGrid = document.getElementById('driverGrid');
        if (driverGrid) {
            if (drivers.length === 0) {
                driverGrid.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No drivers found</p>';
            } else {
                driverGrid.innerHTML = drivers.map(driver => `
                    <div class="driver-card">
                        <div class="driver-info">
                            <i class="fas fa-truck"></i>
                            <div class="driver-details">
                                <h4>${driver.name}</h4>
                                <p>${driver.active_packages} packages assigned</p>
                                <p style="font-size: 0.8rem; color: #666;">Total Deliveries: ${driver.total_deliveries}</p>
                            </div>
                        </div>
                        <div style="margin-top: 15px;">
                            <span class="availability-badge ${driver.status}">${driver.status === 'available' ? 'Available' : 'Busy'}</span>
                        </div>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading drivers:', error);
        alert('Failed to load drivers: ' + error.message);
    }
}

async function loadAssignments() {
    try {
        // Load unassigned packages
        const packagesResult = await apiCall('packages.php?action=list&status=pending', 'GET');
        const unassignedPackages = packagesResult.data || [];
        const unassignedContainer = document.getElementById('unassignedPackages');
        if (unassignedContainer) {
            if (unassignedPackages.length === 0) {
                unassignedContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No unassigned packages</p>';
            } else {
                unassignedContainer.innerHTML = unassignedPackages.map(pkg => `
                    <div class="package-item">
                        <div class="package-info">
                            <strong>${pkg.package_id}</strong>
                            <span>${pkg.destination}</span>
                        </div>
                        <button class="action-btn" onclick="openAssignmentModal('${pkg.package_id}')">
                            <i class="fas fa-user-plus"></i> Assign
                        </button>
                    </div>
                `).join('');
            }
        }

        // Load available drivers
        const driversResult = await apiCall('drivers.php?action=available', 'GET');
        const availableDrivers = driversResult.data || [];
        const availableContainer = document.getElementById('availableDrivers');
        if (availableContainer) {
            if (availableDrivers.length === 0) {
                availableContainer.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No available drivers</p>';
            } else {
                availableContainer.innerHTML = availableDrivers.map(driver => `
                    <div class="driver-item">
                        <div class="driver-info">
                            <i class="fas fa-truck"></i>
                            <div class="driver-details">
                                <h4>${driver.name}</h4>
                                <p>${driver.active_packages} packages assigned</p>
                            </div>
                        </div>
                        <span class="availability-badge available">Available</span>
                    </div>
                `).join('');
            }
        }
    } catch (error) {
        console.error('Error loading assignments:', error);
    }
}

async function openAssignmentModal(packageId) {
    let packageData = null;
    
    // Try to find package in current list
    const pkg = packages.find(p => p.package_id === packageId || p.id == packageId);
    
    if (pkg) {
        packageData = pkg;
    } else {
        // Try to fetch package from API
        try {
            const result = await apiCall(`packages.php?action=get&id=${encodeURIComponent(packageId)}`, 'GET');
            if (result.success && result.data) {
                packageData = result.data;
            }
        } catch (error) {
            console.error('Error fetching package:', error);
            alert('Failed to load package details: ' + error.message);
            return;
        }
    }
    
    if (!packageData) {
        alert('Package not found');
        return;
    }
    
    // Check if package is pending
    if (packageData.status !== 'pending') {
        alert('This package cannot be assigned. Current status: ' + packageData.status);
        return;
    }
    
    // Update modal content
    document.getElementById('modalPackageId').textContent = packageData.package_id;
    document.getElementById('modalDestination').textContent = packageData.destination;
    
    // Load available drivers
    try {
        const result = await apiCall('drivers.php?action=available', 'GET');
        const availableDrivers = result.data || [];
        
        if (availableDrivers.length === 0) {
            alert('No available drivers at the moment');
            return;
        }
        
        const driverSelect = document.getElementById('driverSelect');
        driverSelect.innerHTML = availableDrivers.map(driver => 
            `<option value="${driver.id}">${driver.name} (${driver.active_packages} packages)</option>`
        ).join('');

        // Store package_id (string) for assignment
        const modal = document.getElementById('assignmentModal');
        modal.classList.add('active');
        modal.setAttribute('data-package-id', packageData.package_id);
    } catch (error) {
        console.error('Error loading drivers:', error);
        alert('Failed to load available drivers: ' + error.message);
    }
}

function setupAssignmentModal() {
    const confirmBtn = document.getElementById('confirmAssignment');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            const modal = document.getElementById('assignmentModal');
            const packageId = modal.getAttribute('data-package-id');
            const driverId = document.getElementById('driverSelect').value;

            if (!packageId) {
                alert('Package ID is missing');
                return;
            }

            if (!driverId) {
                alert('Please select a driver');
                return;
            }

            // Disable button and show loading
            const originalText = confirmBtn.innerHTML;
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';

            try {
                const result = await apiCall('assignments.php?action=assign', 'POST', {
                    package_id: packageId,
                    driver_id: parseInt(driverId)
                });
                
                if (result.success) {
                    // Reload data
                    await loadOverview();
                    await loadPackages();
                    await loadDrivers();
                    await loadAssignments();
                    
                    // Close modal
                    modal.classList.remove('active');
                    
                    // Show success message
                    showSuccessMessage('Package assigned successfully!');
                }
            } catch (error) {
                console.error('Assignment error:', error);
                alert('Failed to assign package: ' + error.message);
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        });
    }
}

function showSuccessMessage(message) {
    // Create a temporary success message
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #10b981;
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        font-weight: 600;
    `;
    notification.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.3s';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function viewPackage(packageId) {
    const pkg = packages.find(p => p.package_id === packageId);
    if (pkg) {
        alert(`Package Details:\n\nID: ${pkg.package_id}\nDestination: ${pkg.destination}\nRecipient: ${pkg.recipient_name}\nStatus: ${pkg.status}\nDriver: ${pkg.driver_name || 'Unassigned'}`);
    }
}

async function loadNotifications() {
    try {
        const result = await apiCall('notifications.php?action=list&limit=20', 'GET');
        notifications = result.data || [];
        
        const notificationList = document.getElementById('adminNotificationList');
        const notificationBadge = document.getElementById('adminNotificationBadge');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        
        if (!notificationList) return;

        const unreadCount = notifications.filter(n => !n.is_read).length;
        if (notificationBadge) {
            notificationBadge.textContent = unreadCount;
            notificationBadge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }

        // Show/hide mark all read button
        if (markAllReadBtn) {
            markAllReadBtn.style.display = unreadCount > 0 ? 'flex' : 'none';
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

async function markAllNotificationsRead() {
    try {
        const unreadNotifications = notifications.filter(n => !n.is_read);
        for (const notif of unreadNotifications) {
            await apiCall('notifications.php?action=read', 'PUT', {
                id: notif.id
            });
        }
        await loadNotifications();
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
    }
}

function openAddPackageModal() {
    // Reset form
    document.getElementById('addPackageForm').reset();
    
    // Generate suggested package ID
    const suggestedId = 'PKG' + String(Date.now()).slice(-6);
    document.getElementById('newPackageId').value = suggestedId;
    document.getElementById('newPackageId').focus();
    
    // Show modal
    document.getElementById('addPackageModal').classList.add('active');
}

async function handleAddPackage() {
    const packageId = document.getElementById('newPackageId').value.trim();
    const destination = document.getElementById('newDestination').value.trim();
    const recipientName = document.getElementById('newRecipientName').value.trim();
    const recipientPhone = document.getElementById('newRecipientPhone').value.trim();
    const notes = document.getElementById('newNotes').value.trim();

    // Validation
    if (!packageId || !destination || !recipientName) {
        alert('Please fill in all required fields (Package ID, Destination, Recipient Name)');
        return;
    }

    // Check if package ID already exists
    const existingPackage = packages.find(p => p.package_id === packageId);
    if (existingPackage) {
        alert('Package ID already exists. Please use a different ID.');
        return;
    }

    const confirmBtn = document.getElementById('confirmAddPackage');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

    try {
        const result = await apiCall('packages.php?action=create', 'POST', {
            package_id: packageId,
            destination: destination,
            recipient_name: recipientName,
            recipient_phone: recipientPhone || null,
            notes: notes || null
        });

        if (result.success) {
            // Close modal
            document.getElementById('addPackageModal').classList.remove('active');
            
            // Reload packages list
            await loadPackages();
            await loadOverview();
            await loadAssignments();
            await loadNotifications();
            
            // Show success message
            showSuccessMessage('Package added successfully!');
        }
    } catch (error) {
        console.error('Error adding package:', error);
        alert('Failed to add package: ' + error.message);
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    }
}

// Make functions available globally
window.openAssignmentModal = openAssignmentModal;
window.viewPackage = viewPackage;
window.markNotificationRead = markNotificationRead;
