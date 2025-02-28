<?php
require_once 'auth.php';
require_once 'functions.php';

// بررسی دسترسی مدیر سیستم
if (!isSystemAdmin()) {
    header('Location: index.php');
    exit();
}

// دریافت شناسه شبکه اجتماعی
$networkId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// بررسی وجود شبکه اجتماعی
$stmt = $conn->prepare("SELECT * FROM social_networks WHERE id = ?");
$stmt->bind_param("i", $networkId);
$stmt->execute();
$network = $stmt->get_result()->fetch_assoc();

if (!$network) {
    header('Location: social_networks.php');
    exit();
}

// پیام‌های نتیجه عملیات
$success = '';
$error = '';

// عملیات حذف فیلد
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $fieldId = $_GET['delete'];
    
    try {
        // بررسی وجود مقادیر وابسته
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM social_page_fields WHERE field_id = ?");
        $stmt->bind_param("i", $fieldId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $error = 'این فیلد دارای مقادیر ثبت شده است و نمی‌توان آن را حذف کرد.';
        } else {
            // حذف فیلد
            $stmt = $conn->prepare("DELETE FROM social_network_fields WHERE id = ? AND social_network_id = ?");
            $stmt->bind_param("ii", $fieldId, $networkId);
            
            if ($stmt->execute()) {
                $success = 'فیلد با موفقیت حذف شد.';
            } else {
                $error = 'خطا در حذف فیلد: ' . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $error = 'خطا در حذف فیلد: ' . $e->getMessage();
    }
}

// عملیات افزودن فیلد جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_field'])) {
    $fieldName = trim($_POST['field_name']);
    $fieldLabel = trim($_POST['field_label']);
    $fieldType = $_POST['field_type'];
    $isRequired = isset($_POST['is_required']) ? 1 : 0;
    $isKpi = isset($_POST['is_kpi']) ? 1 : 0;
    $sortOrder = (int)$_POST['sort_order'];
    
    if (empty($fieldName) || empty($fieldLabel)) {
        $error = 'نام فیلد و برچسب نمی‌توانند خالی باشند.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO social_network_fields (social_network_id, field_name, field_label, field_type, is_required, is_kpi, sort_order) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiis", $networkId, $fieldName, $fieldLabel, $fieldType, $isRequired, $isKpi, $sortOrder);
            
            if ($stmt->execute()) {
                $success = 'فیلد جدید با موفقیت افزوده شد.';
            } else {
                $error = 'خطا در افزودن فیلد: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $error = 'خطا در افزودن فیلد: ' . $e->getMessage();
        }
    }
}

// دریافت لیست فیلدها
$fields = [];
$stmt = $conn->prepare("SELECT * FROM social_network_fields WHERE social_network_id = ? ORDER BY sort_order, id");
$stmt->bind_param("i", $networkId);
$stmt->execute();
$fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">مدیریت فیلدهای <?php echo htmlspecialchars($network['name']); ?></h1>
            <p class="text-muted">فیلدهای اطلاعاتی مورد نیاز برای هر صفحه <?php echo htmlspecialchars($network['name']); ?> را مدیریت کنید.</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                <i class="bi bi-plus-lg"></i> افزودن فیلد جدید
            </button>
            <a href="social_networks.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> بازگشت
            </a>
        </div>
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
                            <th>نام فیلد</th>
                            <th>برچسب</th>
                            <th>نوع</th>
                            <th>اجباری</th>
                            <th>شاخص KPI</th>
                            <th>ترتیب</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <tr>
                                <td><?php echo $field['id']; ?></td>
                                <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                                <td><?php echo htmlspecialchars($field['field_label']); ?></td>
                                <td>
                                    <?php
                                    $fieldTypes = [
                                        'text' => 'متن',
                                        'number' => 'عدد',
                                        'date' => 'تاریخ',
                                        'url' => 'آدرس اینترنتی'
                                    ];
                                    echo $fieldTypes[$field['field_type']] ?? $field['field_type'];
                                    ?>
                                </td>
                                <td>
                                    <?php if ($field['is_required']): ?>
                                        <span class="badge bg-success">بله</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">خیر</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($field['is_kpi']): ?>
                                        <span class="badge bg-primary">بله</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">خیر</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $field['sort_order']; ?></td>
                                <td>
                                    <a href="social_network_field_edit.php?id=<?php echo $field['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                    <a href="social_network_fields.php?id=<?php echo $networkId; ?>&delete=<?php echo $field['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('آیا از حذف این فیلد اطمینان دارید؟')">
                                        <i class="bi bi-trash"></i> حذف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($fields)): ?>
                            <tr>
                                <td colspan="8" class="text-center">هیچ فیلدی برای این شبکه اجتماعی تعریف نشده است.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Field -->
<div class="modal fade" id="addFieldModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">افزودن فیلد جدید</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="field_name" class="form-label">نام فیلد (انگلیسی)</label>
                        <input type="text" class="form-control" id="field_name" name="field_name" required 
                               pattern="[a-zA-Z0-9_]+" title="فقط حروف انگلیسی، اعداد و زیرخط مجاز است">
                        <div class="form-text">فقط از حروف انگلیسی، اعداد و زیرخط استفاده کنید (مثال: followers_count)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="field_label" class="form-label">برچسب فیلد (فارسی)</label>
                        <input type="text" class="form-control" id="field_label" name="field_label" required>
                        <div class="form-text">نام نمایشی فیلد که در فرم‌ها نمایش داده می‌شود</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="field_type" class="form-label">نوع فیلد</label>
                        <select class="form-select" id="field_type" name="field_type" required>
                            <option value="text">متن</option>
                            <option value="number">عدد</option>
                            <option value="date">تاریخ</option>
                            <option value="url">آدرس اینترنتی</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1">
                            <label class="form-check-label" for="is_required">
                                فیلد اجباری است
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_kpi" name="is_kpi" value="1">
                            <label class="form-check-label" for="is_kpi">
                                استفاده به عنوان شاخص KPI
                            </label>
                            <div class="form-text">فیلدهایی که می‌توانند به عنوان شاخص عملکرد (KPI) استفاده شوند را مشخص کنید</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sort_order" class="form-label">ترتیب نمایش</label>
                        <input type="number" class="form-control" id="sort_order" name="sort_order" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" name="add_field" class="btn btn-primary">ذخیره</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>