<?php
require_once 'auth.php';
require_once 'functions.php';

// پیام‌های نتیجه عملیات
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// فیلترها
$networkFilter = isset($_GET['network']) ? (int)$_GET['network'] : 0;
$companyFilter = isset($_GET['company']) ? (int)$_GET['company'] : 0;

if (!isSystemAdmin()) {
    // کاربر غیر مدیر سیستم فقط صفحات شرکت خود را می‌بیند
    $companyFilter = $_SESSION['company_id'];
}

// دریافت لیست شبکه‌های اجتماعی برای فیلتر
$networks = [];
$result = $conn->query("SELECT id, name FROM social_networks ORDER BY name");
if ($result) {
    $networks = $result->fetch_all(MYSQLI_ASSOC);
}

// دریافت لیست شرکت‌ها برای فیلتر (فقط برای مدیر سیستم)
$companies = [];
if (isSystemAdmin()) {
    $result = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");
    if ($result) {
        $companies = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// عملیات حذف صفحه
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pageId = $_GET['delete'];
    
    // بررسی دسترسی
    if (!isSystemAdmin()) {
        $stmt = $conn->prepare("SELECT company_id FROM social_pages WHERE id = ?");
        $stmt->bind_param("i", $pageId);
        $stmt->execute();
        $page = $stmt->get_result()->fetch_assoc();
        
        if ($page['company_id'] != $_SESSION['company_id']) {
            header("Location: social_pages.php?error=شما دسترسی به حذف این صفحه را ندارید.");
            exit();
        }
    }
    
    try {
        // حذف مقادیر فیلدها
        $stmt = $conn->prepare("DELETE FROM social_page_fields WHERE page_id = ?");
        $stmt->bind_param("i", $pageId);
        $stmt->execute();
        
        // حذف KPI ها
        $stmt = $conn->prepare("DELETE FROM page_kpis WHERE page_id = ?");
        $stmt->bind_param("i", $pageId);
        $stmt->execute();
        
        // حذف صفحه
        $stmt = $conn->prepare("DELETE FROM social_pages WHERE id = ?");
        $stmt->bind_param("i", $pageId);
        
        if ($stmt->execute()) {
            header("Location: social_pages.php?success=صفحه با موفقیت حذف شد.");
            exit();
        } else {
            header("Location: social_pages.php?error=خطا در حذف صفحه: " . $stmt->error);
            exit();
        }
    } catch (Exception $e) {
        header("Location: social_pages.php?error=خطا در حذف صفحه: " . $e->getMessage());
        exit();
    }
}

// ساخت کوئری دریافت صفحات
$sql = "SELECT sp.*, sn.name as network_name, sn.icon as network_icon, c.company_name 
        FROM social_pages sp 
        JOIN social_networks sn ON sp.social_network_id = sn.id 
        JOIN companies c ON sp.company_id = c.id";

$where = [];
$params = [];
$types = "";

if ($networkFilter) {
    $where[] = "sp.social_network_id = ?";
    $params[] = $networkFilter;
    $types .= "i";
}

if ($companyFilter) {
    $where[] = "sp.company_id = ?";
    $params[] = $companyFilter;
    $types .= "i";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY sp.created_at DESC";

// اجرای کوئری
$pages = [];
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $pages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $pages = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">مدیریت صفحات شبکه‌های اجتماعی</h1>
        <a href="social_page_add.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> افزودن صفحه جدید
        </a>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- فیلترها -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="network" class="form-label">فیلتر بر اساس شبکه اجتماعی</label>
                    <select name="network" id="network" class="form-select">
                        <option value="">همه شبکه‌ها</option>
                        <?php foreach ($networks as $network): ?>
                            <option value="<?php echo $network['id']; ?>" <?php echo $networkFilter == $network['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($network['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (isSystemAdmin()): ?>
                <div class="col-md-4">
                    <label for="company" class="form-label">فیلتر بر اساس شرکت</label>
                    <select name="company" id="company" class="form-select">
                        <option value="">همه شرکت‌ها</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $companyFilter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                    <a href="social_pages.php" class="btn btn-secondary">حذف فیلترها</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
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
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td><?php echo $page['id']; ?></td>
                                <td><?php echo htmlspecialchars($page['page_name']); ?></td>
                                <td>
                                    <i class="<?php echo htmlspecialchars($page['network_icon']); ?>"></i>
                                    <?php echo htmlspecialchars($page['network_name']); ?>
                                </td>
                                <?php if (isSystemAdmin()): ?>
                                    <td><?php echo htmlspecialchars($page['company_name']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <a href="<?php echo htmlspecialchars($page['page_url']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($page['page_url']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatDate($page['start_date']); ?></td>
                                <td>
                                    <a href="social_page_view.php?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> مشاهده
                                    </a>
                                    <a href="social_page_edit.php?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                    <a href="social_page_kpis.php?id=<?php echo $page['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-graph-up"></i> اهداف (KPI)
                                    </a>
                                    <a href="social_pages.php?delete=<?php echo $page['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این صفحه اطمینان دارید؟ تمامی اطلاعات مربوط به این صفحه حذف خواهد شد.')">
                                        <i class="bi bi-trash"></i> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pages)): ?>
                            <tr>
                                <td colspan="<?php echo isSystemAdmin() ? 7 : 6; ?>" class="text-center">هیچ صفحه‌ای یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>