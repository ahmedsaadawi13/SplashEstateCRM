<?php
// FILE: /app/helpers/SMTPMailer.php

/**
 * SplashEstate CRM - SMTP Mailer
 * Standalone SMTP email implementation (no external dependencies)
 */

class SMTPMailer {

    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $timeout = 30;
    private $socket;
    private $debug = false;

    /**
     * Constructor
     */
    public function __construct($config = array()) {
        $this->host = isset($config['host']) ? $config['host'] : MAIL_HOST;
        $this->port = isset($config['port']) ? $config['port'] : MAIL_PORT;
        $this->username = isset($config['username']) ? $config['username'] : MAIL_USERNAME;
        $this->password = isset($config['password']) ? $config['password'] : MAIL_PASSWORD;
        $this->encryption = isset($config['encryption']) ? $config['encryption'] : MAIL_ENCRYPTION;
        $this->debug = isset($config['debug']) ? $config['debug'] : false;
    }

    /**
     * Send email via SMTP
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $from From email
     * @param string $fromName From name
     * @return bool Success status
     */
    public function send($to, $subject, $body, $from = null, $fromName = null) {
        $from = $from ?: MAIL_FROM_ADDRESS;
        $fromName = $fromName ?: MAIL_FROM_NAME;

        try {
            // Connect to SMTP server
            $this->connect();

            // Send EHLO
            $this->command("EHLO " . $this->host, 250);

            // Authenticate if credentials provided
            if ($this->username && $this->password) {
                $this->authenticate();
            }

            // Send MAIL FROM
            $this->command("MAIL FROM: <$from>", 250);

            // Send RCPT TO
            $this->command("RCPT TO: <$to>", 250);

            // Send DATA command
            $this->command("DATA", 354);

            // Build email headers and body
            $headers = $this->buildHeaders($from, $fromName, $to, $subject);
            $message = $headers . "\r\n\r\n" . $body . "\r\n.";

            // Send message
            $this->command($message, 250);

            // Quit
            $this->command("QUIT", 221);

            // Close connection
            $this->disconnect();

            return true;

        } catch (Exception $e) {
            $this->log("SMTP Error: " . $e->getMessage());
            $this->disconnect();
            return false;
        }
    }

    /**
     * Connect to SMTP server
     */
    private function connect() {
        $errno = 0;
        $errstr = '';

        // Determine connection string
        if ($this->encryption === 'ssl') {
            $host = 'ssl://' . $this->host;
        } elseif ($this->encryption === 'tls') {
            $host = $this->host; // TLS is initiated after connection
        } else {
            $host = $this->host;
        }

        // Open socket connection
        $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
        }

        // Set timeout
        stream_set_timeout($this->socket, $this->timeout);

        // Get server response
        $response = $this->getResponse();
        if (substr($response, 0, 3) !== '220') {
            throw new Exception("SMTP connection failed: " . $response);
        }

        // Start TLS if needed
        if ($this->encryption === 'tls') {
            $this->command("STARTTLS", 220);

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption");
            }
        }
    }

    /**
     * Authenticate with SMTP server
     */
    private function authenticate() {
        // Send AUTH LOGIN
        $this->command("AUTH LOGIN", 334);

        // Send username (base64 encoded)
        $this->command(base64_encode($this->username), 334);

        // Send password (base64 encoded)
        $this->command(base64_encode($this->password), 235);
    }

    /**
     * Send SMTP command
     */
    private function command($command, $expectedCode) {
        $this->log(">> " . $command);

        fwrite($this->socket, $command . "\r\n");
        $response = $this->getResponse();

        if (substr($response, 0, 3) != $expectedCode) {
            throw new Exception("SMTP command failed: $command | Response: $response");
        }

        return $response;
    }

    /**
     * Get response from SMTP server
     */
    private function getResponse() {
        $response = '';

        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            $this->log("<< " . trim($line));

            // Check if this is the last line
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        return $response;
    }

    /**
     * Build email headers
     */
    private function buildHeaders($from, $fromName, $to, $subject) {
        $headers = array();

        $headers[] = "From: $fromName <$from>";
        $headers[] = "To: <$to>";
        $headers[] = "Subject: " . $this->encodeHeader($subject);
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset=UTF-8";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "Date: " . date('r');
        $headers[] = "Message-ID: <" . md5(uniqid(time())) . "@" . $this->host . ">";
        $headers[] = "X-Mailer: SplashEstate CRM";

        return implode("\r\n", $headers);
    }

    /**
     * Encode header for non-ASCII characters
     */
    private function encodeHeader($text) {
        if (preg_match('/[^\x20-\x7E]/', $text)) {
            return "=?UTF-8?B?" . base64_encode($text) . "?=";
        }
        return $text;
    }

    /**
     * Disconnect from SMTP server
     */
    private function disconnect() {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Log debug messages
     */
    private function log($message) {
        if ($this->debug) {
            error_log("SMTPMailer: " . $message);
        }
    }

    /**
     * Test SMTP connection
     * @return array Test result
     */
    public function testConnection() {
        try {
            $this->connect();

            if ($this->username && $this->password) {
                $this->authenticate();
            }

            $this->command("QUIT", 221);
            $this->disconnect();

            return array(
                'success' => true,
                'message' => 'SMTP connection successful'
            );

        } catch (Exception $e) {
            $this->disconnect();
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}
