        document.addEventListener('DOMContentLoaded', async () => {
            // Check if already logged in
            const { isLoggedIn } = await checkAuthStatus();
            if (isLoggedIn) {
                window.location.href = 'index.html';
                return;
            }
            
            // Login form handling
            const loginForm = document.getElementById('login-form');
            const alertMessage = document.getElementById('alert-message');
            
            // Show alert message
            function showAlert(message, type) {
                alertMessage.textContent = message;
                alertMessage.className = 'alert';
                alertMessage.classList.add(`alert-${type}`);
                alertMessage.style.display = 'block';
                
                // Auto hide after 5 seconds for success messages
                if (type === 'success') {
                    setTimeout(() => {
                        alertMessage.style.display = 'none';
                    }, 5000);
                }
            }
            
            // Handle form submission
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Display a loading message
                showAlert('Processing login request...', 'info');
                
                // Get form data
                const formData = new FormData(loginForm);
                
                try {
                    // Send login request
                    const response = await fetch('backend/login.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include' // Important for cookies/sessions
                    });
                    
                    // Get response text first for debugging
                    const responseText = await response.text();
                    console.log("Raw server response:", responseText);
                    
                    try {
                        // If the response is empty, show specific error
                        if (!responseText.trim()) {
                            showAlert('Server returned an empty response. This might be a server configuration issue.', 'danger');
                            console.error("Empty server response - common causes include PHP errors or incorrect server configuration");
                            return;
                        }
                        
                        const data = JSON.parse(responseText);
                        console.log("Parsed response:", data);
                        
                        if (data.success) {
                            // Store user data in localStorage for other parts of the app
                            if (data.user) {
                                localStorage.setItem('username', data.user.username);
                                localStorage.setItem('profileName', data.user.username);
                                localStorage.setItem('userId', data.user.id);
                                localStorage.setItem('userEmail', data.user.email);
                            }
                            
                            // Show success message
                            showAlert('Login successful! Redirecting...', 'success');
                            
                            // Redirect to home page after 1 second
                            setTimeout(() => {
                                window.location.href = 'index.html';
                            }, 1000);
                        } else {
                            // Show error message
                            showAlert(data.message || 'Login failed. Please try again.', 'danger');
                            
                            // Log any debug info
                            if (data.debug) {
                                console.log("Server debug info:", data.debug);
                            }
                            if (data.error_details) {
                                console.error("Server error details:", data.error_details);
                            }
                        }
                    } catch (parseError) {
                        console.error("JSON parse error:", parseError);
                        showAlert('Server returned invalid data. Check browser console for details.', 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showAlert('Connection error. Please check your internet connection.', 'danger');
                }
            });
        });
