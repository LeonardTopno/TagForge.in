<?php

function razorpay_configured()
{
    $key = trim((string) app_config('razorpay_key_id', ''));
    $secret = trim((string) app_config('razorpay_key_secret', ''));
    return $key !== '' && $secret !== '';
}

function razorpay_api_request($method, $path, $payload = null)
{
    $key = trim((string) app_config('razorpay_key_id', ''));
    $secret = trim((string) app_config('razorpay_key_secret', ''));
    if ($key === '' || $secret === '') {
        throw new RuntimeException('Razorpay is not configured. Add razorpay_key_id and razorpay_key_secret in includes/config.php.');
    }

    $url = 'https://api.razorpay.com/v1/' . ltrim($path, '/');
    $body = $payload !== null ? json_encode($payload) : null;
    $auth = base64_encode($key . ':' . $secret);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialise Razorpay request.');
        }
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Basic ' . $auth,
        );
        $opts = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        );
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Razorpay request failed: ' . $error);
        }
    } else {
        $headerLines = "Content-Type: application/json\r\nAuthorization: Basic {$auth}\r\n";
        $context = stream_context_create(array(
            'http' => array(
                'method' => strtoupper($method),
                'header' => $headerLines,
                'content' => $body !== null ? $body : '',
                'timeout' => 30,
                'ignore_errors' => true,
            ),
        ));
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('Razorpay request failed. Enable the PHP curl extension or allow outbound HTTPS.');
        }
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\\/\\S+\\s+(\\d+)/', $line, $match)) {
                    $status = (int) $match[1];
                    break;
                }
            }
        }
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid Razorpay response.');
    }
    if ($status < 200 || $status >= 300) {
        $detail = isset($data['error']['description']) ? $data['error']['description'] : 'Razorpay API error';
        throw new RuntimeException($detail);
    }
    return $data;
}

function razorpay_create_order($amountInr, $receipt, $notes = array())
{
    return razorpay_api_request('POST', 'orders', array(
        'amount' => (int) $amountInr * 100,
        'currency' => 'INR',
        'receipt' => substr((string) $receipt, 0, 40),
        'payment_capture' => 1,
        'notes' => $notes,
    ));
}

function razorpay_verify_signature($orderId, $paymentId, $signature)
{
    $secret = trim((string) app_config('razorpay_key_secret', ''));
    if ($secret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
        return false;
    }
    $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
    return hash_equals($expected, $signature);
}
