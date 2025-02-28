<?php
require_once 'config.php';

// تابع بررسی دسترسی مدیر شرکت
function isCompanyAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $conn;
    $roleId = $_SESSION['role_id'];
    
    $stmt = $conn->prepare("SELECT role_name FROM roles WHERE id = ?");
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $role = $stmt->get_result()->fetch_assoc();
    
    return $role && $role['role_name'] === 'مدیر شرکت';
}

// تابع دریافت نام شبکه اجتماعی
function getSocialNetworkName($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT name FROM social_networks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['name'] : 'نامشخص';
}

// تابع دریافت نام شرکت
function getCompanyName($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT company_name FROM companies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result ? $result['company_name'] : 'نامشخص';
}

// تابع دریافت اطلاعات کاربر
function getUserInfo($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT p.*, c.company_name, r.role_name 
                           FROM personnel p 
                           JOIN companies c ON p.company_id = c.id 
                           JOIN roles r ON p.role_id = r.id 
                           WHERE p.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// تابع دریافت پیام‌های جدید
function getNewMessages() {
    if (!isLoggedIn()) {
        return [];
    }
    
    global $conn;
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT m.*, p.full_name as sender_name 
                           FROM messages m 
                           JOIN message_recipients mr ON m.id = mr.message_id 
                           JOIN personnel p ON m.sender_id = p.id 
                           WHERE mr.recipient_id = ? AND mr.is_read = 0 
                           ORDER BY m.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function calculateAveragePerformance($pageId, $filters = null) {
    global $conn;
    
    if ($filters === null) {
        $filters = [
            'followers' => true,
            'engagement' => true,
            'views' => true,
            'leads' => true,
            'customers' => true
        ];
    }
    
    // دریافت تمام گزارش‌ها
    $sql = "SELECT * FROM monthly_reports WHERE page_id = ? ORDER BY report_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (empty($reports)) return null;

    $totalScores = [
        'followers' => 0,
        'engagement' => 0,
        'views' => 0,
        'leads' => 0,
        'customers' => 0,
        'overall' => 0
    ];
    
    // محاسبه نمره برای هر گزارش و جمع کردن آنها
    foreach ($reports as $report) {
        $score = calculateReportScore($report['id'], $filters);
        if ($score) {
            foreach ($totalScores as $key => $value) {
                $totalScores[$key] += $score['scores'][$key];
            }
        }
    }
    
    // محاسبه میانگین
    $reportCount = count($reports);
    foreach ($totalScores as $key => $value) {
        $totalScores[$key] = $value / $reportCount;
    }
    
    return $totalScores;
}

function getMetricLabel($metric) {
    $labels = [
        'followers' => 'فالوور',
        'engagement' => 'تعامل',
        'views' => 'بازدید',
        'leads' => 'لید',
        'customers' => 'مشتری'
    ];
    return $labels[$metric] ?? $metric;
}

function getScoreColor($score) {
    if ($score >= 6) return 'success';
    if ($score >= 4) return 'warning';
    return 'danger';
}

function displayAchievementBadge($actual, $expected) {
    if ($expected <= 0) return;
    
    $percentage = ($actual / $expected) * 100;
    $color = $percentage >= 100 ? 'success' : ($percentage >= 70 ? 'warning' : 'danger');
    
    echo '<span class="badge bg-' . $color . '">' . number_format($percentage, 1) . '%</span>';
}

// Template Functions
function getTemplates() {
    global $conn;
    $sql = "SELECT * FROM kpi_templates ORDER BY name ASC";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTemplate($id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM kpi_templates WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function deleteTemplate($id) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM kpi_templates WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Performance Calculation Functions
function calculateMetricScore($actual, $expected) {
    if ($expected <= 0) return 0;
    $achievement = ($actual / $expected) * 100;
    return min(7, ($achievement / 100) * 7);
}

function calculateExpectedValues($pageId, $monthNumber) {
    global $conn;
    
    $sql = "SELECT * FROM instagram_pages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $page = $stmt->get_result()->fetch_assoc();
    
    if (!$page) return null;

    $expectedFollowers = $page['initial_followers'] * pow(1 + ($page['follower_growth_kpi'] / 100), $monthNumber);
    $expectedEngagement = $page['initial_engagement'] * pow(1 + ($page['engagement_growth_kpi'] / 100), $monthNumber);
    $expectedViews = $page['initial_views'] * pow(1 + ($page['view_growth_kpi'] / 100), $monthNumber);
    $expectedLeads = $expectedFollowers * ($page['lead_follower_ratio_kpi'] / 100);
    $expectedCustomers = $expectedLeads * ($page['customer_lead_ratio_kpi'] / 100);

    return [
        'followers' => round($expectedFollowers),
        'engagement' => round($expectedEngagement),
        'views' => round($expectedViews),
        'leads' => round($expectedLeads),
        'customers' => round($expectedCustomers)
    ];
}

function calculateReportScore($reportId, $filters = null) {
    global $conn;
    
    if ($filters === null) {
        $filters = [
            'followers' => true,
            'engagement' => true,
            'views' => true,
            'leads' => true,
            'customers' => true
        ];
    }
    
    // Get report and page data
    $sql = "SELECT r.*, p.* FROM monthly_reports r 
            JOIN instagram_pages p ON r.page_id = p.id 
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    
    if (!$data) return null;

    // Calculate months difference
    $startDate = new DateTime($data['start_date']);
    $reportDate = new DateTime($data['report_date']);
    $monthsDiff = $startDate->diff($reportDate)->m + ($startDate->diff($reportDate)->y * 12);

    // Calculate expected values
    $expected = calculateExpectedValues($data['page_id'], $monthsDiff);
    
    // Actual values
    $actual = [
        'followers' => $data['followers_count'],
        'engagement' => $data['engagement_count'],
        'views' => $data['views_count'],
        'leads' => $data['leads_count'],
        'customers' => $data['customers_count']
    ];

    // Calculate scores
    $scores = [
        'followers' => $filters['followers'] ? calculateMetricScore($actual['followers'], $expected['followers']) : 0,
        'engagement' => $filters['engagement'] ? calculateMetricScore($actual['engagement'], $expected['engagement']) : 0,
        'views' => $filters['views'] ? calculateMetricScore($actual['views'], $expected['views']) : 0,
        'leads' => $filters['leads'] ? calculateMetricScore($actual['leads'], $expected['leads']) : 0,
        'customers' => $filters['customers'] ? calculateMetricScore($actual['customers'], $expected['customers']) : 0
    ];

    // Calculate overall score
    $activeFilters = 0;
    $totalScore = 0;

    foreach ($filters as $metric => $active) {
        if ($active && isset($scores[$metric])) {
            $totalScore += $scores[$metric];
            $activeFilters++;
        }
    }

    $scores['overall'] = $activeFilters > 0 ? $totalScore / $activeFilters : 0;

    return [
        'scores' => $scores,
        'expected' => $expected,
        'actual' => $actual
    ];
}

function calculatePerformanceScore($pageId, $filters = null) {
    global $conn;
    
    if ($filters === null) {
        $filters = [
            'followers' => true,
            'engagement' => true,
            'views' => true,
            'leads' => true,
            'customers' => true
        ];
    }
    
    // Get page KPIs
    $sql = "SELECT * FROM instagram_pages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $page = $stmt->get_result()->fetch_assoc();
    
    if (!$page) return null;
    
    // Get latest monthly reports
    $sql = "SELECT * FROM monthly_reports WHERE page_id = ? ORDER BY report_date DESC LIMIT 12";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (count($reports) < 2) return null;
    
    $latest = reset($reports); // Get the latest report
    
    $actual = [
        'followers' => $latest['followers_count'],
        'engagement' => $latest['engagement_count'],
        'views' => $latest['views_count'],
        'leads' => $latest['leads_count'],
        'customers' => $latest['customers_count']
    ];
    
    $expected = calculateExpectedValues($pageId, count($reports));
    
    $scores = [
        'followers' => $filters['followers'] ? calculateMetricScore($actual['followers'], $expected['followers']) : 0,
        'engagement' => $filters['engagement'] ? calculateMetricScore($actual['engagement'], $expected['engagement']) : 0,
        'views' => $filters['views'] ? calculateMetricScore($actual['views'], $expected['views']) : 0,
        'leads' => $filters['leads'] ? calculateMetricScore($actual['leads'], $expected['leads']) : 0,
        'customers' => $filters['customers'] ? calculateMetricScore($actual['customers'], $expected['customers']) : 0
    ];

    $activeFilters = 0;
    $totalScore = 0;

    foreach ($filters as $metric => $active) {
        if ($active && isset($scores[$metric])) {
            $totalScore += $scores[$metric];
            $activeFilters++;
        }
    }

    $scores['overall'] = $activeFilters > 0 ? $totalScore / $activeFilters : 0;
    
    return $scores;
}

function getPagePerformanceData($pageId, $filters = null) {
    global $conn;
    
    if ($filters === null) {
        $filters = [
            'followers' => true,
            'engagement' => true,
            'views' => true,
            'leads' => true,
            'customers' => true
        ];
    }
    
    $sql = "SELECT * FROM monthly_reports WHERE page_id = ? ORDER BY report_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $performanceData = [
        'dates' => [],
        'actual' => [
            'followers' => [],
            'engagement' => [],
            'views' => [],
            'leads' => [],
            'customers' => []
        ],
        'expected' => [
            'followers' => [],
            'engagement' => [],
            'views' => [],
            'leads' => [],
            'customers' => []
        ],
        'scores' => []
    ];

    foreach ($reports as $report) {
        $reportData = calculateReportScore($report['id'], $filters);
        $performanceData['dates'][] = $report['report_date'];
        
        foreach (['followers', 'engagement', 'views', 'leads', 'customers'] as $metric) {
            $performanceData['actual'][$metric][] = $reportData['actual'][$metric];
            $performanceData['expected'][$metric][] = $reportData['expected'][$metric];
        }
        
        $performanceData['scores'][] = $reportData['scores'];
    }

    return $performanceData;
}

function getReportsList($pageId) {
    global $conn;
    
    $sql = "SELECT * FROM monthly_reports WHERE page_id = ? ORDER BY report_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function formatNumber($number) {
    return number_format($number, 0, '.', ',');
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date, $format = 'Y/m/d') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

// تابع محاسبه ارزش KPI برای یک فیلد
function calculateKpiValue($pageId, $fieldId, $reportDate) {
    global $conn;
    
    // دریافت اطلاعات KPI این فیلد
    $stmt = $conn->prepare("SELECT * FROM page_kpis WHERE page_id = ? AND field_id = ?");
    $stmt->bind_param("ii", $pageId, $fieldId);
    $stmt->execute();
    $kpi = $stmt->get_result()->fetch_assoc();
    
    if (!$kpi) {
        return null;
    }
    
    // دریافت تاریخ شروع صفحه
    $stmt = $conn->prepare("SELECT start_date FROM social_pages WHERE id = ?");
    $stmt->bind_param("i", $pageId);
    $stmt->execute();
    $page = $stmt->get_result()->fetch_assoc();
    
    if (!$page) {
        return null;
    }
    
    // محاسبه تعداد روزهای گذشته از شروع
    $startDate = new DateTime($page['start_date']);
    $currentDate = new DateTime($reportDate);
    $daysPassed = $startDate->diff($currentDate)->days;
    
    if ($kpi['kpi_model_id'] == 1) {
        // مدل 1: رشد زمانی
        // محاسبه تعداد دوره‌های رشد
        $periods = floor($daysPassed / $kpi['growth_period_days']);
        
        // دریافت مقدار اولیه فیلد
        $stmt = $conn->prepare("SELECT field_value FROM social_page_fields 
                               WHERE page_id = ? AND field_id = ? 
                               ORDER BY created_at ASC LIMIT 1");
        $stmt->bind_param("ii", $pageId, $fieldId);
        $stmt->execute();
        $initialField = $stmt->get_result()->fetch_assoc();
        
        if (!$initialField) {
            return null;
        }
        
        $initialValue = (float)$initialField['field_value'];
        
        // اگر رشد درصدی است
        if ($kpi['growth_value'] > 0 && $kpi['growth_value'] < 100) {
            // فرمول: مقدار اولیه * (1 + نرخ رشد)^تعداد دوره
            return $initialValue * pow(1 + ($kpi['growth_value'] / 100), $periods);
        } else {
            // رشد عددی ثابت
            return $initialValue + ($kpi['growth_value'] * $periods);
        }
    } elseif ($kpi['kpi_model_id'] == 2) {
        // مدل 2: درصدی از فیلد دیگر
        if (!$kpi['related_field_id']) {
            return null;
        }
        
        // دریافت آخرین مقدار فیلد مرتبط
        $stmt = $conn->prepare("SELECT field_value FROM social_page_fields 
                               WHERE page_id = ? AND field_id = ? 
                               ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("ii", $pageId, $kpi['related_field_id']);
        $stmt->execute();
        $relatedField = $stmt->get_result()->fetch_assoc();
        
        if (!$relatedField) {
            return null;
        }
        
        $relatedValue = (float)$relatedField['field_value'];
        
        // فرمول: مقدار فیلد مرتبط * درصد تعیین شده
        return $relatedValue * ($kpi['percentage_value'] / 100);
    }
    
    return null;
}
?>