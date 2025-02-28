<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Clean instagram_url (remove @ if exists and any whitespace)
        $instagram_url = '@' . trim(ltrim($_POST['instagram_url'], '@'));

        $stmt = $conn->prepare("INSERT INTO instagram_pages (
            company_name, instagram_url, activity_field, start_date,
            initial_views, initial_followers, initial_engagement,
            follower_growth_kpi, engagement_growth_kpi, view_growth_kpi,
            lead_follower_ratio_kpi, customer_lead_ratio_kpi
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("ssssiiiddddd",
            $_POST['company_name'],
            $instagram_url,
            $_POST['activity_field'],
            $_POST['start_date'],
            $_POST['initial_views'],
            $_POST['initial_followers'],
            $_POST['initial_engagement'],
            $_POST['follower_growth_kpi'],
            $_POST['engagement_growth_kpi'],
            $_POST['view_growth_kpi'],
            $_POST['lead_follower_ratio_kpi'],
            $_POST['customer_lead_ratio_kpi']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        header("Location: index.php?success=1");
        exit();
    } catch (Exception $e) {
        header("Location: index.php?error=1&message=" . urlencode($e->getMessage()));
        exit();
    }
}
?>