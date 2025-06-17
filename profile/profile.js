document.addEventListener('DOMContentLoaded', () => {
    // First check authentication status, then load profile data accordingly
    initializeProfile();
    setupEventListeners();
});

/**
 * Initialize the profile page with authentication check
 */
async function initializeProfile() {
    const { isLoggedIn, user } = await updateAuthUI();
    
    if (isLoggedIn) {
        loadProfileData();
    } else {
        displayNotLoggedIn();
    }
}

/**
 * Display not logged in state
 */
function displayNotLoggedIn() {
    const usernameDisplay = document.getElementById('profile-username-display');
    const emailDisplay = document.getElementById('profile-email-display');
    const addressDisplay = document.getElementById('profile-address-display');
    const ticketsContainer = document.getElementById('user-tickets-container');

    const notLoggedInMessage = 'Please log in to view your profile';
    
    if (usernameDisplay) usernameDisplay.textContent = notLoggedInMessage;
    if (emailDisplay) emailDisplay.textContent = notLoggedInMessage;
    if (addressDisplay) addressDisplay.textContent = notLoggedInMessage;
    if (ticketsContainer) {
        ticketsContainer.innerHTML = `
            <div style="text-align: center; grid-column: 1 / -1; padding: 2rem;">
                <p style="color: rgba(255, 255, 255, 0.8); font-size: 1.2rem; margin-bottom: 1rem;">
                    Please log in to view your tickets
                </p>
                <a href="../login.html" style="color: #4ecdc4; text-decoration: none; padding: 0.8rem 1.5rem; border: 2px solid #4ecdc4; border-radius: 8px; display: inline-block; transition: all 0.3s ease;">
                    Go to Login
                </a>
            </div>
        `;
    }
    
    // Clear any cached user data
    localStorage.removeItem('profileName');
    localStorage.removeItem('userEmail');
    localStorage.removeItem('userId');
    localStorage.removeItem('username');
}

/**
 * Load profile data from the backend
 */
