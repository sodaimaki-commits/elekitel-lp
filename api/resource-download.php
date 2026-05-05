<?php
// ============================================
// 資料ダウンロードフォーム処理
// ============================================
require_once __DIR__ . '/sendgrid.php';

// CORS
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

try {
    // JSONデータの取得
    $input = json_decode(file_get_contents('php://input'), true);

    $companyName  = isset($input['companyName']) ? trim($input['companyName']) : '';
    $name         = isset($input['name']) ? trim($input['name']) : '';
    $phone        = isset($input['phone']) ? trim($input['phone']) : '';
    $email        = isset($input['email']) ? trim($input['email']) : '';
    $resourceName = isset($input['resourceName']) ? trim($input['resourceName']) : 'サービス資料';

    // 必須項目チェック
    if (empty($name) || empty($email) || empty($phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '必須項目が入力されていません']);
        exit;
    }

    // 使い捨てメールアドレスのチェック
    $disposableDomains = [
        'mailinator.com', 'guerrillamail.com', 'temp-mail.org',
        '10minutemail.com', 'trashmail.com', 'yopmail.com'
    ];
    $emailDomain = strtolower(explode('@', $email)[1] ?? '');
    if (in_array($emailDomain, $disposableDomains)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '使い捨てメールアドレスはご利用いただけません']);
        exit;
    }

    // メールアドレス形式チェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '正しいメールアドレスを入力してください']);
        exit;
    }

    // ============================================
    // 資料PDFの取得と検証
    // ============================================
    if (strpos($resourceName, '事例') !== false) {
        $pdfPath = RESOURCE_PDF_CASESTUDY;
    } else {
        $pdfPath = RESOURCE_PDF_SERVICE;
    }

    if (!file_exists($pdfPath)) {
        error_log('資料PDFファイルが見つかりません: ' . $pdfPath);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => '資料の準備中です。しばらくお待ちください']);
        exit;
    }

    $pdfBase64 = base64_encode(file_get_contents($pdfPath));

    // 資料ラベルの決定
    if (strpos($resourceName, '事例') !== false) {
        $resourceLabel = '事例資料';
    } else {
        $resourceLabel = 'サービス資料';
    }

    // ============================================
    // 宛名の組み立て
    // ============================================
    $greeting = '';
    if (!empty($companyName)) {
        $greeting .= "{$companyName}\n";
    }
    $greeting .= "{$name} 様";

    // ============================================
    // お客様向けサンクスメール
    // ============================================
    $customerEmailContent = "{$greeting}

株式会社エレキテル スキャンプロです。
この度は当社ホームページより資料のダウンロードを
お申し込みいただき、誠にありがとうございます。

ご請求いただきました「{$resourceLabel}」を
添付ファイルにてお送りいたします。
ぜひご活用いただければ幸いです。

詳しいご説明やお見積もりも承っておりますので、
ご検討の際はお気軽にご連絡ください。

今後ともスキャンプロをよろしくお願いいたします。


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
　ダウンロード資料：{$resourceLabel}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━


**************************************************
　■ お気軽にお電話ください ■

　〒458-0044
　愛知県名古屋市緑区池上台2-21
　株式会社エレキテル スキャンプロ

　Tel　052-842-9697
　Fax　052-893-8729
　Mobile　080-3554-3427

　https://scan-pro.jp/
**************************************************
";

    // 添付ファイル名の決定
    $attachmentFilename = ($resourceLabel === '事例資料')
        ? 'スキャンプロ_導入事例資料.pdf'
        : 'スキャンプロ_サービス資料.pdf';

    $customerAttachments = [
        [
            'content'     => $pdfBase64,
            'filename'    => $attachmentFilename,
            'type'        => 'application/pdf',
            'disposition' => 'attachment'
        ]
    ];

    // ============================================
    // 社内向け通知メール
    // ============================================
    $companyEmailContent = "【{$resourceLabel}】ダウンロード申込みがありました。

--------------------------------------
ダウンロード資料：{$resourceLabel}

会社名：{$companyName}
お名前：{$name}
電話番号：{$phone}
メールアドレス：{$email}
--------------------------------------
このメールは スキャンプロ (https://scan-pro.jp) の資料ダウンロードフォームから送信されました
";

    // ============================================
    // メール送信
    // ============================================
    // お客様向け
    $result1 = sendMail(
        $email,
        "【{$resourceLabel}】ダウンロード資料をお送りいたします - スキャンプロ",
        $customerEmailContent,
        $customerAttachments
    );

    $result2 = sendMail(
        COMPANY_EMAIL,
        "【{$resourceLabel}】資料ダウンロード申込み：{$name}様（{$companyName}）",
        $companyEmailContent
    );

    if (!$result1['success'] || !$result2['success']) {
        $errorMsg = '';
        if (!$result1['success']) $errorMsg .= 'お客様メール: ' . $result1['message'] . ' ';
        if (!$result2['success']) $errorMsg .= '社内メール: ' . $result2['message'];
        error_log('メール送信エラー: ' . $errorMsg);

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'メール送信に失敗しました。お手数ですが、お電話でご連絡ください。'
        ]);
        exit;
    }

    // 成功
    echo json_encode([
        'success' => true,
        'message' => '資料をお送りしました。メールをご確認ください。'
    ]);

} catch (Exception $e) {
    error_log('資料ダウンロード処理エラー: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'メール送信に失敗しました。お手数ですが、お電話でご連絡ください。'
    ]);
}