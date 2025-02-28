<?php
require_once 'auth.php';
require_once 'functions.php';

// بررسی ارسال پیام جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    $recipients = $_POST['recipients'] ?? [];
    
    // اعتبارسنجی داده‌ها
    $errors = [];
    
    if (empty($subject)) {
        $errors[] = 'موضوع پیام نمی‌تواند خالی باشد.';
    }
    
    if (empty($content)) {
        $errors[] = 'متن پیام نمی‌تواند خالی باشد.';
    }
    
    if (empty($recipients)) {
        $errors[] = 'حداقل یک گیرنده باید انتخاب شود.';
    }
    
    // اگر خطایی وجود نداشت، ارسال پیام
    if (empty($errors)) {
        try {
            // شروع تراکنش
            $conn->begin_transaction();
            
            // افزودن پیام
            $senderId = $_SESSION['user_id'];
            $parentId = isset($_POST['parent_id']) ? $_POST['parent_id'] : null;
            
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, subject, content, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $senderId, $subject, $content, $parentId);
            
            if (!$stmt->execute()) {
                throw new Exception("خطا در ارسال پیام: " . $stmt->error);
            }
            
            $messageId = $conn->insert_id;
            
            // افزودن دریافت‌کنندگان
            foreach ($recipients as $recipientId) {
                // بررسی وجود کاربر
                $stmt = $conn->prepare("SELECT id FROM personnel WHERE id = ?");
                $stmt->bind_param("i", $recipientId);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows === 0) {
                    continue;
                }
                
                $stmt = $conn->prepare("INSERT INTO message_recipients (message_id, recipient_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $messageId, $recipientId);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در ثبت گیرنده پیام: " . $stmt->error);
                }
            }
            
            // تایید تراکنش
            $conn->commit();
            
            // علامت‌گذاری پیام والد به عنوان خوانده شده
            if ($parentId) {
                $stmt = $conn->prepare("UPDATE message_recipients SET is_read = 1, read_at = NOW() 
                                       WHERE message_id = ? AND recipient_id = ?");
                $stmt->bind_param("ii", $parentId, $senderId);
                $stmt->execute();
            }
            
            $success = 'پیام با موفقیت ارسال شد.';
        } catch (Exception $e) {
            // لغو تراکنش در صورت بروز خطا
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// بررسی علامت‌گذاری پیام به عنوان خوانده شده
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $messageId = $_GET['read'];
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE message_recipients SET is_read = 1, read_at = NOW() 
                           WHERE message_id = ? AND recipient_id = ?");
    $stmt->bind_param("ii", $messageId, $userId);
    $stmt->execute();
}

// دریافت پیام‌های دریافتی
$userId = $_SESSION['user_id'];
$inboxSql = "SELECT m.*, p.full_name as sender_name, mr.is_read, mr.read_at 
             FROM messages m 
             JOIN message_recipients mr ON m.id = mr.message_id 
             JOIN personnel p ON m.sender_id = p.id 
             WHERE mr.recipient_id = ? 
             ORDER BY m.created_at DESC";

