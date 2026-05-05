<?php
// ============================================
// お問い合わせフォーム処理（LP簡易版：5項目）
// company / name / email / phone / freeText
// ============================================
require_once __DIR__ . '/sendgrid.php';
require_once __DIR__ . '/google-sheets.php';

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
    // multipart/form-data or JSON
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

    if (strpos($contentType, 'multipart/form-data') !== false) {
        $input = $_POST;
    } elseif (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }

    // フィールド取得（新5項目 + 旧互換）
    $company  = isset($input['company'])  ? trim($input['company'])  : '';
    $name     = isset($input['name'])     ? trim($input['name'])     : '';
    $email    = isset($input['email'])    ? trim($input['email'])    : '';
    $phone    = isset($input['phone'])    ? trim($input['phone'])    : '';
    $freeText = isset($input['freeText']) ? trim($input['freeText']) : '';

    // 旧フォーム互換（lastName/firstNameが送られてきたら結合）
    if (empty($name)) {
        $lastName  = isset($input['lastName'])  ? trim($input['lastName'])  : '';
        $firstName = isset($input['firstName']) ? trim($input['firstName']) : '';
        if (!empty($lastName) || !empty($firstName)) {
            $name = trim($lastName . ' ' . $firstName);
        }
    }

    // バリデーション（新フォーム：氏名・メール・電話が必須）
    if (empty($name) || empty($email) || empty($phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '必須項目が入力されていません']);
        exit;
    }

    // メールアドレス形式チェック
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'メールアドレスの形式が正しくありません']);
        exit;
    }

    // ========================================
    // メール本文の組み立て
    // ========================================
    $greeting = '';
    if (!empty($company)) {
        $greeting .= "{$company}\n";
    }
    $greeting .= "{$name} 様";

    // お客様向け返信メール
    $customerSubject = '【スキャンプロ】お問い合わせありがとうございます';
    $customerEmailContent = "{$greeting}

株式会社エレキテル スキャンプロです。
この度は当社ホームページからのお問い合わせ、
誠にありがとうございます。

内容を確認させていただき、
担当より折り返しご連絡いたします。

ご不明な点がございましたら、
お気軽にお問い合わせください。

今後ともスキャンプロをよろしくお願いいたします。


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
　お問い合わせ内容
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

会社名・団体名：" . ($company ?: '（未入力）') . "
お名前：{$name} 様
メールアドレス：{$email}
電話番号：{$phone}

お問い合わせ内容：
" . ($freeText ?: '（未入力）') . "

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

    // 社内向け通知メール
    $companySubject = "【お問い合わせ】{$name}様より";
    $companyEmailContent = "お問い合わせフォームから下記の問い合わせがありました。

--------------------------------------
会社名・団体名: " . ($company ?: '（未入力）') . "
お名前: {$name}
メールアドレス: {$email}
電話番号: {$phone}

お問い合わせ内容:
" . ($freeText ?: '（未入力）') . "

--------------------------------------
このメールは スキャンプロ (https://scan-pro.jp) のお問い合わせフォームから送信されました
";

    // ========================================
    // メール送信
    // ========================================
    $result1 = sendMail($email, $customerSubject, $customerEmailContent);
    $result2 = sendMail(COMPANY_EMAIL, $companySubject, $companyEmailContent);

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

    // ========================================
    // Google スプレッドシートに記録（エラーでも続行）
    // ========================================
    try {
        appendToContactSheet([
            'type'          => 'お問い合わせ',
            'company'       => $company,
            'department'    => '',
            'customerName'  => $name,
            'customerKana'  => '',
            'email'         => $email,
            'phone'         => $phone,
            'zip'           => '',
            'address'       => '',
            'scanTarget'    => '',
            'quantity'      => '',
            'deadline'      => '',
            'budget'        => '',
            'freeText'      => $freeText,
            'hasAttachment' => false
        ]);
    } catch (Exception $e) {
        error_log('スプレッドシート記録エラー: ' . $e->getMessage());
    }

    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'メールを送信しました。ご連絡ありがとうございます！'
    ]);

} catch (Exception $e) {
    error_log('お問い合わせ処理エラー: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'メール送信に失敗しました。お手数ですが、お電話でご連絡ください。'
    ]);
}
