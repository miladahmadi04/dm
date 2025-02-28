<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    try {
        // Get page_id first for redirect
        $stmt = $conn->prepare("SELECT page_id FROM monthly_reports WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("گزارش مورد نظر یافت نشد.");
        }
        
        $report = $result->fetch_assoc();
        $page_id = $report['page_id'];

        // Delete the report
        $stmt = $conn->prepare("DELETE FROM monthly_reports WHERE id = ?");
        $stmt->bind_param("i", $_GET['id']);
        
        if (!$stmt->execute()) {
            throw new Exception("خطا در حذف گزارش: " . $stmt->error);
        }

        header("Location: reports.php?page_id=" . $page_id . "&success=2");
        exit();
    } catch (Exception $e) {
        header("Location: reports.php?page_id=" . $page_id . "&error=2&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>