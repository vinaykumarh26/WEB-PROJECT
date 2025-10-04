<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if logged in as company
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$job_id = $_GET['id'] ?? 0;

if ($job_id) {
    // First get company ID
    $company_stmt = $conn->prepare("SELECT id FROM companies WHERE user_id = ?");
    $company_stmt->bind_param("i", $user_id);
    $company_stmt->execute();
    $company_result = $company_stmt->get_result();
    $company = $company_result->fetch_assoc();
    
    if ($company) {
        // Delete job skills first (due to foreign key constraint)
        $conn->query("DELETE FROM job_skills WHERE job_id = $job_id");
        
        // Then delete the job
        $stmt = $conn->prepare("DELETE FROM job_postings WHERE id = ? AND company_id = ?");
        $stmt->bind_param("ii", $job_id, $company['id']);
        $stmt->execute();
    }
}

header("Location: company.php");
exit();
?>