<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - Ticket Share</title>
    <!--  ---------  CSS (unchanged)  --------- -->
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Verdana,sans-serif;background:linear-gradient(135deg,#2c3e50 0%,#34495e 100%);min-height:100vh;color:#333}
        .header{background:rgba(255,255,255,.1);backdrop-filter:blur(10px);padding:1rem 2rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,.2)}
        .logo{display:flex;align-items:center;gap:10px;text-decoration:none;color:#fff}
        .logo-icon{width:40px;height:40px;background:linear-gradient(45deg,#ff6b6b,#4ecdc4);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:1.2rem}
        .logo-text{font-size:1.5rem;font-weight:bold}
        .nav-buttons{display:flex;gap:1rem}
        .nav-btn{padding:.5rem 1rem;background:rgba(255,255,255,.2);color:#fff;text-decoration:none;border-radius:5px;transition:all .3s ease;border:1px solid rgba(255,255,255,.3)}
        .nav-btn:hover{background:rgba(255,255,255,.3);transform:translateY(-2px)}
        .container{max-width:1200px;margin:2rem auto;padding:0 1rem}
        .favorites-header{text-align:center;margin-bottom:2rem}
        .favorites-title{font-size:3rem;font-weight:bold;color:#fff;text-shadow:2px 2px 4px rgba(0,0,0,.3);margin-bottom:1rem}
        .loading-message,.no-favorites{text-align:center;color:#fff;font-size:1.2rem;margin:2rem 0}
        .no-favorites{background:rgba(255,255,255,.1);padding:2rem;border-radius:15px;backdrop-filter:blur(10px)}
        .tickets-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:2rem}
        .ticket-card{background:rgba(255,255,255,.95);border-radius:15px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.2);transition:all .3s ease;position:relative}
        .ticket-card:hover{transform:translateY(-10px);box-shadow:0 20px 40px rgba(0,0,0,.3)}
        .ticket-card.sold{opacity:0.7;border:3px solid #e74c3c;background:rgba(240,240,240,.95)}
        .ticket-card.sold::before{content:'SOLD';position:absolute;top:15px;right:15px;background:#e74c3c;color:white;padding:8px 15px;border-radius:20px;font-weight:bold;font-size:0.9rem;z-index:10;box-shadow:0 2px 8px rgba(0,0,0,0.3);text-shadow:1px 1px 2px rgba(0,0,0,0.5)}
        .ticket-card.sold:hover{transform:translateY(-3px)}
        .ticket-image{width:100%;height:200px;object-fit:cover;background:linear-gradient(45deg,#f0f0f0,#e0e0e0)}
        .ticket-image.sold{filter:grayscale(50%) brightness(0.8)}
        .ticket-card.sold .ticket-title{color:#666;text-decoration:line-through}
        .ticket-card.sold .ticket-price{color:#999}
        .ticket-content{padding:1.5rem}
        .ticket-title{font-size:1.3rem;font-weight:bold;color:#2c3e50;margin-bottom:.5rem}
        .ticket-info{color:#666;margin-bottom:.5rem;display:flex;align-items:center;gap:.5rem}
        .ticket-price{font-size:1.5rem;font-weight:bold;color:#27ae60;margin:1rem 0}
        .ticket-actions{display:flex;gap:1rem;margin-top:1rem}
        .btn{padding:.75rem 1.5rem;border:none;border-radius:8px;cursor:pointer;font-weight:bold;transition:all .3s ease;flex:1;text-align:center}
        .btn-primary{background:linear-gradient(135deg,#3498db,#2980b9);color:#fff}
        .btn-primary:hover{background:linear-gradient(135deg,#2980b9,#21618c);transform:translateY(-2px)}
        .btn-danger{background:linear-gradient(135deg,#e74c3c,#c0392b);color:#fff}
        .btn-danger:hover{background:linear-gradient(135deg,#c0392b,#a93226);transform:translateY(-2px)}
        .error-message{background:rgba(231,76,60,.9);color:#fff;padding:1rem;border-radius:8px;margin-bottom:1rem;text-align:center}
        .success-message{background:rgba(39,174,96,.9);color:#fff;padding:1rem;border-radius:8px;margin-bottom:1rem;text-align:center}
        @media(max-width:768px){.favorites-title{font-size:2rem}.tickets-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <!-- ---------  HEADER  --------- -->
    <div class="header">
        <a href="index.html" class="logo">
            <div class="logo-icon">TS</div>
            <div class="logo-text">Ticket Share</div>
        </a>
        <div class="nav-buttons">
            <a href="index.html" class="nav-btn">Home</a>
            <a href="favourites.php" class="nav-btn">Favorites</a>
            <a href="purchases.html" class="nav-btn">My Purchases</a>
            <a href="wallet.html" class="nav-btn">Wallet</a>
            <a href="profile/profile.html" class="nav-btn">Profile</a>
            <a href="create-ticket.html" class="nav-btn">Create Ticket</a>
            <a href="login.html" class="nav-btn">Login / Register</a>
        </div>
    </div>

    <!-- ---------  MAIN  --------- -->
    <div class="container">
        <div class="favorites-header">
            <h1 class="favorites-title">❤️ My Favorites</h1>
        </div>

        <div id="messageContainer"></div>
        <div id="loadingMessage" class="loading-message">Loading your favorite tickets...</div>
        <div id="noFavoritesMessage" class="no-favorites" style="display:none">
            <h3>No favorites yet!</h3>
            <p>Start browsing tickets and add them to your favorites by clicking the heart icon.</p><br>
            <a href="index.html" class="btn btn-primary" style="display:inline-block;text-decoration:none">Browse Tickets</a>
        </div>
        <div id="ticketsContainer" class="tickets-grid"></div>
    </div>

    <!-- ---------  SCRIPTS  --------- -->
    <script src="js/auth.js"></script>
    <script>
        /* ----------------  Helpers  ---------------- */
        function showMessage(msg, type='error'){
            const c=document.getElementById('messageContainer');
            c.innerHTML=`<div class="${type}-message">${msg}</div>`;
            if(type==='success')setTimeout(()=>c.innerHTML='',5000);
        }
        async function checkAuth(){
            const {isLoggedIn}=await checkAuthStatus();
            if(!isLoggedIn){
                showMessage('Please log in to view your favorites');
                return false;
            }
            return true;
        }
        document.addEventListener('DOMContentLoaded',updateAuthUI);

        /* -------------  MAIN LOADER  ------------- */
        async function loadFavorites(){
            if(!await checkAuth())return;
            try{
                const res=await fetch('backend/manage-favorites.php',{credentials:'include'});
                const data=await res.json();
                console.log('manage-favorites response',data);
                document.getElementById('loadingMessage').style.display='none';
                if(!data.success){
                    showMessage('Failed: '+data.message);
                    return;
                }
                // -------- normalize favourites --------
                let raw=data.favorites??null;
                let favIds=[]; // numeric ids
                let favTickets=[]; // ticket objects

                if(Array.isArray(raw)){
                    if(raw.length&&typeof raw[0]==='object'){
                        favTickets=raw; // backend already gave full tickets
                    }else{
                        favIds=raw;
                    }
                }else if(typeof raw==='string'){
                    try{favIds=JSON.parse(raw);}catch(e){favIds=raw.split(',').map(n=>+n)}
                }else if(typeof raw==='number'){
                    favIds=[raw];
                }
                if(!favTickets.length&&favIds.length){
                    // Fetch ticket details in parallel
                    const ticketPromises=favIds.map(id=>fetch(`backend/get-ticket.php?ticketId=${id}`)
                        .then(r=>r.json()).catch(()=>null));
                    const ticketResults=await Promise.all(ticketPromises);
                    favTickets=ticketResults.filter(t=>t&&t.success).map(t=>t.ticket);
                }

                if(!favTickets.length){
                    document.getElementById('noFavoritesMessage').style.display='block';
                    return;
                }
                displayFavorites(favTickets);
            }catch(err){
                console.error(err);
                document.getElementById('loadingMessage').style.display='none';
                showMessage('Failed to load favorites. '+err.message);
            }
        }

        /* -------------  UI RENDERING  ------------- */
        function displayFavorites(tickets){
            const container=document.getElementById('ticketsContainer');
            container.innerHTML='';
            tickets.forEach(t=>container.appendChild(createTicketCard(t)));
        }
        function createTicketCard(t){
            const card=document.createElement('div');
            const isSold = t.BuyerID && t.BuyerID !== null;
            card.className = isSold ? 'ticket-card sold' : 'ticket-card';
            card.setAttribute('data-ticket-id', t.TicketID);
            
            const img=t.ImageURL||t.image||'https://via.placeholder.com/350x200?text=Event+Image';
            const buyButtonHtml = isSold 
                ? `<button class="btn btn-primary" disabled style="opacity:0.5;cursor:not-allowed">Sold Out</button>`
                : `<button class="btn btn-primary" onclick="buyTicket(${t.TicketID},${+t.Price||+t.price})">Buy Ticket</button>`;
            
            card.innerHTML=`
                <img src="${img}" alt="${t.TicketName||t.eventName}" class="ticket-image ${isSold ? 'sold' : ''}" onerror="this.src='https://via.placeholder.com/350x200?text=Event+Image'">
                <div class="ticket-content">
                    <h3 class="ticket-title">${t.TicketName||t.eventName}</h3>
                    <div class="ticket-info">📅 ${(t.Date||t.date)} at ${(t.Time||t.time)}</div>
                    <div class="ticket-info">📍 ${(t.Location||t.location)}</div>
                    <div class="ticket-info">👤 Seller: ${t.SellerName||t.sellerName||'Unknown'}</div>
                    ${isSold ? '<div class="ticket-info" style="color:#e74c3c;font-weight:bold">❌ This ticket has been sold</div>' : ''}
                    <div class="ticket-price">$${(+t.Price||+t.price).toFixed(2)}</div>
                    <div class="ticket-actions">
                        ${buyButtonHtml}
                        <button class="btn btn-danger" onclick="removeFromFavorites(${t.TicketID})">Remove ❤️</button>
                    </div>
                </div>`;
            return card;
        }

        /* -------------  ACTIONS  ------------- */
        async function removeFromFavorites(id){
            if(!await checkAuth())return;
            
            // Immediately update UI to prevent flickering
            const cardToRemove = document.querySelector(`[data-ticket-id="${id}"]`);
            if(cardToRemove) {
                cardToRemove.style.opacity = '0.5';
                cardToRemove.style.pointerEvents = 'none';
            }
            
            try{
                const r=await fetch('backend/manage-favorites.php',{
                    method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({action:'remove',ticketId:id,csrf_token:localStorage.getItem('csrf_token')})
                });
                const d=await r.json();
                
                if(d.success) {
                    showMessage('Removed from favorites','success');
                    // Smooth removal animation
                    if(cardToRemove) {
                        cardToRemove.style.transition = 'all 0.3s ease';
                        cardToRemove.style.transform = 'scale(0.8)';
                        cardToRemove.style.opacity = '0';
                        setTimeout(() => {
                            cardToRemove.remove();
                            // Check if no favorites left
                            if(document.getElementById('ticketsContainer').children.length === 0) {
                                document.getElementById('noFavoritesMessage').style.display = 'block';
                            }
                        }, 300);
                    }
                } else {
                    showMessage('Failed: '+d.message);
                    // Restore card if removal failed
                    if(cardToRemove) {
                        cardToRemove.style.opacity = '1';
                        cardToRemove.style.pointerEvents = 'auto';
                    }
                }
            }catch(e){
                showMessage('Error removing favorite');
                // Restore card if error occurred
                if(cardToRemove) {
                    cardToRemove.style.opacity = '1';
                    cardToRemove.style.pointerEvents = 'auto';
                }
            }
        }
        async function buyTicket(id,price){
            if(!await checkAuth())return;
            if(!confirm(`Buy for $${(+price).toFixed(2)}?`))return;
            
            // Immediately update UI to show purchase in progress
            const cardToBuy = document.querySelector(`[data-ticket-id="${id}"]`);
            const buyButton = cardToBuy?.querySelector('.btn-primary');
            if(buyButton) {
                buyButton.disabled = true;
                buyButton.textContent = 'Processing...';
                buyButton.style.opacity = '0.7';
            }
            
            try{
                const r=await fetch('backend/buy-ticket.php',{
                    method:'POST',credentials:'include',headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({ticketId:id,csrf_token:localStorage.getItem('csrf_token')})
                });
                const d=await r.json();
                
                if(d.success) {
                    showMessage(d.message,'success');
                    // Update card to show as sold without full reload
                    if(cardToBuy) {
                        cardToBuy.classList.add('sold');
                        const img = cardToBuy.querySelector('.ticket-image');
                        if(img) img.classList.add('sold');
                        const title = cardToBuy.querySelector('.ticket-title');
                        if(title) title.style.textDecoration = 'line-through';
                        if(buyButton) {
                            buyButton.textContent = 'Sold Out';
                            buyButton.style.cursor = 'not-allowed';
                        }
                        // Add sold indicator
                        if(!cardToBuy.querySelector('::before')) {
                            cardToBuy.style.position = 'relative';
                        }
                    }
                } else {
                    showMessage(d.message);
                    // Restore buy button if purchase failed
                    if(buyButton) {
                        buyButton.disabled = false;
                        buyButton.textContent = 'Buy Ticket';
                        buyButton.style.opacity = '1';
                    }
                }
            }catch(e){
                showMessage('Purchase failed');
                // Restore buy button if error occurred
                if(buyButton) {
                    buyButton.disabled = false;
                    buyButton.textContent = 'Buy Ticket';
                    buyButton.style.opacity = '1';
                }
            }
        }

        /* -------------  INIT  ------------- */
        window.addEventListener('load',loadFavorites);
    </script>
</body>
</html>