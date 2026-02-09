// Shared JavaScript functionality

// API Base URL
const API_BASE = 'api';

// API Helper Functions
async function apiCall(endpoint, method = 'GET', data = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        credentials: 'include' // Include cookies for session
    };
    
    if (data && (method === 'POST' || method === 'PUT')) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(`${API_BASE}/${endpoint}`, options);
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Invalid response from server');
        }
        
        const result = await response.json();
        
        if (!result.success) {
            const errorMsg = result.message || 'API request failed';
            console.error('API Error:', endpoint, errorMsg, result);
            throw new Error(errorMsg);
        }
        
        return result;
    } catch (error) {
        console.error('API Call Error:', {
            endpoint: endpoint,
            method: method,
            error: error.message
        });
        
        // If it's already an Error object with message, rethrow it
        if (error instanceof Error) {
            throw error;
        }
        
        // Otherwise create a new error
        throw new Error(error.message || 'Network error or server unavailable');
    }
}

// Login form handlers
document.addEventListener('DOMContentLoaded', function() {
    // Admin login
    const adminLoginForm = document.getElementById('adminLoginForm');
    if (adminLoginForm) {
        adminLoginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            
            try {
                const result = await apiCall('auth.php?action=login', 'POST', {
                    username: username,
                    password: password,
                    role: 'admin'
                });
                
                if (result.success) {
                    window.location.href = 'admin-dashboard.html';
                }
            } catch (error) {
                alert('Login failed: ' + error.message + '\n\nAdmin Credentials:\nUsername: admin\nPassword: admin123');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Driver login
    const driverLoginForm = document.getElementById('driverLoginForm');
    if (driverLoginForm) {
        driverLoginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            
            try {
                const result = await apiCall('auth.php?action=login', 'POST', {
                    username: username,
                    password: password,
                    role: 'driver'
                });
                
                if (result.success) {
                    window.location.href = 'driver-dashboard.html';
                }
            } catch (error) {
                alert('Login failed: ' + error.message + '\n\nDriver Credentials:\nUsername: driver (or driver1-driver5)\nPassword: driver123');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }

    // Modal close handlers
    const modals = document.querySelectorAll('.modal');
    const closeButtons = document.querySelectorAll('.modal-close');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            modals.forEach(modal => {
                modal.classList.remove('active');
            });
        });
    });

    // Close modal on outside click
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
});

// Utility functions
function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function formatTime(date) {
    return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

