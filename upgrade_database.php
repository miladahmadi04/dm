<?php
require_once 'config.php';

// تابع بررسی وجود جدول
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// ماژول 1: کاربران - جدول شرکت‌ها
if (!tableExists($conn, 'companies')) {
    $conn->query("CREATE TABLE `companies` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_name` varchar(255) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول companies ایجاد شد.<br>";
}

// ماژول 1: کاربران - جدول نقش‌ها
if (!tableExists($conn, 'roles')) {
    $conn->query("CREATE TABLE `roles` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role_name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول roles ایجاد شد.<br>";
    
    // نقش‌های پیش‌فرض
    $conn->query("INSERT INTO `roles` (`role_name`, `description`) VALUES 
        ('مدیر سیستم', 'دسترسی کامل به تمام بخش‌های سیستم'),
        ('مدیر شرکت', 'مدیریت اطلاعات مربوط به شرکت'),
        ('کارمند', 'دسترسی محدود به سیستم')");
}

// ماژول 1: کاربران - جدول پرسنل
if (!tableExists($conn, 'personnel')) {
    $conn->query("CREATE TABLE `personnel` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) NOT NULL,
        `role_id` int(11) NOT NULL,
        `full_name` varchar(255) NOT NULL,
        `gender` enum('male','female','other') NOT NULL,
        `email` varchar(255) NOT NULL,
        `mobile` varchar(20) NOT NULL,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        KEY `company_id` (`company_id`),
        KEY `role_id` (`role_id`),
        CONSTRAINT `personnel_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
        CONSTRAINT `personnel_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول personnel ایجاد شد.<br>";
}

// ماژول 2: شبکه‌های اجتماعی - جدول شبکه‌های اجتماعی
if (!tableExists($conn, 'social_networks')) {
    $conn->query("CREATE TABLE `social_networks` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `icon` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول social_networks ایجاد شد.<br>";
    
    // شبکه‌های اجتماعی پیش‌فرض
    $conn->query("INSERT INTO `social_networks` (`name`, `icon`) VALUES 
        ('Instagram', 'bi bi-instagram'),
        ('Twitter', 'bi bi-twitter'),
        ('Facebook', 'bi bi-facebook'),
        ('LinkedIn', 'bi bi-linkedin'),
        ('YouTube', 'bi bi-youtube')");
}

// ماژول 2: شبکه‌های اجتماعی - جدول فیلدهای شبکه اجتماعی
if (!tableExists($conn, 'social_network_fields')) {
    $conn->query("CREATE TABLE `social_network_fields` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `social_network_id` int(11) NOT NULL,
        `field_name` varchar(255) NOT NULL,
        `field_label` varchar(255) NOT NULL,
        `field_type` enum('text','number','date','url') NOT NULL,
        `is_required` tinyint(1) NOT NULL DEFAULT 0,
        `is_kpi` tinyint(1) NOT NULL DEFAULT 0,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `social_network_id` (`social_network_id`),
        CONSTRAINT `social_network_fields_ibfk_1` FOREIGN KEY (`social_network_id`) REFERENCES `social_networks` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول social_network_fields ایجاد شد.<br>";
    
    // فیلدهای پیش‌فرض برای اینستاگرام
    $conn->query("INSERT INTO `social_network_fields` 
        (`social_network_id`, `field_name`, `field_label`, `field_type`, `is_required`, `is_kpi`, `sort_order`) VALUES 
        (1, 'instagram_url', 'آدرس اینستاگرام', 'text', 1, 0, 1),
        (1, 'followers', 'تعداد فالوور', 'number', 1, 1, 2),
        (1, 'engagement', 'تعداد تعامل', 'number', 1, 1, 3),
        (1, 'views', 'تعداد بازدید', 'number', 1, 1, 4),
        (1, 'leads', 'تعداد لید', 'number', 0, 1, 5),
        (1, 'customers', 'تعداد مشتری', 'number', 0, 1, 6)");
}

// ماژول 2: شبکه‌های اجتماعی - جدول صفحات اجتماعی
if (!tableExists($conn, 'social_pages')) {
    $conn->query("CREATE TABLE `social_pages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) NOT NULL,
        `social_network_id` int(11) NOT NULL,
        `page_name` varchar(255) NOT NULL,
        `page_url` varchar(255) NOT NULL,
        `start_date` date NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`),
        KEY `social_network_id` (`social_network_id`),
        CONSTRAINT `social_pages_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
        CONSTRAINT `social_pages_ibfk_2` FOREIGN KEY (`social_network_id`) REFERENCES `social_networks` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول social_pages ایجاد شد.<br>";
}

// ماژول 2: شبکه‌های اجتماعی - جدول مقادیر صفحات
if (!tableExists($conn, 'social_page_fields')) {
    $conn->query("CREATE TABLE `social_page_fields` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `page_id` int(11) NOT NULL,
        `field_id` int(11) NOT NULL,
        `field_value` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `page_id` (`page_id`),
        KEY `field_id` (`field_id`),
        CONSTRAINT `social_page_fields_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `social_pages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `social_page_fields_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `social_network_fields` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول social_page_fields ایجاد شد.<br>";
}

// ماژول 2: شبکه‌های اجتماعی - جدول مدل‌های KPI
if (!tableExists($conn, 'kpi_models')) {
    $conn->query("CREATE TABLE `kpi_models` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(255) NOT NULL,
        `description` text DEFAULT NULL,
        `model_type` enum('growth_over_time','percentage_of_field') NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول kpi_models ایجاد شد.<br>";
    
    // مدل‌های KPI پیش‌فرض
    $conn->query("INSERT INTO `kpi_models` (`name`, `description`, `model_type`) VALUES 
        ('رشد زمانی', 'انتظار دارم فیلد X هر Y روز به مقدار N رشد کند', 'growth_over_time'),
        ('درصد از فیلد دیگر', 'انتظار دارم فیلد X به مقدار N درصد از فیلد دیگر باشد', 'percentage_of_field')");
}

// ماژول 2: شبکه‌های اجتماعی - جدول KPI های صفحات
if (!tableExists($conn, 'page_kpis')) {
    $conn->query("CREATE TABLE `page_kpis` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `page_id` int(11) NOT NULL,
        `field_id` int(11) NOT NULL,
        `kpi_model_id` int(11) NOT NULL,
        `related_field_id` int(11) DEFAULT NULL,
        `growth_value` decimal(10,2) DEFAULT NULL,
        `growth_period_days` int(11) DEFAULT NULL,
        `percentage_value` decimal(10,2) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `page_id` (`page_id`),
        KEY `field_id` (`field_id`),
        KEY `kpi_model_id` (`kpi_model_id`),
        KEY `related_field_id` (`related_field_id`),
        CONSTRAINT `page_kpis_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `social_pages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `page_kpis_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `social_network_fields` (`id`),
        CONSTRAINT `page_kpis_ibfk_3` FOREIGN KEY (`kpi_model_id`) REFERENCES `kpi_models` (`id`),
        CONSTRAINT `page_kpis_ibfk_4` FOREIGN KEY (`related_field_id`) REFERENCES `social_network_fields` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول page_kpis ایجاد شد.<br>";
}

// ماژول 3: گزارش روزانه پرسنل - جدول دسته‌بندی‌های گزارش
if (!tableExists($conn, 'report_categories')) {
    $conn->query("CREATE TABLE `report_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `company_id` (`company_id`),
        CONSTRAINT `report_categories_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول report_categories ایجاد شد.<br>";
}

// ماژول 3: گزارش روزانه پرسنل - جدول گزارش‌های روزانه
if (!tableExists($conn, 'daily_reports')) {
    $conn->query("CREATE TABLE `daily_reports` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `personnel_id` int(11) NOT NULL,
        `report_date` date NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `personnel_id` (`personnel_id`),
        CONSTRAINT `daily_reports_ibfk_1` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول daily_reports ایجاد شد.<br>";
}

// ماژول 3: گزارش روزانه پرسنل - جدول آیتم‌های گزارش
if (!tableExists($conn, 'report_items')) {
    $conn->query("CREATE TABLE `report_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `report_id` int(11) NOT NULL,
        `content` text NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `report_id` (`report_id`),
        CONSTRAINT `report_items_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول report_items ایجاد شد.<br>";
}

// ماژول 3: گزارش روزانه پرسنل - جدول دسته‌بندی آیتم‌های گزارش
if (!tableExists($conn, 'report_item_categories')) {
    $conn->query("CREATE TABLE `report_item_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `report_item_id` int(11) NOT NULL,
        `category_id` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `report_item_id` (`report_item_id`),
        KEY `category_id` (`category_id`),
        CONSTRAINT `report_item_categories_ibfk_1` FOREIGN KEY (`report_item_id`) REFERENCES `report_items` (`id`) ON DELETE CASCADE,
        CONSTRAINT `report_item_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `report_categories` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول report_item_categories ایجاد شد.<br>";
}

// ماژول 4: پیامرسان - جدول پیام‌ها
if (!tableExists($conn, 'messages')) {
    $conn->query("CREATE TABLE `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sender_id` int(11) NOT NULL,
        `subject` varchar(255) NOT NULL,
        `content` text NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `sender_id` (`sender_id`),
        KEY `parent_id` (`parent_id`),
        CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE,
        CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول messages ایجاد شد.<br>";
}

// ماژول 4: پیامرسان - جدول دریافت‌کنندگان پیام
if (!tableExists($conn, 'message_recipients')) {
    $conn->query("CREATE TABLE `message_recipients` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `message_id` int(11) NOT NULL,
        `recipient_id` int(11) NOT NULL,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `read_at` timestamp NULL DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `message_id` (`message_id`),
        KEY `recipient_id` (`recipient_id`),
        CONSTRAINT `message_recipients_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
        CONSTRAINT `message_recipients_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول message_recipients ایجاد شد.<br>";
}

// ماژول 5: منو - جدول آیتم‌های منو
if (!tableExists($conn, 'menu_items')) {
    $conn->query("CREATE TABLE `menu_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `parent_id` int(11) DEFAULT NULL,
        `title` varchar(255) NOT NULL,
        `link` varchar(255) NOT NULL,
        `icon` varchar(255) DEFAULT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `parent_id` (`parent_id`),
        CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول menu_items ایجاد شد.<br>";
    
    // منوهای پیش‌فرض
    $conn->query("INSERT INTO `menu_items` (`title`, `link`, `icon`, `sort_order`) VALUES 
        ('داشبورد', 'index.php', 'bi bi-house-door', 1),
        ('مدیریت کاربران', 'users.php', 'bi bi-people', 2),
        ('شبکه‌های اجتماعی', 'social_networks.php', 'bi bi-share', 3),
        ('گزارش‌های روزانه', 'daily_reports.php', 'bi bi-journal', 4),
        ('پیام‌ها', 'messages.php', 'bi bi-envelope', 5)");
}

// ماژول 5: منو - جدول دسترسی‌های منو
if (!tableExists($conn, 'role_menu_items')) {
    $conn->query("CREATE TABLE `role_menu_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `role_id` int(11) NOT NULL,
        `menu_item_id` int(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `role_id` (`role_id`),
        KEY `menu_item_id` (`menu_item_id`),
        CONSTRAINT `role_menu_items_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
        CONSTRAINT `role_menu_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    
    echo "جدول role_menu_items ایجاد شد.<br>";
    
    // دسترسی‌های پیش‌فرض برای مدیر سیستم
    $result = $conn->query("SELECT id FROM roles WHERE role_name = 'مدیر سیستم'");
    if ($result->num_rows > 0) {
        $adminRoleId = $result->fetch_assoc()['id'];
        $menuResult = $conn->query("SELECT id FROM menu_items");
        while ($menuItem = $menuResult->fetch_assoc()) {
            $conn->query("INSERT INTO `role_menu_items` (`role_id`, `menu_item_id`) VALUES ($adminRoleId, {$menuItem['id']})");
        }
    }
}

// انجام عملیات به‌روزرسانی برای ادغام سیستم فعلی با سیستم جدید
echo "<h2>به‌روزرسانی سیستم فعلی</h2>";

// انتقال شرکت‌های فعلی به جدول شرکت‌ها
if (tableExists($conn, 'instagram_pages') && tableExists($conn, 'companies')) {
    $result = $conn->query("SELECT DISTINCT company_name FROM instagram_pages WHERE company_name NOT IN (SELECT company_name FROM companies)");
    while ($row = $result->fetch_assoc()) {
        $company_name = $conn->real_escape_string($row['company_name']);
        $conn->query("INSERT INTO companies (company_name) VALUES ('$company_name')");
    }
    echo "شرکت‌های موجود به جدول جدید منتقل شدند.<br>";
}

echo "<p>عملیات به‌روزرسانی دیتابیس با موفقیت انجام شد.</p>";