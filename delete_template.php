<?php
require_once 'functions.php';

if (isset($_GET['id'])) {
    try {
        if (!deleteTemplate($_GET['id'])) {
            throw new Exception("خطا در حذف قالب");
        }
        header("Location: kpi_templates.php?success=2");
    } catch (Exception $e) {
        header("Location: kpi_templates.php?error=1&message=" . urlencode($e->getMessage()));
    }
} else {
    header("Location: kpi_templates.php");
}
exit();
?>