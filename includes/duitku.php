<?php
declare(strict_types=1);

function duitku_configured(): bool
{
    return (bool) (env_value('API_KEY') && (env_value('MERCHANT') ?: env_value('MERHANT')));
}

function duitku_base_url(): string
{
    return strtolower((string) env_value('MODE', 'sandbox')) === 'production'
        ? 'https://passport.duitku.com'
        : 'https://sandbox.duitku.com';
}

function duitku_signature(string $value, ?string $mode = null): string
{
    $mode = strtolower($mode ?: (string) env_value('DUITKU_SIGNATURE_MODE', 'hmac'));
    return $mode === 'md5' ? md5($value . env_value('API_KEY')) : hash_hmac('sha256', $value, (string) env_value('API_KEY'));
}

function duitku_post_json(string $endpoint, array $payload): array
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $curl = curl_init(duitku_base_url() . $endpoint);
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $response = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($response === false || $curlError !== '') throw new RuntimeException('Payment gateway tidak dapat dihubungi.');
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) throw new RuntimeException('Respons payment gateway tidak valid.');
    $decoded['_http_code'] = $httpCode;
    return $decoded;
}

function duitku_public_url(string $path): string
{
    $configured = env_value('DUITKU_' . strtoupper($path === 'duitku-callback.php' ? 'CALLBACK_URL' : 'RETURN_URL'));
    if ($configured) return $configured;
    $configured = $path === 'duitku-callback.php' ? env_value('CALLBACK_URL') : null;
    if ($configured) return $configured;
    $base = rtrim((string) env_value('APP_URL', ''), '/');
    if ($base !== '') return $base . '/' . ltrim($path, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return $scheme . '://' . ($host ?: 'localhost') . url($path);
}

function duitku_request(string $endpoint, array $payload): array
{
    if (!duitku_configured()) throw new RuntimeException('Payment gateway belum dikonfigurasi dengan lengkap.');
    $merchantCode = env_value('MERCHANT') ?: env_value('MERHANT');
    $apiKey = env_value('API_KEY');
    $payload['merchantCode'] = $merchantCode;
    $signingText = $merchantCode . $payload['merchantOrderId'] . $payload['paymentAmount'];
    $mode = strtolower((string) env_value('DUITKU_SIGNATURE_MODE', 'hmac'));
    $payload['signature'] = duitku_signature($signingText, $mode);
    $decoded = duitku_post_json($endpoint, $payload);
    $message = (string) ($decoded['statusMessage'] ?? $decoded['responseMessage'] ?? $decoded['Message'] ?? $decoded['message'] ?? '');
    $responseCode = (string) ($decoded['statusCode'] ?? $decoded['responseCode'] ?? '00');
    if ((($decoded['_http_code'] ?? 200) === 401 || $responseCode !== '00') && stripos($message, 'signature') !== false) {
        $payload['signature'] = duitku_signature($signingText, $mode === 'md5' ? 'hmac' : 'md5');
        $decoded = duitku_post_json($endpoint, $payload);
    }
    $httpCode = (int) ($decoded['_http_code'] ?? 0);
    unset($decoded['_http_code']);
    if ($httpCode < 200 || $httpCode >= 300) throw new RuntimeException('Duitku: ' . ($decoded['statusMessage'] ?? $decoded['responseMessage'] ?? $decoded['Message'] ?? $decoded['message'] ?? 'transaksi ditolak oleh gateway.'));
    return $decoded;
}

function duitku_payment_methods(int $amount): array
{
    $merchantCode = env_value('MERCHANT') ?: env_value('MERHANT');
    $datetime = date('Y-m-d H:i:s');
    $payload = ['merchantcode' => $merchantCode, 'amount' => $amount, 'datetime' => $datetime, 'signature' => duitku_signature($merchantCode . $amount . $datetime)];
    $response = duitku_post_json('/webapi/api/merchant/paymentmethod/getpaymentmethod', $payload);
    if (($response['statusCode'] ?? $response['responseCode'] ?? '') !== '00') throw new RuntimeException('Duitku: ' . ($response['statusMessage'] ?? $response['responseMessage'] ?? 'metode pembayaran tidak tersedia.'));
    $methods = $response['paymentMethods'] ?? $response['paymentMethod'] ?? $response['paymentFee'] ?? [];
    return is_array($methods) ? array_values(array_filter($methods, static fn ($method) => is_array($method) && !empty($method['paymentMethod'] ?? $method['paymentCode'] ?? null))) : [];
}

function duitku_create_invoice(array $transaction, array $cart, string $paymentMethod): array
{
    $user = current_user() ?? [];
    $items = [];
    $itemTotal = 0;
    foreach ($cart as $row) {
        $price = (int) round((float) $row['harga']);
        $quantity = (int) $row['qty'];
        $lineTotal = $price * $quantity;
        $itemTotal += $lineTotal;
        // Duitku validates the sum of itemDetails.price (not price * quantity).
        // Send the line total while retaining quantity for display.
        $items[] = ['name' => mb_substr((string) $row['nama'], 0, 100), 'price' => $lineTotal, 'quantity' => $quantity];
    }
    if ($itemTotal !== (int) round((float) $transaction['total'])) {
        throw new RuntimeException('Total pembayaran berubah. Silakan buat ulang keranjang.');
    }
    $name = mb_substr((string) ($user['nama'] ?? 'Pelanggan'), 0, 20);
    $payload = [
        'paymentAmount' => $itemTotal,
        'merchantOrderId' => $transaction['nomor_transaksi'],
        'productDetails' => 'Pembelian di TokoKu',
        'paymentMethod' => $paymentMethod,
        'merchantUserInfo' => mb_substr((string) ($user['username'] ?? ''), 0, 100),
        'customerVaName' => $name ?: 'Pelanggan',
        'email' => env_value('DUITKU_CUSTOMER_EMAIL', 'customer@tokoku.id'),
        'phoneNumber' => env_value('DUITKU_CUSTOMER_PHONE', ''),
        'itemDetails' => $items,
        'callbackUrl' => duitku_public_url('duitku-callback.php'),
        'returnUrl' => duitku_public_url('duitku-return.php'),
        'expiryPeriod' => 60,
    ];
    $response = duitku_request('/webapi/api/merchant/v2/inquiry', $payload);
    if (($response['statusCode'] ?? $response['responseCode'] ?? '') !== '00' || empty($response['paymentUrl'])) {
        throw new RuntimeException('Duitku: ' . ($response['statusMessage'] ?? $response['responseMessage'] ?? 'transaksi ditolak oleh gateway.'));
    }
    return ['reference' => (string) ($response['reference'] ?? ''), 'payment_url' => (string) $response['paymentUrl']];
}

function duitku_verify_callback(array $data): bool
{
    $merchantCode = env_value('MERCHANT') ?: env_value('MERHANT');
    $apiKey = env_value('API_KEY');
    $signature = (string) ($data['signature'] ?? '');
    $text = $merchantCode . ($data['amount'] ?? '') . ($data['merchantOrderId'] ?? '');
    $hmac = duitku_signature($text, 'hmac');
    $md5 = duitku_signature($text, 'md5');
    return $signature !== '' && (hash_equals(strtolower($hmac), strtolower($signature)) || hash_equals(strtolower($md5), strtolower($signature)));
}

function duitku_status(string $merchantOrderId): array
{
    $merchantCode = env_value('MERCHANT') ?: env_value('MERHANT');
    $apiKey = env_value('API_KEY');
    $payload = ['merchantCode' => $merchantCode, 'merchantOrderId' => $merchantOrderId];
    $payload['signature'] = hash_hmac('sha256', $merchantCode . $merchantOrderId, $apiKey);
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $curl = curl_init(duitku_base_url() . '/webapi/api/merchant/transactionStatus');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $json, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    $response = curl_exec($curl); $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    $decoded = json_decode((string) $response, true);
    if ($response === false || $httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) throw new RuntimeException('Status payment gateway tidak dapat diperiksa.');
    return $decoded;
}
