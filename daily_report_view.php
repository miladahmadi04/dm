<?php
require_once 'auth.php';
require_once 'functions.php';

// بررسی دسترسی (همه کاربران می‌توانند گزارش ثبت کنند)
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// دریافت دسته‌بندی‌های گزارش
$companyId = $_SESSION['company_id'];
$stmt = $conn->prepare("SELECT * FROM report_categories WHERE company_id = ? ORDER BY name");
$stmt->bind_param("i", $companyId);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // دریافت داده‌های فرم
    $reportDate = $_POST['report_date'];
    $reportItems = $_POST['item'] ?? [];
    $reportItemCategories = $_POST['categories'] ?? [];
    
    // اعتبارسنجی داده‌ها
    $errors = [];
    
    if (empty($reportDate)) {
        $errors[] = 'تاریخ گزارش نمی‌تواند خالی باشد.';
    }
    
    if (empty($reportItems)) {
        $errors[] = 'حداقل یک آیتم گزارش باید وارد شود.';
    }
    
    // اگر خطایی وجود نداشت، ذخیره گزارش
    if (empty($errors)) {
        try {
            // شروع تراکنش
            $conn->begin_transaction();
            
            // افزودن گزارش
            $userId = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO daily_reports (personnel_id, report_date) VALUES (?, ?)");
            $stmt->bind_param("is", $userId, $reportDate);
            
            if (!$stmt->execute()) {
                throw new Exception("خطا در ذخیره گزارش: " . $stmt->error);
            }
            
            $reportId = $conn->insert_id;
            
            // افزودن آیتم‌های گزارش
            foreach ($reportItems as $index => $itemContent) {
                if (empty(trim($itemContent))) {
                    continue;
                }
                
                $stmt = $conn->prepare("INSERT INTO report_items (report_id, content) VALUES (?, ?)");
                $stmt->bind_param("is", $reportId, $itemContent);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در ذخیره آیتم گزارش: " . $stmt->error);
                }
                
                $itemId = $conn->insert_id;
                
                // افزودن دسته‌بندی‌های آیتم
                if (isset($reportItemCategories[$index]) && !empty($reportItemCategories[$index])) {
                    foreach ($reportItemCategories[$index] as $categoryId) {
                        $stmt = $conn->prepare("INSERT INTO report_item_categories (report_item_id, category_id) VALUES (?, ?)");
                        $stmt->bind_param("ii", $itemId, $categoryId);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("خطا در ذخیره دسته‌بندی آیتم: " . $stmt->error);
                        }
                    }
                }
            }
            
            // تایید تراکنش
            $conn->commit();
            
            // هدایت به صفحه مشاهده
            header("Location: daily_report_view.php?id=" . $reportId . "&success=گزارش با موفقیت ثبت شد.");
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
        <h1 class="h3">ثبت گزارش روزانه جدید</h1>
        <a href="daily_reports.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> بازگشت
        </a>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form id="reportForm" method="POST" action="">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="report_date" class="form-label">تاریخ گزارش</label>
                            <input type="date" class="form-control" id="report_date" name="report_date" 
                                   value="<?php echo isset($_POST['report_date']) ? $_POST['report_date'] : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>
                
                <h5 class="mb-3">آیتم‌های گزارش</h5>
                
                <div id="items-container">
                    <div class="item-row mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">متن گزارش</label>
                                    <textarea class="form-control" name="item[]" rows="3" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">دسته‌بندی‌ها</label>
                                    <div class="row">
                                        <?php foreach ($categories as $index => $category): ?>
                                            <div class="col-md-3 col-sm-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="categories[0][]" value="<?php echo $category['id']; ?>" id="category_0_<?php echo $index; ?>">
                                                    <label class="form-check-label" for="category_0_<?php echo $index; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-outline-danger remove-item" 
                                        onclick="removeItem(this)" style="display: none;">
                                    <i class="bi bi-trash"></i> حذف این آیتم
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <button type="button" class="btn btn-success" onclick="addItem()">
                        <i class="bi bi-plus-lg"></i> افزودن آیتم جدید
                    </button>
                </div>
                
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary">ذخیره گزارش</button>
                    <a href="daily_reports.php" class="btn btn-secondary">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let itemCount = 1; // شمارنده برای آیتم‌های گزارش
    
    // تابع افزودن آیتم جدید
    function addItem() {
        const itemsContainer = document.getElementById('items-container');
        const template = document.querySelector('.item-row').cloneNode(true);
        
        // تغییر نام فیلدها
        const textarea = template.querySelector('textarea');
        textarea.name = `item[${itemCount}]`;
        textarea.value = '';
        
        // تغییر نام چک‌باکس‌های دسته‌بندی
        const checkboxes = template.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach((checkbox, index) => {
            checkbox.name = `categories[${itemCount}][]`;
            checkbox.id = `category_${itemCount}_${index}`;
            checkbox.checked = false;
            
            const label = checkbox.nextElementSibling;
            label.htmlFor = `category_${itemCount}_${index}`;
        });
        
        // نمایش دکمه حذف
        template.querySelector('.remove-item').style.display = 'inline-block';
        
        itemsContainer.appendChild(template);
        itemCount++;
        
        // نمایش/مخفی کردن دکمه‌های حذف
        updateRemoveButtons();
    }
    
    // تابع حذف آیتم
    function removeItem(button) {
        const itemRow = button.closest('.item-row');
        itemRow.remove();
        
        // نمایش/مخفی کردن دکمه‌های حذف
        updateRemoveButtons();
    }
    
    // تابع به‌روزرسانی دکمه‌های حذف
    function updateRemoveButtons() {
        const removeButtons = document.querySelectorAll('.remove-item');
        
        // اگر فقط یک آیتم وجود دارد، دکمه حذف را مخفی کن
        if (removeButtons.length <= 1) {
            removeButtons.forEach(button => button.style.display = 'none');
        } else {
            removeButtons.forEach(button => button.style.display = 'inline-block');
        }
    }
    
    // به‌روزرسانی اولیه دکمه‌های حذف
    document.addEventListener('DOMContentLoaded', updateRemoveButtons);
</script>

<?php
// شامل کردن فوتر
include 'footer.php';
?>