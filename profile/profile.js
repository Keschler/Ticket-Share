document.addEventListener('DOMContentLoaded', () => {
    // Get all necessary DOM elements
    const profilePicture = document.getElementById('profile-picture');
    const uploadPicture = document.getElementById('upload-picture');
    const profileNameDisplay = document.getElementById('profile-name-display');
    const profileNameInput = document.getElementById('profile-name');
    const profileDescriptionDisplay = document.getElementById('profile-description-display');
    const profileDescriptionInput = document.getElementById('profile-description');
    const editProfileButton = document.getElementById('edit-profile-button');
    const userTicketsContainer = document.getElementById('user-tickets-container');
    const historyPopup = document.getElementById('historyPopup');
    const historyTitle = document.getElementById('historyTitle');
    const historyContent = document.getElementById('historyContent');
    const closeHistory = document.getElementById('closeHistory');

    // Validate that all required elements exist
    if (!profilePicture || !uploadPicture || !profileNameDisplay || !profileNameInput || 
        !profileDescriptionDisplay || !profileDescriptionInput || !editProfileButton || 
        !userTicketsContainer || !historyPopup || !historyTitle || !historyContent || !closeHistory) {
        console.error('One or more elements are missing in the DOM.');
        return;
    }

    // Handle profile picture upload click
    profilePicture.addEventListener('click', () => {
        uploadPicture.click();
    });

    // Handle file selection for profile picture
    uploadPicture.addEventListener('change', (event) => {
        const file = event.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                profilePicture.src = e.target.result;
                // Save profile picture to local storage
                localStorage.setItem('profilePicture', e.target.result);
            };
            reader.readAsDataURL(file);
        } else {
            alert('Please upload a valid image file.');
        }
    });

    // Load user profile data from localStorage
    const loadProfileData = () => {
        const profileName = localStorage.getItem('profileName') || 'John Doe';
        const profileDescription = localStorage.getItem('profileDescription') || 'This is a sample description about the user.';
        const profilePictureSrc = localStorage.getItem('profilePicture') || 'default-profile.jpg';

        profileNameDisplay.textContent = profileName;
        profileNameInput.value = profileName;
        profileDescriptionDisplay.textContent = profileDescription;
        profileDescriptionInput.value = profileDescription;
        profilePicture.src = profilePictureSrc;
    };

    // Save profile data to localStorage
    const saveProfileData = () => {
        localStorage.setItem('profileName', profileNameInput.value);
        localStorage.setItem('profileDescription', profileDescriptionInput.value);
    };

    // Toggle edit mode for profile information
    editProfileButton.addEventListener('click', () => {
        const isEditing = profileNameInput.style.display === 'none';
        profileNameDisplay.style.display = isEditing ? 'none' : 'block';
        profileNameInput.style.display = isEditing ? 'block' : 'none';
        profileDescriptionDisplay.style.display = isEditing ? 'none' : 'block';
        profileDescriptionInput.style.display = isEditing ? 'block' : 'none';
        editProfileButton.textContent = isEditing ? 'Save' : 'Edit';

        if (!isEditing) {
            saveProfileData();
        }
    });

    // Load user's tickets from database
    const loadUserTickets = async () => {
        const currentUserId = localStorage.getItem('userId');
        
        if (!currentUserId) {
            userTicketsContainer.innerHTML = '<p style="color: rgba(255, 255, 255, 0.8); text-align: center; padding: 2rem;">Please log in to view your tickets.</p>';
            return;
        }
        
        try {
            // Fetch tickets from database
            const response = await fetch('../backend/get-tickets.php');
            const data = await response.json();
            
            if (data.success) {
                // Filter tickets to show only current user's tickets
                const userTickets = data.tickets.filter(ticket => 
                    ticket.SellerID && ticket.SellerID.toString() === currentUserId.toString()
                );
                
                console.log('Current user ID:', currentUserId);
                console.log('User tickets:', userTickets);
                
                userTicketsContainer.innerHTML = '';
                
                if (userTickets.length === 0) {
                    userTicketsContainer.innerHTML = '<p style="color: rgba(255, 255, 255, 0.8); text-align: center; padding: 2rem;">You haven\'t created any tickets yet. <a href="../create-ticket.html" style="color: #4ecdc4;">Create your first ticket</a></p>';
                } else {
                    userTickets.forEach(ticket => {
                        const ticketElement = createTicketElement(ticket);
                        userTicketsContainer.appendChild(ticketElement);
                    });
                    addEventListenersToTickets();
                }
            } else {
                console.error('Failed to fetch tickets:', data.message);
                userTicketsContainer.innerHTML = '<p style="color: rgba(255, 255, 255, 0.8); text-align: center; padding: 2rem;">Failed to load tickets. Please try again later.</p>';
            }
        } catch (error) {
            console.error('Error fetching tickets:', error);
            userTicketsContainer.innerHTML = '<p style="color: rgba(255, 255, 255, 0.8); text-align: center; padding: 2rem;">Error loading tickets. Please check your connection.</p>';
        }
    };

    // Create HTML for a single ticket
    const createTicketElement = (ticket) => {
        const ticketElement = document.createElement('div');
        ticketElement.classList.add('ticket-card');
        
        // Handle different image sources
        const imageUrl = ticket.image || ticket.ImageURL || 'https://via.placeholder.com/300x200?text=No+Image';
        
        ticketElement.innerHTML = `
            <img src="${imageUrl}" style="width: 100%; height: 150px; object-fit: cover; border-radius: 10px; margin-bottom: 1rem;" alt="${ticket.eventName}">
            <h3>${ticket.eventName}</h3>
            <p><strong>Date:</strong> ${ticket.date}</p>
            <p><strong>Time:</strong> ${ticket.time}</p>
            <p><strong>Location:</strong> ${ticket.location}</p>
            <p><strong>Expires:</strong> ${new Date(ticket.expiration).toLocaleString()}</p>
            <div style="margin-top: 1rem;">
                ${ticket.SaleType?.includes('Auction') ? 
                    `<p style="color: #4ecdc4;"><strong>Starting Bid:</strong> ${ticket.currency || '$'}${ticket.price}</p>` : ''
                }
                ${ticket.SaleType?.includes('Buy It Now') ?
                    `<p style="color: #4ecdc4;"><strong>Buy It Now:</strong> ${ticket.currency || '$'}${ticket.price}</p>` : ''
                }
                <p style="color: rgba(255, 255, 255, 0.7);"><strong>Sale Type:</strong> ${ticket.SaleType || 'Buy It Now'}</p>
                <button class="btn btn-danger delete-ticket-btn" data-ticket-id="${ticket.TicketID}" style="margin-top: 1rem; width: 100%;">
                    Delete Ticket
                </button>
            </div>
        `;
        return ticketElement;
    };

    // Generate HTML for sale type badge
    const getSaleTypeBadge = (saleTypes) => {
        if (saleTypes.includes('Buy It Now') && saleTypes.includes('Auction')) {
            return `<span style="white-space: nowrap;"><span style="color: green;">Buy It Now</span> & <span style="color: orange;">Auction</span></span>`;
        } else if (saleTypes.includes('Buy It Now')) {
            return `<span style="color: green;">Buy It Now</span>`;
        } else if (saleTypes.includes('Auction')) {
            return `<span style="color: orange;">Auction</span>`;
        } else {
            return '';
        }
    };

    // Handle bid button clicks
    const handleBidButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const currentBidElement = ticketDiv.querySelector('.current-bid');
        const titleElement = ticketDiv.querySelector('#title');
        const popup = document.getElementById('bidPopup');
        const confirmBtn = document.getElementById('confirmBid');
        const cancelBtn = document.getElementById('cancelBid');
        const bidAmountField = document.getElementById('bidAmount');

        popup.style.display = 'block';

        confirmBtn.onclick = () => {
            const currentBidText = currentBidElement.textContent.replace(/[^\d,.]/g, '').replace(',', '.');
            const currentBidValue = parseFloat(currentBidText);
            let newBid = parseFloat(bidAmountField.value.replace(',', '.'));

            const ticketsData = JSON.parse(localStorage.getItem('tickets')) || [];
            const ticketObj = ticketsData.find(t => t.eventName === titleElement.textContent);
            const originalStart = parseFloat(ticketObj.startingBid);

            const epsilon = 0.000001;
            const isFirstBid = Math.abs(currentBidValue - originalStart) < epsilon;

            if (isNaN(newBid) || (!isFirstBid && newBid <= currentBidValue) || (isFirstBid && newBid < currentBidValue)) {
                alert(isFirstBid ?
                      "Please enter a bid equal to or higher than the starting bid." :
                      "Please enter a bid higher than the current bid.");
                return;
            }

            let currencySymbol = currentBidElement.textContent.replace(/[0-9.,]/g, '').trim() || '$';
            currentBidElement.textContent = `${currencySymbol}${newBid.toFixed(2)}`;

            // Save bid to history
            const bidHistory = JSON.parse(localStorage.getItem('bidHistory')) || [];
            const userName = localStorage.getItem('profileName') || 'Anonymous';
            const currentTime = new Date().toLocaleString();

            if (titleElement) {
                // Add to bid history
                bidHistory.push({
                    eventName: titleElement.textContent,
                    bid: newBid,
                    user: userName,
                    time: currentTime,
                    currency: currencySymbol
                });
                localStorage.setItem('bidHistory', JSON.stringify(bidHistory));

                // Update ticket's current bid in localStorage
                const ticketsData = JSON.parse(localStorage.getItem('tickets')) || [];
                const ticketObj = ticketsData.find(t => t.eventName === titleElement.textContent);
                if (ticketObj) {
                    ticketObj.currentBid = newBid; // Update currentBid instead of startingBid
                    ticketObj.startingBidCurrency = currencySymbol;
                    localStorage.setItem('tickets', JSON.stringify(ticketsData));
                }

                // Update the ticket's current bid in the favorites list
                let favorites = JSON.parse(localStorage.getItem('favorites')) || [];
                const favoriteTicket = favorites.find(fav => fav.eventName === titleElement.textContent);
                if (favoriteTicket) {
                    favoriteTicket.currentBid = newBid;
                    favoriteTicket.startingBidCurrency = currencySymbol;
                    localStorage.setItem('favorites', JSON.stringify(favorites));
                }
            }

            popup.style.display = 'none';
            bidAmountField.value = '';
        };

        cancelBtn.onclick = () => {
            popup.style.display = 'none';
            bidAmountField.value = '';
        };
    };

    // Handle bid history button clicks
    const handleBidHistoryButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const titleElement = ticketDiv.querySelector('#title');
        if (titleElement) {
            const title = titleElement.textContent;
            const bidHistory = JSON.parse(localStorage.getItem('bidHistory')) || [];
            const eventBids = bidHistory.filter(bid => bid.eventName === title);

            historyTitle.textContent = `Bid History for ${title}`;
            historyContent.innerHTML = eventBids.length
                ? eventBids.slice().reverse().map(bid => {
                    const bidDate = new Date(bid.time);
                    const formattedDate = bidDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
                    return `<p class="bid-entry">${bid.user} bid ${bid.bid} ${bid.currency} on ${formattedDate}</p>`;
                }).join('')
                : '<p>No bids yet.</p>';
            historyPopup.style.display = 'block';

            closeHistory.onclick = () => {
                historyPopup.style.display = 'none';
            };
        }
    };

    // Handle adding tickets to favorites
    const handleFavoriteButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const titleElement = ticketDiv.querySelector('#title');
        const tickets = JSON.parse(localStorage.getItem('tickets')) || [];
        const ticket = tickets.find(t => t.eventName === titleElement.textContent);
        if (ticket) {
            let favorites = JSON.parse(localStorage.getItem('favorites')) || [];
            if (!favorites.some(fav => fav.eventName === ticket.eventName)) {
                favorites.push(ticket);
                localStorage.setItem('favorites', JSON.stringify(favorites));
                alert('Added to favorites!');
                event.target.textContent = 'Remove from Favorites';
                event.target.classList.remove('favorite-button');
                event.target.classList.add('remove-favorite-button');
                event.target.removeEventListener('click', handleFavoriteButtonClick);
                event.target.addEventListener('click', handleRemoveFavoriteButtonClick);
            } else {
                alert('Already in favorites!');
            }
        }
    };

    // Handle removing tickets from favorites
    const handleRemoveFavoriteButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const titleElement = ticketDiv.querySelector('#title');
        let favorites = JSON.parse(localStorage.getItem('favorites')) || [];
        favorites = favorites.filter(fav => fav.eventName !== titleElement.textContent);
        localStorage.setItem('favorites', JSON.stringify(favorites));
        alert('Removed from favorites!');
        event.target.textContent = 'Add to Favorites';
        event.target.classList.remove('remove-favorite-button');
        event.target.classList.add('favorite-button');
        event.target.removeEventListener('click', handleRemoveFavoriteButtonClick);
        event.target.addEventListener('click', handleFavoriteButtonClick);
    };

    // Handle deleting tickets
    const handleDeleteTicketClick = async (event) => {
        const ticketId = event.target.getAttribute('data-ticket-id');
        const currentUserId = localStorage.getItem('userId');
        
        if (!ticketId || !currentUserId) {
            alert('Error: Cannot delete ticket. Please try again.');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this ticket? This action cannot be undone.')) {
            return;
        }
        
        try {
            // Disable the button to prevent multiple clicks
            event.target.disabled = true;
            event.target.textContent = 'Deleting...';
            
            const response = await fetch('../backend/delete-ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    ticketId: parseInt(ticketId),
                    userId: parseInt(currentUserId)
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Ticket deleted successfully!');
                // Reload tickets to refresh the display
                await loadUserTickets();
            } else {
                alert('Failed to delete ticket: ' + result.message);
                // Re-enable the button
                event.target.disabled = false;
                event.target.textContent = 'Delete Ticket';
            }
            
        } catch (error) {
            console.error('Error deleting ticket:', error);
            alert('Failed to delete ticket. Please check your connection and try again.');
            // Re-enable the button
            event.target.disabled = false;
            event.target.textContent = 'Delete Ticket';
        }
    };

    // Add event listeners to all ticket buttons
    const addEventListenersToTickets = () => {
        const deleteButtons = document.querySelectorAll('.delete-ticket-btn');
        deleteButtons.forEach(button => button.addEventListener('click', handleDeleteTicketClick));
    };

    // Initialize profile page
    loadProfileData();
    loadUserTickets();
});