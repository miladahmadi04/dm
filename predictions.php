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

// Calculate predictions for next 12 months
$predictions = [];
$startDate = new DateTime($page['start_date']);
$currentDate = new DateTime();
$monthsPassed = $startDate->diff($currentDate)->m + ($startDate->diff($currentDate)->y * 12);

for ($i = 1; $i <= 12; $i++) {
    $monthNumber = $monthsPassed + $i;
    $predictions[$i] = calculateExpectedValues($page_id, $monthNumber);
    $predictions[$i]['date'] = date('Y-m-d', strtotime("+{$i} months"));
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
	<link href="fontstyle.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌بینی عملکرد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-2">پیش‌بینی عملکرد <?php echo htmlspecialchars($page['company_name']); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($page['instagram_url']); ?></p>
            </div>
            <div>
                <a href="reports.php?page_id=<?php echo $page_id; ?>" class="btn btn-secondary me-2">
                    بازگشت به گزارش‌ها
                </a>
                <a href="generate_pdf.php?page_id=<?php echo $page_id; ?>&type=predictions" class="btn btn-primary">
                    دانلود PDF
                </a>
            </div>
        </div>

        <!-- KPI Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">اهداف تعیین شده (KPI)</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">رشد ماهانه فالوور:</label>
                        <span class="badge bg-primary"><?php echo $page['follower_growth_kpi']; ?>%</span>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">رشد ماهانه تعامل:</label>
                        <span class="badge bg-primary"><?php echo $page['engagement_growth_kpi']; ?>%</span>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">رشد ماهانه بازدید:</label>
                        <span class="badge bg-primary"><?php echo $page['view_growth_kpi']; ?>%</span>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">نسبت لید به فالوور:</label>
                        <span class="badge bg-info"><?php echo $page['lead_follower_ratio_kpi']; ?>%</span>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="fw-bold">نسبت مشتری به لید:</label>
                        <span class="badge bg-info"><?php echo $page['customer_lead_ratio_kpi']; ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prediction Charts -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">پیش‌بینی رشد فالوور</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="followersChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">پیش‌بینی رشد تعامل</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="engagementChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">پیش‌بینی رشد بازدید</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="viewsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">پیش‌بینی رشد لید و مشتری</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="leadsCustomersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prediction Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">جدول پیش‌بینی 12 ماه آینده</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>تاریخ</th>
                                <th>فالوور</th>
                                <th>تعامل</th>
                                <th>بازدید</th>
                                <th>لید</th>
                                <th>مشتری</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($predictions as $month => $prediction): ?>
                            <tr>
                                <td><?php echo $prediction['date']; ?></td>
                                <td><?php echo formatNumber($prediction['followers']); ?></td>
                                <td><?php echo formatNumber($prediction['engagement']); ?></td>
                                <td><?php echo formatNumber($prediction['views']); ?></td>
                                <td><?php echo formatNumber($prediction['leads']); ?></td>
                                <td><?php echo formatNumber($prediction['customers']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const predictions = <?php echo json_encode($predictions); ?>;
        const dates = Object.values(predictions).map(p => p.date);
        
        const chartOptions = {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };

        // Followers Chart
        new Chart(document.getElementById('followersChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'فالوور',
                    data: Object.values(predictions).map(p => p.followers),
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });

        // Engagement Chart
        new Chart(document.getElementById('engagementChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'تعامل',
                    data: Object.values(predictions).map(p => p.engagement),
                    borderColor: 'rgb(153, 102, 255)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });

        // Views Chart
        new Chart(document.getElementById('viewsChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'بازدید',
                    data: Object.values(predictions).map(p => p.views),
                    borderColor: 'rgb(255, 99, 132)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });

        // Leads and Customers Chart
        new Chart(document.getElementById('leadsCustomersChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'لید',
                    data: Object.values(predictions).map(p => p.leads),
                    borderColor: 'rgb(54, 162, 235)',
                    tension: 0.1
                },
                {
                    label: 'مشتری',
                    data: Object.values(predictions).map(p => p.customers),
                    borderColor: 'rgb(255, 159, 64)',
                    tension: 0.1
                }]
            },
            options: chartOptions
        });
    </script>
</body>
</html>