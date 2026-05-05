<?php
// ============================================
// 設定ファイル
// ============================================

// SendGrid API キー（SG. で始まる実際のキーに置き換えてください）
define('SENDGRID_API_KEY', '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');

// 送信元メールアドレス（SendGridでドメイン認証済みのもの）
define('SENDGRID_FROM_EMAIL', 'info@box-scan.jp');

// 社内通知先メールアドレス
define('COMPANY_EMAIL', 'info@box-scan.jp');

// Google Apps Script URLs（スプレッドシート連携）
define('GOOGLE_SCRIPT_CONTACT_URL', 'https://script.google.com/macros/s/AKfycbybW7G0jXpRh9QyZ2aEcfXDafQQcnnfYEDRImtMg7B464HwS3ih7AL5EF2hbnpF7kp3/exec');
define('GOOGLE_SCRIPT_ESTIMATE_URL', 'https://script.google.com/macros/s/AKfycbwRUueAyfUJB_yuTJQ84nE510E9JPONNvyDY3qHltoW3rZPpefCqVNf5zl4cXrV4w6m/exec');

// 資料PDFのパス
// サービス資料PDF
define('RESOURCE_PDF_SERVICE', __DIR__ . '/../attached_assets/scanpro-service-guide.pdf');
// 事例資料PDF
define('RESOURCE_PDF_CASESTUDY', __DIR__ . '/../attached_assets/casestudy.pdf');

// CORSの許可元（本番では 'https://scan-pro.jp' に変更推奨）
define('ALLOWED_ORIGIN', '*');