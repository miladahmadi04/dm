<?php
require_once 'functions.php';
require_once('tcpdf/tcpdf.php');

class MYPDF extends TCPDF {
    protected $last_page_flag = false;

    public function Close() {
        $this->last_page_flag = true;
        parent::Close();
    }
    
    public function Header() {
        if ($this->last_page_flag) return;
        
        // Logo & Title
        $this->SetFont('dejavusans', 'B', 18);
        $this->SetTextColor(51, 51, 51);
        $this->Cell(0, 15, 'گزارش عملکرد اینستاگرام', 0, false, 'C');
        $this->Ln(20);
    }

    public function Footer() {
        if ($this->last_page_flag) return;
        
        $this->SetY(-15);
        $this->SetFont('dejavusans', '', 8);
        $this->Cell(0, 10, 'صفحه ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C');
    }

    public function ColoredTable($header, $data, $withColors = true) {
        if ($withColors) {
            $this->SetFillColor(52, 144, 220);
            $this->SetTextColor(255);
        }
        $this->SetDrawColor(52, 144, 220);
        $this->SetLineWidth(0.3);
        $this->SetFont('dejavusans', 'B', 11);

        // Table Header
        $w = array(30, 30, 30, 30, 35, 35);
        for($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', $withColors);
        }
        $this->Ln();

        // Data
        if ($withColors) {
            $this->SetFillColor(248, 249, 250);
            $this->SetTextColor(51, 51, 51);
        }
        $this->SetFont('dejavusans', '', 10);
        
        $fill = false;
        foreach($data as $row) {
            foreach($row as $i => $col) {
                $align = is_numeric(str_replace(',', '', $col)) ? 'C' : 'C';
                $this->Cell($w[$i], 6, $col, 1, 0, $align, $fill && $withColors);
            }
            $this->Ln();
            $fill = !$fill;
        }
    }

    public function ComparisonTable($title, $actual, $expected, $percent, $score) {
        // Title
        $this->SetFont('dejavusans', 'B', 12);
        $this->SetTextColor(52, 144, 220);
        $this->Cell(0, 10, $title, 0, 1, 'R');
        
        // Header
        $headers = array('شاخص', 'مقدار واقعی', 'مقدار مورد انتظار', 'درصد تحقق', 'نمره');
        $data = array(array(
            $title,
            formatNumber($actual),
            formatNumber($expected),
            number_format($percent, 1) . '%',
            number_format($score, 1) . ' از 7'
        ));
        
        $this->ColoredTable($headers, $data);
        $this->Ln(5);
    }
}

// Get parameters
$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'reports';

// Get page details
$stmt = $conn->prepare("SELECT * FROM instagram_pages WHERE id = ?");
$stmt->bind_param("i", $page_id);
$stmt->execute();
$page = $stmt->get_result()->fetch_assoc();

if (!$page) {
    die('پیج مورد نظر یافت نشد.');
}

// Create PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Digital Marketing System');
$pdf->SetAuthor('System');
$pdf->SetTitle($page['company_name'] . ' - گزارش اینستاگرام');

// Set margins
$pdf->SetMargins(15, 27, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 25);

// First Page - Basic Info
$pdf->AddPage();

// Page Info
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->SetTextColor(52, 144, 220);
$pdf->Cell(0, 10, 'اطلاعات پیج', 0, 1, 'R');
$pdf->Line($pdf->GetX(), $pdf->GetY()-2, $pdf->GetX()+180, $pdf->GetY()-2);
$pdf->Ln(5);

$pdf->SetTextColor(51, 51, 51);
$pdf->SetFont('dejavusans', '', 12);

// Info Table
$info_headers = array('عنوان', 'مقدار');
$info_data = array(
    array('نام شرکت', $page['company_name']),
    array('آدرس اینستاگرام', $page['instagram_url']),
    array('حوزه فعالیت', $page['activity_field']),
    array('تاریخ شروع', $page['start_date'])
);
$pdf->ColoredTable($info_headers, $info_data, false);
$pdf->Ln(10);

// KPI Section
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->SetTextColor(52, 144, 220);
$pdf->Cell(0, 10, 'اهداف تعیین شده (KPI)', 0, 1, 'R');
$pdf->Line($pdf->GetX(), $pdf->GetY()-2, $pdf->GetX()+180, $pdf->GetY()-2);
$pdf->Ln(5);

