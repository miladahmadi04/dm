<?php
require_once 'auth.php';

// اگر کاربر لاگین نباشد، به صفحه ورود هدایت می‌شود
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$currentUser = getCurrentUser();
$unreadMessages = getUnreadMessagesCount();

// دریافت منوهای قابل دسترس برای کاربر
$menuItems = [];
$roleId = $_SESSION['role_id'];
$stmt = $conn->prepare("SELECT mi.* FROM menu_items mi 
                       JOIN role_menu_items rmi ON mi.id = rmi.menu_item_id 
                       WHERE rmi.role_id = ? AND mi.parent_id IS NULL 
                       ORDER BY mi.sort_order");
$stmt->bind_param("i", $roleId);
$stmt->execute();
$menuItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// دریافت زیرمنوهای هر منو
foreach ($menuItems as &$item) {
    $parentId = $item['id'];
    $stmt = $conn->prepare("SELECT mi.* FROM menu_items mi 
                           JOIN role_menu_items rmi ON mi.id = rmi.menu_item_id 
                           WHERE rmi.role_id = ? AND mi.parent_id = ? 
                           ORDER BY mi.sort_order");
    $stmt->bind_param("ii", $roleId, $parentId);
    $stmt->execute();
    $item['submenu'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سیستم مدیریت</title>
    <link href="fontstyle.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar-menu {
            padding: 0;
        }
        .sidebar-menu li {
            list-style: none;
        }
        .sidebar-menu a {
            display: block;
            padding: 10px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border-left-color: #007bff;
        }
        .sidebar-menu .submenu {
            padding-left: 20px;
            display: none;
        }
        .sidebar-menu .has-submenu.open .submenu {
            display: block;
        }
        .sidebar-header {
            padding: 15px;
            color: white;
            background-color: #2c3136;
        }
        .content-wrapper {
            min-height: 100vh;
            background-color: #f4f6f9;
        }
        .navbar-profile-image {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
        }
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.6rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-header d-flex align-items-center">
                    <img src="logo.png" alt="Logo" height="40" class="me-2">
                    <h5 class="mb-0">سیستم مدیریت</h5>
                </div>
                <ul class="sidebar-menu mt-3">
                    <?php foreach ($menuItems as $item): ?>
                        <li class="<?php echo !empty($item['submenu']) ? 'has-submenu' : ''; ?>">
                            <a href="<?php echo $item['link']; ?>" class="d-flex align-items-center">
                                <i class="<?php echo $item['icon']; ?> me-2"></i>
                                <span><?php echo htmlspecialchars($item['title']); ?></span>
                                <?php if (!empty($item['submenu'])): ?>
                                    <i class="bi bi-chevron-down ms-auto"></i>
                                <?php endif; ?>
                            </a>
                            <?php if (!empty($item['submenu'])): ?>
                                <ul class="submenu">
                                    <?php foreach ($item['submenu'] as $submenu): ?>
                                        <li>
                                            <a href="<?php echo $submenu['link']; ?>">
                                                <i class="<?php echo $submenu['icon']; ?> me-2"></i>
                                                <?php echo htmlspecialchars($submenu['title']); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
                <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom py-3 mb-4">
                    <div class="container-fluid">
                        <button class="btn btn-light d-md-none me-2" type="button" data-bs-toggle="collapse" data-bs-target=".sidebar">
                            <i class="bi bi-list"></i>
                        </button>
                        
                        <span class="navbar-text">
                            <?php echo date('Y/m/d'); ?>
                        </span>
                        
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item position-relative me-3">
                                <a class="nav-link" href="messages.php">
                                    <i class="bi bi-envelope fs-5"></i>
                                    <?php if ($unreadMessages > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                            <?php echo $unreadMessages; ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <span class="me-2"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                                    <img src="https://via.placeholder.com/30" alt="Profile" class="navbar-profile-image">
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>پروفایل</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>خروج</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>

                <!-- Content here -->
                <div class="container-fluid">