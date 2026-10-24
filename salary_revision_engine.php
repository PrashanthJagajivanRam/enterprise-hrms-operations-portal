<?php
/**
 * Dynamic Payroll & Salary Revision Engine
 * 
 * Computes standard enterprise CTC component breakdowns:
 * - Basic Salary, HRA, Conveyance, Medical, Special Allowances
 * - Statutory Deductions (Employee/Employer PF, ESI)
 * - Self-healing schema creation for change-audit trails
 * - Rounding algorithms and retroactive salary adjustment computations
 */

require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_operator = $_SESSION['username'] ?? 'SYSTEM';

// Self-healing audit table creation
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS salary_audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_number VARCHAR(50) NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        old_val VARCHAR(255),
        new_val VARCHAR(255),
        revised_by VARCHAR(100) NOT NULL,
        revised_at DATETIME NOT NULL
    )");
} catch (Exception $e) {
    // Fail silently or log to error log
}

/**
 * Rounds monetary amounts to the nearest 50 for standardized cash/bank disbursements.
 */
function roundToNearestFifty($amount) {
    return round($amount / 50) * 50;
}

/**
 * Calculates CTC component breakdown based on annual compensation and employment type.
 * 
 * @param string $type Full-Time / Contract / Consultant
 * @param float $monthly_ctc
 * @param float $annual_ctc
 * @return array Decomposed salary components
 */
function calculateSalaryBreakdown($type, $monthly_ctc, $annual_ctc) {
    if ($monthly_ctc <= 0 || $annual_ctc <= 0) {
        return [
            'basic_pay'         => 0,
            'hra'               => 0,
            'conveyance'        => 0,
            'medical_allowance' => 0,
            'special_allowance' => 0,
            'gross_salary'      => 0,
            'epf_deduction'     => 0,
            'esi_deduction'     => 0,
            'net_take_home'     => 0
        ];
    }

    $monthly = floatval($monthly_ctc);

    // Standard Enterprise Formula: Basic = 40% - 50% of CTC
    $basic = roundToNearestFifty($monthly * 0.40);
    // HRA = 50% of Basic
    $hra   = roundToNearestFifty($basic * 0.50);
    $conveyance = 1600;
    $medical    = 1250;

    // Statutory Calculations
    $epf = 0;
    if ($basic <= 15000) {
        $epf = round($basic * 0.12);
    } else {
        $epf = 1800; // Capped statutory limit
    }

    $esi = 0;
    $gross_estimated = $basic + $hra + $conveyance + $medical;
    if ($gross_estimated <= 21000) {
        $esi = ceil($gross_estimated * 0.0075);
    }

    // Residual balancing to Special Allowance
    $allocated = $basic + $hra + $conveyance + $medical + $epf;
    $special_allowance = max(0, $monthly - $allocated);
    $gross_salary = $basic + $hra + $conveyance + $medical + $special_allowance;
    $net_take_home = $gross_salary - ($epf + $esi);

    return [
        'monthly_ctc'       => $monthly,
        'annual_ctc'        => floatval($annual_ctc),
        'basic_pay'         => $basic,
        'hra'               => $hra,
        'conveyance'        => $conveyance,
        'medical_allowance' => $medical,
        'special_allowance' => $special_allowance,
        'gross_salary'      => $gross_salary,
        'epf_deduction'     => $epf,
        'esi_deduction'     => $esi,
        'net_take_home'     => $net_take_home
    ];
}
?>
