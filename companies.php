<?php
require_once 'auth.php';
require_once 'functions.php';

// بررسی دسترسی مدیر سیستم
if (!isSystemAdmin()) {
    header('Location: index.php');
    exit();
}

// پیام‌های نتیجه عملیات
$success = '';
$error = '';

// عملیات حذف شرکت
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $companyId = $_GET['delete'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
        $stmt->bind_param("i", $companyId);
        
        if ($stmt->execute()) {
            $success = 'شرکت با موفقیت حذف شد.';
        } else {
            $error = 'خطا در حذف شرکت: ' . $stmt->error;
        }
    } catch (Exception $e) {
        $error = 'خطا در حذف شرکت: ' . $e->getMessage();
    }
}

// عملیات فعال/غیرفعال کردن شرکت
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $companyId = $_GET['toggle'];
    
    try {
        $stmt = $conn->prepare("UPDATE companies SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $companyId);
        
        if ($stmt->execute()) {
            $success = 'وضعیت شرکت با موفقیت تغییر کرد.';
        } else {
            $error = 'خطا در تغییر وضعیت شرکت: ' . $stmt->error;
        }
    } catch (Exception $e) {
        $error = 'خطا در تغییر وضعیت شرکت: ' . $e->getMessage();
    }
}

// عملیات افزودن شرکت جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_company'])) {
    $companyName = trim($_POST['company_name']);
    
    if (empty($companyName)) {
        $error = 'نام شرکت نمی‌تواند خالی باشد.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO companies (company_name) VALUES (?)");
            $stmt->bind_param("s", $companyName);
            
            if ($stmt->execute()) {
                $success = 'شرکت جدید با موفقیت افزوده شد.';
            } else {
                $error = 'خطا در افزودن شرکت: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $error = 'خطا در افزودن شرکت: ' . $e->getMessage();
        }
    }
}

// دریافت لیست شرکت‌ها
$companies = [];
$result = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM personnel WHERE company_id = c.id) as personnel_count FROM companies c ORDER BY c.created_at DESC");
if ($result) {
    $companies = $result->fetch_all(MYSQLI_ASSOC);
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">مدیریت شرکت‌ها</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
            <i class="bi bi-plus-lg"></i> افزودن شرکت جدید
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
                            <th>نام شرکت</th>
                            <th>تعداد پرسنل</th>
                            <th>تاریخ ایجاد</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td><?php echo $company['id']; ?></td>
                                <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                <td><?php echo $company['personnel_count']; ?></td>
                                <td><?php echo $company['created_at']; ?></td>
                                <td>
                                    <?php if ($company['is_active']): ?>
                                        <span class="badge bg-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="company_edit.php?id=<?php echo $company['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                    <a href="companies.php?toggle=<?php echo $company['id']; ?>" class="btn btn-sm <?php echo $company['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                        <i class="bi <?php echo $company['is_active'] ? 'bi-toggle-off' : 'bi-toggle-on'; ?>"></i> 
                                        <?php echo $company['is_active'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>
                                    </a>
                                    <a href="companies.php?delete=<?php echo $company['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این شرکت اطمینان دارید؟ تمامی پرسنل و اطلاعات مربوط به این شرکت حذف خواهد شد.')">
                                        <i class="bi bi-trash"></i> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="6" class="text-center">هیچ شرکتی یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Company -->
<div class="modal fade" id="addCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن شرکت جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="company_name" class="form-label">نام شرکت</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_company" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>