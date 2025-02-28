<?php
require_once 'functions.php';

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;

// Get page details
$stmt = $conn->prepare("SELECT * FROM instagram_pages WHERE id = ?");
$stmt->bind_param("i", $page_id);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    die('<div class="container py-4"><div class="alert alert-danger">پیج مورد نظر یافت نشد.</div></div>');
}

// Initialize filters
$filters = [
    'followers' => isset($_GET['filter_followers']),
    'engagement' => isset($_GET['filter_engagement']),
    'views' => isset($_GET['filter_views']),
    'leads' => isset($_GET['filter_leads']),
    'customers' => isset($_GET['filter_customers'])
];

// If no filters are selected, select all
if (!array_filter($filters)) {
    $filters = array_fill_keys(array_keys($filters), true);
}

// Get performance data for charts with filters
$performanceData = getPagePerformanceData($page_id, $filters);
$reports = getReportsList($page_id);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش‌های ماهانه</title>
    <link href="fontstyle.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
            <div class="alert alert-<?php echo isset($_GET['success']) ? 'success' : 'danger'; ?> alert-dismissible fade show">
                <?php
                if (isset($_GET['success'])) {
                    if ($_GET['success'] == 1) echo 'گزارش با موفقیت ثبت شد.';
                    elseif ($_GET['success'] == 2) echo 'گزارش با موفقیت حذف شد.';
                    elseif ($_GET['success'] == 3) echo 'گزارش با موفقیت ویرایش شد.';
                }
                if (isset($_GET['error'])) {
                    echo 'خطا: ' . htmlspecialchars($_GET['message'] ?? 'خطایی رخ داده است.');
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-2"><?php echo htmlspecialchars($page['company_name']); ?></h1>
                <h2 class="h5 text-muted"><?php echo htmlspecialchars($page['instagram_url']); ?></h2>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReportModal">
                    <i class="bi bi-plus-lg"></i> افزودن گزارش جدید
                </button>
				<a href="predictions.php?page_id=<?php echo $page_id; ?>" class="btn btn-info me-2">
                    <i class="bi bi-graph-up"></i> پیش‌بینی عملکرد
                </a>
                <a href="generate_pdf.php?page_id=<?php echo $page_id; ?>&type=reports" class="btn btn-primary me-2">
                    <i class="bi bi-file-pdf"></i> دانلود PDF
                </a>
                <a href="index.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> بازگشت
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">فیلترهای نمایش</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="page_id" value="<?php echo $page_id; ?>">
                    
                    <div class="col-auto">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="filter_followers" id="filter_followers"
                                   <?php echo $filters['followers'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filter_followers">فالوور</label>
                        </div>
                    </div>
                    
                    <div class="col-auto">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="filter_engagement" id="filter_engagement"
                                   <?php echo $filters['engagement'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filter_engagement">تعامل</label>
                        </div>
                    </div>
                    
                    <div class="col-auto">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="filter_views" id="filter_views"
                                   <?php echo $filters['views'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filter_views">بازدید</label>
                        </div>
                    </div>
                    
                    <div class="col-auto">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="filter_leads" id="filter_leads"
                                   <?php echo $filters['leads'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filter_leads">لید</label>
                        </div>
                    </div>
                    
                    <div class="col-auto">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="filter_customers" id="filter_customers"
                                   <?php echo $filters['customers'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="filter_customers">مشتری</label>
                        </div>
                    </div>
                    
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">اعمال فیلتر</button>
                    </div>
                </form>
            </div>
        </div>

     <!-- Performance Summary -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">خلاصه عملکرد (میانگین تمام ماه‌ها)</h5>
    </div>
    <div class="card-body">
        <?php
        $averagePerformance = calculateAveragePerformance($page_id, $filters);
        if ($averagePerformance): 
            foreach (['followers', 'engagement', 'views', 'leads', 'customers'] as $metric):
                if ($filters[$metric]):
                    $score = $averagePerformance[$metric];
                    $colorClass = $score >= 6 ? 'success' : ($score >= 4 ? 'warning' : 'danger');
        ?>
        <div class="col-md-2 mb-3 d-inline-block">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="mb-2"><?php echo getMetricLabel($metric); ?></h6>
                    <span class="badge bg-<?php echo $colorClass; ?> fs-5">
                        <?php echo number_format($score, 1); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php 
                endif;
            endforeach;
        ?>
        <div class="col-md-2 mb-3 d-inline-block">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6 class="mb-2">میانگین کل</h6>
                    <span class="fs-5">
                        <?php echo number_format($averagePerformance['overall'], 1); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            هنوز گزارشی ثبت نشده است.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reports Table -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">گزارش‌های ثبت شده</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>فالوور</th>
                        <th>تعامل</th>
                        <th>بازدید</th>
                        <th>لید</th>
                        <th>مشتری</th>
                        <th>نمره عملکرد</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($reports as $report):
                        $reportData = calculateReportScore($report['id'], $filters);
                        $actual = $reportData['actual'];
                        $expected = $reportData['expected'];
                        $scores = $reportData['scores'];
                    ?>
                    <tr>
                        <td><?php echo $report['report_date']; ?></td>
                        <td>
                            <?php echo formatNumber($actual['followers']); ?>
                            <br>
                            <small class="text-muted">هدف: <?php echo formatNumber($expected['followers']); ?></small>
                            <?php displayAchievementBadge($actual['followers'], $expected['followers']); ?>
                        </td>
                        <td>
                            <?php echo formatNumber($actual['engagement']); ?>
                            <br>
                            <small class="text-muted">هدف: <?php echo formatNumber($expected['engagement']); ?></small>
                            <?php displayAchievementBadge($actual['engagement'], $expected['engagement']); ?>
                        </td>
                        <td>
                            <?php echo formatNumber($actual['views']); ?>
                            <br>
                            <small class="text-muted">هدف: <?php echo formatNumber($expected['views']); ?></small>
                            <?php displayAchievementBadge($actual['views'], $expected['views']); ?>
                        </td>
                        <td>
                            <?php echo formatNumber($actual['leads']); ?>
                            <br>
                            <small class="text-muted">هدف: <?php echo formatNumber($expected['leads']); ?></small>
                            <?php displayAchievementBadge($actual['leads'], $expected['leads']); ?>
                        </td>
                        <td>
                            <?php echo formatNumber($actual['customers']); ?>
                            <br>
                            <small class="text-muted">هدف: <?php echo formatNumber($expected['customers']); ?></small>
                            <?php displayAchievementBadge($actual['customers'], $expected['customers']); ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo getScoreColor($scores['overall']); ?>">
                                <?php echo number_format($scores['overall'], 1); ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_report.php?id=<?php echo $report['id']; ?>" 
                               class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button onclick="deleteReport(<?php echo $report['id']; ?>)" 
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

        <!-- Performance Charts -->
        <div class="row">
            <?php
            $metrics = [
                'followers' => 'فالوور',
                'engagement' => 'تعامل',
                'views' => 'بازدید',
                'leads' => 'لید',
                'customers' => 'مشتری'
            ];
            
            foreach ($metrics as $metric => $label):
                if ($filters[$metric]):
            ?>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">روند <?php echo $label; ?></h5>
                    </div>
                    <div class="card-body">
                        <canvas id="<?php echo $metric; ?>Chart"></canvas>
                    </div>
                </div>
            </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
    </div>

    <!-- Add Report Modal -->
    <div class="modal fade" id="addReportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">افزودن گزارش جدید</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addReportForm" method="POST" action="add_report.php">
                    <input type="hidden" name="page_id" value="<?php echo $page_id; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">تاریخ گزارش</label>
                            <input type="date" class="form-control" name="report_date" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تعداد فالوور</label>
                            <input type="number" class="form-control" name="followers_count" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تعداد تعامل</label>
                            <input type="number" class="form-control" name="engagement_count" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تعداد بازدید</label>
                            <input type="number" class="form-control" name="views_count" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تعداد لید</label>
                            <input type="number" class="form-control" name="leads_count" required min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">تعداد مشتری</label>
                            <input type="number" class="form-control" name="customers_count" required min="0">
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
        // Delete confirmation
        function deleteReport(reportId) {
            if (confirm('آیا از حذف این گزارش اطمینان دارید؟')) {
                window.location.href = 'delete_report.php?id=' + reportId;
            }
        }

        // Charts
        const performanceData = <?php echo json_encode($performanceData); ?>;
        const metrics = <?php echo json_encode($metrics); ?>;
        const filters = <?php echo json_encode($filters); ?>;

        const chartOptions = {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };

        Object.entries(metrics).forEach(([metric, label]) => {
            if (filters[metric]) {
                const ctx = document.getElementById(metric + 'Chart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: performanceData.dates,
                        datasets: [
                            {
                                label: 'مقدار واقعی',
                                data: performanceData.actual[metric],
                                borderColor: 'rgb(75, 192, 192)',
                                tension: 0.1
                            },
                            {
                                label: 'مقدار مورد انتظار',
                                data: performanceData.expected[metric],
                                borderColor: 'rgb(255, 159, 64)',
                                borderDash: [5, 5],
                                tension: 0.1
                            }
                        ]
                    },
                    options: chartOptions
                });
            }
        });
    </script>
</body>
</html>