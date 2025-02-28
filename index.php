<?php
require_once 'auth.php';
require_once 'functions.php';

// شامل کردن هدر
include 'header.php';

// دریافت اطلاعات آماری
$stats = [];

// تعداد شرکت‌ها (فقط برای مدیر سیستم)
if (isSystemAdmin()) {
    $result = $conn->query("SELECT COUNT(*) as count FROM companies");
    $stats['companies'] = $result->fetch_assoc()['count'];
}

// تعداد کاربران
if (isSystemAdmin()) {
    $result = $conn->query("SELECT COUNT(*) as count FROM personnel");
    $stats['users'] = $result->fetch_assoc()['count'];
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM personnel WHERE company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stats['users'] = $stmt->get_result()->fetch_assoc()['count'];
}

// تعداد صفحات شبکه‌های اجتماعی
if (isSystemAdmin()) {
    $result = $conn->query("SELECT COUNT(*) as count FROM social_pages");
    $stats['social_pages'] = $result->fetch_assoc()['count'];
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM social_pages WHERE company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stats['social_pages'] = $stmt->get_result()->fetch_assoc()['count'];
}

// تعداد گزارش‌های روزانه
if (isSystemAdmin()) {
    $result = $conn->query("SELECT COUNT(*) as count FROM daily_reports");
    $stats['daily_reports'] = $result->fetch_assoc()['count'];
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM daily_reports dr 
                           JOIN personnel p ON dr.personnel_id = p.id 
                           WHERE p.company_id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $stats['daily_reports'] = $stmt->get_result()->fetch_assoc()['count'];
}

// آخرین پیام‌های دریافتی
$newMessages = getNewMessages();

// دریافت آخرین گزارش‌های روزانه
if (isSystemAdmin()) {
    $recentReports = $conn->query("SELECT dr.*, p.full_name, c.company_name 
                                  FROM daily_reports dr 
                                  JOIN personnel p ON dr.personnel_id = p.id 
                                  JOIN companies c ON p.company_id = c.id 
                                  ORDER BY dr.created_at DESC LIMIT 5");
    $recentReports = $recentReports->fetch_all(MYSQLI_ASSOC);
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT dr.*, p.full_name 
                           FROM daily_reports dr 
                           JOIN personnel p ON dr.personnel_id = p.id 
                           WHERE p.company_id = ? 
                           ORDER BY dr.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $recentReports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// دریافت آخرین صفحات اجتماعی
if (isSystemAdmin()) {
    $recentSocialPages = $conn->query("SELECT sp.*, c.company_name, sn.name as network_name 
                                      FROM social_pages sp 
                                      JOIN companies c ON sp.company_id = c.id 
                                      JOIN social_networks sn ON sp.social_network_id = sn.id 
                                      ORDER BY sp.created_at DESC LIMIT 5");
    $recentSocialPages = $recentSocialPages->fetch_all(MYSQLI_ASSOC);
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT sp.*, sn.name as network_name 
                           FROM social_pages sp 
                           JOIN social_networks sn ON sp.social_network_id = sn.id 
                           WHERE sp.company_id = ? 
                           ORDER BY sp.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $recentSocialPages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">داشبورد</h1>
    
    <!-- آمار کلی -->
    <div class="row mb-4">
        <?php if (isSystemAdmin()): ?>
        <div class="col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-primary text-uppercase mb-1">شرکت‌ها</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo formatNumber($stats['companies']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-building fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-3 mb-4">
            <div class="card border-left-success shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-success text-uppercase mb-1">کاربران</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo formatNumber($stats['users']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-info shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-info text-uppercase mb-1">صفحات اجتماعی</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo formatNumber($stats['social_pages']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-share fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-warning shadow h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-warning text-uppercase mb-1">گزارش‌های روزانه</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo formatNumber($stats['daily_reports']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-journal-text fs-2 text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- آخرین پیام‌ها -->
        <div class="col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">آخرین پیام‌های دریافتی</h6>
                    <a href="messages.php" class="btn btn-sm btn-primary">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($newMessages)): ?>
                        <div class="list-group">
                            <?php foreach ($newMessages as $message): ?>
                                <a href="messages.php?read=<?php echo $message['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                        <small><?php echo formatDate($message['created_at']); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo mb_substr(htmlspecialchars($message['content']), 0, 100) . '...'; ?></p>
                                    <small>فرستنده: <?php echo htmlspecialchars($message['sender_name']); ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ پیام جدیدی ندارید.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- آخرین گزارش‌های روزانه -->
        <div class="col-md-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">آخرین گزارش‌های روزانه</h6>
                    <a href="daily_reports.php" class="btn btn-sm btn-primary">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentReports)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>تاریخ</th>
                                        <th>کاربر</th>
                                        <?php if (isSystemAdmin()): ?>
                                            <th>شرکت</th>
                                        <?php endif; ?>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td><?php echo formatDate($report['report_date']); ?></td>
                                            <td><?php echo htmlspecialchars($report['full_name']); ?></td>
                                            <?php if (isSystemAdmin()): ?>
                                                <td><?php echo htmlspecialchars($report['company_name']); ?></td>
                                            <?php endif; ?>
                                            <td>
                                                <a href="daily_report_view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ گزارش روزانه‌ای ثبت نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- آخرین صفحات اجتماعی -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold">آخرین صفحات اجتماعی</h6>
                    <a href="social_pages.php" class="btn btn-sm btn-primary">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentSocialPages)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>نام صفحه</th>
                                        <th>شبکه اجتماعی</th>
                                        <?php if (isSystemAdmin()): ?>
                                            <th>شرکت</th>
                                        <?php endif; ?>
                                        <th>آدرس</th>
                                        <th>تاریخ شروع</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSocialPages as $page): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($page['page_name']); ?></td>
                                            <td><?php echo htmlspecialchars($page['network_name']); ?></td>
                                            <?php if (isSystemAdmin()): ?>
                                                <td><?php echo htmlspecialchars($page['company_name']); ?></td>
                                            <?php endif; ?>
                                            <td><a href="<?php echo htmlspecialchars($page['page_url']); ?>" target="_blank"><?php echo htmlspecialchars($page['page_url']); ?></a></td>
                                            <td><?php echo formatDate($page['start_date']); ?></td>
                                            <td>
                                                <a href="social_page_view.php?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="bi bi-eye"></i> مشاهده
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ صفحه اجتماعی ثبت نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>