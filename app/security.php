<?php
/**
 * Security helper functions.
 * Provides security headers and other security-related utilities.
 */

namespace App\security;

/**
 * Sends security headers to protect against common attacks.
 * Should be called early in request processing, before any output.
 *
 * @return void
 */
function send_security_headers(): void
{
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Control referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // XSS protection for older browsers
    header('X-XSS-Protection: 1; mode=block');
    
    // Basic permissions policy (disable sensitive features)
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}
