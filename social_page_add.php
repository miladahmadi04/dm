<?php
require_once 'auth.php';
require_once 'functions.php';

// دریافت لیست شبکه‌های اجتماعی
$networks = [];
$result = $conn->query("SELECT id, name FROM social_networks ORDER BY name");
if ($result) {
    $networks = $result->fetch_all(MYSQLI_ASSOC);
}

// دریافت لیست شرکت‌ها (فقط برای مدیر سیستم)
$companies = [];
if (isSystemAdmin()) {
    $result = $conn->query("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name");
    if ($result) {
        $companies = $result->fetch_all(MYSQLI_ASSOC);
    }
} else {
    // کاربر غیر مدیر سیستم فقط شرکت خود را می‌بیند
    $companyId = $_SESSION['company_id'];
    $stmt = $conn->prepare("SELECT id, company_name FROM companies WHERE id = ? AND is_active = 1");
    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $companies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // دریافت داده‌های فرم
    $networkId = filter_input(INPUT_POST, 'network_id', FILTER_VALIDATE_INT);
    $companyId = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
    $pageName = trim($_POST['page_name']);
    $pageUrl = trim($_POST['page_url']);
    $startDate = $_POST['start_date'];
    
    // اعتبارسنجی داده‌ها
    $errors = [];
    
    if (!$networkId) {
        $errors[] = 'لطفاً یک شبکه اجتماعی انتخاب کنید.';
    }
    
    if (!$companyId) {
        $errors[] = 'لطفاً یک شرکت انتخاب کنید.';
    }
    
    if (empty($pageName)) {
        $errors[] = 'نام صفحه نمی‌تواند خالی باشد.';
    }
    
    if (empty($pageUrl)) {
        $errors[] = 'آدرس صفحه نمی‌تواند خالی باشد.';
    }
    
    if (empty($startDate)) {
        $errors[] = 'تاریخ شروع نمی‌تواند خالی باشد.';
    }
    
    // بررسی دسترسی کاربر غیر مدیر سیستم
    if (!isSystemAdmin() && $companyId != $_SESSION['company_id']) {
        $errors[] = 'شما مجاز به ایجاد صفحه برای شرکت دیگر نیستید.';
    }
    
    // اگر خطایی وجود نداشت، ذخیره صفحه
    if (empty($errors)) {
        try {
            // شروع تراکنش
            $conn->begin_transaction();
            
            // افزودن صفحه
            $stmt = $conn->prepare("INSERT INTO social_pages (company_id, social_network_id, page_name, page_url, start_date) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $companyId, $networkId, $pageName, $pageUrl, $startDate);
            
            if (!$stmt->execute()) {
                throw new Exception("خطا در ذخیره صفحه: " . $stmt->error);
            }
            
            $pageId = $conn->insert_id;
            
            // دریافت فیلدهای شبکه اجتماعی
            $stmt = $conn->prepare("SELECT * FROM social_network_fields WHERE social_network_id = ? ORDER BY sort_order");
            $stmt->bind_param("i", $networkId);
            $stmt->execute();
            $fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // افزودن مقادیر فیلدها
            foreach ($fields as $field) {
                $fieldId = $field['id'];
                $fieldValue = $_POST['field_' . $fieldId] ?? '';
                
                if ($field['is_required'] && empty($fieldValue)) {
                    throw new Exception("فیلد '" . $field['field_label'] . "' اجباری است.");
                }
                
                $stmt = $conn->prepare("INSERT INTO social_page_fields (page_id, field_id, field_value) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $pageId, $fieldId, $fieldValue);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در ذخیره مقدار فیلد: " . $stmt->error);
                }
            }
            
            // تایید تراکنش
            $conn->commit();
            
            // هدایت به صفحه مشاهده
            header("Location: social_page_view.php?id=" . $pageId . "&success=صفحه با موفقیت ایجاد شد.");
            exit();
        } catch (Exception $e) {
            // لغو تراکنش در صورت بروز خطا
            $conn->rollback();
            $error = $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// شامل کردن هدر
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">افزودن صفحه جدید</h1>
        <a href="social_pages.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> بازگشت
        </a>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form id="addPageForm" method="POST" action="">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="network_id" class="form-label">شبکه اجتماعی</label>
                            <select class="form-select" id="network_id" name="network_id" required onchange="loadFields()">
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($networks as $network): ?>
                                    <option value="<?php echo $network['id']; ?>" <?php echo (isset($_POST['network_id']) && $_POST['network_id'] == $network['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($network['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="company_id" class="form-label">شرکت</label>
                            <select class="form-select" id="company_id" name="company_id" required <?php echo count($companies) == 1 ? 'disabled' : ''; ?>>
                                <?php if (count($companies) > 1): ?>
                                    <option value="">انتخاب کنید</option>
                                <?php endif; ?>
                                
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" <?php echo (count($companies) == 1 || (isset($_POST['company_id']) && $_POST['company_id'] == $company['id'])) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($companies) == 1): ?>
                                <input type="hidden" name="company_id" value="<?php echo $companies[0]['id']; ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="page_name" class="form-label">نام صفحه</label>
                            <input type="text" class="form-control" id="page_name" name="page_name" value="<?php echo isset($_POST['page_name']) ? htmlspecialchars($_POST['page_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="page_url" class="form-label">آدرس صفحه</label>
                            <input type="url" class="form-control" id="page_url" name="page_url" value="<?php echo isset($_POST['page_url']) ? htmlspecialchars($_POST['page_url']) : ''; ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">تاریخ شروع</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div id="fields-container">
                    <!-- فیلدهای شبکه اجتماعی به صورت پویا اینجا بارگذاری می‌شوند -->
                </div>
                
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary">ذخیره</button>
                    <a href="social_pages.php" class="btn btn-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // تابع بارگذاری فیلدهای شبکه اجتماعی
    function loadFields() {
        const networkId = document.getElementById('network_id').value;
        const fieldsContainer = document.getElementById('fields-container');
        
        if (!networkId) {
            fieldsContainer.innerHTML = '';
            return;
        }
        
        // ارسال درخواست AJAX برای دریافت فیلدها
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_network_fields.php?network_id=' + networkId, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                fieldsContainer.innerHTML = xhr.responseText;
            } else {
                fieldsContainer.innerHTML = '<div class="alert alert-danger">خطا در بارگذاری فیلدها</div>';
            }
        };
        
        xhr.onerror = function() {
            fieldsContainer.innerHTML = '<div class="alert alert-danger">خطا در برقراری ارتباط با سرور</div>';
        };
        
        fieldsContainer.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">در حال بارگذاری...</span></div></div>';
        
        xhr.send();
    }
    
    // بارگذاری فیلدها در زمان بارگذاری صفحه اگر شبکه اجتماعی انتخاب شده باشد
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('network_id').value) {
            loadFields();
        }
    });
</script>

<?php
// شامل کردن فوتر
include 'footer.php';
?>