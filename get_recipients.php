<?php
require_once 'auth.php';
require_once 'functions.php';

header('Content-Type: application/json');

// بررسی درخواست
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'شناسه پیام نامعتبر است.']);
    exit();
}

$messageId = $_GET['id'];
$userId = $_SESSION['user_id'];

// بررسی دسترسی به پیام
$stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
$stmt->bind_param("i", $messageId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if (!$result || $result['sender_id'] != $userId) {
    echo json_encode(['success' => false, 'message' => 'شما دسترسی به این پیام را ندارید.']);
    exit();
}

// دریافت گیرندگان
$stmt = $conn->prepare("SELECT mr.is_read, mr.read_at, p.full_name 
                       FROM message_recipients mr 
                       JOIN personnel p ON mr.recipient_id = p.id 
                       WHERE mr.message_id = ?");
$stmt->bind_param("i", $messageId);
$stmt->execute();
$recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// فرمت تاریخ خواندن
foreach ($recipients as &$recipient) {
    if ($recipient['read_at']) {
        $recipient['read_at'] = formatDate($recipient['read_at'], 'Y/m/d H:i');
    }
}

echo json_encode(['success' => true, 'recipients' => $recipients]);