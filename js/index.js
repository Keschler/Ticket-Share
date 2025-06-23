        document.addEventListener('DOMContentLoaded', async () => {
            // Update authentication UI
            const { isLoggedIn, user } = await updateAuthUI();
            
            // Get DOM elements for search and container
            const searchInput = document.querySelector('.search-bar input');
            const ticketsContainer = document.querySelector('.container');

            // Create HTML element for a single ticket
            const createTicketElement = (ticket) => {
                const ticketElement = document.createElement('div');
                ticketElement.classList.add('ticket');
                
                // Set data attributes for easier access
                ticketElement.setAttribute('data-ticket-id', ticket.TicketID);
                ticketElement.setAttribute('data-currency', ticket.currency || '$');
                
                ticketElement.innerHTML = `
                    <img src="${ticket.image || 'https://via.placeholder.com/150x150?text=No+Image'}" class="ticket-image" alt="${ticket.eventName || 'Ticket'}">
                    <div class="ticket-content">
                        <h2>${ticket.eventName || 'No Event Name'}</h2>
                        <p><strong>Date:</strong> ${ticket.date || 'N/A'}</p>
                        <p><strong>Time:</strong> ${ticket.time || 'N/A'}</p>
                        <p><strong>Location:</strong> ${ticket.location || 'N/A'}</p>
                        <p><strong>Expires:</strong> ${ticket.expiration ? new Date(ticket.expiration).toLocaleString() : 'N/A'}</p>
                        <p><strong>Seller:</strong> ${ticket.sellerName || 'Unknown'}</p>
                    </div>
                    <div class="ticket-actions">
                        <p class="current-bid">${ticket.currency || '$'}${ticket.price || '0.00'}</p>
                        <button class="btn btn-primary buy-button">Buy Now</button>
                        <button class="btn btn-warning favorite-button">Add to Favorites</button>
                    </div>
                `;
                return ticketElement;
            };

            // Load and display all tickets from database via PHP
            const loadTickets = () => {
                // Show loading indicator
                ticketsContainer.innerHTML = '<p>Loading tickets...</p>';
                
                // Fetch tickets from the backend
                fetch('backend/get-tickets.php')
                    .then(response => response.json())
                    .then(data => {
                        ticketsContainer.innerHTML = '';
                        
                        if (data.success && data.tickets.length > 0) {
                            data.tickets.forEach(ticket => {
                                const ticketElement = createTicketElement(ticket);
                                ticketsContainer.appendChild(ticketElement);
                            });
                        } else {
                            ticketsContainer.innerHTML = '<p>No tickets available.</p>';
                            console.log('No tickets or error message:', data.message);
                        }
                        addEventListenersToTickets();
                    })
                    .catch(error => {
                        console.error('Error fetching tickets:', error);
                        ticketsContainer.innerHTML = '<p>Failed to load tickets. Please try again later.</p>';
                    });
            };

            // Filter tickets based on search input
            const filterTickets = () => {
                const searchTerm = searchInput.value.toLowerCase();
                const ticketElements = document.querySelectorAll('.ticket');
                ticketElements.forEach(ticket => {
                    const ticketText = ticket.textContent.toLowerCase();
                    ticket.style.display = ticketText.includes(searchTerm) ? '' : 'none';
                });
            };

            // Update countdown timers for ticket expiration
            const updateTimers = () => {
                const timers = document.querySelectorAll('.timer');
                timers.forEach(timer => {
                    // Get expiration time from data attribute
                    const expiration = new Date(timer.getAttribute('data-expiration')).getTime();

                    // Update timer display
                    const updateTimer = () => {
                        const now = new Date().getTime();
                        const distance = expiration - now;

                        if (distance < 0) {
                            timer.textContent = "Expired";
                            clearInterval(interval);
                            return;
                        }

                        // Calculate time components
                        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                        timer.textContent =
                            `${days > 0 ? days + "d " : ""}` +
                            `${hours < 10 ? "0" + hours : hours}h ` +
                            `${minutes < 10 ? "0" + minutes : minutes}m ` +
                            `${seconds < 10 ? "0" + seconds : seconds}s`;
                    };

                    updateTimer();
                    const interval = setInterval(updateTimer, 1000);
                });
            };

            // Handle adding tickets to favorites
            const handleFavoriteButtonClick = async (event) => {
                const ticketDiv = event.target.closest('.ticket');
                const titleElement = ticketDiv.querySelector('h2');
                const ticketId = ticketDiv.getAttribute('data-ticket-id');
                
                // Check if user is logged in using auth.js
                const { isLoggedIn } = await checkAuthStatus();
                
                if (!isLoggedIn) {
                    alert('Please log in to add tickets to favorites.');
                    window.location.href = 'login.html';
                    return;
                }
                
                if (!ticketId) {
                    alert('Unable to identify the ticket. Please try again.');
                    return;
                }

                try {
                    // Get CSRF token for the request
                    const csrfToken = getCsrfToken();
                    if (!csrfToken) {
                        alert('Security token missing. Please refresh the page and try again.');
                        return;
                    }

                    const response = await fetch('backend/manage-favorites.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include', // Important for sending cookies/session data
                        body: JSON.stringify({
                            action: 'add',
                            ticketId: parseInt(ticketId),
                            csrf_token: csrfToken
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Added to favorites!');
                        // Change button text temporarily
                        event.target.textContent = 'Added ✓';
                        event.target.style.backgroundColor = '#27ae60';
                        setTimeout(() => {
                            event.target.textContent = 'Add to Favorites';
                            event.target.style.backgroundColor = '';
                        }, 2000);
                    } else {
                        alert(data.message || 'Failed to add to favorites');
                    }
                } catch (error) {
                    console.error('Error adding to favorites:', error);
                    alert('Failed to add to favorites. Please try again.');
                }
            };

            // Handle buy button clicks
            const handleBuyButtonClick = async (event) => {
                const ticketDiv = event.target.closest('.ticket');
                const ticketId = ticketDiv.getAttribute('data-ticket-id');
                
                // Check if user is logged in using auth.js
                const { isLoggedIn } = await checkAuthStatus();
                
                if (!isLoggedIn) {
                    alert('Please log in to purchase tickets.');
                    window.location.href = 'login.html';
                    return;
                }
                
                if (confirm('Are you sure you want to purchase this ticket?')) {
                    // Send purchase request to server
                    fetch('backend/buy-ticket.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include', // Important for sending cookies/session data
                        body: JSON.stringify({
                            ticketId: parseInt(ticketId),
                            csrf_token: getCsrfToken()
                            // buyerId is retrieved from session on server
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            // Reload tickets to reflect the change
                            loadTickets();
                        } else {
                            alert('Purchase failed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error purchasing ticket:', error);
                        alert('An error occurred while processing your purchase. Please try again later.');
                    });
                }
            };

            // Add event listeners to all interactive elements in tickets
            const addEventListenersToTickets = () => {
                // Add listeners for favorite buttons
                const favoriteButtons = document.querySelectorAll('.favorite-button');
                favoriteButtons.forEach(button => button.addEventListener('click', handleFavoriteButtonClick));

                // Add listeners for buy buttons
                const buyButtons = document.querySelectorAll('.buy-button');
                buyButtons.forEach(button => button.addEventListener('click', handleBuyButtonClick));
            };

            // Add search functionality
            searchInput.addEventListener('input', filterTickets);

            // Initialize authentication and page
            updateAuthUI();
            loadTickets();
            updateTimers();

        });
