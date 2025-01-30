<?php

declare(strict_types=1);

/**
 * Micropub Endpoint Implementation
 * 
 * This script handles Micropub requests by validating IndieAuth tokens and processing
 * post submissions. It implements the Micropub specification for accepting posts
 * from Micropub clients.
 * 
 * @license CC0 1.0 Universal (CC0 1.0) Public Domain Dedication
 * @link http://creativecommons.org/publicdomain/zero/1.0/
 */

final class MicropubEndpoint
{
    private const HTTP_OK = 200;
    private const HTTP_CREATED = 201;
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_UNAUTHORIZED = 401;
    private const HTTP_FORBIDDEN = 403;

    private string $mysite;
    private string $tokenEndpoint;
    private array $headers;
    private array $postData;

    /**
     * @param string $mysite Your website URL
     * @param string $tokenEndpoint IndieAuth token endpoint
     * @param array $headers Request headers
     * @param array $postData POST data
     */
    public function __construct(
        string $mysite,
        string $tokenEndpoint,
        array $headers,
        array $postData
    ) {
        $this->mysite = $this->normalizeUrl($mysite);
        $this->tokenEndpoint = $tokenEndpoint;
        $this->headers = $headers;
        $this->postData = $postData;
    }

    /**
     * Process the Micropub request
     */
    public function process(): void
    {
        try {
            $this->validateRequest();
            $tokenData = $this->validateToken();
            $this->validateTokenData($tokenData);
            $this->handleContent();
            
            $this->sendResponse(
                self::HTTP_CREATED,
                ['Location' => $this->mysite]
            );
        } catch (RuntimeException $e) {
            $this->sendError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Validate the incoming request has required fields
     * 
     * @throws RuntimeException if validation fails
     */
    private function validateRequest(): void
    {
        if (!isset($this->headers['Authorization'])) {
            throw new RuntimeException(
                'Missing "Authorization" header.',
                self::HTTP_UNAUTHORIZED
            );
        }

        if (!isset($this->postData['h'])) {
            throw new RuntimeException(
                'Missing "h" value.',
                self::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Validate the IndieAuth token by checking with the token endpoint
     * 
     * @return array Token response data
     * @throws RuntimeException if token validation fails
     */
    private function validateToken(): array
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException(
                'Failed to initialize cURL',
                self::HTTP_BAD_REQUEST
            );
        }

        $options = [
            CURLOPT_URL => $this->tokenEndpoint,
            CURLOPT_HTTPGET => true,
            CURLOPT_USERAGENT => $this->mysite,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'Content-type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->headers['Authorization']
            ]
        ];

        curl_setopt_array($curl, $options);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $httpCode !== self::HTTP_OK) {
            throw new RuntimeException(
                'Token validation failed',
                self::HTTP_BAD_REQUEST
            );
        }

        $values = [];
        parse_str((string)$response, $values);
        return $values;
    }

    /**
     * Validate the token response data
     * 
     * @param array $tokenData Token response data
     * @throws RuntimeException if validation fails
     */
    private function validateTokenData(array $tokenData): void
    {
        if (!isset($tokenData['me'])) {
            throw new RuntimeException(
                'Missing "me" value in authentication token.',
                self::HTTP_BAD_REQUEST
            );
        }

        if (!isset($tokenData['scope'])) {
            throw new RuntimeException(
                'Missing "scope" value in authentication token.',
                self::HTTP_BAD_REQUEST
            );
        }

        $normalizedMe = $this->normalizeUrl($tokenData['me']);
        
        if (strcasecmp($normalizedMe, $this->mysite) !== 0) {
            throw new RuntimeException(
                'Mismatching "me" value in authentication token.',
                self::HTTP_FORBIDDEN
            );
        }

        if (!stristr($tokenData['scope'], 'post')) {
            throw new RuntimeException(
                'Missing "post" value in "scope".',
                self::HTTP_FORBIDDEN
            );
        }
    }

    /**
     * Validate and handle the content submission
     * 
     * @throws RuntimeException if content is missing
     */
    private function handleContent(): void
    {
        if (!isset($this->postData['content'])) {
            throw new RuntimeException(
                'Missing "content" value.',
                self::HTTP_BAD_REQUEST
            );
        }

        // Here you would implement your content handling logic
        // such as creating a new entry, storing in a database, etc.
    }

    /**
     * Normalize a URL by ensuring it ends with a trailing slash
     * 
     * @param string $url URL to normalize
     * @return string Normalized URL
     */
    private function normalizeUrl(string $url): string
    {
        return rtrim($url, '/') . '/';
    }

    /**
     * Send an HTTP response
     * 
     * @param int $code HTTP status code
     * @param array $headers Additional headers to send
     */
    private function sendResponse(int $code, array $headers = []): void
    {
        http_response_code($code);
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        exit;
    }

    /**
     * Send an error response
     * 
     * @param int $code HTTP status code
     * @param string $message Error message
     */
    private function sendError(int $code, string $message): void
    {
        $this->sendResponse($code, ['Content-Type' => 'text/plain']);
        echo $message;
        exit;
    }
}

// Configuration
$config = [
    'mysite' => 'https://adactio.com/',  // Change this to your website
    'tokenEndpoint' => 'https://tokens.indieauth.com/token'
];

// Initialize and run the endpoint
$endpoint = new MicropubEndpoint(
    $config['mysite'],
    $config['tokenEndpoint'],
    getallheaders(),
    $_POST
);
$endpoint->process();