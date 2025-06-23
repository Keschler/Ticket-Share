        document.addEventListener('DOMContentLoaded', () => {
            // Handle login/register button
            const username = localStorage.getItem('profileName') || localStorage.getItem('username');
            const userId = localStorage.getItem('userId');
            const loginRegisterBtn = document.getElementById('login-register-btn');
            
            // Always show Login / Register button
            loginRegisterBtn.textContent = 'Login / Register';
            loginRegisterBtn.onclick = function() {
                window.location.href = 'login.html';
            };
            
            // Registration form handling
            const registerForm = document.getElementById('register-form');
            const alertMessage = document.getElementById('alert-message');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            
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
            
            // Validate password match
            confirmPasswordInput.addEventListener('input', () => {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.setCustomValidity("Passwords don't match");
                } else {
                    confirmPasswordInput.setCustomValidity('');
                }
            });
            
            // Handle form submission
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                // Display a loading message
                showAlert('Processing registration...', 'info');
                
                // Additional validation
                if (passwordInput.value !== confirmPasswordInput.value) {
                    showAlert("Passwords don't match", 'danger');
                    return;
                }
                
                // Get form data
                const formData = new FormData(registerForm);
                
                try {
                    // Log what we're sending
                    console.log("Sending form data:", Object.fromEntries(formData));
                    
                    
                    // Send registration request
                    const response = await fetch('backend/register.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    // Get response text first for debugging
                    const responseText = await response.text();
                    console.log("Raw server response:", responseText);
                    
                    // Try to parse it as JSON
                    try {
                        // If the response is empty, show specific error
                        if (!responseText.trim()) {
                            showAlert('Server returned an empty response. This likely indicates a PHP error.', 'danger');
                            console.error("Empty response received. Check server PHP logs for errors.");
                            return;
                        }
                        
                        const data = JSON.parse(responseText);
                        console.log("Parsed response:", data);
                        
                        if (data.success) {
                            // Show success message
                            showAlert('Registration successful! Redirecting to login page...', 'success');
                            
                            // Store initial profile data in localStorage
                            if (data.profile) {
                                localStorage.setItem('profileName', data.profile.username);
                                localStorage.setItem('userEmail', data.profile.email);
                            }
                            
                            // Redirect to login page after 2 seconds
                            setTimeout(() => {
                                window.location.href = 'login.html';
                            }, 2000);
                        } else {
                            // Show error message from server
                            showAlert(data.message || 'Registration failed. Please try again.', 'danger');
                            
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
                        
                        // Add additional debug information
                        console.error("Response could not be parsed as JSON:", responseText);
                        showAlert('Server response is not valid JSON. This could indicate a PHP error.', 'danger');
                    }
                } catch (networkError) {
                    console.error("Network error:", networkError);
                    showAlert('Connection error. Please check your internet connection.', 'danger');
                }
            });
        });