async function loadProfileData() {
    try {
        // First try to get profile data using session authentication (preferred method)
        let response = await fetch('../backend/get-profile.php', {
            method: 'GET',
            credentials: 'include',
            headers: {
                'Cache-Control': 'no-cache'
            }
        });

        let data = await response.json();

        if (data.success) {
            displayProfileData(data.user);
            displayUserTickets(data.tickets);
            
            // Store user data in localStorage for consistency
            if (data.user) {
                localStorage.setItem('username', data.user.username);
                localStorage.setItem('profileName', data.user.username);
                localStorage.setItem('userEmail', data.user.email);
            }
        } else {
            console.error('Profile error:', data.message);
            
            // If not logged in, show the not logged in state
            if (data.message && (data.message.includes('not logged in') || data.message.includes('User is not logged in'))) {
                displayNotLoggedIn();
            } else {
                displayError(data.message || 'Failed to load profile data. Please log in.');
            }
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        displayError('Network error: Unable to load profile data');
    }
}

/**
 * Display profile data in the UI
 */
function displayProfileData(user) {
    const usernameDisplay = document.getElementById('profile-username-display');
    const emailDisplay = document.getElementById('profile-email-display');
    const addressDisplay = document.getElementById('profile-address-display');

    if (usernameDisplay) {
        usernameDisplay.textContent = user.username || 'Not set';
    }
    
    if (emailDisplay) {
        emailDisplay.textContent = user.email || 'Not set';
    }
    
    if (addressDisplay) {
        addressDisplay.textContent = user.address || 'Not set';
    }
}

/**
 * Display user's tickets
 */
function displayUserTickets(tickets) {
    const container = document.getElementById('user-tickets-container');
    
    if (!container) return;

    container.innerHTML = '';

    if (!tickets || tickets.length === 0) {
        container.innerHTML = '<p style="color: rgba(255, 255, 255, 0.8); text-align: center; grid-column: 1 / -1;">No tickets found.</p>';
        return;
    }

    tickets.forEach(ticket => {
        const ticketCard = createTicketCard(ticket);
        container.appendChild(ticketCard);
    });
}

/**
 * Create a ticket card element
 */
function createTicketCard(ticket) {
    const card = document.createElement('div');
    card.className = 'ticket-card';
    
    // Format date and time
    const eventDate = ticket.eventDate ? new Date(ticket.eventDate).toLocaleDateString() : 'Not set';
    const eventTime = ticket.eventTime || 'Not set';
    
    // Format price
    const price = ticket.price ? `${ticket.currency || '€'} ${parseFloat(ticket.price).toFixed(2)}` : 'Not set';

    // Determine status badge and action buttons
    let statusBadge = '';
    let actionButtons = '';
    
    if (ticket.userRole === 'buyer') {
        // Buyer perspective - show confirmation/dispute options
        if (ticket.disputeStatus === 'open') {
            statusBadge = '<div class="status-badge status-disputed">⚠️ Under Dispute</div>';
        } else if (ticket.confirmationStatus === 'confirmed') {
            statusBadge = '<div class="status-badge status-confirmed">✅ Ticket Confirmed</div>';
        } else if (ticket.confirmationStatus === 'auto_confirmed') {
            statusBadge = '<div class="status-badge status-confirmed">✅ Auto-Confirmed</div>';
        } else if (ticket.confirmationStatus === 'pending') {
            const eventDateObj = new Date(ticket.eventDate);
            const expiryDate = ticket.confirmationExpiry ? new Date(ticket.confirmationExpiry) : null;
            const now = new Date();
            
            // Check if we can confirm/dispute (3 days after event date and before expiry)
            const canConfirmOrDispute = now > eventDateObj && expiryDate && now < expiryDate;
            
            if (canConfirmOrDispute) {
                statusBadge = '<div class="status-badge status-pending">⏳ Awaiting Your Confirmation</div>';
                actionButtons = `
                    <div class="confirmation-actions">
                        <button class="confirm-btn" onclick="confirmTicket(${ticket.id})">
                            ✅ Confirm Valid Ticket
                        </button>
                        <button class="dispute-btn" onclick="disputeTicket(${ticket.id})">
                            ⚠️ Create Dispute
                        </button>
                    </div>
                `;
            } else if (expiryDate && now < expiryDate) {
                const daysUntilCanConfirm = Math.ceil((eventDateObj - now) / (1000 * 60 * 60 * 24));
                statusBadge = `<div class="status-badge status-waiting">⏰ Can confirm in ${daysUntilCanConfirm} day(s)</div>`;
            } else {
                statusBadge = '<div class="status-badge status-expired">⏰ Confirmation Expired</div>';
            }
        } else {
            statusBadge = '<div class="status-badge status-purchased">🎫 Purchased</div>';
        }
    } else {
        // Seller perspective - show ticket status
        if (ticket.isSold) {
            if (ticket.disputeStatus === 'open') {
                statusBadge = '<div class="status-badge status-disputed">⚠️ Under Dispute</div>';
            } else if (ticket.confirmationStatus === 'confirmed') {
                statusBadge = '<div class="status-badge status-confirmed">✅ Payment Received</div>';
            } else if (ticket.confirmationStatus === 'auto_confirmed') {
                statusBadge = '<div class="status-badge status-confirmed">✅ Auto-Confirmed</div>';
            } else if (ticket.confirmationStatus === 'pending') {
                const expiryDate = ticket.confirmationExpiry ? new Date(ticket.confirmationExpiry) : null;
                const now = new Date();
                if (expiryDate && now < expiryDate) {
                    statusBadge = '<div class="status-badge status-pending">⏳ Awaiting Buyer Confirmation</div>';
                } else {
                    statusBadge = '<div class="status-badge status-expired">⏰ Confirmation Expired</div>';
                }
            } else {
                statusBadge = '<div class="status-badge status-sold">💰 Sold</div>';
            }
        } else {
            statusBadge = '<div class="status-badge status-available">🎫 Available</div>';
        }
    }

    // Add role indicator
    const roleIndicator = ticket.userRole === 'buyer' ? 
        '<div class="role-indicator buyer-role">📦 Purchased</div>' : 
        '<div class="role-indicator seller-role">🏪 Selling</div>';

    card.innerHTML = `
        <h3>${escapeHtml(ticket.title || 'Untitled Ticket')}</h3>
        ${ticket.description ? `<p><strong>Description:</strong> ${escapeHtml(ticket.description)}</p>` : ''}
        <p><strong>Price:</strong> ${price}</p>
        <p><strong>Location:</strong> ${escapeHtml(ticket.location || 'Not set')}</p>
        <p><strong>Event Date:</strong> ${eventDate}</p>
        <p><strong>Event Time:</strong> ${eventTime}</p>
        ${ticket.category ? `<p><strong>Category:</strong> ${escapeHtml(ticket.category)}</p>` : ''}
        ${roleIndicator}
        ${statusBadge}
        ${actionButtons}
    `;

    return card;
}

/**
 * Display error message
 */
function displayError(message) {
    const usernameDisplay = document.getElementById('profile-username-display');
    const emailDisplay = document.getElementById('profile-email-display');
    const addressDisplay = document.getElementById('profile-address-display');
    const ticketsContainer = document.getElementById('user-tickets-container');

    const errorMessage = `Error: ${message}`;
    
    if (usernameDisplay) usernameDisplay.textContent = errorMessage;
    if (emailDisplay) emailDisplay.textContent = errorMessage;
    if (addressDisplay) addressDisplay.textContent = errorMessage;
    if (ticketsContainer) {
        ticketsContainer.innerHTML = `
            <div style="text-align: center; grid-column: 1 / -1; padding: 2rem;">
                <p style="color: #ff6b6b; font-size: 1.1rem;">${errorMessage}</p>
            </div>
        `;
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (typeof text !== 'string') return text;
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Profile picture upload
    const profilePicture = document.getElementById('profile-picture');
    const uploadInput = document.getElementById('upload-picture');

    if (profilePicture && uploadInput) {
        profilePicture.addEventListener('click', () => {
            uploadInput.click();
        });

        uploadInput.addEventListener('change', handleProfilePictureUpload);
    }

    // Close history popup
    const closeHistoryBtn = document.getElementById('closeHistory');
    if (closeHistoryBtn) {
        closeHistoryBtn.addEventListener('click', () => {
            document.getElementById('historyPopup').style.display = 'none';
        });
    }

    // Bid popup controls
    const cancelBidBtn = document.getElementById('cancelBid');
    if (cancelBidBtn) {
        cancelBidBtn.addEventListener('click', () => {
            document.getElementById('bidPopup').style.display = 'none';
        });
    }

    const confirmBidBtn = document.getElementById('confirmBid');
    if (confirmBidBtn) {
        confirmBidBtn.addEventListener('click', handleBidConfirmation);
    }
}

/**
 * Handle profile picture upload
 */
function handleProfilePictureUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Please select a valid image file.');
        return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB.');
        return;
    }

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
        const profilePicture = document.getElementById('profile-picture');
        if (profilePicture) {
            profilePicture.src = e.target.result;
        }
    };
    reader.readAsDataURL(file);

    // TODO: Implement actual upload to server
    console.log('Profile picture upload not yet implemented');
}

