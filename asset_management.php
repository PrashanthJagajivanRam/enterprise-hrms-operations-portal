<?php
/**
 * IT Asset & Hardware Lifecycle Management Module
 * 
 * Manages employee IT hardware inventory, serial tracking, equipment
 * allocation, duplicate assignment prevention, and custody audits.
 * 
 * Features:
 * - Atomic database transactions via PDO
 * - Duplicate asset assignment validation across active personnel
 * - Custody sign-off status tracking
 * - Prepared statements to eliminate SQL injection risks
 */

require_once 'config/database.php';
require_once 'includes/session.php';

// Ensure user is authenticated and authorized
requireLogin();

$employee_number = isset($_GET['employee_number']) ? trim($_GET['employee_number']) : '';
$error = '';
$success = '';

// Handle Asset Allocation & Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assets'])) {
    try {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $emp_number     = trim($_POST['employee_number']);
        $computer_asset = trim($_POST['computer_asset'] ?? '');
        $laptop_asset   = trim($_POST['laptop_asset'] ?? '');
        $mobile_asset   = trim($_POST['mobile_asset'] ?? '');
        $access_card    = trim($_POST['access_card'] ?? '');
        $custody_signed = $_POST['custody_signed'] ?? 'No';

        // 1. Begin atomic transaction
        $pdo->beginTransaction();

        // 2. Verify target employee existence
        $emp_stmt = $pdo->prepare("SELECT employee_name, designation, department FROM employees WHERE employee_number = ? LIMIT 1");
        $emp_stmt->execute([$emp_number]);
        $employee = $emp_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {
            throw new Exception("Target employee record not found.");
        }

        // 3. Fetch existing allocated assets
        $curr_stmt = $pdo->prepare("SELECT computer_asset, laptop_asset, mobile_asset, access_card FROM employees WHERE employee_number = ?");
        $curr_stmt->execute([$emp_number]);
        $current_assets = $curr_stmt->fetch(PDO::FETCH_ASSOC);

        // 4. Duplicate Asset Prevention (Validate uniqueness across active employees)
        if (!empty($laptop_asset) && $laptop_asset !== $current_assets['laptop_asset']) {
            $dup_stmt = $pdo->prepare("SELECT employee_name FROM employees WHERE laptop_asset = ? AND employee_number != ? AND employment_status = 'Working'");
            $dup_stmt->execute([$laptop_asset, $emp_number]);
            if ($duplicate = $dup_stmt->fetch(PDO::FETCH_ASSOC)) {
                throw new Exception("Asset conflict: Laptop [{$laptop_asset}] is currently assigned to {$duplicate['employee_name']}.");
            }
        }

        // 5. Update asset assignments
        $update_stmt = $pdo->prepare("
            UPDATE employees 
            SET computer_asset = ?, 
                laptop_asset   = ?, 
                mobile_asset   = ?, 
                access_card    = ?, 
                custody_signed = ?, 
                asset_updated_at = NOW() 
            WHERE employee_number = ?
        ");
        $update_stmt->execute([
            $computer_asset,
            $laptop_asset,
            $mobile_asset,
            $access_card,
            $custody_signed,
            $emp_number
        ]);

        // 6. Record audit trail in asset history
        $log_stmt = $pdo->prepare("
            INSERT INTO asset_history (employee_number, laptop_asset, computer_asset, action_type, logged_by, logged_at) 
            VALUES (?, ?, ?, 'ALLOCATION_UPDATE', ?, NOW())
        ");
        $log_stmt->execute([
            $emp_number,
            $laptop_asset,
            $computer_asset,
            $_SESSION['user_id'] ?? 'SYSTEM'
        ]);

        $pdo->commit();
        $success = "Asset allocation updated and audited successfully.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>
