<?php
require_once 'auth.php';
require_once 'functions.php';

// پیام‌های نتیجه عملیات
$success = isset($_GET['success']) ? $_GET['success'] : '';
$error = isset($_GET['error']) ? $_GET['error'] : '';

// فیلترها
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$userFilter = isset($_GET['user']) ? (int)$_GET['user'] : 0;

// دریافت گزارش‌های روزانه
$sql = "SELECT dr.*, p.full_name, c.company_name 
        FROM daily_reports dr 
        JOIN personnel p ON dr.personnel_id = p.id 
        JOIN companies c ON p.company_id = c.id";

$where = [];
$params = [];
$types = "";

// فیلتر تاریخ
if ($dateFilter) {
    $where[] = "dr.report_date = ?";
    $params[] = $dateFilter;
    $types .= "s";
}

// فیلتر کاربر
if ($userFilter) {
    $where[] = "dr.personnel_id = ?";
    $params[] = $userFilter;
    $types .= "i";
}

// مدیر سیستم همه گزارش‌ها را می‌بیند، سایر کاربران فقط گزارش‌های شرکت خود را
if (!isSystemAdmin()) {
    $where[] = "p.company_id = ?";
    $params[] = $_SESSION['company_id'];
    $types .= "i";
}

// فیلتر دسته‌بندی
if ($categoryFilter) {
    // اضافه کردن JOIN برای فیلتر بر اساس دسته‌بندی
    $sql = "SELECT DISTINCT dr.*, p.full_name, c.company_name 
            FROM daily_reports dr 
            JOIN personnel p ON dr.personnel_id = p.id 
            JOIN companies c ON p.company_id = c.id 
            JOIN report_items ri ON dr.id = ri.report_id 
            JOIN report_item_categories ric ON ri.id = ric.report_item_id";
    
    $where[] = "ric.category_id = ?";
    $params[] = $categoryFilter;
    $types .= "i";
}

// اضافه کردن شرط‌ها به کوئری
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY dr.report_date DESC, dr.created_at DESC";

// اجرای کوئری
$reports = [];
if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    if ($result) {
        $reports = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// دریافت لیست کاربران برای فیلتر
if (isSystemAdmin()) {
    $users = $conn->query("SELECT id, full_name FROM personnel ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT id, full_name FROM personnel WHERE company_id = ? ORDER BY full_name");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// دریافت لیست دسته‌بندی‌ها برای فیلتر
if (isSystemAdmin()) {
    $categories = $conn->query("SELECT id, name FROM report_categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
} else {
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT id, name FROM report_categories WHERE company_id = ? ORDER BY name");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">گزارش‌های روزانه</h1>
        <a href="daily_report_add.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> ثبت گزارش جدید
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
                <div class="col-md-3">
                    <label for="date" class="form-label">تاریخ</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?php echo $dateFilter; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="user" class="form-label">کاربر</label>
                    <select class="form-select" id="user" name="user">
                        <option value="">همه کاربران</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="category" class="form-label">دسته‌بندی</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">همه دسته‌بندی‌ها</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">اعمال فیلتر</button>
                    <a href="daily_reports.php" class="btn btn-secondary">حذف فیلترها</a>
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
                            <th>تاریخ</th>
                            <th>کاربر</th>
                            <?php if (isSystemAdmin()): ?>
                                <th>شرکت</th>
                            <?php endif; ?>
                            <th>تعداد آیتم‌ها</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo $report['id']; ?></td>
                                <td><?php echo formatDate($report['report_date']); ?></td>
                                <td><?php echo htmlspecialchars($report['full_name']); ?></td>
                                <?php if (isSystemAdmin()): ?>
                                    <td><?php echo htmlspecialchars($report['company_name']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM report_items WHERE report_id = ?");
                                    $stmt->bind_param("i", $report['id']);
                                    $stmt->execute();
                                    $itemCount = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $itemCount;
                                    ?>
                                </td>
                                <td><?php echo formatDate($report['created_at'], 'Y/m/d H:i'); ?></td>
                                <td>
                                    <a href="daily_report_view.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-eye"></i> مشاهده
                                    </a>
                                    <?php if ($_SESSION['user_id'] == $report['personnel_id'] || isSystemAdmin()): ?>
                                        <a href="daily_report_edit.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="bi bi-pencil"></i> ویرایش
                                        </a>
                                        <a href="daily_report_delete.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این گزارش اطمینان دارید؟')">
                                            <i class="bi bi-trash"></i> حذف
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="<?php echo isSystemAdmin() ? 7 : 6; ?>" class="text-center">هیچ گزارشی یافت نشد.</td>
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