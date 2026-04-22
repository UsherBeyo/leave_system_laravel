<?php

class Mail {
    /**
     * Simple wrapper around PHP mail().  In real deployments replace with PHPMailer/SwiftMailer.
     */
    public static function send($to, $subject, $body, $headers = '') {
        // default headers
        if (empty($headers)) {
            $headers = "From: noreply@company.com\r\n" .
                       "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        // you may add SMTP configuration in php.ini or use a library
        return mail($to, $subject, $body, $headers);
    }
}
