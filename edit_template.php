<?php
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE kpi_templates SET 
            name = ?,
            description = ?,
            activity_field = ?,
            follower_growth_kpi = ?,
            engagement_growth_kpi = ?,
            view_growth_kpi = ?,
            lead_follower_ratio_kpi = ?,
            customer_lead_ratio_kpi = ?
            WHERE id = ?");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("sssdddddi",
            $_POST['name'],
            $_POST['description'],
            $_POST['activity_field'],
            $_POST['follower_growth_kpi'],
            $_POST['engagement_growth_kpi'],
            $_POST['view_growth_kpi'],
            $_POST['lead_follower_ratio_kpi'],
            $_POST['customer_lead_ratio_kpi'],
            $_POST['template_id']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        header("Location: kpi_templates.php?success=3");
        exit();
    } catch (Exception $e) {
        header("Location: kpi_templates.php?error=1&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    $template_id = $_GET['id'] ?? 0;
    $template = getTemplate($template_id);
    
    if (!$template) {
        die('قالب مورد نظر یافت نشد.');
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش قالب KPI</title>
    <link href="fontstyle.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">ویرایش قالب</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="edit_template.php">
                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">نام قالب</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?php echo htmlspecialchars($template['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">توضیحات</label>
                        <textarea class="form-control" name="description" rows="3"><?php 
                            echo htmlspecialchars($template['description']); 
                        ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">حیطه فعالیت</label>
                        <input type="text" class="form-control" name="activity_field" 
                               value="<?php echo htmlspecialchars($template['activity_field']); ?>" required>
                    </div>

                    <h6 class="mb-3">مقادیر KPI:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ رشد فالوور (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="follower_growth_kpi" 
                                       value="<?php echo $template['follower_growth_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ رشد تعامل (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="engagement_growth_kpi" 
                                       value="<?php echo $template['engagement_growth_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ رشد ویو (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="view_growth_kpi" 
                                       value="<?php echo $template['view_growth_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ لید به فالوور (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="lead_follower_ratio_kpi" 
                                       value="<?php echo $template['lead_follower_ratio_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ مشتری به لید (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="customer_lead_ratio_kpi" 
                                       value="<?php echo $template['customer_lead_ratio_kpi']; ?>" required min="0">
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="kpi_templates.php" class="btn btn-secondary">انصراف</a>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>