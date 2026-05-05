<?php
// ============================================
// Google Sheets 連携（Google Apps Script 経由）
// ============================================
require_once __DIR__ . '/config.php';

/**
 * お問い合わせデータをスプレッドシートに記録
 */
function appendToContactSheet($data) {
    $scriptUrl = GOOGLE_SCRIPT_CONTACT_URL;
    if (empty($scriptUrl)) {
        return ['success' => false, 'reason' => 'GOOGLE_SCRIPT_CONTACT_URL not configured'];
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
    $timestamp = $now->format('Y/m/d H:i:s');

    $row = [
        $timestamp,
        isset($data['type']) ? $data['type'] : 'お問い合わせ',
        isset($data['company']) ? $data['company'] : '',
        isset($data['department']) ? $data['department'] : '',
        isset($data['customerName']) ? $data['customerName'] : '',
        isset($data['customerKana']) ? $data['customerKana'] : '',
        isset($data['email']) ? $data['email'] : '',
        isset($data['phone']) ? $data['phone'] : '',
        isset($data['zip']) ? $data['zip'] : '',
        isset($data['address']) ? $data['address'] : '',
        isset($data['scanTarget']) ? $data['scanTarget'] : '',
        isset($data['quantity']) ? $data['quantity'] : '',
        isset($data['deadline']) ? $data['deadline'] : '',
        isset($data['budget']) ? $data['budget'] : '',
        isset($data['freeText']) ? $data['freeText'] : '',
        !empty($data['hasAttachment']) ? 'あり' : 'なし'
    ];

    return postToGoogleScript($scriptUrl, $row);
}

/**
 * Google Apps Script にPOSTでデータを送信する共通関数
 */
function postToGoogleScript($scriptUrl, $row) {
    $payload = json_encode(['values' => $row]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $scriptUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Google Sheets cURLエラー: ' . $curlError);
        return ['success' => false, 'reason' => $curlError];
    }

    if ($httpCode >= 200 && $httpCode < 400) {
        return ['success' => true];
    }

    error_log("Google Sheets HTTPエラー ({$httpCode}): {$response}");
    return ['success' => false, 'reason' => "HTTP {$httpCode}"];
}