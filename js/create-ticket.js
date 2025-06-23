        function showMessage(message, type = 'error') {
            const c = document.getElementById('messageContainer');
            c.innerHTML = `<div class="message ${type}">${message}</div>`;
            if (type === 'success') setTimeout(() => (c.innerHTML = ''), 5000);
        }

        document.addEventListener('DOMContentLoaded', async () => {
            // Check if user is logged in using our auth.js
            const { isLoggedIn } = await checkAuthStatus();

            // Only logged in users should be able to create tickets
            if (!isLoggedIn) {
                showMessage('Please log in to create a ticket.');
                return;
            }
            
            // Update the login/register button
            await updateAuthUI();
            
            // Set minimum dates to prevent past date selection
            const today = new Date();
            const todayString = today.toISOString().split('T')[0]; // YYYY-MM-DD format
            const now = new Date();
            const nowString = now.toISOString().slice(0, 16); // YYYY-MM-DDTHH:MM format
            
            // Set minimum date for event date (today)
            document.getElementById('date').setAttribute('min', todayString);
            
            // Set minimum datetime for expiration (now)
            document.getElementById('expiration').setAttribute('min', nowString);
            
            // Add event listener to update expiration min date when event date changes
            document.getElementById('date').addEventListener('change', function() {
                const selectedDate = this.value;
                const eventTime = document.getElementById('time').value || '23:59';
                
                if (selectedDate) {
                    // Set expiration max to the event date and time
                    const maxDateTime = selectedDate + 'T' + eventTime;
                    document.getElementById('expiration').setAttribute('max', maxDateTime);
                }
            });
            
            // Update expiration max when time changes
            document.getElementById('time').addEventListener('change', function() {
                const selectedDate = document.getElementById('date').value;
                const selectedTime = this.value;
                
                if (selectedDate && selectedTime) {
                    // Set expiration max to the event date and time
                    const maxDateTime = selectedDate + 'T' + selectedTime;
                    document.getElementById('expiration').setAttribute('max', maxDateTime);
                }
            });

            // Handle form submission
            document.getElementById('ticketForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Double-check authentication before submission
                const authStatus = await checkAuthStatus();
                if (!authStatus.isLoggedIn) {
                    showMessage('Your session has expired. Please log in again to create a ticket.');
                    return;
                }
                
                // Validate dates before submission
                const formData = new FormData(e.target);
                const eventDate = formData.get('date');
                const eventTime = formData.get('time');
                const expirationDateTime = formData.get('expiration');
                
                // Validate event date
                const eventDateObj = new Date(eventDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0); // Reset time to start of day for comparison
                
                if (eventDateObj < today) {
                    alert('Event date cannot be in the past. Please select a future date.');
                    return;
                }
                
                // Validate expiration date
                const expirationDateObj = new Date(expirationDateTime);
                const now = new Date();
                
                if (expirationDateObj <= now) {
                    alert('Expiration date must be in the future.');
                    return;
                }
                
                // Validate that expiration is before or on the event date
                const eventWithTime = new Date(eventDate + 'T' + eventTime);
                if (expirationDateObj > eventWithTime) {
                    alert('Expiration date cannot be after the event date and time.');
                    return;
                }

                const ticketData = {
                    eventName: formData.get('eventName'),
                    date: formData.get('date'),
                    time: formData.get('time'),
                    location: formData.get('location'),
                    imageURL: formData.get('imageURL'),
                    saleType: 'Buy It Now', // Fixed value since all tickets are instant buy
                    currency: formData.get('currency'),
                    price: formData.get('price'),
                    expiration: formData.get('expiration'),
                    csrf_token: localStorage.getItem('csrf_token') // Add CSRF token
                    // Note: We don't include sellerID here since the server will get it from the session
                };

                try {
                    const response = await fetch('backend/create-ticket.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'include', // Important for sending cookies/session data
                        body: JSON.stringify(ticketData)
                    });

                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Ticket created successfully!');
                        e.target.reset();
                        // Redirect to index page
                        window.location.href = 'index.html';
                    } else {
                        alert('Error creating ticket: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while creating the ticket.');
                }
            });
        });
