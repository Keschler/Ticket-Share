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

    // Load user's tickets from localStorage
    const loadUserTickets = () => {
        const tickets = JSON.parse(localStorage.getItem('tickets')) || [];
        const currentUser = localStorage.getItem('profileName') || 'Anonymous';
        
        console.log('All tickets:', tickets); // Debug log
        console.log('Current user:', currentUser); // Debug log
        
        userTicketsContainer.innerHTML = '';
        
        // Temporarily show all tickets for testing
        tickets.forEach(ticket => {
            const ticketElement = createTicketElement(ticket);
            userTicketsContainer.appendChild(ticketElement);
        });
        
        addEventListenersToTickets();
    };

    // Create HTML for a single ticket
    const createTicketElement = (ticket) => {
        const ticketElement = document.createElement('div');
        ticketElement.classList.add('ticket');
        // Add buy-it-now class if it's only Buy It Now type
        if (ticket.saleType?.includes('Buy It Now') && !ticket.saleType?.includes('Auction')) {
            ticketElement.classList.add('buy-it-now');
        }
        ticketElement.innerHTML = `
            <img src="${ticket.image}" class="ticket-image" alt="${ticket.eventName}">
            <h2 id="title">${ticket.eventName}</h2>
            <p id="date"><strong>Date:</strong> ${ticket.date}</p>
            <p id="time"><strong>Time:</strong> ${ticket.time}</p>
            <p id="location"><strong>Location:</strong> ${ticket.location}</p>
            <p id="expiration"><strong>Expires:</strong> ${new Date(ticket.expiration).toLocaleString()}</p>
            <div class="auction">
                ${ticket.saleType?.includes('Auction') ? 
                    `<p class="current-bid">
                        ${ticket.startingBidCurrency}${ticket.startingBid}
                    </p>` : ''
                }
                ${ticket.saleType?.includes('Buy It Now') ?
                    `<p class="buy-now-price"><strong>Buy It Now:</strong> ${ticket.currency}${ticket.price}</p>` : ''
                }
                ${ticket.saleType?.includes('Auction') ? 
                    `<button class="bid-button">Place Bid</button>
                     <button class="bid-history-button">Bid History</button>` :
                    `<button class="buy-button">Buy Now</button>`
                }
                <button class="favorite-button">Add to Favorites</button>
                ${getSaleTypeBadge(ticket.saleType || [])}
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
                    ticketObj.startingBid = newBid; // Update startingBid instead of price
                    ticketObj.startingBidCurrency = currencySymbol;
                    localStorage.setItem('tickets', JSON.stringify(ticketsData));
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
    const handleDeleteButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const titleElement = ticketDiv.querySelector('#title');
        if (titleElement) {
            const title = titleElement.textContent;
            let tickets = JSON.parse(localStorage.getItem('tickets')) || [];
            tickets = tickets.filter(ticket => ticket.eventName !== title);
            localStorage.setItem('tickets', JSON.stringify(tickets));
            loadUserTickets();
        }
    };

    // Add event listeners to all ticket buttons
    const addEventListenersToTickets = () => {
        const bidButtons = document.querySelectorAll('.bid-button');
        bidButtons.forEach(button => button.addEventListener('click', handleBidButtonClick));

        const bidHistoryButtons = document.querySelectorAll('.bid-history-button');
        bidHistoryButtons.forEach(button => button.addEventListener('click', handleBidHistoryButtonClick));

        const favoriteButtons = document.querySelectorAll('.favorite-button');
        favoriteButtons.forEach(button => button.addEventListener('click', handleFavoriteButtonClick));

        const removeFavoriteButtons = document.querySelectorAll('.remove-favorite-button');
        removeFavoriteButtons.forEach(button => button.addEventListener('click', handleRemoveFavoriteButtonClick));

        const deleteButtons = document.querySelectorAll('.delete-button');
        deleteButtons.forEach(button => button.addEventListener('click', handleDeleteButtonClick));
    };

    // Initialize profile page
    loadProfileData();
    loadUserTickets();
});