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
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM message_recipients WHERE message_id = ? AND recipient_id = ?");
$stmt->bind_param("ii", $messageId, $userId);
$stmt->execute();
$isRecipient = $stmt->get_result()->fetch_assoc()['count'] > 0;

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE id = ? AND sender_id = ?");
$stmt->bind_param("ii", $messageId, $userId);
$stmt->execute();
$isSender = $stmt->get_result()->fetch_assoc()['count'] > 0;

if (!$isRecipient && !$isSender) {
    echo json_encode(['success' => false, 'message' => 'شما دسترسی به این پیام را ندارید.']);
    exit();
}

// دریافت اطلاعات پیام
$sql = "SELECT m.*, p.full_name as sender_name FROM messages m JOIN personnel p ON m.sender_id = p.id WHERE m.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $messageId);
$stmt->execute();
$message = $stmt->get_result()->fetch_assoc();

if (!$message) {
    echo json_encode(['success' => false, 'message' => 'پیام یافت نشد.']);
    exit();
}

// دریافت گیرندگان برای پیام‌های ارسالی
if ($isSender) {
    $stmt = $conn->prepare("SELECT p.full_name, mr.is_read, mr.read_at 
                           FROM message_recipients mr 
                           JOIN personnel p ON mr.recipient_id = p.id 
                           WHERE mr.message_id = ?");
    $stmt->bind_param("i", $messageId);
    $stmt->execute();
    $recipients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $recipientNames = array_map(function($recipient) {
        return $recipient['full_name'];
    }, $recipients);
    
    $message['recipients'] = implode(', ', $recipientNames);
}

// فرمت تاریخ
$message['created_at'] = formatDate($message['created_at'], 'Y/m/d H:i');

echo json_encode(['success' => true, 'message' => $message]);