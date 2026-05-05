/* ============================================
   モーダルフォーム JavaScript
   - お問い合わせ・見積もりモーダル
   - 資料ダウンロードモーダル
   ============================================ */

// ============================================
// モーダル開閉
// ============================================
function openContactModal() {
    document.getElementById('contactModal').classList.add('active');
    document.body.classList.add('modal-open');
}
function closeContactModal() {
    document.getElementById('contactModal').classList.remove('active');
    document.body.classList.remove('modal-open');
}

// 選択された資料名を保持するグローバル変数
let selectedResourceName = 'スキャンPro サービス資料';

function openDownloadModal(resourceType) {
    if (resourceType) {
        selectedResourceName = resourceType;
    }
    document.getElementById('downloadModal').classList.add('active');
    document.body.classList.add('modal-open');
}
function closeDownloadModal() {
    document.getElementById('downloadModal').classList.remove('active');
    document.body.classList.remove('modal-open');
}

// Escキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(function(modal) {
            modal.classList.remove('active');
        });
        document.body.classList.remove('modal-open');
    }
});

// ============================================
// お問い合わせ・見積もりフォーム
// ============================================

// 個人チェックで会社名・部署を表示/非表示
function togglePersonalFields() {
    var isPersonal = document.getElementById('isPersonal').checked;
    var companyFields = document.getElementById('companyFields');
    companyFields.style.display = isPersonal ? 'none' : 'block';
}

// 問い合わせのみチェックで見積もり項目を表示/非表示
function toggleInquiryFields() {
    var isInquiryOnly = document.getElementById('isInquiryOnly').checked;
    var estimateFields = document.getElementById('estimateFields');
    estimateFields.style.display = isInquiryOnly ? 'none' : 'block';
}

// 電話番号自動フォーマット（ハイフン挿入）
function formatPhone(input) {
    var value = input.value.replace(/[^\d]/g, '');
    if (value.length <= 3) {
        input.value = value;
    } else if (value.length <= 7) {
        input.value = value.slice(0, 3) + '-' + value.slice(3);
    } else {
        input.value = value.slice(0, 3) + '-' + value.slice(3, 7) + '-' + value.slice(7, 11);
    }
}

// 郵便番号自動フォーマット＋住所自動入力
function formatZip(input) {
    var value = input.value.replace(/[^\d]/g, '');
    if (value.length > 3) {
        input.value = value.slice(0, 3) + '-' + value.slice(3, 7);
    } else {
        input.value = value;
    }
    // 7桁入力されたら住所検索
    if (value.length === 7) {
        fetchAddress(value);
    }
}

function fetchAddress(zip) {
    fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + zip)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.results && data.results[0]) {
                var result = data.results[0];
                document.getElementById('contactAddr1').value =
                    result.address1 + result.address2 + result.address3;
            }
        })
        .catch(function(err) {
            console.log('住所検索エラー:', err);
        });
}

// お問い合わせフォーム送信
function submitContactForm(event) {
    event.preventDefault();
    var errors = validateContactForm();
    if (errors.length > 0) { showContactErrors(errors); return; }
    var submitBtn = document.getElementById('contactSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span>送信中...';
    var formData = new FormData();
    formData.append('company', document.getElementById('contactCompany').value);
    formData.append('name', document.getElementById('contactName').value);
    formData.append('email', document.getElementById('contactEmail').value);
    formData.append('phone', document.getElementById('contactPhone').value.replace(/-/g, ''));
    formData.append('freeText', document.getElementById('contactFreeText').value);
    fetch('api/contact.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showContactSuccess();
            } else {
                showContactErrors([data.message || 'エラーが発生しました。']);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '送信する';
            }
        })
        .catch(function(err) {
            showContactErrors(['通信エラーが発生しました。お手数ですがお電話（052-842-9697）でご連絡ください。']);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '送信する';
        });
}

function validateContactForm() {
    var errors = [];
    if (!document.getElementById('contactCompany').value.trim()) errors.push('会社名を入力してください');
    if (!document.getElementById('contactName').value.trim()) errors.push('氏名を入力してください');
    if (!document.getElementById('contactEmail').value.trim()) errors.push('メールアドレスを入力してください');
    if (!document.getElementById('contactPhone').value.trim()) errors.push('電話番号を入力してください');
    if (!document.getElementById('contactPrivacy').checked) errors.push('プライバシーポリシーに同意してください');
    return errors;
}

function showContactErrors(errors) {
    var container = document.getElementById('contactErrors');
    container.innerHTML = errors.map(function(e) { return '<div>' + e + '</div>'; }).join('');
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showContactSuccess() {
    document.getElementById('contactFormContent').style.display = 'none';
    document.getElementById('contactSuccess').style.display = 'block';
}

// ============================================
// 資料ダウンロードフォーム
// ============================================
function submitDownloadForm(event) {
    event.preventDefault();

    var errors = validateDownloadForm();
    if (errors.length > 0) {
        showDownloadErrors(errors);
        return;
    }

    var submitBtn = document.getElementById('downloadSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span>送信中...';

    var payload = {
        companyName: document.getElementById('dlCompanyName').value,
        name: document.getElementById('dlName').value,
        phone: document.getElementById('dlPhone').value,
        email: document.getElementById('dlEmail').value,
        resourceName: selectedResourceName
    };

    fetch('api/resource-download.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            showDownloadSuccess();
        } else {
            showDownloadErrors([data.message || 'エラーが発生しました。']);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '資料を受け取る';
        }
    })
    .catch(function(err) {
        showDownloadErrors(['通信エラーが発生しました。お手数ですがお電話（052-842-9697）でご連絡ください。']);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '資料を受け取る';
    });
}

function validateDownloadForm() {
    var errors = [];
    if (!document.getElementById('dlCompanyName').value.trim()) errors.push('会社名を入力してください');
    if (!document.getElementById('dlName').value.trim()) errors.push('お名前を入力してください');
    if (!document.getElementById('dlPhone').value.trim()) errors.push('電話番号を入力してください');
    if (!document.getElementById('dlEmail').value.trim()) errors.push('メールアドレスを入力してください');
    if (!document.getElementById('dlPrivacy').checked) errors.push('プライバシーポリシーに同意してください');
    return errors;
}

function showDownloadErrors(errors) {
    var container = document.getElementById('downloadErrors');
    container.innerHTML = errors.map(function(e) { return '<div>' + e + '</div>'; }).join('');
    container.style.display = 'block';
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function showDownloadSuccess() {
    document.getElementById('downloadFormContent').style.display = 'none';
    document.getElementById('downloadSuccess').style.display = 'block';
}

// ============================================
// URL hash detection for auto-opening modals
// ============================================
function checkHashAndOpenModal() {
    var hash = window.location.hash;
    if (hash === '#contact') {
        openContactModal();
    } else if (hash === '#download') {
        openDownloadModal('スキャンPro サービス資料');
    } else if (hash === '#download-case') {
        openDownloadModal('スキャンPro 業界別導入事例資料');
    }
}

// DOMContentLoadedがまだの場合はリスナーで待つ、既に発火済みなら即実行
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkHashAndOpenModal);
} else {
    checkHashAndOpenModal();
}

// ページ内でハッシュが変わった場合にも対応
window.addEventListener('hashchange', checkHashAndOpenModal);
