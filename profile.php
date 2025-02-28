<?php
require_once 'auth.php';
require_once 'functions.php';

// دریافت اطلاعات کاربر
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT p.*, c.company_name, r.role_name 
                       FROM personnel p 
                       JOIN companies c ON p.company_id = c.id 
                       JOIN roles r ON p.role_id = r.id 
                       WHERE p.id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $mobile = trim($_POST['mobile']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // اعتبارسنجی داده‌ها
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = 'نام و نام خانوادگی نمی‌تواند خالی باشد.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'ایمیل نامعتبر است.';
    }
    
    if (empty($mobile)) {
        $errors[] = 'شماره موبایل نمی‌تواند خالی باشد.';
    }
    
    // بررسی تغییر رمز عبور
    if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
        // بررسی رمز عبور فعلی
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'رمز عبور فعلی اشتباه است.';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'رمز عبور جدید نمی‌تواند خالی باشد.';
        } elseif (strlen($newPassword) < 6) {
            $errors[] = 'رمز عبور جدید باید حداقل 6 کاراکتر باشد.';
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'رمز عبور جدید و تکرار آن مطابقت ندارند.';
        }
    }
    
    // اگر خطایی وجود نداشت، به‌روزرسانی پروفایل
    if (empty($errors)) {
        try {
            // به‌روزرسانی اطلاعات پایه
            $stmt = $conn->prepare("UPDATE personnel SET full_name = ?, email = ?, mobile = ? WHERE id = ?");
            $stmt->bind_param("sssi", $fullName, $email, $mobile, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("خطا در به‌روزرسانی اطلاعات: " . $stmt->error);
            }
            
            // به‌روزرسانی رمز عبور در صورت نیاز
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE personnel SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if (!$stmt->execute()) {
                    throw new Exception("خطا در به‌روزرسانی رمز عبور: " . $stmt->error);
                }
            }
            
            $success = 'پروفایل با موفقیت به‌روزرسانی شد.';
            
            // به‌روزرسانی اطلاعات کاربر
            $stmt = $conn->prepare("SELECT p.*, c.company_name, r.role_name 
                                   FROM personnel p 
                                   JOIN companies c ON p.company_id = c.id 
                                   JOIN roles r ON p.role_id = r.id 
                                   WHERE p.id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
        } catch (Exception $e) {
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
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">پروفایل کاربری</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">شرکت</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['company_name']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نقش کاربری</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role_name']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">نام و نام خانوادگی</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">نام کاربری</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">ایمیل</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mobile" class="form-label">موبایل</label>
                                    <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="mb-3">تغییر رمز عبور</h5>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">رمز عبور فعلی</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">رمز عبور جدید</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">تکرار رمز عبور جدید</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// شامل کردن فوتر
include 'footer.php';
?>