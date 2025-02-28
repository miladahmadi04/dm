<?php
require_once 'config.php';

if (isset($_GET['id'])) {
    // First delete all reports for this page
    $stmt = $conn->prepare("DELETE FROM monthly_reports WHERE page_id = ?");
    $stmt->bind_param("i", $_GET['id']);
    $stmt->execute();

    // Then delete the page
    $stmt = $conn->prepare("DELETE FROM instagram_pages WHERE id = ?");
    $stmt->bind_param("i", $_GET['id']);
    
    if ($stmt->execute()) {
        header("Location: index.php?success=2");
    } else {
        header("Location: index.php?error=2");
    }
}
?>