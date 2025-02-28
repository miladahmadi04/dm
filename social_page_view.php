<?php
require_once 'auth.php';
require_once 'functions.php';

// دریافت شناسه صفحه
$pageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$pageId) {
    header('Location: social_pages.php');
    exit();
}

// دریافت اطلاعات صفحه
$stmt = $conn->prepare("SELECT sp.*, sn.name as network_name, sn.icon as network_icon, c.company_name 
                       FROM social_pages sp 
                       JOIN social_networks sn ON sp.social_network_id = sn.id 
                       JOIN companies c ON sp.company_id = c.id 
                       WHERE sp.id = ?");
$stmt->bind_param("i", $pageId);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    header('Location: social_pages.php');
    exit();
}

// بررسی دسترسی
if (!isSystemAdmin() && $page['company_id'] != $_SESSION['company_id']) {
    header('Location: social_pages.php');
    exit();
}

// دریافت فیلدها و مقادیر
$stmt = $conn->prepare("SELECT sf.*, snf.field_label, snf.field_type, snf.is_kpi 
                       FROM social_page_fields sf 
                       JOIN social_network_fields snf ON sf.field_id = snf.id 
                       WHERE sf.page_id = ? 
                       ORDER BY snf.sort_order, snf.id");
$stmt->bind_param("i", $pageId);
$stmt->execute();
$fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// دریافت KPI ها
$stmt = $conn->prepare("SELECT pk.*, snf.field_label, km.name as model_name, km.model_type, 
                       related.field_label as related_field_label 
                       FROM page_kpis pk 
                       JOIN social_network_fields snf ON pk.field_id = snf.id 
                       JOIN kpi_models km ON pk.kpi_model_id = km.id 
                       LEFT JOIN social_network_fields related ON pk.related_field_id = related.id 
                       WHERE pk.page_id = ? 
                       ORDER BY snf.sort_order, snf.id");
$stmt->bind_param("i", $pageId);
$stmt->execute();
$kpis = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// پیام‌های نتیجه عملیات
$success = isset($_GET['success']) ? $_GET['success'] : '';

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3"><?php echo htmlspecialchars($page['page_name']); ?></h1>
            <p class="text-muted mb-0">
                <i class="<?php echo htmlspecialchars($page['network_icon']); ?>"></i>
                <?php echo htmlspecialchars($page['network_name']); ?> /
                <?php echo htmlspecialchars($page['company_name']); ?>
            </p>
        </div>
        <div>
            <a href="social_page_edit.php?id=<?php echo $pageId; ?>" class="btn btn-warning">
                <i class="bi bi-pencil"></i> ویرایش
            </a>
            <a href="social_page_kpis.php?id=<?php echo $pageId; ?>" class="btn btn-primary">
                <i class="bi bi-graph-up"></i> اهداف (KPI)
            </a>
            <a href="social_pages.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> بازگشت
            </a>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">اطلاعات کلی</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <td width="30%"><strong>آدرس صفحه:</strong></td>
                            <td>
                                <a href="<?php echo htmlspecialchars($page['page_url']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($page['page_url']); ?>
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>تاریخ شروع:</strong></td>
                            <td><?php echo formatDate($page['start_date']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>تاریخ ثبت:</strong></td>
                            <td><?php echo formatDate($page['created_at']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">اطلاعات تکمیلی</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($fields)): ?>
                        <table class="table">
                            <?php foreach ($fields as $field): ?>
                                <tr>
                                    <td width="30%">
                                        <strong><?php echo htmlspecialchars($field['field_label']); ?>:</strong>
                                        <?php if ($field['is_kpi']): ?>
                                            <span class="badge bg-primary ms-1">KPI</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($field['field_type'] === 'url') {
                                            echo '<a href="' . htmlspecialchars($field['field_value']) . '" target="_blank">' . 
                                                 htmlspecialchars($field['field_value']) . ' <i class="bi bi-box-arrow-up-right"></i></a>';
                                        } else {
                                            echo htmlspecialchars($field['field_value']);
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php else: ?>
                        <p class="text-center">هیچ اطلاعات تکمیلی برای این صفحه ثبت نشده است.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">اهداف (KPI)</h5>
                    <a href="social_page_kpis.php?id=<?php echo $pageId; ?>" class="btn btn-sm btn-primary">مدیریت KPI ها</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($kpis)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>فیلد</th>
                                        <th>مدل</th>
                                        <th>توضیحات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kpis as $kpi): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($kpi['field_label']); ?></td>
                                            <td><?php echo htmlspecialchars($kpi['model_name']); ?></td>
                                            <td>
                                                <?php
                                                if ($kpi['model_type'] === 'growth_over_time') {
                                                    echo "رشد " . ($kpi['growth_value'] < 10 ? " به میزان " . $kpi['growth_value'] . "% " : " به میزان " . $kpi['growth_value'] . " واحد ") . 
                                                         "هر " . $kpi['growth_period_days'] . " روز";
                                                } else if ($kpi['model_type'] === 'percentage_of_field') {
                                                    echo $kpi['percentage_value'] . "% از فیلد " . htmlspecialchars($kpi['related_field_label']);
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">هیچ هدفی (KPI) برای این صفحه تعریف نشده است.</p>
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