<?php
require_once 'auth.php';
require_once 'functions.php';

// دریافت شناسه شبکه اجتماعی
$networkId = filter_input(INPUT_GET, 'network_id', FILTER_VALIDATE_INT);

if (!$networkId) {
    echo '<div class="alert alert-danger">شناسه شبکه اجتماعی نامعتبر است.</div>';
    exit();
}

// دریافت فیلدهای شبکه اجتماعی
$fields = [];
$stmt = $conn->prepare("SELECT * FROM social_network_fields WHERE social_network_id = ? ORDER BY sort_order, id");
$stmt->bind_param("i", $networkId);
$stmt->execute();
$fields = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($fields)) {
    echo '<div class="alert alert-warning">هیچ فیلدی برای این شبکه اجتماعی تعریف نشده است.</div>';
    exit();
}

// ساخت فرم‌های پویا برای فیلدها
echo '<h5 class="mt-4 mb-3">اطلاعات تکمیلی</h5>';
echo '<div class="row">';

foreach ($fields as $field) {
    $fieldId = $field['id'];
    $fieldName = 'field_' . $fieldId;
    $fieldValue = $_POST[$fieldName] ?? '';
    $isRequired = $field['is_required'] ? 'required' : '';
    
    echo '<div class="col-md-6 mb-3">';
    echo '<label for="' . $fieldName . '" class="form-label">' . htmlspecialchars($field['field_label']) . ($isRequired ? ' <span class="text-danger">*</span>' : '') . '</label>';
    
    switch ($field['field_type']) {
        case 'text':
        case 'url':
            echo '<input type="' . $field['field_type'] . '" class="form-control" id="' . $fieldName . '" name="' . $fieldName . '" value="' . htmlspecialchars($fieldValue) . '" ' . $isRequired . '>';
            break;
            
        case 'number':
            echo '<input type="number" class="form-control" id="' . $fieldName . '" name="' . $fieldName . '" value="' . htmlspecialchars($fieldValue) . '" ' . $isRequired . '>';
            break;
            
        case 'date':
            echo '<input type="date" class="form-control" id="' . $fieldName . '" name="' . $fieldName . '" value="' . htmlspecialchars($fieldValue) . '" ' . $isRequired . '>';
            break;
    }
    
    if ($field['is_kpi']) {
        echo '<small class="form-text text-muted">این فیلد به عنوان شاخص KPI استفاده می‌شود</small>';
    }
    
    echo '</div>';
}

echo '</div>';
?>