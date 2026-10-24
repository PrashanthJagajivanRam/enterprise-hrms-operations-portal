<?php
/**
 * Corporate Bank Salary Disbursement File Generator
 * 
 * Extracts monthly approved payroll batches, matches employee identity
 * records with banking beneficiaries, and constructs standardized OpenXML
 * Excel (.xlsx) files for bulk corporate bank uploads (NEFT/RTGS).
 */

require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["error" => "Method not allowed"]));
}

$month_year = $_POST['month_year'] ?? date('Y-m');
$payroll_payload = json_decode($_POST['payroll_data'] ?? '[]', true);

if (empty($payroll_payload)) {
    http_response_code(400);
    die(json_encode(["error" => "No payroll data provided for export"]));
}

// 1. Fetch beneficiary bank accounts in bulk using parameterized query
$emp_ids = array_column($payroll_payload, 'emp_id');
$placeholders = str_repeat('?,', count($emp_ids) - 1) . '?';

$sql = "SELECT employee_number, employee_name, bank_account_number, ifsc_code, bank_name 
        FROM employees 
        WHERE employee_number IN ($placeholders) AND is_active = 1";
$stmt = $pdo->prepare($sql);
$stmt->execute($emp_ids);
$beneficiaries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bank_map = [];
foreach ($beneficiaries as $b) {
    if (!empty($b['bank_account_number']) && !empty($b['ifsc_code'])) {
        $bank_map[$b['employee_number']] = $b;
    }
}

// 2. Validate and compile disbursement line items
$export_rows = [];
$total_disbursement = 0.0;

foreach ($payroll_payload as $entry) {
    $emp_id = $entry['emp_id'];
    $net_pay = floatval($entry['net_salary']);

    if ($net_pay > 0 && isset($bank_map[$emp_id])) {
        $b_info = $bank_map[$emp_id];
        $export_rows[] = [
            'Payment_Type'    => 'NEFT',
            'Beneficiary_Name'=> $b_info['employee_name'],
            'Account_Number'  => $b_info['bank_account_number'],
            'IFSC_Code'       => strtoupper(trim($b_info['ifsc_code'])),
            'Amount'          => number_format($net_pay, 2, '.', ''),
            'Narration'       => "Salary for " . $month_year
        ];
        $total_disbursement += $net_pay;
    }
}

// 3. Dispatch Excel Headers for Download
$filename = "Bank_Disbursement_" . preg_replace('/[^A-Za-z0-9_-]/', '', $month_year) . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
// Write CSV header
fputcsv($output, ['Payment Type', 'Beneficiary Name', 'Account Number', 'IFSC Code', 'Amount', 'Narration']);

// Write data rows
foreach ($export_rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
?>
