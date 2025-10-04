<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/Applications/XAMPP/xamppfiles/logs/php_error.log');

session_start();
include "../includes/config.php";

// Check if user is logged in as job seeker
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'jobseeker') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Check if profile is already completed
$check_profile = $conn->prepare("SELECT * FROM job_seeker_profiles WHERE user_id = ?");
$check_profile->bind_param("i", $user_id);
$check_profile->execute();
$profile_result = $check_profile->get_result();

if ($profile_result->num_rows > 0) {
    header("Location: job_seeker_dashboard.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic Information
    $college_name = $conn->real_escape_string($_POST['college_name'] ?? '');
    $degree = $conn->real_escape_string($_POST['degree'] ?? '');
    $graduation_year = $conn->real_escape_string($_POST['graduation_year'] ?? '');
    $skills = isset($_POST['skills']) ? implode(", ", array_map(function($skill) use ($conn) {
        return $conn->real_escape_string(trim($skill));
    }, $_POST['skills'])) : '';
    $location = $conn->real_escape_string($_POST['location'] ?? '');
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $resume_link = $conn->real_escape_string($_POST['resume_link'] ?? '');
    
    // Aptitude Test Answers
    $aptitude_score = 0;
    $correct_answers = ['A', 'B', 'C', 'A', 'B'];
    for ($i = 1; $i <= 5; $i++) {
        if (isset($_POST['q'.$i])) {
            $aptitude_score += ($_POST['q'.$i] == $correct_answers[$i-1]) ? 20 : 0;
        }
    }
    
    // Validate Google Drive link
    if (empty($resume_link)) {
        $error = "Please provide your resume link";
    } elseif (!filter_var($resume_link, FILTER_VALIDATE_URL)) {
        $error = "Please provide a valid URL for your resume";
    } elseif (strpos($resume_link, 'drive.google.com') === false) {
        $error = "Please provide a Google Drive link for your resume";
    }
    
    if (!isset($error)) {
        // Save to database - using existing resume_path column to store the link
        $stmt = $conn->prepare("INSERT INTO job_seeker_profiles 
                              (user_id, college_name, degree, graduation_year, skills, location, phone, aptitude_score, resume_path)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississsis", $user_id, $college_name, $degree, $graduation_year, $skills, $location, $phone, $aptitude_score, $resume_link);
        
        if ($stmt->execute()) {
            header("Location: job_seeker_dashboard.php");
            exit();
        } else {
            $error = "Error saving profile: " . $conn->error;
            error_log("Database error: " . $conn->error);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .progress-bar {
            transition: width 0.5s ease;
        }
        .skill-tag {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .form-section {
            display: none;
        }
        .form-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .aptitude-question {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            color: #dc3545;
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
        }
        .card-header {
            background-color: #4361ee;
            color: white;
        }
        .btn-primary {
            background-color: #4361ee;
            border-color: #4361ee;
        }
        .resume-help {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h3 class="mb-0">Complete Your Profile</h3>
                        <div class="progress mt-3">
                            <div id="progressBar" class="progress-bar progress-bar-striped" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form id="profileForm" method="post" novalidate>
                            <!-- Section 1: Education -->
                            <div class="form-section active" id="section1">
                                <h4 class="mb-4 text-primary">Education Details</h4>
                                <div class="mb-3">
                                    <label for="college_name" class="form-label">College/University Name</label>
                                    <input type="text" class="form-control" id="college_name" name="college_name" required>
                                    <div class="invalid-feedback">Please provide your college/university name.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="degree" class="form-label">Degree</label>
                                    <select class="form-select" id="degree" name="degree" required>
                                        <option value="">Select Degree</option>
                                        <option value="B.Tech">B.Tech</option>
                                        <option value="B.E">B.E</option>
                                        <option value="B.Sc">B.Sc</option>
                                        <option value="B.Com">B.Com</option>
                                        <option value="B.A">B.A</option>
                                        <option value="M.Tech">M.Tech</option>
                                        <option value="M.Sc">M.Sc</option>
                                        <option value="MBA">MBA</option>
                                        <option value="PhD">PhD</option>
                                    </select>
                                    <div class="invalid-feedback">Please select your degree.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="graduation_year" class="form-label">Graduation Year</label>
                                    <select class="form-select" id="graduation_year" name="graduation_year" required>
                                        <option value="">Select Year</option>
                                        <?php 
                                        $current_year = date('Y');
                                        for ($year = $current_year; $year >= $current_year - 10; $year--) {
                                            echo "<option value='$year'>$year</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">Please select your graduation year.</div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <button type="button" class="btn btn-primary next-btn" data-next="section2">Next</button>
                                </div>
                            </div>
                            
                            <!-- Section 2: Skills & Basic Info -->
                            <div class="form-section" id="section2">
                                <h4 class="mb-4 text-primary">Your Skills & Information</h4>
                                <div class="mb-3">
                                    <label class="form-label">Add Your Skills (At least 3)</label>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" id="skillInput" placeholder="e.g. PHP, JavaScript">
                                        <button class="btn btn-outline-primary" type="button" id="addSkill">Add</button>
                                    </div>
                                    <div id="skillsContainer" class="d-flex flex-wrap mb-3 gap-2"></div>
                                    <input type="hidden" name="skills[]" id="skillsHidden">
                                    <div class="invalid-feedback" id="skillsError">Please add at least 3 skills.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="location" class="form-label">Preferred Job Location</label>
                                    <input type="text" class="form-control" id="location" name="location" required>
                                    <div class="invalid-feedback">Please provide your preferred job location.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required pattern="[0-9]{10,15}">
                                    <div class="invalid-feedback">Please provide a valid phone number (10-15 digits).</div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary prev-btn" data-prev="section1">Previous</button>
                                    <button type="button" class="btn btn-primary next-btn" data-next="section3">Next</button>
                                </div>
                            </div>
                            
                            <!-- Section 3: Aptitude Test -->
                            <div class="form-section" id="section3">
                                <h4 class="mb-4 text-primary">Aptitude Test</h4>
                                <p class="mb-4">Please answer these questions to help us understand your strengths</p>
                                
                                <div class="aptitude-question">
                                    <p class="fw-bold">1. If a train travels 300 km in 5 hours, what is its speed?</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="q1a" value="A" required>
                                        <label class="form-check-label" for="q1a">A) 60 km/h</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="q1b" value="B">
                                        <label class="form-check-label" for="q1b">B) 50 km/h</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q1" id="q1c" value="C">
                                        <label class="form-check-label" for="q1c">C) 70 km/h</label>
                                    </div>
                                    <div class="invalid-feedback">Please select an answer.</div>
                                </div>
                                
                                <div class="aptitude-question">
                                    <p class="fw-bold">2. Which number comes next in the sequence: 2, 4, 8, 16, ___?</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q2" id="q2a" value="A" required>
                                        <label class="form-check-label" for="q2a">A) 20</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q2" id="q2b" value="B">
                                        <label class="form-check-label" for="q2b">B) 32</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q2" id="q2c" value="C">
                                        <label class="form-check-label" for="q2c">C) 24</label>
                                    </div>
                                    <div class="invalid-feedback">Please select an answer.</div>
                                </div>
                                
                                <div class="aptitude-question">
                                    <p class="fw-bold">3. If all Bloops are Razzies and all Razzies are Lazzies, then all Bloops are definitely Lazzies?</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q3" id="q3a" value="A" required>
                                        <label class="form-check-label" for="q3a">A) False</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q3" id="q3b" value="B">
                                        <label class="form-check-label" for="q3b">B) True</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q3" id="q3c" value="C">
                                        <label class="form-check-label" for="q3c">C) Uncertain</label>
                                    </div>
                                    <div class="invalid-feedback">Please select an answer.</div>
                                </div>
                                
                                <div class="aptitude-question">
                                    <p class="fw-bold">4. Which word doesn't belong: Apple, Banana, Orange, Carrot?</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q4" id="q4a" value="A" required>
                                        <label class="form-check-label" for="q4a">A) Carrot</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q4" id="q4b" value="B">
                                        <label class="form-check-label" for="q4b">B) Banana</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q4" id="q4c" value="C">
                                        <label class="form-check-label" for="q4c">C) Orange</label>
                                    </div>
                                    <div class="invalid-feedback">Please select an answer.</div>
                                </div>
                                
                                <div class="aptitude-question">
                                    <p class="fw-bold">5. If today is Monday, what day will it be in 10 days?</p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q5" id="q5a" value="A" required>
                                        <label class="form-check-label" for="q5a">A) Thursday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q5" id="q5b" value="B">
                                        <label class="form-check-label" for="q5b">B) Thursday</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="q5" id="q5c" value="C">
                                        <label class="form-check-label" for="q5c">C) Friday</label>
                                    </div>
                                    <div class="invalid-feedback">Please select an answer.</div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary prev-btn" data-prev="section2">Previous</button>
                                    <button type="button" class="btn btn-primary next-btn" data-next="section4">Next</button>
                                </div>
                            </div>
                            
                            <!-- Section 4: Resume Link -->
                            <div class="form-section" id="section4">
                                <h4 class="mb-4 text-primary">Provide Your Resume Link</h4>
                                <div class="mb-3">
                                    <label for="resume_link" class="form-label">Google Drive Resume Link</label>
                                    <input type="url" class="form-control" id="resume_link" name="resume_link" 
                                           placeholder="https://drive.google.com/file/d/..." required>
                                    <div class="resume-help">
                                        <p>How to get your Google Drive link:</p>
                                        <ol>
                                            <li>Upload your resume to Google Drive</li>
                                            <li>Right-click the file and select "Share"</li>
                                            <li>Change sharing settings to "Anyone with the link"</li>
                                            <li>Copy the link and paste it here</li>
                                        </ol>
                                    </div>
                                    <div class="invalid-feedback">Please provide a valid Google Drive link.</div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary prev-btn" data-prev="section3">Previous</button>
                                    <button type="submit" class="btn btn-success">Complete Profile</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sections = ['section1', 'section2', 'section3', 'section4'];
            let currentSection = 0;
            const progressBar = document.getElementById('progressBar');
            const skills = [];
            
            // Skills management
            document.getElementById('addSkill').addEventListener('click', function() {
                const skillInput = document.getElementById('skillInput');
                const skill = skillInput.value.trim();
                
                if (skill && !skills.includes(skill)) {
                    skills.push(skill);
                    renderSkills();
                    skillInput.value = '';
                    validateSkills();
                }
            });
            
            function renderSkills() {
                const container = document.getElementById('skillsContainer');
                container.innerHTML = '';
                
                skills.forEach((skill, index) => {
                    const tag = document.createElement('span');
                    tag.className = 'badge bg-primary skill-tag';
                    tag.innerHTML = `${skill} <span class="remove-skill" data-index="${index}">×</span>`;
                    container.appendChild(tag);
                });
                
                document.getElementById('skillsHidden').value = JSON.stringify(skills);
            }
            
            function validateSkills() {
                const skillsError = document.getElementById('skillsError');
                if (skills.length < 3) {
                    skillsError.style.display = 'block';
                    return false;
                } else {
                    skillsError.style.display = 'none';
                    return true;
                }
            }
            
            document.getElementById('skillsContainer').addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-skill')) {
                    const index = e.target.getAttribute('data-index');
                    skills.splice(index, 1);
                    renderSkills();
                    validateSkills();
                }
            });
            
            // Form navigation
            function showSection(index) {
                sections.forEach((section, i) => {
                    document.getElementById(section).classList.toggle('active', i === index);
                });
                currentSection = index;
                updateProgress();
            }
            
            function updateProgress() {
                const progress = ((currentSection + 1) / sections.length) * 100;
                progressBar.style.width = `${progress}%`;
                progressBar.setAttribute('aria-valuenow', progress);
            }
            
            function validateSection(index) {
                let isValid = true;
                const section = document.getElementById(sections[index]);
                
                // Validate required fields
                const inputs = section.querySelectorAll('[required]');
                inputs.forEach(input => {
                    if (input.type === 'radio') {
                        const radioGroup = document.querySelectorAll(`input[name="${input.name}"]`);
                        const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                        if (!isChecked) {
                            radioGroup.forEach(radio => {
                                radio.classList.add('is-invalid');
                            });
                            const feedback = document.querySelector(`input[name="${input.name}"]`).closest('.aptitude-question').querySelector('.invalid-feedback');
                            feedback.style.display = 'block';
                            isValid = false;
                        } else {
                            radioGroup.forEach(radio => {
                                radio.classList.remove('is-invalid');
                            });
                            const feedback = document.querySelector(`input[name="${input.name}"]`).closest('.aptitude-question').querySelector('.invalid-feedback');
                            feedback.style.display = 'none';
                        }
                    } else if (!input.value) {
                        input.classList.add('is-invalid');
                        input.nextElementSibling.style.display = 'block';
                        isValid = false;
                    } else {
                        input.classList.remove('is-invalid');
                        if (input.nextElementSibling && input.nextElementSibling.classList.contains('invalid-feedback')) {
                            input.nextElementSibling.style.display = 'none';
                        }
                    }
                });
                
                // Special validation for skills
                if (index === 1 && !validateSkills()) {
                    isValid = false;
                }
                
                // Validate Google Drive link format
                if (index === 3) {
                    const resumeLink = document.getElementById('resume_link').value;
                    if (resumeLink && !resumeLink.includes('drive.google.com')) {
                        document.getElementById('resume_link').classList.add('is-invalid');
                        document.querySelector('#resume_link + .invalid-feedback').textContent = 'Please provide a Google Drive link';
                        document.querySelector('#resume_link + .invalid-feedback').style.display = 'block';
                        isValid = false;
                    }
                }
                
                return isValid;
            }
            
            document.querySelectorAll('.next-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (validateSection(currentSection)) {
                        const nextSection = sections.indexOf(this.getAttribute('data-next'));
                        showSection(nextSection);
                    }
                });
            });
            
            document.querySelectorAll('.prev-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const prevSection = sections.indexOf(this.getAttribute('data-prev'));
                    showSection(prevSection);
                });
            });
            
            // Form submission validation
            document.getElementById('profileForm').addEventListener('submit', function(e) {
                if (!validateSection(currentSection)) {
                    e.preventDefault();
                    // Scroll to first invalid field
                    const firstInvalid = this.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
            
            // Initialize
            updateProgress();
            
            // Add input validation on blur
            document.querySelectorAll('input, select').forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.hasAttribute('required')) {
                        if (this.type === 'radio') {
                            // Radio buttons handled in group validation
                            return;
                        }
                        if (!this.value) {
                            this.classList.add('is-invalid');
                            if (this.nextElementSibling && this.nextElementSibling.classList.contains('invalid-feedback')) {
                                this.nextElementSibling.style.display = 'block';
                            }
                        } else {
                            this.classList.remove('is-invalid');
                            if (this.nextElementSibling && this.nextElementSibling.classList.contains('invalid-feedback')) {
                                this.nextElementSibling.style.display = 'none';
                            }
                        }
                    }
                    
                    // Special validation for Google Drive link
                    if (this.id === 'resume_link' && this.value) {
                        if (!this.value.includes('drive.google.com')) {
                            this.classList.add('is-invalid');
                            document.querySelector('#resume_link + .invalid-feedback').textContent = 'Please provide a Google Drive link';
                            document.querySelector('#resume_link + .invalid-feedback').style.display = 'block';
                        } else {
                            this.classList.remove('is-invalid');
                            document.querySelector('#resume_link + .invalid-feedback').style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>