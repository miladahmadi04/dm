<?php
session_start();

// اگر قبلاً نصب شده، به صفحه اصلی هدایت شود
if (file_exists('config.php') && !isset($_GET['reinstall'])) {
    header('Location: index.php');
    exit();
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// بررسی نیازمندی‌های PHP
$requirements = [
    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'MySQLi Extension' => extension_loaded('mysqli'),
    'JSON Extension' => extension_loaded('json'),
    'Writable Directory' => is_writable('.')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // تست اتصال به دیتابیس
        $host = $_POST['db_host'];
        $user = $_POST['db_user'];
        $pass = $_POST['db_pass'];
        $name = $_POST['db_name'];
        
        try {
            $conn = new mysqli($host, $user, $pass);
            if ($conn->connect_error) {
                throw new Exception("خطا در اتصال به دیتابیس: " . $conn->connect_error);
            }

            // ایجاد دیتابیس اگر وجود نداشت
            $conn->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $conn->select_db($name);

            // ذخیره اطلاعات در سشن
            $_SESSION['db_config'] = [
                'host' => $host,
                'user' => $user,
                'pass' => $pass,
                'name' => $name
            ];

            header('Location: install.php?step=2');
            exit();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($step == 2) {
        try {
            $config = $_SESSION['db_config'];
            $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);

            // ایجاد جداول
            $tables = [
                'instagram_pages' => "CREATE TABLE IF NOT EXISTS `instagram_pages` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `company_name` varchar(255) NOT NULL,
                    `instagram_url` varchar(255) NOT NULL,
                    `activity_field` varchar(255) NOT NULL,
                    `start_date` date NOT NULL,
                    `initial_views` int(11) NOT NULL DEFAULT 0,
                    `initial_followers` int(11) NOT NULL DEFAULT 0,
                    `initial_engagement` int(11) NOT NULL DEFAULT 0,
                    `follower_growth_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `engagement_growth_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `view_growth_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `lead_follower_ratio_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `customer_lead_ratio_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

                'monthly_reports' => "CREATE TABLE IF NOT EXISTS `monthly_reports` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `page_id` int(11) NOT NULL,
                    `report_date` date NOT NULL,
                    `followers_count` int(11) NOT NULL DEFAULT 0,
                    `engagement_count` int(11) NOT NULL DEFAULT 0,
                    `views_count` int(11) NOT NULL DEFAULT 0,
                    `customers_count` int(11) NOT NULL DEFAULT 0,
                    `leads_count` int(11) NOT NULL DEFAULT 0,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `page_id` (`page_id`),
                    CONSTRAINT `monthly_reports_ibfk_1` FOREIGN KEY (`page_id`) 
                    REFERENCES `instagram_pages` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",

                'kpi_templates' => "CREATE TABLE IF NOT EXISTS `kpi_templates` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `description` text DEFAULT NULL,
                    `activity_field` varchar(255) NOT NULL,
                    `follower_growth_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `engagement_growth_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `view_growth_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `lead_follower_ratio_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `customer_lead_ratio_kpi` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                // جداول جدید
                'companies' => "CREATE TABLE IF NOT EXISTS `companies` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `company_name` varchar(255) NOT NULL,
                    `is_active` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'roles' => "CREATE TABLE IF NOT EXISTS `roles` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `role_name` varchar(255) NOT NULL,
                    `description` text DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'personnel' => "CREATE TABLE IF NOT EXISTS `personnel` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'social_networks' => "CREATE TABLE IF NOT EXISTS `social_networks` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `icon` varchar(255) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'social_network_fields' => "CREATE TABLE IF NOT EXISTS `social_network_fields` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'social_pages' => "CREATE TABLE IF NOT EXISTS `social_pages` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'social_page_fields' => "CREATE TABLE IF NOT EXISTS `social_page_fields` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'kpi_models' => "CREATE TABLE IF NOT EXISTS `kpi_models` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(255) NOT NULL,
                    `description` text DEFAULT NULL,
                    `model_type` enum('growth_over_time','percentage_of_field') NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'page_kpis' => "CREATE TABLE IF NOT EXISTS `page_kpis` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'report_categories' => "CREATE TABLE IF NOT EXISTS `report_categories` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `company_id` int(11) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `company_id` (`company_id`),
                    CONSTRAINT `report_categories_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'daily_reports' => "CREATE TABLE IF NOT EXISTS `daily_reports` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `personnel_id` int(11) NOT NULL,
                    `report_date` date NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `personnel_id` (`personnel_id`),
                    CONSTRAINT `daily_reports_ibfk_1` FOREIGN KEY (`personnel_id`) REFERENCES `personnel` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'report_items' => "CREATE TABLE IF NOT EXISTS `report_items` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `report_id` int(11) NOT NULL,
                    `content` text NOT NULL,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `report_id` (`report_id`),
                    CONSTRAINT `report_items_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'report_item_categories' => "CREATE TABLE IF NOT EXISTS `report_item_categories` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `report_item_id` int(11) NOT NULL,
                    `category_id` int(11) NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `report_item_id` (`report_item_id`),
                    KEY `category_id` (`category_id`),
                    CONSTRAINT `report_item_categories_ibfk_1` FOREIGN KEY (`report_item_id`) REFERENCES `report_items` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `report_item_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `report_categories` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'messages' => "CREATE TABLE IF NOT EXISTS `messages` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'message_recipients' => "CREATE TABLE IF NOT EXISTS `message_recipients` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'menu_items' => "CREATE TABLE IF NOT EXISTS `menu_items` (
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
                
                'role_menu_items' => "CREATE TABLE IF NOT EXISTS `role_menu_items` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `role_id` int(11) NOT NULL,
                    `menu_item_id` int(11) NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `role_id` (`role_id`),
                    KEY `menu_item_id` (`menu_item_id`),
                    CONSTRAINT `role_menu_items_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
                    CONSTRAINT `role_menu_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;"
            ];

            foreach ($tables as $name => $sql) {
                if (!$conn->query($sql)) {
                    throw new Exception("خطا در ایجاد جدول $name: " . $conn->error);
                }
            }

            // ایجاد فایل config.php
            $config_content = "<?php
define('DB_HOST', '{$config['host']}');
define('DB_USER', '{$config['user']}');
define('DB_PASS', '{$config['pass']}');
define('DB_NAME', '{$config['name']}');
define('DB_CHARSET', 'utf8mb4');

\$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (\$conn->connect_error) {
    die(\"Connection failed: \" . \$conn->connect_error);
}
\$conn->set_charset(DB_CHARSET);
?>";

            if (!file_put_contents('config.php', $config_content)) {
                throw new Exception("خطا در ایجاد فایل config.php");
            }

            // داده‌های پیش‌فرض
            // افزودن نقش‌ها
            $conn->query("INSERT INTO roles (role_name, description) VALUES 
                ('مدیر سیستم', 'دسترسی کامل به تمام بخش‌های سیستم'),
                ('مدیر شرکت', 'مدیریت اطلاعات مربوط به شرکت'),
                ('کارمند', 'دسترسی محدود به سیستم')");
            
            // افزودن شبکه‌های اجتماعی پیش‌فرض
            $conn->query("INSERT INTO social_networks (name, icon) VALUES 
                ('Instagram', 'bi bi-instagram'),
                ('Twitter', 'bi bi-twitter'),
                ('Facebook', 'bi bi-facebook'),
                ('LinkedIn', 'bi bi-linkedin'),
                ('YouTube', 'bi bi-youtube')");
            
            // افزودن فیلدهای پیش‌فرض برای اینستاگرام
            $conn->query("INSERT INTO social_network_fields 
                (social_network_id, field_name, field_label, field_type, is_required, is_kpi, sort_order) VALUES 
                (1, 'instagram_url', 'آدرس اینستاگرام', 'text', 1, 0, 1),
                (1, 'followers', 'تعداد فالوور', 'number', 1, 1, 2),
                (1, 'engagement', 'تعداد تعامل', 'number', 1, 1, 3),
                (1, 'views', 'تعداد بازدید', 'number', 1, 1, 4),
                (1, 'leads', 'تعداد لید', 'number', 0, 1, 5),
                (1, 'customers', 'تعداد مشتری', 'number', 0, 1, 6)");
            
            // افزودن مدل‌های KPI
            $conn->query("INSERT INTO kpi_models (name, description, model_type) VALUES 
                ('رشد زمانی', 'انتظار دارم فیلد X هر Y روز به مقدار N رشد کند', 'growth_over_time'),
                ('درصد از فیلد دیگر', 'انتظار دارم فیلد X به مقدار N درصد از فیلد دیگر باشد', 'percentage_of_field')");
            
            // افزودن منوهای پیش‌فرض
            $conn->query("INSERT INTO menu_items (title, link, icon, sort_order) VALUES 
                ('داشبورد', 'index.php', 'bi bi-house-door', 1),
                ('مدیریت کاربران', 'users.php', 'bi bi-people', 2),
                ('شبکه‌های اجتماعی', 'social_networks.php', 'bi bi-share', 3),
                ('گزارش‌های روزانه', 'daily_reports.php', 'bi bi-journal', 4),
                ('پیام‌ها', 'messages.php', 'bi bi-envelope', 5)");

            $success = "جداول و داده‌های پیش‌فرض با موفقیت ایجاد شدند. لطفاً اطلاعات مدیر سیستم را وارد کنید.";
            
            // بدون نیاز به خروج از صفحه، مرحله 3 را نمایش می‌دهیم
            $step = 3;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($step == 3) {
        try {
            $config = $_SESSION['db_config'];
            $conn = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
            
            // دریافت اطلاعات مدیر سیستم
            $companyName = trim($_POST['company_name']);
            $fullName = trim($_POST['full_name']);
            $gender = $_POST['gender'];
            $email = trim($_POST['email']);
            $mobile = trim($_POST['mobile']);
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            
            // اعتبارسنجی داده‌ها
            if (empty($companyName) || empty($fullName) || empty($username) || empty($password)) {
                throw new Exception("لطفاً تمام فیلدهای ضروری را پر کنید.");
            }
            
            if (strlen($password) < 6) {
                throw new Exception("رمز عبور باید حداقل 6 کاراکتر باشد.");
            }
            
            // شروع تراکنش
            $conn->begin_transaction();
            
            // افزودن شرکت
            $stmt = $conn->prepare("INSERT INTO companies (company_name, is_active) VALUES (?, 1)");
            $stmt->bind_param("s", $companyName);
            
            if (!$stmt->execute()) {
                throw new Exception("خطا در ایجاد شرکت: " . $stmt->error);
            }
            
            $companyId = $conn->insert_id;
            
            // بررسی نقش مدیر سیستم
            $result = $conn->query("SELECT id FROM roles WHERE role_name = 'مدیر سیستم'");
            if ($result->num_rows == 0) {
                throw new Exception("نقش مدیر سیستم تعریف نشده است.");
            }
            
            $roleId = $result->fetch_assoc()['id'];
            
            // هش کردن رمز عبور
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // افزودن مدیر سیستم
            $stmt = $conn->prepare("INSERT INTO personnel (company_id, role_id, full_name, gender, email, mobile, username, password, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("iissssss", $companyId, $roleId, $fullName, $gender, $email, $mobile, $username, $hashedPassword);
            
            if (!$stmt->execute()) {
                throw new Exception("خطا در ایجاد حساب مدیر سیستم: " . $stmt->error);
            }
            
            $adminId = $conn->insert_id;
            
            // اختصاص همه منوها به مدیر سیستم
            $result = $conn->query("SELECT id FROM menu_items");
            while ($menu = $result->fetch_assoc()) {
                $stmt = $conn->prepare("INSERT INTO role_menu_items (role_id, menu_item_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $roleId, $menu['id']);
                $stmt->execute();
            }
            
            // تایید تراکنش
            $conn->commit();
            
            $success = "نصب با موفقیت انجام شد. اکنون می‌توانید وارد سیستم شوید.";
            $installation_complete = true;
            session_destroy();
        } catch (Exception $e) {
            // لغو تراکنش در صورت بروز خطا
            if (isset($conn) && $conn instanceof mysqli) {
                $conn->rollback();
            }
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سیستم مدیریت دیجیتال مارکتینگ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .install-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .requirements-list {
            list-style: none;
            padding: 0;
        }
        .requirement-item {
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .requirement-success {
            background-color: #d4edda;
            color: #155724;
        }
        .requirement-error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title m-0">نصب سیستم مدیریت دیجیتال مارکتینگ</h3>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if (isset($installation_complete) && $installation_complete): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <br>
                        <a href="login.php" class="btn btn-success mt-3">ورود به سیستم</a>
                    </div>
                <?php elseif ($step == 1): ?>
                    <h5 class="mb-4">مرحله 1: بررسی نیازمندی‌ها و تنظیمات دیتابیس</h5>
                    
                    <div class="mb-4">
                        <h6>نیازمندی‌های سیستم:</h6>
                        <ul class="requirements-list">
                            <?php foreach ($requirements as $requirement => $satisfied): ?>
                                <li class="requirement-item <?php echo $satisfied ? 'requirement-success' : 'requirement-error'; ?>">
                                    <?php echo $requirement; ?>
                                    <span class="float-end">
                                        <?php echo $satisfied ? '✓' : '✗'; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if (array_product($requirements)): ?>
                        <form method="POST" action="install.php?step=1">
                            <div class="mb-3">
                                <label class="form-label">آدرس هاست دیتابیس</label>
                                <input type="text" class="form-control" name="db_host" value="localhost" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">نام کاربری دیتابیس</label>
                                <input type="text" class="form-control" name="db_user" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">رمز عبور دیتابیس</label>
                                <input type="password" class="form-control" name="db_pass">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">نام دیتابیس</label>
                                <input type="text" class="form-control" name="db_name" required>
                            </div>
                            <button type="submit" class="btn btn-primary">ادامه نصب</button>
                        </form>
                    <?php endif; ?>

                <?php elseif ($step == 2): ?>
                    <h5 class="mb-4">مرحله 2: نصب دیتابیس</h5>
                    <?php if ($success): ?>
                        <div class="alert alert-success mb-4"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="install.php?step=2">
                        <p>آیا از نصب سیستم اطمینان دارید؟</p>
                        <button type="submit" class="btn btn-success">شروع نصب</button>
                    </form>
                    
                <?php elseif ($step == 3): ?>
                    <h5 class="mb-4">مرحله 3: تنظیمات اولیه و ایجاد مدیر سیستم</h5>
                    <?php if ($success && !isset($installation_complete)): ?>
                        <div class="alert alert-success mb-4"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="install.php?step=3">
                        <div class="mb-4">
                            <h6>اطلاعات شرکت اصلی:</h6>
                            <div class="mb-3">
                                <label for="company_name" class="form-label">نام شرکت</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6>اطلاعات مدیر سیستم:</h6>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">نام و نام خانوادگی</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="gender" class="form-label">جنسیت</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="male">مرد</option>
                                    <option value="female">زن</option>
                                    <option value="other">سایر</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">ایمیل</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mobile" class="form-label">موبایل</label>
                                <input type="text" class="form-control" id="mobile" name="mobile" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">نام کاربری</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">رمز عبور</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                <div class="form-text">رمز عبور باید حداقل 6 کاراکتر باشد.</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">تکمیل نصب</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>