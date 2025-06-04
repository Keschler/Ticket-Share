<?php
// Rate limiting functionality to prevent brute force attacks

function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) { // 15 minutes
    session_start();
    
    $currentTime = time();
    $rateLimitKey = 'rate_limit_' . $identifier;
    
    // Initialize or get existing attempts data
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [
            'attempts' => 0,
            'first_attempt' => $currentTime,
            'locked_until' => 0
        ];
    }
    
    $attempts = &$_SESSION[$rateLimitKey];
    
    // Check if still locked
    if ($attempts['locked_until'] > $currentTime) {
        $remainingTime = $attempts['locked_until'] - $currentTime;
        return [
            'allowed' => false,
            'message' => "Too many failed attempts. Please try again in " . ceil($remainingTime / 60) . " minutes.",
            'remaining_time' => $remainingTime
        ];
    }
    
    // Reset if time window has passed
    if ($currentTime - $attempts['first_attempt'] > $timeWindow) {
        $attempts['attempts'] = 0;
        $attempts['first_attempt'] = $currentTime;
        $attempts['locked_until'] = 0;
    }
    
    // Check if max attempts reached
    if ($attempts['attempts'] >= $maxAttempts) {
        $attempts['locked_until'] = $currentTime + $timeWindow;
        return [
            'allowed' => false,
            'message' => "Too many failed attempts. Please try again in " . ceil($timeWindow / 60) . " minutes.",
            'remaining_time' => $timeWindow
        ];
    }
    
    return [
        'allowed' => true,
        'attempts' => $attempts['attempts'],
        'remaining_attempts' => $maxAttempts - $attempts['attempts'] - 1
    ];
}

function recordFailedAttempt($identifier) {
    session_start();
    
    $rateLimitKey = 'rate_limit_' . $identifier;
    
    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [
            'attempts' => 0,
            'first_attempt' => time(),
            'locked_until' => 0
        ];
    }
    
    $_SESSION[$rateLimitKey]['attempts']++;
}

function resetRateLimit($identifier) {
    session_start();
    
    $rateLimitKey = 'rate_limit_' . $identifier;
    unset($_SESSION[$rateLimitKey]);
}
?>
