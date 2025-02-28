<?php
require_once 'config.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $conn->prepare("UPDATE monthly_reports SET 
            report_date = ?,
            followers_count = ?,
            engagement_count = ?,
            views_count = ?,
            customers_count = ?,
            leads_count = ?
            WHERE id = ?");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("siiiiii",
            $_POST['report_date'],
            $_POST['followers_count'],
            $_POST['engagement_count'],
            $_POST['views_count'],
            $_POST['customers_count'],
            $_POST['leads_count'],
            $_POST['report_id']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        header("Location: reports.php?page_id=" . $_POST['page_id'] . "&success=3");
        exit();
    } catch (Exception $e) {
        header("Location: reports.php?page_id=" . $_POST['page_id'] . "&error=3&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Get report data for edit form
    $report_id = $_GET['id'] ?? 0;
    $stmt = $conn->prepare("SELECT r.*, p.company_name, p.instagram_url 
                           FROM monthly_reports r 
                           JOIN instagram_pages p ON r.page_id = p.id 
                           WHERE r.id = ?");
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();

    if (!$report) {
        die('گزارش مورد نظر یافت نشد.');
    }

    // Calculate performance scores
    $reportScore = calculateReportScore($report_id);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش گزارش</title>
    <link href="fontstyle.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">ویرایش گزارش - <?php echo htmlspecialchars($report['company_name']); ?></h5>
                <small class="text-muted"><?php echo htmlspecialchars($report['instagram_url']); ?></small>
            </div>
            <div class="card-body">
                <form method="POST" action="edit_report.php">
                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                    <input type="hidden" name="page_id" value="<?php echo $report['page_id']; ?>">
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="alert alert-info">
                                <h6 class="mb-2">نمره‌های عملکرد فعلی:</h6>
                                <div>فالوور: <?php echo number_format($reportScore['scores']['followers'], 1); ?> از 7</div>
                                <div>تعامل: <?php echo number_format($reportScore['scores']['engagement'], 1); ?> از 7</div>
                                <div>بازدید: <?php echo number_format($reportScore['scores']['views'], 1); ?> از 7</div>
                                <div>لید: <?php echo number_format($reportScore['scores']['leads'], 1); ?> از 7</div>
                                <div>مشتری: <?php echo number_format($reportScore['scores']['customers'], 1); ?> از 7</div>
                                <div class="mt-2 fw-bold">نمره کل: <?php echo number_format($reportScore['scores']['overall'], 1); ?> از 7</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-warning">
                                <h6 class="mb-2">مقادیر مورد انتظار:</h6>
                                <div>فالوور: <?php echo formatNumber($reportScore['expected']['followers']); ?></div>
                                <div>تعامل: <?php echo formatNumber($reportScore['expected']['engagement']); ?></div>
                                <div>بازدید: <?php echo formatNumber($reportScore['expected']['views']); ?></div>
                                <div>لید: <?php echo formatNumber($reportScore['expected']['leads']); ?></div>
                                <div>مشتری: <?php echo formatNumber($reportScore['expected']['customers']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تاریخ گزارش</label>
                                <input type="date" class="form-control" name="report_date" 
                                       value="<?php echo $report['report_date']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد فالوور</label>
                                <input type="number" class="form-control" name="followers_count" 
                                       value="<?php echo $report['followers_count']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد تعامل</label>
                                <input type="number" class="form-control" name="engagement_count" 
                                       value="<?php echo $report['engagement_count']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد بازدید</label>
                                <input type="number" class="form-control" name="views_count" 
                                       value="<?php echo $report['views_count']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد لید</label>
                                <input type="number" class="form-control" name="leads_count" 
                                       value="<?php echo $report['leads_count']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد مشتری</label>
                                <input type="number" class="form-control" name="customers_count" 
                                       value="<?php echo $report['customers_count']; ?>" required min="0">
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <a href="reports.php?page_id=<?php echo $report['page_id']; ?>" class="btn btn-secondary me-2">انصراف</a>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>