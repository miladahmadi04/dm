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

// فیلتر شرکت
$companyFilter = $_GET['company'] ?? null;

// دریافت لیست شرکت‌ها برای فیلتر
$companies = [];
if (isSystemAdmin()) {
    $result = $conn->query("SELECT id, company_name FROM companies ORDER BY company_name");
    $companies = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // مدیر شرکت فقط شرکت خود را می‌بیند
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT id, company_name FROM companies WHERE id = ?");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $companies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $companyFilter = $companyId;
}

// دریافت لیست نقش‌ها
$roles = [];
$sql = "SELECT id, role_name FROM roles";
if (!isSystemAdmin()) {
    // مدیر شرکت نمی‌تواند مدیر سیستم تعریف کند
    $sql .= " WHERE role_name != 'مدیر سیستم'";
}
$result = $conn->query($sql);
$roles = $result->fetch_all(MYSQLI_ASSOC);

// عملیات حذف کاربر
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $userId = $_GET['delete'];
    
    // بررسی دسترسی
    if (!isSystemAdmin()) {
        $stmt = $conn->prepare("SELECT company_id FROM personnel WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['company_id'] != $_SESSION['company_id']) {
            $error = 'شما دسترسی به حذف این کاربر را ندارید.';
            header("Location: users.php?error=" . urlencode($error));
            exit();
        }
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM personnel WHERE id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $success = 'کاربر با موفقیت حذف شد.';
        } else {
            $error = 'خطا در حذف کاربر: ' . $stmt->error;
        }
    } catch (Exception $e) {
        $error = 'خطا در حذف کاربر: ' . $e->getMessage();
    }
}

// عملیات فعال/غیرفعال کردن کاربر
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $userId = $_GET['toggle'];
    
    // بررسی دسترسی
    if (!isSystemAdmin()) {
        $stmt = $conn->prepare("SELECT company_id FROM personnel WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if ($user['company_id'] != $_SESSION['company_id']) {
            $error = 'شما دسترسی به تغییر وضعیت این کاربر را ندارید.';
            header("Location: users.php?error=" . urlencode($error));
            exit();
        }
    }
    
    try {
        $stmt = $conn->prepare("UPDATE personnel SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            $success = 'وضعیت کاربر با موفقیت تغییر کرد.';
        } else {
            $error = 'خطا در تغییر وضعیت کاربر: ' . $stmt->error;
        }
    } catch (Exception $e) {
        $error = 'خطا در تغییر وضعیت کاربر: ' . $e->getMessage();
    }
}

// دریافت لیست کاربران
$users = [];
$sql = "SELECT p.*, c.company_name, r.role_name 
        FROM personnel p 
        JOIN companies c ON p.company_id = c.id 
        JOIN roles r ON p.role_id = r.id";

$where = [];
$params = [];
$types = "";

if ($companyFilter) {
    $where[] = "p.company_id = ?";
    $params[] = $companyFilter;
    $types .= "i";
}

if (!isSystemAdmin()) {
    $where[] = "p.company_id = ?";
    $params[] = $_SESSION['company_id'];
    $types .= "i";
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY p.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $result = $conn->query($sql);
    $users = $result->fetch_all(MYSQLI_ASSOC);
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">مدیریت کاربران</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg"></i> افزودن کاربر جدید
        </button>
    </div>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- فیلترها -->
    <?php if (isSystemAdmin() && count($companies) > 1): ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row">
                <div class="col-md-6 mb-2">
                    <label for="company" class="form-label">فیلتر بر اساس شرکت</label>
                    <select name="company" id="company" class="form-select" onchange="this.form.submit()">
                        <option value="">همه شرکت‌ها</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $companyFilter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>نام و نام خانوادگی</th>
                            <th>شرکت</th>
                            <th>نقش</th>
                            <th>ایمیل</th>
                            <th>موبایل</th>
                            <th>نام کاربری</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['mobile']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">فعال</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">غیرفعال</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                    <a href="users.php?toggle=<?php echo $user['id']; ?>" class="btn btn-sm <?php echo $user['is_active'] ? 'btn-secondary' : 'btn-success'; ?>">
                                        <i class="bi <?php echo $user['is_active'] ? 'bi-toggle-off' : 'bi-toggle-on'; ?>"></i> 
                                        <?php echo $user['is_active'] ? 'غیرفعال کردن' : 'فعال کردن'; ?>
                                    </a>
                                    <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این کاربر اطمینان دارید؟')">
                                        <i class="bi bi-trash"></i> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" class="text-center">هیچ کاربری یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن کاربر جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="user_add.php">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="company_id" class="form-label">شرکت</label>
                            <select name="company_id" id="company_id" class="form-select" required>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" <?php echo count($companies) == 1 ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role_id" class="form-label">نقش کاربری</label>
                            <select name="role_id" id="role_id" class="form-select" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">نام و نام خانوادگی</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">جنسیت</label>
                            <select name="gender" id="gender" class="form-select" required>
                                <option value="male">مرد</option>
                                <option value="female">زن</option>
                                <option value="other">سایر</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">ایمیل</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="mobile" class="form-label">موبایل</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">نام کاربری</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">رمز عبور</label>
                            <input type="password" class="form-control" id="password" name="password" required>
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

<?php
// شامل کردن فوتر
include 'footer.php';
?>