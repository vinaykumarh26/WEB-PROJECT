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

// Get company info
$company = [];
$company_stmt = $conn->prepare("SELECT id FROM companies WHERE user_id = ?");
$company_stmt->bind_param("i", $user_id);
$company_stmt->execute();
$company_result = $company_stmt->get_result();
$company = $company_result->fetch_assoc();

// Get job details
$job = [];
$skills = [];
if ($company && $job_id) {
    $job_stmt = $conn->prepare("SELECT * FROM job_postings WHERE id = ? AND company_id = ?");
    $job_stmt->bind_param("ii", $job_id, $company['id']);
    $job_stmt->execute();
    $job_result = $job_stmt->get_result();
    $job = $job_result->fetch_assoc();
    
    // Get skills for this job
    $skills_stmt = $conn->prepare("SELECT skill FROM job_skills WHERE job_id = ?");
    $skills_stmt->bind_param("i", $job_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    while ($row = $skills_result->fetch_assoc()) {
        $skills[] = $row['skill'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_job'])) {
    $title = $conn->real_escape_string($_POST['title'] ?? '');
    $description = $conn->real_escape_string($_POST['description'] ?? '');
    $requirements = $conn->real_escape_string($_POST['requirements'] ?? '');
    $location = $conn->real_escape_string($_POST['location'] ?? '');
    $salary_range = $conn->real_escape_string($_POST['salary_range'] ?? '');
    $job_type = $conn->real_escape_string($_POST['job_type'] ?? 'Full-time');
    $expires_at = $conn->real_escape_string($_POST['expires_at'] ?? '');
    $min_aptitude_score = (int)($_POST['min_aptitude_score'] ?? 0);
    $new_skills = isset($_POST['skills']) ? explode(",", $conn->real_escape_string($_POST['skills'])) : [];

    // Update job posting
    $stmt = $conn->prepare("UPDATE job_postings SET 
        title = ?, description = ?, requirements = ?, location = ?, 
        salary_range = ?, job_type = ?, expires_at = ?, min_aptitude_score = ?
        WHERE id = ? AND company_id = ?");
    $stmt->bind_param("sssssssiii", $title, $description, $requirements, 
        $location, $salary_range, $job_type, $expires_at, $min_aptitude_score, 
        $job_id, $company['id']);
    $stmt->execute();
    
    // Update skills - first delete old ones
    $conn->query("DELETE FROM job_skills WHERE job_id = $job_id");
    
    // Add new skills
    if (!empty($new_skills)) {
        $skill_stmt = $conn->prepare("INSERT INTO job_skills (job_id, skill) VALUES (?, ?)");
        foreach ($new_skills as $skill) {
            $skill = trim($skill);
            if (!empty($skill)) {
                $skill_stmt->bind_param("is", $job_id, $skill);
                $skill_stmt->execute();
            }
        }
    }
    
    header("Location: company.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Posting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4>Edit Job Posting</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($job)): ?>
                            <div class="alert alert-danger">Job not found or you don't have permission to edit it.</div>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="update_job" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" class="form-control" name="title" 
                                           value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Job Description</label>
                                    <textarea class="form-control" name="description" rows="4" required><?php 
                                        echo htmlspecialchars($job['description']); 
                                    ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Requirements</label>
                                    <textarea class="form-control" name="requirements" rows="4" required><?php 
                                        echo htmlspecialchars($job['requirements']); 
                                    ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Location</label>
                                        <input type="text" class="form-control" name="location" 
                                               value="<?php echo htmlspecialchars($job['location']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Salary Range</label>
                                        <input type="text" class="form-control" name="salary_range" 
                                               value="<?php echo htmlspecialchars($job['salary_range']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Job Type</label>
                                        <select class="form-select" name="job_type" required>
                                            <option value="Full-time" <?php echo $job['job_type'] == 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                            <option value="Part-time" <?php echo $job['job_type'] == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                            <option value="Contract" <?php echo $job['job_type'] == 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                            <option value="Internship" <?php echo $job['job_type'] == 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" name="expires_at" 
                                               value="<?php echo htmlspecialchars($job['expires_at']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Required Skills (comma separated)</label>
                                    <input type="text" class="form-control" name="skills" 
                                           value="<?php echo htmlspecialchars(implode(', ', $skills)); ?>" 
                                           placeholder="e.g. PHP, JavaScript, Project Management">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Minimum Aptitude Score (0-100)</label>
                                    <input type="number" class="form-control" name="min_aptitude_score" 
                                           min="0" max="100" value="<?php echo htmlspecialchars($job['min_aptitude_score']); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Job</button>
                                <a href="company.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>