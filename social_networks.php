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

// عملیات حذف شبکه اجتماعی
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $networkId = $_GET['delete'];
    
    try {
        // بررسی وجود صفحات وابسته
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM social_pages WHERE social_network_id = ?");
        $stmt->bind_param("i", $networkId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = 'این شبکه اجتماعی دارای صفحات وابسته است و نمی‌توان آن را حذف کرد.';
        } else {
            // حذف فیلدهای مرتبط
            $stmt = $conn->prepare("DELETE FROM social_network_fields WHERE social_network_id = ?");
            $stmt->bind_param("i", $networkId);
            $stmt->execute();
            
            // حذف شبکه اجتماعی
            $stmt = $conn->prepare("DELETE FROM social_networks WHERE id = ?");
            $stmt->bind_param("i", $networkId);
            
            if ($stmt->execute()) {
                $success = 'شبکه اجتماعی با موفقیت حذف شد.';
            } else {
                $error = 'خطا در حذف شبکه اجتماعی: ' . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $error = 'خطا در حذف شبکه اجتماعی: ' . $e->getMessage();
    }
}

// عملیات افزودن شبکه اجتماعی جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_network'])) {
    $networkName = trim($_POST['name']);
    $icon = trim($_POST['icon']);
    
    if (empty($networkName)) {
        $error = 'نام شبکه اجتماعی نمی‌تواند خالی باشد.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO social_networks (name, icon) VALUES (?, ?)");
            $stmt->bind_param("ss", $networkName, $icon);
            
            if ($stmt->execute()) {
                $success = 'شبکه اجتماعی جدید با موفقیت افزوده شد.';
            } else {
                $error = 'خطا در افزودن شبکه اجتماعی: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $error = 'خطا در افزودن شبکه اجتماعی: ' . $e->getMessage();
        }
    }
}

// دریافت لیست شبکه‌های اجتماعی
$networks = [];
$result = $conn->query("SELECT sn.*, 
                       (SELECT COUNT(*) FROM social_network_fields WHERE social_network_id = sn.id) as fields_count,
                       (SELECT COUNT(*) FROM social_pages WHERE social_network_id = sn.id) as pages_count
                       FROM social_networks sn
                       ORDER BY sn.name");
if ($result) {
    $networks = $result->fetch_all(MYSQLI_ASSOC);
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">مدیریت شبکه‌های اجتماعی</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNetworkModal">
            <i class="bi bi-plus-lg"></i> افزودن شبکه اجتماعی جدید
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
                            <th>نام</th>
                            <th>آیکون</th>
                            <th>تعداد فیلدها</th>
                            <th>تعداد صفحات</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($networks as $network): ?>
                            <tr>
                                <td><?php echo $network['id']; ?></td>
                                <td><?php echo htmlspecialchars($network['name']); ?></td>
                                <td><i class="<?php echo htmlspecialchars($network['icon']); ?>"></i> <?php echo htmlspecialchars($network['icon']); ?></td>
                                <td><?php echo $network['fields_count']; ?></td>
                                <td><?php echo $network['pages_count']; ?></td>
                                <td>
                                    <a href="social_network_fields.php?id=<?php echo $network['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bi bi-list-ul"></i> فیلدها
                                    </a>
                                    <a href="social_network_edit.php?id=<?php echo $network['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                    <?php if ($network['pages_count'] == 0): ?>
                                        <a href="social_networks.php?delete=<?php echo $network['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این شبکه اجتماعی اطمینان دارید؟')">
                                            <i class="bi bi-trash"></i> حذف
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($networks)): ?>
                            <tr>
                                <td colspan="6" class="text-center">هیچ شبکه اجتماعی یافت نشد.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Network -->
<div class="modal fade" id="addNetworkModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن شبکه اجتماعی جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">نام شبکه اجتماعی</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="icon" class="form-label">آیکون (کلاس Bootstrap Icons)</label>
                        <input type="text" class="form-control" id="icon" name="icon" placeholder="bi bi-instagram">
                        <div class="form-text">
                            می‌توانید از آیکون‌های <a href="https://icons.getbootstrap.com/" target="_blank">Bootstrap Icons</a> استفاده کنید.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_network" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>