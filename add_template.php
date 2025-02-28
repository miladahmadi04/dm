<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("INSERT INTO kpi_templates (
            name, description, activity_field, follower_growth_kpi, 
            engagement_growth_kpi, view_growth_kpi, lead_follower_ratio_kpi, 
            customer_lead_ratio_kpi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("sssddddd",
            $_POST['name'],
            $_POST['description'],
            $_POST['activity_field'],
            $_POST['follower_growth_kpi'],
            $_POST['engagement_growth_kpi'],
            $_POST['view_growth_kpi'],
            $_POST['lead_follower_ratio_kpi'],
            $_POST['customer_lead_ratio_kpi']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        header("Location: kpi_templates.php?success=1");
        exit();
    } catch (Exception $e) {
        header("Location: kpi_templates.php?error=1&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: kpi_templates.php");
    exit();
}
?>