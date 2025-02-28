<?php
session_start();
require_once 'config.php';

// تابع بررسی لاگین بودن کاربر
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// تابع دریافت اطلاعات کاربر لاگین شده
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT p.*, c.company_name, r.role_name 
                           FROM personnel p 
                           JOIN companies c ON p.company_id = c.id 
                           JOIN roles r ON p.role_id = r.id 
                           WHERE p.id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// تابع بررسی دسترسی کاربر به یک منو
function userHasAccess($menuLink) {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM personnel p 
                           JOIN role_menu_items rmi ON p.role_id = rmi.role_id 
                           JOIN menu_items mi ON rmi.menu_item_id = mi.id 
                           WHERE p.id = ? AND mi.link = ?");
    $stmt->bind_param("is", $userId, $menuLink);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] > 0;
}

// تابع لاگین کاربر
function login($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT p.*, c.is_active as company_active 
                           FROM personnel p 
                           JOIN companies c ON p.company_id = c.id 
                           WHERE p.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user && password_verify($password, $user['password'])) {
        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'حساب کاربری شما غیرفعال شده است.'
            ];
        }
        
        if (!$user['company_active']) {
            return [
                'success' => false,
                'message' => 'شرکت شما غیرفعال شده است.'
            ];
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['company_id'] = $user['company_id'];
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    return [
        'success' => false,
        'message' => 'نام کاربری یا رمز عبور اشتباه است.'
    ];
}

// تابع خروج کاربر
function logout() {
    session_unset();
    session_destroy();
}

// تابع بررسی دسترسی مدیر سیستم
function isSystemAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $conn;
    $roleId = $_SESSION['role_id'];
    
    $stmt = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    
    return $role && $role['role_name'] === 'مدیر سیستم';
}

// تابع دریافت تعداد پیام‌های خوانده نشده
function getUnreadMessagesCount() {
    if (!isLoggedIn()) {
        return 0;
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM message_recipients 
                           WHERE recipient_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'];
}