/**
 * Handle bid confirmation
 */
function handleBidConfirmation() {
    const bidAmount = document.getElementById('bidAmount').value;
    
    if (!bidAmount || isNaN(bidAmount) || parseFloat(bidAmount) <= 0) {
        alert('Please enter a valid bid amount.');
        return;
    }

    // TODO: Implement bidding functionality
    console.log('Bid amount:', bidAmount);
    alert('Bidding functionality will be implemented soon.');
    
    document.getElementById('bidPopup').style.display = 'none';
    document.getElementById('bidAmount').value = '';
}

/**
 * Refresh profile data
 */
function refreshProfile() {
    loadProfileData();
}

/**
 * Confirm a ticket as valid
 */
async function confirmTicket(ticketId) {
    if (!confirm('Are you sure you want to confirm this ticket as valid? This will release payment to the seller.')) {
        return;
    }

    try {
        const csrfToken = localStorage.getItem('csrf_token');
        const response = await fetch('../backend/ticket-confirmations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'confirm',
                ticketId: ticketId,
                csrf_token: csrfToken
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('Ticket confirmed successfully! Payment has been released to the seller.');
            loadProfileData(); // Refresh the page to show updated status
        } else {
            alert('Error: ' + (data.message || 'Failed to confirm ticket'));
        }
    } catch (error) {
        console.error('Error confirming ticket:', error);
        alert('Network error: Failed to confirm ticket');
    }
}

/**
 * Create a dispute for a ticket
 */
async function disputeTicket(ticketId) {
    const reason = prompt('Please describe the issue with this ticket:');
    
    if (!reason || reason.trim() === '') {
        return;
    }

    if (!confirm('Are you sure you want to create a dispute? This will hold the payment until the issue is resolved.')) {
        return;
    }

    try {
        const csrfToken = localStorage.getItem('csrf_token');
        const response = await fetch('../backend/ticket-confirmations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
                action: 'dispute',
                ticketId: ticketId,
                reason: reason.trim(),
                csrf_token: csrfToken
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('Dispute created successfully! Payment is on hold pending resolution.');
            loadProfileData(); // Refresh the page to show updated status
        } else {
            alert('Error: ' + (data.message || 'Failed to create dispute'));
        }
    } catch (error) {
        console.error('Error creating dispute:', error);
        alert('Network error: Failed to create dispute');
    }
}