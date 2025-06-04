document.addEventListener('DOMContentLoaded', () => {
    // Get container for favorite tickets
    const favoritesContainer = document.getElementById('favorites-container');

    // Generate HTML for sale type badge with color coding
    const getSaleTypeBadge = (saleTypes) => {
        if (saleTypes.includes('Buy It Now') && saleTypes.includes('Auction')) {
            return `<span style="white-space: nowrap;">
                        <span style="color: green;">Buy It Now</span> & 
                        <span style="color: orange;">Auction</span>
                    </span>`;
        } else if (saleTypes.includes('Buy It Now')) {
            return `<span style="color: green;">Buy It Now</span>`;
        } else if (saleTypes.includes('Auction')) {
            return `<span style="color: orange;">Auction</span>`;
        } else {
            return '';
        }
    };

    // Load favorite tickets from localStorage and display them
    const loadFavorites = () => {
        const favorites = JSON.parse(localStorage.getItem('favorites')) || [];
        favoritesContainer.innerHTML = '';
        favorites.forEach(ticket => {
            const saleType = ticket.saleType || [];
            const ticketElement = document.createElement('div');
            ticketElement.classList.add('ticket');
            // Add buy-it-now class if it's only Buy It Now type
            if (saleType.includes('Buy It Now') && !saleType.includes('Auction')) {
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
                    ${saleType.includes('Auction') ? 
                        `<p class="current-bid">
                            ${ticket.startingBidCurrency}${ticket.currentBid || ticket.startingBid}
                        </p>` : ''
                    }
                    ${saleType.includes('Buy It Now') ?
                        `<p class="buy-now-price"><strong>Buy It Now:</strong> ${ticket.currency}${ticket.price}</p>` : ''
                    }
                    ${saleType.includes('Auction') ? 
                        `<button class="bid-button">Place Bid</button>
                         <button class="bid-history-button">Bid History</button>` :
                        `<button class="buy-button">Buy Now</button>`
                    }
                    <button class="remove-favorite-button">Remove from Favorites</button>
                    ${getSaleTypeBadge(saleType)}
                </div>
            `;
            favoritesContainer.appendChild(ticketElement);
        });
        addEventListenersToRemoveButtons();
        addEventListenersToTickets(); // Ensure event listeners are added to new elements
    };

    // Handle removing tickets from favorites
    const handleRemoveFavoriteButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const titleElement = ticketDiv.querySelector('#title');
        let favorites = JSON.parse(localStorage.getItem('favorites')) || [];
        favorites = favorites.filter(fav => fav.eventName !== titleElement.textContent);
        localStorage.setItem('favorites', JSON.stringify(favorites));
        loadFavorites();
    };

    // Add event listeners to all remove buttons
    const addEventListenersToRemoveButtons = () => {
        const removeButtons = document.querySelectorAll('.remove-favorite-button');
        removeButtons.forEach(button => button.addEventListener('click', handleRemoveFavoriteButtonClick));
    };

    // Handle bid button clicks and show bid popup
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

    // Handle bid history button clicks and show history popup
    const handleBidHistoryButtonClick = (event) => {
        const ticketDiv = event.target.closest('.ticket');
        const titleElement = ticketDiv.querySelector('#title');
        if (titleElement) {
            const title = titleElement.textContent;
            const bidHistory = JSON.parse(localStorage.getItem('bidHistory')) || [];
            const eventBids = bidHistory.filter(bid => bid.eventName === title);
            const historyPopup = document.getElementById('historyPopup');
            const historyTitle = document.getElementById('historyTitle');
            const historyContent = document.getElementById('historyContent');

            historyTitle.textContent = `Bid History for ${title}`;
            historyContent.innerHTML = eventBids.length
                ? eventBids.slice().reverse().map(bid => {
                    const bidDate = new Date(bid.time);
                    const formattedDate = bidDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
                    return `<p class="bid-entry">${bid.user} bid ${bid.bid} ${bid.currency} on ${formattedDate}</p>`;
                }).join('')
                : '<p>No bids yet.</p>';
            historyPopup.style.display = 'block';

            document.getElementById('closeHistory').onclick = () => {
                historyPopup.style.display = 'none';
            };
        }
    };

    // Add event listeners to all interactive elements in tickets
    const addEventListenersToTickets = () => {
        // Add listeners for bid buttons
        const bidButtons = document.querySelectorAll('.bid-button');
        bidButtons.forEach(button => button.addEventListener('click', handleBidButtonClick));

        // Add listeners for bid history buttons
        const bidHistoryButtons = document.querySelectorAll('.bid-history-button');
        bidHistoryButtons.forEach(button => button.addEventListener('click', handleBidHistoryButtonClick));

        // Add listeners for remove favorite buttons
        const removeFavoriteButtons = document.querySelectorAll('.remove-favorite-button');
        removeFavoriteButtons.forEach(button => button.addEventListener('click', handleRemoveFavoriteButtonClick));
    };

    // Load favorites when page loads
    loadFavorites();
    addEventListenersToTickets();

    // Navigation event listeners
    const profileButton = document.getElementById('profile-button');
    if (profileButton) {
        profileButton.addEventListener('click', () => {
            window.location.href = 'profile/profile.html';
        });
    }

    const ticketsButton = document.getElementById('tickets-button');
    if (ticketsButton) {
        ticketsButton.addEventListener('click', () => {
            window.location.href = 'index.html';
        });
    }

    const loginButton = document.getElementById('login-button');
    if (loginButton) {
        loginButton.addEventListener('click', () => {
            alert('Login clicked!');
            // TODO: Add login functionality
        });
    }
});
