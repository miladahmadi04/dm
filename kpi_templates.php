<?php
require_once 'functions.php';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت قالب‌های KPI</title>
    <link href="fontstyle.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
            <div class="alert alert-<?php echo isset($_GET['success']) ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php
                if (isset($_GET['success'])) {
                    if ($_GET['success'] == 1) echo 'قالب با موفقیت ایجاد شد.';
                    elseif ($_GET['success'] == 2) echo 'قالب با موفقیت حذف شد.';
                    elseif ($_GET['success'] == 3) echo 'قالب با موفقیت ویرایش شد.';
                }
                if (isset($_GET['error'])) {
                    echo 'خطا: ' . htmlspecialchars($_GET['message'] ?? 'خطایی رخ داده است.');
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>مدیریت قالب‌های KPI</h1>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="bi bi-plus-lg"></i> افزودن قالب جدید
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> بازگشت
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>نام قالب</th>
                                <th>حیطه فعالیت</th>
                                <th>رشد فالوور</th>
                                <th>رشد تعامل</th>
                                <th>رشد بازدید</th>
                                <th>نسبت لید</th>
                                <th>نسبت مشتری</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $templates = getTemplates();
                            foreach ($templates as $template):
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($template['name']); ?></td>
                                <td><?php echo htmlspecialchars($template['activity_field']); ?></td>
                                <td><?php echo $template['follower_growth_kpi']; ?>%</td>
                                <td><?php echo $template['engagement_growth_kpi']; ?>%</td>
                                <td><?php echo $template['view_growth_kpi']; ?>%</td>
                                <td><?php echo $template['lead_follower_ratio_kpi']; ?>%</td>
                                <td><?php echo $template['customer_lead_ratio_kpi']; ?>%</td>
                                <td>
                                    <a href="edit_template.php?id=<?php echo $template['id']; ?>" 
                                       class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button onclick="deleteTemplate(<?php echo $template['id']; ?>)" 
                                            class="btn btn-sm btn-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Template Modal -->
    <div class="modal fade" id="addTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">افزودن قالب جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add_template.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">نام قالب</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">حیطه فعالیت</label>
                            <input type="text" class="form-control" name="activity_field" required>
                        </div>

                        <h6 class="mb-3">مقادیر KPI:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نرخ رشد فالوور (درصد)</label>
                                    <input type="number" step="0.01" class="form-control" name="follower_growth_kpi" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نرخ رشد تعامل (درصد)</label>
                                    <input type="number" step="0.01" class="form-control" name="engagement_growth_kpi" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نرخ رشد ویو (درصد)</label>
                                    <input type="number" step="0.01" class="form-control" name="view_growth_kpi" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نرخ لید به فالوور (درصد)</label>
                                    <input type="number" step="0.01" class="form-control" name="lead_follower_ratio_kpi" required min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نرخ مشتری به لید (درصد)</label>
                                    <input type="number" step="0.01" class="form-control" name="customer_lead_ratio_kpi" required min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteTemplate(templateId) {
            if (confirm('آیا از حذف این قالب اطمینان دارید؟')) {
                window.location.href = 'delete_template.php?id=' + templateId;
            }
        }
    </script>
</body>
</html>