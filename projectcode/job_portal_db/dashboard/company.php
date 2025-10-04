<?php
// Enable full error reporting at the very top
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Include database configuration
require_once __DIR__ . '/../includes/config.php';

// Debug: Check if session user exists
if (!isset($_SESSION['user'])) {
    die("Session user not found. Please login first.");
}

// Debug: Check user role
if ($_SESSION['user']['role'] !== 'company') {
    die("Access denied. Company role required.");
}

$user_id = $_SESSION['user']['id'];

// Debug: Verify database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Check which form was submitted
    error_log("Form submitted: " . print_r($_POST, true));
    
    if (isset($_POST['add_company'])) {
        try {
            $company_name = $conn->real_escape_string($_POST['company_name'] ?? '');
            $industry = $conn->real_escape_string($_POST['industry'] ?? '');
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $website = $conn->real_escape_string($_POST['website'] ?? '');

            // Check if company exists
            $check = $conn->prepare("SELECT id FROM companies WHERE user_id = ?");
            if (!$check) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $check->bind_param("i", $user_id);
            
            if (!$check->execute()) {
                throw new Exception("Execute failed: " . $check->error);
            }
            
            $check->store_result();
            
            if ($check->num_rows > 0) {
                // Update existing company
                $stmt = $conn->prepare("UPDATE companies SET 
                    company_name = ?, industry = ?, description = ?, website = ?
                    WHERE user_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("ssssi", $company_name, $industry, $description, $website, $user_id);
            } else {
                // Insert new company
                $stmt = $conn->prepare("INSERT INTO companies 
                    (user_id, company_name, industry, description, website) 
                    VALUES (?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("issss", $user_id, $company_name, $industry, $description, $website);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // Refresh company data
            header("Location: company.php");
            exit();
            
        } catch (Exception $e) {
            die("Error saving company: " . $e->getMessage());
        }
    }
    elseif (isset($_POST['add_job'])) {
        try {
            $company_id = (int)($_POST['company_id'] ?? 0);
            $title = $conn->real_escape_string($_POST['title'] ?? '');
            $description = $conn->real_escape_string($_POST['description'] ?? '');
            $requirements = $conn->real_escape_string($_POST['requirements'] ?? '');
            $location = $conn->real_escape_string($_POST['location'] ?? '');
            $salary_range = $conn->real_escape_string($_POST['salary_range'] ?? '');
            $job_type = $conn->real_escape_string($_POST['job_type'] ?? 'Full-time');
            $expires_at = $conn->real_escape_string($_POST['expires_at'] ?? '');
            $min_aptitude_score = (int)($_POST['min_aptitude_score'] ?? 0);
            $skills = isset($_POST['skills']) ? explode(",", $conn->real_escape_string($_POST['skills'])) : [];

            // Insert job posting
            $stmt = $conn->prepare("INSERT INTO job_postings 
                (company_id, title, description, requirements, location, 
                 salary_range, job_type, expires_at, min_aptitude_score) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssssssi", $company_id, $title, $description, $requirements, 
                $location, $salary_range, $job_type, $expires_at, $min_aptitude_score);
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $job_id = $stmt->insert_id;
            
            // Add skills
            if (!empty($skills)) {
                $skill_stmt = $conn->prepare("INSERT INTO job_skills (job_id, skill) VALUES (?, ?)");
                if (!$skill_stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                foreach ($skills as $skill) {
                    $skill = trim($skill);
                    if (!empty($skill)) {
                        $skill_stmt->bind_param("is", $job_id, $skill);
                        if (!$skill_stmt->execute()) {
                            throw new Exception("Skill insert failed: " . $skill_stmt->error);
                        }
                    }
                }
            }
            
            // Refresh page
            header("Location: company.php");
            exit();
            
        } catch (Exception $e) {
            die("Error posting job: " . $e->getMessage());
        }
    }
}

// Get company info
$company = [];
try {
    $company_stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
    if (!$company_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $company_stmt->bind_param("i", $user_id);
    
    if (!$company_stmt->execute()) {
        throw new Exception("Execute failed: " . $company_stmt->error);
    }
    
    $company_result = $company_stmt->get_result();
    if ($company_result->num_rows > 0) {
        $company = $company_result->fetch_assoc();
    }
} catch (Exception $e) {
    die("Error fetching company: " . $e->getMessage());
}

// Get job postings for this company
$jobs = [];
if (!empty($company)) {
    try {
        $jobs_stmt = $conn->prepare("SELECT * FROM job_postings WHERE company_id = ? ORDER BY posted_at DESC");
        if (!$jobs_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $jobs_stmt->bind_param("i", $company['id']);
        
        if (!$jobs_stmt->execute()) {
            throw new Exception("Execute failed: " . $jobs_stmt->error);
        }
        
        $jobs_result = $jobs_stmt->get_result();
        $jobs = $jobs_result->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        die("Error fetching jobs: " . $e->getMessage());
    }
}

// Debug: Check what data we have
error_log("Company data: " . print_r($company, true));
error_log("Jobs data: " . print_r($jobs, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /*
    Aesthetic and modern styles for the Company Dashboard.
*/

:root {
    --primary-color: #4361ee;
    --secondary-color: #b3b3b3;
    --background-color: #f0f2f5;
    --card-background: #ffffff;
    --border-color: #e0e0e0;
    --shadow-color: rgba(0, 0, 0, 0.08);
}

body {
    font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background-color: var(--background-color);
    line-height: 1.6;
}

/* Header Section */
.dashboard-header {
    background: var(--primary-color);
    color: #ffffff;
    padding: 3rem 0;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px var(--shadow-color);
}

.dashboard-header h1 {
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.dashboard-header p {
    font-size: 1.1rem;
    opacity: 0.9;
}

.dashboard-header .btn-light {
    color: var(--primary-color);
    font-weight: 600;
    transition: all 0.3s ease;
}

.dashboard-header .btn-light:hover {
    background-color: #f1f1f1;
    transform: translateY(-2px);
}

/* Main Content and Cards */
.card {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.card-header {
    background-color: var(--card-background);
    border-bottom: 1px solid var(--border-color);
    font-weight: 600;
    font-size: 1.25rem;
    padding: 1.5rem;
}

.card-body {
    padding: 2rem;
}

/* Navigation Pills */
.nav-pills .nav-link {
    color: #666;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.nav-pills .nav-link:hover {
    background-color: var(--border-color);
    color: #333;
}

.nav-pills .nav-link.active {
    background-color: var(--primary-color);
    color: #ffffff;
    font-weight: 600;
    box-shadow: 0 2px 10px rgba(67, 97, 238, 0.3);
}

/* Form Elements */
.form-control, .form-select {
    border-radius: 8px;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
}

.form-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    border-radius: 8px;
    transition: transform 0.2s ease;
}

.btn-primary:hover {
    background-color: #3855d4;
    border-color: #3855d4;
    transform: translateY(-2px);
}

.alert {
    border-radius: 8px;
}

/* Table Styles */
.table {
    --bs-table-bg: var(--card-background);
    border-collapse: separate;
    border-spacing: 0 0.8rem;
}

.table th, .table td {
    padding: 1rem;
    vertical-align: middle;
    border-top: none;
    background-color: #f9f9fb;
}

.table thead th {
    font-weight: 600;
    color: #555;
    border-bottom: 1px solid var(--border-color);
    background-color: #f0f2f5;
}

.table tbody tr {
    background-color: var(--card-background);
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
}

.table tbody tr:hover {
    background-color: #f5f5f7;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

/* Utility Badges */
.skill-badge {
    display: inline-block;
    padding: 0.4em 0.8em;
    font-size: 0.85em;
    font-weight: 600;
    color: #ffffff;
    background-color: var(--secondary-color);
    border-radius: 50px;
    margin-right: 0.5rem;
    margin-bottom: 0.5rem;
}

.btn-sm {
    border-radius: 6px;
    padding: 0.4rem 0.8rem;
}
    </style>
</head>
<body>
    <!-- Debug output (remove in production) -->
    <div style="background: #f8f9fa; padding: 10px; margin-bottom: 20px;">
        <h5>Debug Info:</h5>
        <p>User ID: <?php echo $user_id; ?></p>
        <p>Company ID: <?php echo $company['id'] ?? 'Not set'; ?></p>
    </div>

    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></h1>
                    <?php if (!empty($company)): ?>
                        <p class="mb-0"><?php echo htmlspecialchars($company['company_name']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../logout.php" class="btn btn-light">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-pills flex-column">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="pill" href="#company-info">Company Info</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#post-job">Post New Job</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="pill" href="#manage-jobs">Manage Jobs</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Company Info Tab -->
                    <div class="tab-pane fade show active" id="company-info">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Company Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="add_company" value="1">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Company Name</label>
                                        <input type="text" class="form-control" name="company_name" 
                                               value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Industry</label>
                                        <input type="text" class="form-control" name="industry" 
                                               value="<?php echo htmlspecialchars($company['industry'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="4" required><?php 
                                            echo htmlspecialchars($company['description'] ?? ''); 
                                        ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control" name="website" 
                                               value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>">
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Save Company Info</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Post Job Tab -->
                    <div class="tab-pane fade" id="post-job">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Post New Job</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($company)): ?>
                                    <div class="alert alert-warning">
                                        Please complete your company information first before posting jobs.
                                    </div>
                                <?php else: ?>
                                    <form method="post">
                                        <input type="hidden" name="add_job" value="1">
                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Job Title</label>
                                            <input type="text" class="form-control" name="title" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Job Description</label>
                                            <textarea class="form-control" name="description" rows="4" required></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Requirements</label>
                                            <textarea class="form-control" name="requirements" rows="4" required></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Location</label>
                                                <input type="text" class="form-control" name="location" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Salary Range</label>
                                                <input type="text" class="form-control" name="salary_range" required>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Job Type</label>
                                                <select class="form-select" name="job_type" required>
                                                    <option value="Full-time">Full-time</option>
                                                    <option value="Part-time">Part-time</option>
                                                    <option value="Contract">Contract</option>
                                                    <option value="Internship">Internship</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Expiry Date</label>
                                                <input type="date" class="form-control" name="expires_at" required>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Required Skills (comma separated)</label>
                                            <input type="text" class="form-control" name="skills" 
                                                   placeholder="e.g. PHP, JavaScript, Project Management">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Minimum Aptitude Score (0-100)</label>
                                            <input type="number" class="form-control" name="min_aptitude_score" 
                                                   min="0" max="100" value="0">
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Post Job</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Manage Jobs Tab -->
                    <div class="tab-pane fade" id="manage-jobs">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Your Job Postings</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($jobs)): ?>
                                    <div class="alert alert-info">
                                        You haven't posted any jobs yet.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Type</th>
                                                    <th>Location</th>
                                                    <th>Posted</th>
                                                    <th>Expires</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($jobs as $job): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($job['title']); ?></td>
                                                        <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                                        <td><?php echo htmlspecialchars($job['location']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($job['posted_at'])); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($job['expires_at'])); ?></td>
                                                        <td>
                                                            <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                            <a href="delete_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-danger" 
                                                               onclick="return confirm('Are you sure you want to delete this job?')">Delete</a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
        document.addEventListener('DOMContentLoaded', function() {
    // This script adds subtle animations and dynamic behavior.
    
    // Smooth scrolling for anchor links (if any)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Animate cards on load
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 * index + 300);
    });

    // Add confirmation to delete buttons
    document.querySelectorAll('.btn-danger').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // Dynamic Tab Hashing
    // This makes the active tab persistent on page reload and allows direct linking to tabs.
    const url = new URL(window.location.href);
    const tabFromUrl = url.hash;

    if (tabFromUrl) {
        const triggerEl = document.querySelector(`[data-bs-target="${tabFromUrl}"]`);
        if (triggerEl) {
            const tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }

    // Update URL hash when a tab is clicked
    document.querySelectorAll('.nav-pills .nav-link').forEach(tabLink => {
        tabLink.addEventListener('click', function() {
            const targetId = this.getAttribute('href');
            history.pushState(null, '', window.location.pathname + targetId);
        });
    });

    // Listen for back/forward browser button clicks to switch tabs
    window.addEventListener('popstate', function() {
        const currentHash = window.location.hash;
        if (currentHash) {
            const triggerEl = document.querySelector(`[data-bs-target="${currentHash}"]`);
            if (triggerEl) {
                const tab = new bootstrap.Tab(triggerEl);
                tab.show();
            }
        }
    });

    // Auto-focus on the first form field when a tab is shown
    document.querySelectorAll('.tab-pane').forEach(tabPane => {
        tabPane.addEventListener('shown.bs.tab', function (event) {
            const firstInput = this.querySelector('input, textarea, select');
            if (firstInput) {
                firstInput.focus();
            }
        });
    });
});
    </script>
</body>
</html>