$stmt = $conn->prepare($inboxSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$inbox = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// دریافت پیام‌های ارسالی
$sentSql = "SELECT m.*, GROUP_CONCAT(p.full_name SEPARATOR ', ') as recipients, COUNT(mr.id) as recipients_count 
            FROM messages m 
            JOIN message_recipients mr ON m.id = mr.message_id 
            JOIN personnel p ON mr.recipient_id = p.id 
            WHERE m.sender_id = ? 
            GROUP BY m.id 
            ORDER BY m.created_at DESC";

$stmt = $conn->prepare($sentSql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$sent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// دریافت لیست کاربران برای ارسال پیام
$sql = "SELECT p.id, p.full_name, c.company_name 
       FROM personnel p 
       JOIN companies c ON p.company_id = c.id 
       WHERE p.is_active = 1 AND c.is_active = 1";

// اگر مدیر سیستم نیست، فقط کاربران شرکت خود را ببیند
if (!isSystemAdmin()) {
    $companyId = $_SESSION['company_id'];
    $sql .= " AND p.company_id = $companyId";
}

$sql .= " ORDER BY c.company_name, p.full_name";

$stmt = $conn->prepare($sql);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// دریافت اطلاعات پیام برای پاسخ
$replyTo = null;
if (isset($_GET['reply']) && is_numeric($_GET['reply'])) {
    $replyId = $_GET['reply'];
    
    $stmt = $conn->prepare("SELECT m.*, p.full_name as sender_name 
                           FROM messages m 
                           JOIN personnel p ON m.sender_id = p.id 
                           WHERE m.id = ?");
    $stmt->bind_param("i", $replyId);
    $stmt->execute();
    $replyTo = $stmt->get_result()->fetch_assoc();
    
    // بررسی دسترسی به پیام
    if ($replyTo) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM message_recipients 
                              WHERE message_id = ? AND recipient_id = ?");
        $stmt->bind_param("ii", $replyId, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] === 0 && $replyTo['sender_id'] !== $userId) {
            $replyTo = null;
        }
    }
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">پیام‌ها</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
            <i class="bi bi-pencil-square"></i> نوشتن پیام جدید
        </button>
    </div>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" type="button" role="tab" aria-controls="inbox" aria-selected="true">
                                <i class="bi bi-inbox"></i> صندوق دریافت
                                <?php
                                $unreadCount = array_reduce($inbox, function($carry, $item) {
                                    return $carry + ($item['is_read'] ? 0 : 1);
                                }, 0);
                                
                                if ($unreadCount > 0):
                                ?>
                                    <span class="badge bg-danger"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab" aria-controls="sent" aria-selected="false">
                                <i class="bi bi-send"></i> پیام‌های ارسالی
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content" id="myTabContent">
                        <!-- صندوق دریافت -->
                        <div class="tab-pane fade show active" id="inbox" role="tabpanel" aria-labelledby="inbox-tab">
                            <div class="table-responsive mt-3">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>فرستنده</th>
                                            <th>موضوع</th>
                                            <th>تاریخ</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($inbox)): ?>
                                            <?php foreach ($inbox as $message): ?>
                                                <tr class="<?php echo $message['is_read'] ? '' : 'table-primary'; ?>">
                                                    <td><?php echo htmlspecialchars($message['sender_name']); ?></td>
                                                    <td>
                                                        <a href="#" onclick="showMessage(<?php echo $message['id']; ?>, 'inbox'); return false;">
                                                            <?php echo $message['is_read'] ? '' : '<b>'; ?>
                                                            <?php echo htmlspecialchars($message['subject']); ?>
                                                            <?php echo $message['is_read'] ? '' : '</b>'; ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo formatDate($message['created_at'], 'Y/m/d H:i'); ?></td>
                                                    <td>
                                                        <a href="messages.php?reply=<?php echo $message['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-reply"></i> پاسخ
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">صندوق دریافت خالی است.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- پیام‌های ارسالی -->
                        <div class="tab-pane fade" id="sent" role="tabpanel" aria-labelledby="sent-tab">
                            <div class="table-responsive mt-3">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>گیرندگان</th>
                                            <th>موضوع</th>
                                            <th>تاریخ</th>
                                            <th>عملیات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($sent)): ?>
                                            <?php foreach ($sent as $message): ?>
                                                <tr>
                                                    <td>
                                                        <?php
                                                        if ($message['recipients_count'] > 2) {
                                                            echo $message['recipients_count'] . ' نفر';
                                                        } else {
                                                            echo htmlspecialchars($message['recipients']);
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <a href="#" onclick="showMessage(<?php echo $message['id']; ?>, 'sent'); return false;">
                                                            <?php echo htmlspecialchars($message['subject']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo formatDate($message['created_at'], 'Y/m/d H:i'); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" onclick="showRecipients(<?php echo $message['id']; ?>)">
                                                            <i class="bi bi-people"></i> گیرندگان
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">هیچ پیامی ارسال نکرده‌اید.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Compose Message -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $replyTo ? 'پاسخ به پیام' : 'نوشتن پیام جدید'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if ($replyTo): ?>
                        <div class="alert alert-light">
                            <strong>پاسخ به:</strong> <?php echo htmlspecialchars($replyTo['subject']); ?><br>
                            <strong>فرستنده:</strong> <?php echo htmlspecialchars($replyTo['sender_name']); ?><br>
                            <strong>تاریخ:</strong> <?php echo formatDate($replyTo['created_at'], 'Y/m/d H:i'); ?>
                            <hr>
                            <div class="mt-2">
                                <?php echo nl2br(htmlspecialchars($replyTo['content'])); ?>
                            </div>
                        </div>
                        <input type="hidden" name="parent_id" value="<?php echo $replyTo['id']; ?>">
                        <input type="hidden" name="recipients[]" value="<?php echo $replyTo['sender_id']; ?>">
                    <?php else: ?>
                        <div class="mb-3">
                            <label for="recipients" class="form-label">انتخاب گیرندگان</label>
                            <select class="form-select" id="recipients" name="recipients[]" multiple required>
                                <?php
                                $companies = [];
                                foreach ($users as $user) {
                                    if ($user['id'] == $_SESSION['user_id']) continue;
                                    
                                    if (!isset($companies[$user['company_name']])) {
                                        $companies[$user['company_name']] = [];
                                    }
                                    
                                    $companies[$user['company_name']][] = $user;
                                }
                                
                                foreach ($companies as $companyName => $companyUsers):
                                ?>
                                    <optgroup label="<?php echo htmlspecialchars($companyName); ?>">
                                        <?php foreach ($companyUsers as $user): ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="subject" class="form-label">موضوع</label>
                        <input type="text" class="form-control" id="subject" name="subject" required
                               value="<?php echo $replyTo ? 'Re: ' . htmlspecialchars($replyTo['subject']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">متن پیام</label>
                        <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="send_message" class="btn btn-primary">ارسال پیام</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal View Message -->
<div class="modal fade" id="viewMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="message-subject"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div id="message-info"></div>
                </div>
                <div class="card">
                    <div class="card-body" id="message-content"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <a href="#" id="message-reply" class="btn btn-primary">
                    <i class="bi bi-reply"></i> پاسخ
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal View Recipients -->
<div class="modal fade" id="viewRecipientsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">لیست گیرندگان</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
                <div id="recipients-list" class="d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<script>
    // نمایش پیام
    function showMessage(messageId, type) {
        // علامت‌گذاری پیام به عنوان خوانده شده اگر در صندوق دریافت است
        if (type === 'inbox') {
            fetch('messages.php?read=' + messageId)
                .then(response => {
                    // بروزرسانی ظاهر پیام در لیست
                    const row = document.querySelector(`tr[onclick*="showMessage(${messageId}, 'inbox')"]`);
                    if (row) {
                        row.classList.remove('table-primary');
                        const subject = row.querySelector('a');
                        if (subject) {
                            subject.innerHTML = subject.textContent;
                        }
                    }
                });
        }
        
        // دریافت اطلاعات پیام
        fetch('get_message.php?id=' + messageId)
           .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // نمایش اطلاعات پیام در مودال
                    document.getElementById('message-subject').textContent = data.message.subject;
                    
                    let infoHtml = '';
                    if (type === 'inbox') {
                        infoHtml = `<strong>فرستنده:</strong> ${data.message.sender_name}<br>`;
                    } else {
                        infoHtml = `<strong>گیرندگان:</strong> ${data.message.recipients}<br>`;
                    }
                    
                    infoHtml += `<strong>تاریخ:</strong> ${data.message.created_at}`;
                    
                    document.getElementById('message-info').innerHTML = infoHtml;
                    document.getElementById('message-content').innerHTML = data.message.content.replace(/\n/g, '<br>');
                    
                    if (type === 'inbox') {
                        document.getElementById('message-reply').style.display = 'inline-block';
                        document.getElementById('message-reply').href = `messages.php?reply=${messageId}`;
                    } else {
                        document.getElementById('message-reply').style.display = 'none';
                    }
                    
                    // نمایش مودال
                    new bootstrap.Modal(document.getElementById('viewMessageModal')).show();
                } else {
                    alert('خطا در دریافت اطلاعات پیام');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در برقراری ارتباط با سرور');
            });
    }
    
    // نمایش گیرندگان پیام
    function showRecipients(messageId) {
        // نمایش مودال
        const modal = new bootstrap.Modal(document.getElementById('viewRecipientsModal'));
        modal.show();
        
        // نمایش وضعیت بارگذاری
        document.querySelector('#viewRecipientsModal .spinner-border').classList.remove('d-none');
        document.getElementById('recipients-list').classList.add('d-none');
        
        // دریافت لیست گیرندگان
        fetch('get_recipients.php?id=' + messageId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // مخفی کردن وضعیت بارگذاری
                    document.querySelector('#viewRecipientsModal .spinner-border').classList.add('d-none');
                    const recipientsList = document.getElementById('recipients-list');
                    recipientsList.classList.remove('d-none');
                    
                    // نمایش لیست گیرندگان
                    let html = '<ul class="list-group">';
                    data.recipients.forEach(recipient => {
                        const readStatus = recipient.is_read 
                            ? `<span class="badge bg-success">خوانده شده - ${recipient.read_at}</span>` 
                            : '<span class="badge bg-warning text-dark">خوانده نشده</span>';
                            
                        html += `
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                ${recipient.full_name}
                                ${readStatus}
                            </li>
                        `;
                    });
                    html += '</ul>';
                    
                    recipientsList.innerHTML = html;
                } else {
                    alert('خطا در دریافت لیست گیرندگان');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در برقراری ارتباط با سرور');
            });
    }
    
    // باز کردن مودال نوشتن پیام در صورت نیاز
    document.addEventListener('DOMContentLoaded', function() {
        <?php if ($replyTo): ?>
            new bootstrap.Modal(document.getElementById('composeModal')).show();
        <?php endif; ?>
    });
</script>

<?php
// شامل کردن فوتر
include 'footer.php';
?>