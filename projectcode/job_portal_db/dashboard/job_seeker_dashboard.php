<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

// Include database configuration
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in as job seeker
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Get job seeker profile
try {
    $profile_query = $conn->prepare("SELECT * FROM job_seeker_profiles WHERE user_id = ?");
    if (!$profile_query) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $profile_query->bind_param("i", $user_id);
    
    if (!$profile_query->execute()) {
        throw new Exception("Execute failed: " . $profile_query->error);
    }
    
    $profile_result = $profile_query->get_result();
    
    if ($profile_result->num_rows === 0) {
        header("Location: complete_profile.php");
        exit();
    }
    
    $profile = $profile_result->fetch_assoc();
    
} catch (Exception $e) {
    die("Error loading profile: " . $e->getMessage());
}

// Get job recommendations
try {
    $skills = !empty($profile['skills']) ? explode(", ", $profile['skills']) : [];
    $skills_condition = !empty($skills) ? 
        implode("', '", array_map(function($skill) use ($conn) {
            return $conn->real_escape_string($skill);
        }, $skills)) : '';
    
    $sql = "SELECT j.*";
    
    if (!empty($skills_condition)) {
        $sql .= ", (SELECT COUNT(*) FROM job_skills js WHERE js.job_id = j.id AND js.skill IN ('$skills_condition')) AS matched_skills";
    } else {
        $sql .= ", 0 AS matched_skills";
    }
    
    $sql .= " FROM jobs j WHERE j.min_aptitude_score <= ?";
    
    if (!empty($skills_condition)) {
        $sql .= " AND (SELECT COUNT(*) FROM job_skills js WHERE js.job_id = j.id AND js.skill IN ('$skills_condition')) >= 2";
    }
    
    $sql .= " ORDER BY matched_skills DESC, j.min_aptitude_score DESC LIMIT 10";
    
    $recommendations_query = $conn->prepare($sql);
    if (!$recommendations_query) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $recommendations_query->bind_param("i", $profile['aptitude_score']);
    
    if (!$recommendations_query->execute()) {
        throw new Exception("Execute failed: " . $recommendations_query->error);
    }
    
    $recommendations = $recommendations_query->get_result();
    
} catch (Exception $e) {
    $recommendations = false;
    $error_message = "We're currently unable to load job recommendations. Please try again later.";
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seeker Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Custom properties for a modern look */
:root {
    --primary-color: #4361ee;
    --secondary-color: #f7f9fc;
    --card-bg: #ffffff;
    --text-color: #333;
    --muted-color: #6c757d;
    --border-color: #e0e6ed;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05);
    --shadow-hover: 0 8px 20px rgba(0, 0, 0, 0.1);
}

body {
    font-family: 'Poppins', sans-serif;
    background-color: var(--secondary-color);
    color: var(--text-color);
    line-height: 1.6;
}

/* Card Improvements */
.profile-card, .recommendations-card, .job-card {
    border: none;
    border-radius: 12px;
    box-shadow: var(--shadow-light);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.job-card {
    height: 100%; /* Ensures all job cards in a row are the same height */
    margin-bottom: 20px;
}

.job-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.profile-header {
    background: linear-gradient(45deg, #4361ee, #4895ef);
    color: white;
    padding: 2.5rem;
    border-radius: 12px 12px 0 0;
    text-align: center;
}

.card-body {
    padding: 2rem;
}

/* Typography and Layout */
.card-title {
    font-weight: 700;
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.card-subtitle {
    font-weight: 500;
    font-size: 1rem;
}

.card-text i {
    margin-right: 8px;
    color: var(--primary-color);
}

h5.mt-4 {
    font-weight: 600;
    font-size: 1.25rem;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 0.5rem;
    margin-top: 2rem !important;
}

/* Badges & Skills */
.badge {
    font-weight: 500;
    padding: 0.5em 1em;
    border-radius: 50px;
    font-size: 0.8rem;
}

.skill-badge {
    margin-right: 5px;
    margin-bottom: 5px;
}

.bg-primary {
    background-color: var(--primary-color) !important;
}

.bg-success {
    background-color: var(--success-color) !important;
}

.bg-warning {
    background-color: var(--warning-color) !important;
}

.job-card .match-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background-color: var(--success-color) !important;
    color: white !important;
}

/* Progress Bar */
.progress {
    height: 1.5rem;
    border-radius: 10px;
    background-color: var(--border-color);
}

.progress-bar {
    border-radius: 10px;
    transition: width 1s ease-in-out;
}

/* Buttons */
.btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 0.6rem 1.2rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.btn-primary:hover, .btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-4">
                <div class="card profile-card">
                    <div class="profile-header">
                        <h4>Your Profile</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($_SESSION['user']['name']); ?></h5>
                        <p class="card-text">
                            <strong>College:</strong> <?php echo htmlspecialchars($profile['college_name']); ?><br>
                            <strong>Degree:</strong> <?php echo htmlspecialchars($profile['degree']); ?><br>
                            <strong>Graduation Year:</strong> <?php echo htmlspecialchars($profile['graduation_year']); ?><br>
                            <strong>Location:</strong> <?php echo htmlspecialchars($profile['location']); ?><br>
                            <strong>Phone:</strong> <?php echo htmlspecialchars($profile['phone']); ?>
                        </p>
                        
                        <h5 class="mt-4">Your Skills</h5>
                        <div class="d-flex flex-wrap">
                            <?php foreach(explode(", ", $profile['skills']) as $skill): ?>
                                <span class="badge bg-primary skill-badge"><?php echo htmlspecialchars($skill); ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <h5 class="mt-4">Aptitude Score</h5>
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped bg-success" 
                                 role="progressbar" 
                                 style="width: <?php echo $profile['aptitude_score']; ?>%" 
                                 aria-valuenow="<?php echo $profile['aptitude_score']; ?>" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                                <?php echo $profile['aptitude_score']; ?>%
                            </div>
                        </div>
                        
                        <h5 class="mt-4">Resume</h5>
                        <?php if (!empty($profile['resume_path'])): ?>
                            <a href="<?php echo htmlspecialchars($profile['resume_path']); ?>" 
                               class="btn btn-outline-primary resume-link" 
                               target="_blank">
                               View Resume
                            </a>
                        <?php else: ?>
                            <p class="text-muted">No resume uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card recommendations-card">
                    <div class="profile-header">
                        <h4>Job Recommendations</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php elseif ($recommendations && $recommendations->num_rows > 0): ?>
                            <div class="row">
                                <?php while($job = $recommendations->fetch_assoc()): ?>
                                    <div class="col-md-6">
                                        <div class="card job-card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($job['title']); ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                                                <p class="card-text">
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?><br>
                                                    <strong>Salary:</strong> ₹<?php echo number_format($job['salary']); ?>/month<br>
                                                    <strong>Minimum Score:</strong> <?php echo htmlspecialchars($job['min_aptitude_score']); ?>%
                                                </p>
                                                <?php 
                                                $job_skills = $conn->query("SELECT skill FROM job_skills WHERE job_id = {$job['id']}");
                                                if ($job_skills && $job_skills->num_rows > 0): ?>
                                                    <div class="d-flex flex-wrap mb-2">
                                                        <?php while($skill = $job_skills->fetch_assoc()): ?>
                                                            <span class="badge bg-secondary skill-badge"><?php echo htmlspecialchars($skill['skill']); ?></span>
                                                        <?php endwhile; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No job recommendations found based on your profile. Try adding more skills or check back later.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
        document.addEventListener('DOMContentLoaded', function() {

    // Animate the aptitude score progress bar
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        const score = progressBar.getAttribute('aria-valuenow');
        progressBar.style.width = score + '%';
    }

    // Add a fade-in effect to job cards as they scroll into view
    const jobCards = document.querySelectorAll('.job-card');
    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 }); // Trigger when 30% of the element is visible

    jobCards.forEach(card => {
        card.classList.add('animate-fade-in');
        observer.observe(card);
    });

    // CSS for the fade-in animation
    const style = document.createElement('style');
    style.innerHTML = `
        .animate-fade-in {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .is-visible {
            opacity: 1;
            transform: translateY(0);
        }
    `;
    document.head.appendChild(style);
});
    </script>
</body>
</html>