$kpi_headers = array('شاخص', 'مقدار هدف');
$kpi_data = array(
    array('رشد ماهانه فالوور', $page['follower_growth_kpi'] . '%'),
    array('رشد ماهانه تعامل', $page['engagement_growth_kpi'] . '%'),
    array('رشد ماهانه بازدید', $page['view_growth_kpi'] . '%'),
    array('نسبت لید به فالوور', $page['lead_follower_ratio_kpi'] . '%'),
    array('نسبت مشتری به لید', $page['customer_lead_ratio_kpi'] . '%')
);
$pdf->ColoredTable($kpi_headers, $kpi_data, false);

// Reports Section
if ($type === 'reports') {
    $stmt = $conn->prepare("SELECT * FROM monthly_reports WHERE page_id = ? ORDER BY report_date ASC");
    $stmt->bind_param("i", $page_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (!empty($reports)) {
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->SetTextColor(52, 144, 220);
        $pdf->Cell(0, 10, 'گزارش‌های ماهانه', 0, 1, 'R');
        $pdf->Line($pdf->GetX(), $pdf->GetY()-2, $pdf->GetX()+180, $pdf->GetY()-2);
        $pdf->Ln(5);

        // Reports Table
        $report_headers = array('تاریخ', 'فالوور', 'تعامل', 'بازدید', 'لید', 'مشتری');
        $report_data = array();
        foreach($reports as $report) {
            $report_data[] = array(
                $report['report_date'],
                formatNumber($report['followers_count']),
                formatNumber($report['engagement_count']),
                formatNumber($report['views_count']),
                formatNumber($report['leads_count']),
                formatNumber($report['customers_count'])
            );
        }
        $pdf->ColoredTable($report_headers, $report_data);

        // Comparison Tables
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', 'B', 14);
        $pdf->SetTextColor(52, 144, 220);
        $pdf->Cell(0, 10, 'تحلیل عملکرد', 0, 1, 'R');
        $pdf->Line($pdf->GetX(), $pdf->GetY()-2, $pdf->GetX()+180, $pdf->GetY()-2);
        $pdf->Ln(5);

        $latest = end($reports);
        $monthCount = count($reports);
        $expected = calculateExpectedValues($page_id, $monthCount);

        // Followers Comparison
        $follower_percent = ($latest['followers_count'] / $expected['followers']) * 100;
        $follower_score = min(7, ($follower_percent / 100) * 7);
        $pdf->ComparisonTable('فالوورها', $latest['followers_count'], $expected['followers'], $follower_percent, $follower_score);

        // Engagement Comparison
        $engagement_percent = ($latest['engagement_count'] / $expected['engagement']) * 100;
        $engagement_score = min(7, ($engagement_percent / 100) * 7);
        $pdf->ComparisonTable('تعامل', $latest['engagement_count'], $expected['engagement'], $engagement_percent, $engagement_score);

        // Views Comparison
        $views_percent = ($latest['views_count'] / $expected['views']) * 100;
        $views_score = min(7, ($views_percent / 100) * 7);
        $pdf->ComparisonTable('بازدید', $latest['views_count'], $expected['views'], $views_percent, $views_score);

        // Leads Comparison
        $leads_percent = ($latest['leads_count'] / $expected['leads']) * 100;
        $leads_score = min(7, ($leads_percent / 100) * 7);
        $pdf->ComparisonTable('لید', $latest['leads_count'], $expected['leads'], $leads_percent, $leads_score);

        // Customers Comparison
        $customers_percent = ($latest['customers_count'] / $expected['customers']) * 100;
        $customers_score = min(7, ($customers_percent / 100) * 7);
        $pdf->ComparisonTable('مشتری', $latest['customers_count'], $expected['customers'], $customers_percent, $customers_score);

        // Final Score
        $final_score = ($follower_score + $engagement_score + $views_score + $leads_score + $customers_score) / 5;
        $pdf->Ln(10);
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, 'نمره کل عملکرد: ' . number_format($final_score, 1) . ' از 7', 0, 1, 'C');
    }
}

// Output PDF
$pdf->Output($page['company_name'] . '-report.pdf', 'I');
?>