document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('create-ticket-form');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const eventName = document.getElementById('event-name').value;
        const date = document.getElementById('date').value;
        const time = document.getElementById('time').value;
        const location = document.getElementById('location').value;
        const price = document.getElementById('price').value;
        const currency = document.getElementById('currency').value;
        const image = document.getElementById('image').value;
        const expiration = document.getElementById('expiration').value;

        // Validate price
        const buyNowPrice = parseFloat(price);
        if (isNaN(buyNowPrice) || buyNowPrice <= 0) {
            alert('Please enter a valid price.');
            return;
        }

        // Create FormData object for sending to the server
        const formData = new FormData();
        formData.append('eventName', eventName);
        formData.append('date', date);
        formData.append('time', time);
        formData.append('location', location);
        formData.append('expiration', expiration);
        formData.append('image', image);
        formData.append('currency', currency);
        formData.append('price', price);
        formData.append('saleType', 'Buy It Now');

        try {
            // Display loading message or spinner here if desired
            
            // Send data to the server
            const response = await fetch('backend/create-ticket.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('Ticket created successfully!');
                console.log('Ticket created with ID:', data.ticketID);
                form.reset();
                
                // Redirect to index.html
                window.location.href = 'index.html';
            } else {
                alert('Error creating ticket: ' + data.message);
                console.error('Server error:', data);
            }
        } catch (error) {
            alert('Error submitting form. Please try again.');
            console.error('Error:', error);
        }
    });

    /* Function to generate sale type badge HTML */
    const getSaleTypeBadge = (saleType) => {
        return `<span style="color: green;">Buy It Now</span>`;
    };
});