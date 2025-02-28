<?php
require_once 'auth.php';
require_once 'functions.php';

// بررسی دسترسی مدیر سیستم یا مدیر شرکت
if (!isSystemAdmin() && !isCompanyAdmin()) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // دریافت و تمیز کردن داده‌ها
    $companyId = filter_input(INPUT_POST, 'company_id', FILTER_VALIDATE_INT);
    $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
    $fullName = trim($_POST['full_name']);
    $gender = $_POST['gender'];
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $mobile = trim($_POST['mobile']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // اعتبارسنجی داده‌ها
    $errors = [];
    
    if (!$companyId) {
        $errors[] = 'شرکت نامعتبر است.';
    }
    
    if (!$roleId) {
        $errors[] = 'نقش کاربری نامعتبر است.';
    }
    
    if (empty($fullName)) {
        $errors[] = 'نام و نام خانوادگی نمی‌تواند خالی باشد.';
    }
    
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $errors[] = 'جنسیت نامعتبر است.';
    }
    
    if (!$email) {
        $errors[] = 'ایمیل نامعتبر است.';
    }
    
    if (empty($mobile)) {
        $errors[] = 'موبایل نمی‌تواند خالی باشد.';
    }
    
    if (empty($username)) {
        $errors[] = 'نام کاربری نمی‌تواند خالی باشد.';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'رمز عبور باید حداقل 6 کاراکتر باشد.';
    }
    
    // بررسی یکتا بودن نام کاربری
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM personnel WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        $errors[] = 'این نام کاربری قبلاً استفاده شده است.';
    }
    
    // بررسی دسترسی مدیر شرکت
    if (!isSystemAdmin()) {
        // بررسی اینکه آیا کاربر مجاز به ایجاد کاربر برای شرکت انتخاب شده است
        if ($companyId != $_SESSION['company_id']) {
            $errors[] = 'شما مجاز به ایجاد کاربر برای شرکت دیگر نیستید.';
        }
        
        // بررسی اینکه آیا مدیر شرکت مجاز به ایجاد کاربر با نقش مدیر سیستم است
        $stmt = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
        $stmt->bind_param("i", $roleId);
        $stmt->execute();
        $role = $stmt->get_result()->fetch_assoc();
        
        if ($role['role_name'] === 'مدیر سیستم') {
            $errors[] = 'شما مجاز به ایجاد کاربر با نقش مدیر سیستم نیستید.';
        }
    }
    
    // اگر خطایی وجود نداشت، ذخیره کاربر
    if (empty($errors)) {
        // هش کردن رمز عبور
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO personnel (company_id, role_id, full_name, gender, email, mobile, username, password) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissssss", $companyId, $roleId, $fullName, $gender, $email, $mobile, $username, $hashedPassword);
            
            if ($stmt->execute()) {
                header('Location: users.php?success=1');
                exit();
            } else {
                $error = 'خطا در ذخیره کاربر: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $error = 'خطا در ذخیره کاربر: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
    
    // در صورت وجود خطا، بازگشت به صفحه کاربران
    header('Location: users.php?error=' . urlencode($error));
    exit();
} else {
    // در صورت درخواست مستقیم این صفحه، هدایت به صفحه کاربران
    header('Location: users.php');
    exit();
}
?>