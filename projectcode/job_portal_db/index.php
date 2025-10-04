<?php
include "includes/config.php";
session_start();


// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user;
            if ($role == 'jobseeker') header("Location: dashboard/jobseeker.php");
            elseif ($role == 'company') header("Location: dashboard/company.php");
            else header("Location: dashboard/admin.php");
            exit();
        } else {
            $login_error = "Invalid email or password!";
        }
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $register_error = "All fields are required!";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters!";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $register_error = "Email already registered!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $register_success = "Registration successful! Please login.";
                $_POST['name'] = $_POST['email'] = $_POST['password'] = '';
            } else {
                $register_error = "Registration failed: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Portal | Login or Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --error: #f72585;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .auth-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }

        .tabs {
            display: flex;
            background: #f8f9fa;
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            font-weight: 600;
            color: #6c757d;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: var(--primary);
            background: white;
            border-bottom: 3px solid var(--primary);
        }

        .tab-content {
            padding: 30px;
        }

        .form-content {
            display: none;
        }

        .form-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .auth-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .auth-header h2 {
            color: var(--primary);
            margin-bottom: 5px;
        }

        .auth-header p {
            color: #6c757d;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn:hover {
            background: var(--secondary);
        }

        .alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffebee;
            color: var(--error);
            border-left: 3px solid var(--error);
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 3px solid #2e7d32;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6c757d;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            .auth-container {
                max-width: 100%;
            }
            
            .tab-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="tabs">
            <div class="tab active" data-tab="login">Login</div>
            <div class="tab" data-tab="register">Register</div>
        </div>
        

        <div class="tab-content">
            <!-- Login Form -->
            <div class="form-content active" id="login-form">
                <div class="auth-header">
                    <h2>Welcome Back</h2>
                    <p>Sign in to access your account</p>
                </div>
 
                <?php if (isset($login_error)): ?>
                    <div class="alert alert-error"><?php echo $login_error; ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="login" value="1">
                    
                    <div class="form-group">
                        <label for="login-email">Email Address</label>
                        <input type="email" id="login-email" name="email" placeholder="your@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="login-password" name="password" placeholder="••••••••" required>
                            <span class="password-toggle" id="toggleLoginPassword"><i class="bi bi-eye"></i></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="login-role">I am a</label>
                        <select id="login-role" name="role" required>
                            <option value="">Select role</option>
                            <option value="jobseeker">Job Seeker</option>
                            <option value="company">Company</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">Login</button>
                </form>

                <div class="form-footer">
                    Don't have an account? <a href="#" id="show-register">Register now</a>
                </div>
            </div>

            <!-- Register Form -->
            <div class="form-content" id="register-form">
                <div class="auth-header">
                    <h2>Create Account</h2>
                    <p>Get started with your free account</p>
                </div>

                <?php if (isset($register_error)): ?>
                    <div class="alert alert-error"><?php echo $register_error; ?></div>
                <?php endif; ?>

                <?php if (isset($register_success)): ?>
                    <div class="alert alert-success"><?php echo $register_success; ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="register" value="1">
                    
                    <div class="form-group">
                        <label for="register-name">Full Name</label>
                        <input type="text" id="register-name" name="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               placeholder="John Doe" required>
                    </div>

                    <div class="form-group">
                        <label for="register-email">Email Address</label>
                        <input type="email" id="register-email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="your@email.com" required>
                    </div>

                    <div class="form-group">
                        <label for="register-password">Password (min 6 characters)</label>
                        <div class="password-wrapper">
                            <input type="password" id="register-password" name="password" 
                                   placeholder="••••••••" minlength="6" required>
                            <span class="password-toggle" id="toggleRegisterPassword"><i class="bi bi-eye"></i></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="register-role">I am a</label>
                        <select id="register-role" name="role" required>
                            <option value="">Select role</option>
                            <option value="jobseeker" <?php echo ($_POST['role'] ?? '') == 'jobseeker' ? 'selected' : ''; ?>>Job Seeker</option>
                            <option value="company" <?php echo ($_POST['role'] ?? '') == 'company' ? 'selected' : ''; ?>>Company</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">Register</button>
                </form>

                <div class="form-footer">
                    Already have an account? <a href="#" id="show-login">Login here</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show corresponding form
                    const tabName = this.getAttribute('data-tab');
                    document.querySelectorAll('.form-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(`${tabName}-form`).classList.add('active');
                });
            });

            // Switch form links
            document.getElementById('show-register')?.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector('.tab[data-tab="register"]').click();
            });

            document.getElementById('show-login')?.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelector('.tab[data-tab="login"]').click();
            });

            // Password toggle
            function setupPasswordToggle(toggleId, inputId) {
                const toggle = document.getElementById(toggleId);
                const input = document.getElementById(inputId);
                
                if (toggle && input) {
                    toggle.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
                    });
                }
            }

            setupPasswordToggle('toggleLoginPassword', 'login-password');
            setupPasswordToggle('toggleRegisterPassword', 'register-password');

            // Show register form if there was a registration attempt
            <?php if (isset($_POST['register']) || isset($register_error) || isset($register_success)): ?>
                document.querySelector('.tab[data-tab="register"]').click();
            <?php endif; ?>
        });
    </script>
</body>
</html>