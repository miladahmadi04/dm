<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("INSERT INTO monthly_reports (
            page_id, report_date, followers_count, engagement_count,
            views_count, customers_count, leads_count
        ) VALUES (?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("isiiiii",
            $_POST['page_id'],
            $_POST['report_date'],
            $_POST['followers_count'],
            $_POST['engagement_count'],
            $_POST['views_count'],
            $_POST['customers_count'],
            $_POST['leads_count']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        header("Location: reports.php?page_id=" . $_POST['page_id'] . "&success=1");
        exit();
    } catch (Exception $e) {
        header("Location: reports.php?page_id=" . $_POST['page_id'] . "&error=1&message=" . urlencode($e->getMessage()));
        exit();
    }
}
?>