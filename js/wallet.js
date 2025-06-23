        // Check if user is logged in using session-based auth
        async function checkAuth() {
            const { isLoggedIn } = await checkAuthStatus();
            if (!isLoggedIn) {
                showMessage('Please log in to access your wallet', 'error');
                return false;
            }
            return true;
        }

        // Update login/register button
        document.addEventListener('DOMContentLoaded', async () => {
            await updateAuthUI();
        });

        // Load user balance
        async function loadBalance() {
            if (!await checkAuth()) return;
            try {
                const response = await fetch(`backend/manage-balance.php`, {
                    credentials: 'include'
                });
                
                console.log('Balance response status:', response.status);
                console.log('Balance response headers:', response.headers);
                
                // Check if response is actually JSON
                const contentType = response.headers.get('content-type');
                console.log('Content-Type:', contentType);
                
                let data;
                try {
                    const responseText = await response.text();
                    console.log('Raw response:', responseText);
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error('Server returned invalid JSON: ' + parseError.message);
                }
                
                console.log('Balance response data:', data);
                
                if (data.success) {
                    document.getElementById('balanceAmount').textContent = `$${data.balance}`;
                } else {
                    console.error('Backend balance error:', data.message);
                    console.error('Error details:', data.error_details);
                    
                    let errorMessage = 'Failed to load balance: ' + data.message;
                    if (data.error_details) {
                        errorMessage += ` (${data.error_details.type || 'unknown error'})`;
                    }
                    showMessage(errorMessage, 'error');
                }
            } catch (error) {
                console.error('Network/Parse error loading balance:', error);
                console.error('Error stack:', error.stack);
                showMessage('Failed to load balance. Error: ' + error.message, 'error');
            }
        }

        // Add money to wallet
        async function addMoney() {
            if (!await checkAuth()) return;
            const amount = document.getElementById('depositAmount').value;
            if (!amount || amount <= 0) {
                showMessage('Please enter a valid amount', 'error');
                return;
            }
            if (amount > 1000) {
                showMessage('Maximum deposit amount is $1,000 per transaction', 'error');
                return;
            }
            setLoading(true);
            try {
                const response = await fetch('backend/manage-balance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        amount: parseFloat(amount),
                        csrf_token: localStorage.getItem('csrf_token')
                    })
                });
                
                console.log('Add money response status:', response.status);
                
                let data;
                try {
                    const responseText = await response.text();
                    console.log('Add money raw response:', responseText);
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error('Server returned invalid JSON: ' + parseError.message);
                }
                
                console.log('Add money response data:', data);
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('balanceAmount').textContent = `$${data.newBalance}`;
                    document.getElementById('depositAmount').value = '';
                } else {
                    console.error('Backend error:', data.message);
                    console.error('Error details:', data.error_details);
                    
                    let errorMessage = data.message;
                    if (data.error_details) {
                        errorMessage += ` (${data.error_details.type || 'unknown error'})`;
                    }
                    showMessage(errorMessage, 'error');
                }
            } catch (error) {
                console.error('Error adding money:', error);
                console.error('Error stack:', error.stack);
                showMessage('Failed to add money. Error: ' + error.message, 'error');
            } finally {
                setLoading(false);
            }
        }

        // Withdraw money from wallet
        async function withdrawMoney() {
            if (!await checkAuth()) return;
            const amount = document.getElementById('withdrawAmount').value;
            if (!amount || amount <= 0) {
                showMessage('Please enter a valid amount', 'error');
                return;
            }
            if (amount < 1) {
                showMessage('Minimum withdrawal amount is $1.00', 'error');
                return;
            }
            if (!confirm(`Are you sure you want to withdraw $${amount}?`)) {
                return;
            }
            setLoading(true);
            try {
                const response = await fetch('backend/manage-balance.php', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    credentials: 'include',
                    body: JSON.stringify({
                        amount: parseFloat(amount),
                        csrf_token: localStorage.getItem('csrf_token')
                    })
                });
                
                console.log('Withdraw money response status:', response.status);
                
                let data;
                try {
                    const responseText = await response.text();
                    console.log('Withdraw money raw response:', responseText);
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error('Server returned invalid JSON: ' + parseError.message);
                }
                
                console.log('Withdraw money response data:', data);
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    document.getElementById('balanceAmount').textContent = `$${data.newBalance}`;
                    document.getElementById('withdrawAmount').value = '';
                } else {
                    console.error('Backend error:', data.message);
                    console.error('Error details:', data.error_details);
                    
                    let errorMessage = data.message;
                    if (data.error_details) {
                        errorMessage += ` (${data.error_details.type || 'unknown error'})`;
                    }
                    showMessage(errorMessage, 'error');
                }
            } catch (error) {
                console.error('Error withdrawing money:', error);
                console.error('Error stack:', error.stack);
                showMessage('Failed to withdraw money. Error: ' + error.message, 'error');
            } finally {
                setLoading(false);
            }
        }

        // Show message to user
        function showMessage(message, type) {
            const container = document.getElementById('messageContainer');
            container.innerHTML = `<div class="message ${type}">${message}</div>`;
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    container.innerHTML = '';
                }, 5000);
            }
        }

        // Set loading state
        function setLoading(loading) {
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                if (loading) {
                    card.classList.add('loading');
                } else {
                    card.classList.remove('loading');
                }
            });
        }

        // Handle Enter key presses
        document.getElementById('depositAmount').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                addMoney();
            }
        });

        document.getElementById('withdrawAmount').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                withdrawMoney();
            }
        });

        async function loadTransactions() {
            if (!await checkAuth()) return;
            try {
                const response = await fetch('backend/transactions.php', {
                    credentials: 'include'
                });
                const data = await response.json();

                if (data.success) {
                    const tbody = document.getElementById('transactionBody');
                    tbody.innerHTML = '';
                    data.transactions.forEach(tx => {
                        const row = document.createElement('tr');
                        row.innerHTML = `<td>${tx.time}</td><td>${tx.type}</td><td>$${tx.amount}</td>`;
                        tbody.appendChild(row);
                    });
                } else {
                    showMessage('Failed to load transactions: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
                showMessage('Failed to load transactions. Error: ' + error.message, 'error');
            }
        }

        // Load balance and transactions when page loads
        window.addEventListener('load', () => {
            updateAuthUI(); // Update login/logout button
            loadBalance();
            loadTransactions();
        });
