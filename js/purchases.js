        document.addEventListener('DOMContentLoaded', () => {
            updateAuthUI();
            loadPurchases();
        });

        async function loadPurchases() {
            try {
                const response = await fetch('backend/ticket-confirmations.php', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });

                const data = await response.json();
                document.getElementById('loadingMessage').style.display = 'none';

                if (data.success) {
                    if (data.confirmations && data.confirmations.length > 0) {
                        displayPurchases(data.confirmations);
                    } else {
                        document.getElementById('noPurchasesMessage').style.display = 'block';
                    }
                } else {
                    showAlert(data.message || 'Failed to load purchases', 'danger');
                }
            } catch (error) {
                console.error('Error loading purchases:', error);
                document.getElementById('loadingMessage').style.display = 'none';
                showAlert('Network error: Unable to load purchases', 'danger');
            }
        }

        function displayPurchases(confirmations) {
            const container = document.getElementById('confirmationsContainer');
            container.innerHTML = '';

            confirmations.forEach(confirmation => {
                const card = createConfirmationCard(confirmation);
                container.appendChild(card);
            });
        }

        function createConfirmationCard(confirmation) {
            const card = document.createElement('div');
            const statusClass = confirmation.CurrentStatus || confirmation.Status;
            card.className = `confirmation-card ${statusClass}`;

            const eventDate = confirmation.Date ? new Date(confirmation.Date).toLocaleDateString() : 'Not set';
            const eventTime = confirmation.Time || 'Not set';
            const price = confirmation.Price ? `${confirmation.Currency || '$'}${parseFloat(confirmation.Price).toFixed(2)}` : 'Not set';

            let statusBadge = '';
            let actionButtons = '';

            switch (statusClass) {
                case 'pending':
                    statusBadge = '<span class="status-badge status-pending">⏳ Awaiting Confirmation</span>';
                    if (confirmation.canConfirm) {
                        actionButtons = `
                            <div class="action-buttons">
                                <button class="btn btn-success" onclick="confirmTicket(${confirmation.TicketID})">
                                    ✅ Confirm Valid
                                </button>
                                <button class="btn btn-danger" onclick="toggleDisputeForm(${confirmation.TicketID})">
                                    ⚠️ Report Issue
                                </button>
                            </div>
                            <div id="disputeForm${confirmation.TicketID}" class="dispute-form">
                                <textarea id="disputeReason${confirmation.TicketID}" class="form-input" 
                                         placeholder="Please describe the issue with this ticket..."></textarea>
                                <div class="action-buttons">
                                    <button class="btn btn-danger" onclick="submitDispute(${confirmation.TicketID})">
                                        Submit Dispute
                                    </button>
                                    <button class="btn" style="background:#6c757d;" onclick="toggleDisputeForm(${confirmation.TicketID})">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        `;
                    }
                    break;
                case 'confirmed':
                    statusBadge = '<span class="status-badge status-confirmed">✅ Confirmed</span>';
                    break;
                case 'disputed':
                    statusBadge = '<span class="status-badge status-disputed">⚠️ Under Dispute</span>';
                    break;
                case 'expired':
                case 'auto_confirmed':
                    statusBadge = '<span class="status-badge status-expired">⏰ Auto-Confirmed</span>';
                    break;
            }

            card.innerHTML = `
                <h3 class="ticket-title">${escapeHtml(confirmation.TicketName || 'Event Ticket')}</h3>
                <div class="ticket-info">📅 ${eventDate} at ${eventTime}</div>
                <div class="ticket-info">📍 ${escapeHtml(confirmation.Location || 'Not specified')}</div>
                <div class="ticket-info">👤 Seller: ${escapeHtml(confirmation.SellerName || 'Unknown')}</div>
                <div class="ticket-info">💰 Price: ${price}</div>
                ${statusBadge}
                ${confirmation.daysRemaining !== undefined ? 
                    `<div class="ticket-info">⏳ ${confirmation.daysRemaining} days remaining to confirm</div>` : 
                    ''}
                ${actionButtons}
            `;

            return card;
        }

        async function confirmTicket(ticketId) {
            if (!confirm('Are you sure you want to confirm this ticket is valid? The seller will receive payment immediately.')) {
                return;
            }

            try {
                const response = await fetch('backend/ticket-confirmations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'confirm',
                        ticketId: ticketId,
                        csrf_token: localStorage.getItem('csrf_token')
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => loadPurchases(), 1500);
                } else {
                    showAlert(data.message || 'Failed to confirm ticket', 'danger');
                }
            } catch (error) {
                console.error('Error confirming ticket:', error);
                showAlert('Network error: Unable to confirm ticket', 'danger');
            }
        }

        async function submitDispute(ticketId) {
            const reason = document.getElementById(`disputeReason${ticketId}`).value.trim();

            if (!reason) {
                showAlert('Please provide a reason for the dispute', 'danger');
                return;
            }

            if (!confirm('Are you sure you want to dispute this ticket? Payment will be held until the dispute is resolved.')) {
                return;
            }

            try {
                const response = await fetch('backend/ticket-confirmations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        action: 'dispute',
                        ticketId: ticketId,
                        reason: reason,
                        csrf_token: localStorage.getItem('csrf_token')
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => loadPurchases(), 1500);
                } else {
                    showAlert(data.message || 'Failed to create dispute', 'danger');
                }
            } catch (error) {
                console.error('Error creating dispute:', error);
                showAlert('Network error: Unable to create dispute', 'danger');
            }
        }

        function toggleDisputeForm(ticketId) {
            const form = document.getElementById(`disputeForm${ticketId}`);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function showAlert(message, type = 'danger') {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            
            container.innerHTML = '';
            container.appendChild(alert);
            
            if (type === 'success') {
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            }
        }

        function escapeHtml(text) {
            if (typeof text !== 'string') return text;
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
