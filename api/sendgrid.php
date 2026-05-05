<?php
// ============================================
// SendGrid メール送信（cURL使用・外部ライブラリ不要）
// ============================================
require_once __DIR__ . '/config.php';

/**
 * SendGrid Web API v3 でメールを送信する
 *
 * @param string $to 送信先メールアドレス
 * @param string $subject 件名
 * @param string $textContent 本文（プレーンテキスト）
 * @param array $attachments 添付ファイル配列 [['content'=>base64, 'filename'=>名前, 'type'=>MIMEタイプ], ...]
 * @return array ['success' => bool, 'message' => string]
 */
function sendMail($to, $subject, $textContent, $attachments = []) {
    $payload = [
        'personalizations' => [
            [
                'to' => [['email' => $to]]
            ]
        ],
        'from' => ['email' => SENDGRID_FROM_EMAIL],
        'subject' => $subject,
        'content' => [
            [
                'type' => 'text/plain',
                'value' => $textContent
            ]
        ],
        'tracking_settings' => [
            'click_tracking' => ['enable' => false],
            'open_tracking' => ['enable' => false]
        ]
    ];

    if (!empty($attachments)) {
        $payload['attachments'] = $attachments;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.sendgrid.com/v3/mail/send',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . SENDGRID_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => 'cURLエラー: ' . $curlError];
    }

    // SendGrid は 202 Accepted で成功
    if ($httpCode === 202) {
        return ['success' => true, 'message' => '送信成功'];
    }

    return [
        'success' => false,
        'message' => "SendGridエラー (HTTP {$httpCode}): " . ($response ?: '応答なし')
    ];
}