// Check if user is logged in via PHP session
async function checkAuthStatus() {
    try {
        console.log('Checking auth status...');
        const response = await fetch('backend/session.php', {
            method: 'GET',
            credentials: 'include', // Important for cookies/sessions
            headers: {
                'Cache-Control': 'no-cache' // Prevent caching
            }
        });
        
        const data = await response.json();
        console.log('Auth status response:', data);
        
        // Store CSRF token if available
        if (data.csrf_token) {
            localStorage.setItem('csrf_token', data.csrf_token);
        }
        
        return {
            isLoggedIn: data.success,
            user: data.user || null
        };
    } catch (error) {
        console.error('Error checking authentication status:', error);
        return {
            isLoggedIn: false,
            user: null
        };
    }
}

// Get CSRF token from local storage
function getCsrfToken() {
    return localStorage.getItem('csrf_token');
}

// Helper to create fetch options with CSRF token
function createFetchOptions(method = 'GET', body = null) {
    const options = {
        method: method,
        credentials: 'include',
        headers: {
            'Cache-Control': 'no-cache'
        }
    };
    
    if (body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    
    const csrfToken = getCsrfToken();
    if (csrfToken && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
        if (body) {
            // If body is already an object, add the CSRF token
            const bodyObj = typeof body === 'string' ? JSON.parse(body) : body;
            bodyObj.csrf_token = csrfToken;
            options.body = JSON.stringify(bodyObj);
        } else {
            // If no body, create one with just the CSRF token
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({ csrf_token: csrfToken });
        }
    }
    
    return options;
}

// Update UI based on authentication status
async function updateAuthUI() {
    const { isLoggedIn, user } = await checkAuthStatus();
    const loginRegisterBtn = document.getElementById('login-register-btn');
    
    if (!loginRegisterBtn) return;
    
    if (isLoggedIn && user) {
        loginRegisterBtn.textContent = 'Logout';
        loginRegisterBtn.onclick = async function() {
            if (await logout()) {
                window.location.href = 'index.html';
            }
        };
    } else {
        loginRegisterBtn.textContent = 'Login / Register';
        loginRegisterBtn.onclick = function() {
            window.location.href = 'login.html';
        };
    }
    
    // Return the auth status for other uses
    return { isLoggedIn, user };
}

// Logout function
async function logout() {
    try {
        const response = await fetch('backend/logout.php', createFetchOptions('POST'));
        
        const data = await response.json();
        
        // Clear any client-side storage
        localStorage.removeItem('profileName');
        localStorage.removeItem('userEmail');
        localStorage.removeItem('userId');
        localStorage.removeItem('rememberUser');
        localStorage.removeItem('csrf_token');
        
        return data.success;
    } catch (error) {
        console.error('Error during logout:', error);
        return false;
    }
}

// Initialize auth status on page load
document.addEventListener('DOMContentLoaded', updateAuthUI);
