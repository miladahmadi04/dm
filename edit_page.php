<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Clean instagram_url (remove @ if exists and any whitespace)
        $instagram_url = '@' . trim(ltrim($_POST['instagram_url'], '@'));

        $stmt = $conn->prepare("UPDATE instagram_pages SET 
            company_name = ?,
            instagram_url = ?,
            activity_field = ?,
            start_date = ?,
            initial_views = ?,
            initial_followers = ?,
            initial_engagement = ?,
            follower_growth_kpi = ?,
            engagement_growth_kpi = ?,
            view_growth_kpi = ?,
            lead_follower_ratio_kpi = ?,
            customer_lead_ratio_kpi = ?
            WHERE id = ?");

        if (!$stmt) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("ssssiiidddddi",
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
            $_POST['customer_lead_ratio_kpi'],
            $_POST['page_id']
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }

        header("Location: index.php?success=3");
        exit();
    } catch (Exception $e) {
        header("Location: index.php?error=3&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Get page data for edit form
    $page_id = $_GET['id'] ?? 0;
    $stmt = $conn->prepare("SELECT * FROM instagram_pages WHERE id = ?");
    $stmt->bind_param("i", $page_id);
    $stmt->execute();
    $page = $stmt->get_result()->fetch_assoc();

    if (!$page) {
        die('پیج مورد نظر یافت نشد.');
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش پیج</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">ویرایش پیج <?php echo htmlspecialchars($page['company_name']); ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="edit_page.php">
                    <input type="hidden" name="page_id" value="<?php echo $page['id']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">نام شرکت</label>
                        <input type="text" class="form-control" name="company_name" 
                               value="<?php echo htmlspecialchars($page['company_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">آدرس اینستاگرام</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" class="form-control" name="instagram_url" 
                                   value="<?php echo htmlspecialchars(ltrim($page['instagram_url'], '@')); ?>" required
                                   pattern="[A-Za-z0-9._]+" title="فقط حروف انگلیسی، اعداد، نقطه و زیرخط مجاز است">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">حیطه فعالیت</label>
                        <input type="text" class="form-control" name="activity_field" 
                               value="<?php echo htmlspecialchars($page['activity_field']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">تاریخ شروع کار</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $page['start_date']; ?>" required>
                    </div>
                    
                    <h6 class="mb-3">مقادیر اولیه:</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد ویو اولیه</label>
                                <input type="number" class="form-control" name="initial_views" 
                                       value="<?php echo $page['initial_views']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد فالوور اولیه</label>
                                <input type="number" class="form-control" name="initial_followers" 
                                       value="<?php echo $page['initial_followers']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تعداد تعامل اولیه</label>
                                <input type="number" class="form-control" name="initial_engagement" 
                                       value="<?php echo $page['initial_engagement']; ?>" required min="0">
                            </div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">اهداف ماهانه (KPI):</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ رشد فالوور در ماه (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="follower_growth_kpi" 
                                       value="<?php echo $page['follower_growth_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ رشد تعامل در ماه (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="engagement_growth_kpi" 
                                       value="<?php echo $page['engagement_growth_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ رشد ویو در ماه (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="view_growth_kpi" 
                                       value="<?php echo $page['view_growth_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ تعداد لید براساس تعداد فالوور (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="lead_follower_ratio_kpi" 
                                       value="<?php echo $page['lead_follower_ratio_kpi']; ?>" required min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">نرخ تعداد مشتری براساس تعداد لید (درصد)</label>
                                <input type="number" step="0.01" class="form-control" name="customer_lead_ratio_kpi" 
                                       value="<?php echo $page['customer_lead_ratio_kpi']; ?>" required min="0">
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="index.php" class="btn btn-secondary">انصراف</a>
                        <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>