<?php
require_once 'auth.php';
require_once 'functions.php';

// بررسی دسترسی مدیر سیستم یا مدیر شرکت
if (!isSystemAdmin() && !isCompanyAdmin()) {
    header('Location: index.php');
    exit();
}

// پیام‌های نتیجه عملیات
$success = '';
$error = '';

// عملیات حذف دسته‌بندی
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $categoryId = $_GET['delete'];
    
    // بررسی دسترسی
    if (!isSystemAdmin()) {
        $stmt = $conn->prepare("SELECT company_id FROM report_categories WHERE id = ?");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $category = $stmt->get_result()->fetch_assoc();
        
        if ($category['company_id'] != $_SESSION['company_id']) {
          $error = 'شما دسترسی به حذف این دسته‌بندی را ندارید.';
        }
    }
    
    try {
        // بررسی استفاده از دسته‌بندی در آیتم‌های گزارش
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM report_item_categories WHERE category_id = ?");
        $stmt->bind_param("i", $categoryId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = 'این دسته‌بندی در گزارش‌ها استفاده شده است و نمی‌توان آن را حذف کرد.';
        } else {
            // حذف دسته‌بندی
            $stmt = $conn->prepare("DELETE FROM report_categories WHERE id = ?");
            $stmt->bind_param("i", $categoryId);
            
            if ($stmt->execute()) {
                $success = 'دسته‌بندی با موفقیت حذف شد.';
            } else {
                $error = 'خطا در حذف دسته‌بندی: ' . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $error = 'خطا در حذف دسته‌بندی: ' . $e->getMessage();
    }
}

// عملیات افزودن دسته‌بندی جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $categoryName = trim($_POST['name']);
    $companyId = isSystemAdmin() ? $_POST['company_id'] : $_SESSION['company_id'];
    
    if (empty($categoryName)) {
        $error = 'نام دسته‌بندی نمی‌تواند خالی باشد.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO report_categories (company_id, name) VALUES (?, ?)");
            $stmt->bind_param("is", $companyId, $categoryName);
            
            if ($stmt->execute()) {
                $success = 'دسته‌بندی جدید با موفقیت افزوده شد.';
            } else {
                $error = 'خطا در افزودن دسته‌بندی: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $error = 'خطا در افزودن دسته‌بندی: ' . $e->getMessage();
        }
    }
}

// دریافت لیست شرکت‌ها (فقط برای مدیر سیستم)
$companies = [];
if (isSystemAdmin()) {
    $result = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");
    if ($result) {
        $companies = $result->fetch_all(MYSQLI_ASSOC);
    }
}

// دریافت لیست دسته‌بندی‌ها
$categories = [];
$sql = "SELECT rc.*, c.company_name FROM report_categories rc JOIN companies c ON rc.company_id = c.id";

if (!isSystemAdmin()) {
    $sql .= " WHERE rc.company_id = " . $_SESSION['company_id'];
}

$sql .= " ORDER BY c.company_name, rc.name";

$result = $conn->query($sql);
if ($result) {
    $categories = $result->fetch_all(MYSQLI_ASSOC);
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">مدیریت دسته‌بندی‌های گزارش</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-lg"></i> افزودن دسته‌بندی جدید
        </button>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام دسته‌بندی</th>
                            <?php if (isSystemAdmin()): ?>
                                <th>شرکت</th>
                            <?php endif; ?>
                            <th>تعداد استفاده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <?php if (isSystemAdmin()): ?>
                                    <td><?php echo htmlspecialchars($category['company_name']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php
                                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM report_item_categories WHERE category_id = ?");
                                    $stmt->bind_param("i", $category['id']);
                                    $stmt->execute();
                                    $useCount = $stmt->get_result()->fetch_assoc()['count'];
                                    echo $useCount;
                                    ?>
                                </td>
                                <td>
                                    <a href="report_category_edit.php?id=<?php echo $category['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                    <a href="report_categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این دسته‌بندی اطمینان دارید؟')">
                                        <i class="bi bi-trash"></i> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="<?php echo isSystemAdmin() ? 5 : 4; ?>" class="text-center">هیچ دسته‌بندی یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن دسته‌بندی جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <?php if (isSystemAdmin()): ?>
                        <div class="mb-3">
                            <label for="company_id" class="form-label">شرکت</label>
                            <select class="form-select" id="company_id" name="company_id" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">نام دسته‌بندی</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_category" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>