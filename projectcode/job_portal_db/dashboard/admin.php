<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

// Only allow admin access
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../unauthorized.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed for admin action");
        die("CSRF token validation failed");
    }

    try {
        if (isset($_POST['delete_user'])) {
            $user_id = (int)$_POST['user_id'];
            if ($user_id !== $_SESSION['user']['id']) { // Prevent self-deletion
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Log the action
                error_log("Admin deleted user ID: $user_id (Action by: {$_SESSION['user']['id']})");
                $_SESSION['action_message'] = "User deleted successfully";
            }
            
        } elseif (isset($_POST['delete_company'])) {
            $company_id = (int)$_POST['company_id'];
            $stmt = $conn->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            // Log the action
            error_log("Admin deleted company ID: $company_id (Action by: {$_SESSION['user']['id']})");
            $_SESSION['action_message'] = "Company deleted successfully";
            
        } elseif (isset($_POST['approve_company'])) {
            $company_id = (int)$_POST['company_id'];
            $stmt = $conn->prepare("UPDATE companies SET approved = 1 WHERE id = ?");
            $stmt->bind_param("i", $company_id);
            $stmt->execute();
            
            // Log the action
            error_log("Admin approved company ID: $company_id (Action by: {$_SESSION['user']['id']})");
            $_SESSION['action_message'] = "Company approved successfully";
            
        } elseif (isset($_POST['change_role'])) {
            $user_id = (int)$_POST['user_id'];
            $new_role = $conn->real_escape_string($_POST['new_role']);
            $valid_roles = ['admin', 'company', 'jobseeker'];
            
            if (in_array($new_role, $valid_roles)) {
                // Prevent changing own role
                if ($user_id !== $_SESSION['user']['id']) {
                    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_role, $user_id);
                    $stmt->execute();
                    
                    // Log the action
                    error_log("Admin changed role for user ID: $user_id to $new_role (Action by: {$_SESSION['user']['id']})");
                    $_SESSION['action_message'] = "User role updated successfully";
                }
            }
        }
        
        // Refresh the page after action
        header("Location: admin.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Admin action error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred while processing your request.";
        header("Location: admin.php");
        exit();
    }
}

// Get dashboard statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM companies) as total_companies,
        (SELECT COUNT(*) FROM job_postings) as total_jobs,
        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users,
        (SELECT COUNT(*) FROM companies WHERE approved = 0) as pending_companies
")->fetch_assoc();

// Get all users with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$users = $conn->query("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");

$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_pages = ceil($total_users / $limit);

// Get all companies with pagination
$companies = $conn->query("
    SELECT c.*, u.email, u.name as owner_name 
    FROM companies c 
    JOIN users u ON c.user_id = u.id 
    ORDER BY c.approved, c.created_at DESC 
    LIMIT $limit OFFSET $offset
");

$total_companies = $conn->query("SELECT COUNT(*) as count FROM companies")->fetch_assoc()['count'];
$company_pages = ceil($total_companies / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4A55E7;
            --primary-hover: #3c46b8;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --body-bg: #f1f5f9;
            --card-bg: #ffffff;
            --card-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            --border-radius: 12px;
            --transition-speed: 0.3s;
        }

        body {
            background-color: var(--body-bg);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--dark-color);
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #7662e9 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2.5rem;
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }

        .dashboard-header h1 {
            font-weight: 700;
        }

        .stat-card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
            position: relative;
            transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card .card-body {
            position: relative;
            z-index: 2;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            opacity: 0.5;
            transition: transform var(--transition-speed) ease;
        }

        .stat-card:hover::before {
            transform: scale(1.2);
        }

        .card {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--secondary-color);
            transition: all var(--transition-speed) ease;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
        }

        .nav-tabs .nav-link.active,
        .nav-tabs .nav-item.show .nav-link {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom: 3px solid var(--primary-color);
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .table-responsive {
            padding: 0.5rem;
        }

        .table {
            border-collapse: separate;
            border-spacing: 0 0.5rem;
        }

        .table thead th {
            background-color: var(--body-bg);
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            color: var(--secondary-color);
        }

        .table tbody tr {
            background-color: var(--card-bg);
            transition: transform var(--transition-speed) ease, box-shadow var(--transition-speed) ease;
        }

        .table tbody tr:hover {
            transform: scale(1.01);
            box-shadow: 0 4px 10px rgba(0,0,0,0.07);
            z-index: 10;
            position: relative;
        }

        .table td, .table th {
            border: none;
            vertical-align: middle;
            padding: 1rem;
        }

        .table td:first-child, .table th:first-child {
            border-top-left-radius: 8px;
            border-bottom-left-radius: 8px;
        }
        .table td:last-child, .table th:last-child {
            border-top-right-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .unapproved {
            background-color: #fffbeb;
            border-left: 4px solid var(--warning-color);
        }

        .action-btns .btn {
            transition: all var(--transition-speed) ease;
        }
        
        .form-select-sm {
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(var(--primary-color), 0.25);
        }

        .pagination .page-link {
            border: none;
            border-radius: 50% !important;
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            color: var(--secondary-color);
            transition: all var(--transition-speed) ease;
        }

        .pagination .page-link:hover {
            background-color: var(--body-bg);
            color: var(--primary-color);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 8px rgba(var(--primary-color), 0.3);
        }
        
        .highlight {
            background-color: #fff3cd;
            border-radius: 3px;
            padding: 1px 3px;
        }

        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
            min-width: 300px;
        }
    </style>
</head>
<body>
    <!-- Action Message Alert -->
    <?php if (isset($_SESSION['action_message'])): ?>
        <div class="alert-message">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['action_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['action_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert-message">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="bi bi-speedometer2"></i> Admin Dashboard</h1>
                    <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../logout.php" class="btn btn-light">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary">
                    <div class="card-body">
                        <h5><i class="bi bi-people"></i> Total Users</h5>
                        <h2><?php echo $stats['total_users']; ?></h2>
                        <?php if ($stats['new_users'] > 0): ?>
                            <small>+<?php echo $stats['new_users']; ?> new this week</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success">
                    <div class="card-body">
                        <h5><i class="bi bi-building"></i> Companies</h5>
                        <h2><?php echo $stats['total_companies']; ?></h2>
                        <?php if ($stats['pending_companies'] > 0): ?>
                            <small><?php echo $stats['pending_companies']; ?> pending approval</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info">
                    <div class="card-body">
                        <h5><i class="bi bi-briefcase"></i> Job Postings</h5>
                        <h2><?php echo $stats['total_jobs']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning">
                    <div class="card-body">
                        <h5><i class="bi bi-activity"></i> Activity</h5>
                        <h2><?php echo $stats['new_users']; ?></h2>
                        <small>new users this week</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Tabs -->
        <ul class="nav nav-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                    <i class="bi bi-people-fill"></i> User Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="companies-tab" data-bs-toggle="tab" data-bs-target="#companies" type="button" role="tab">
                    <i class="bi bi-buildings"></i> Company Management
                </button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            <!-- Users Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">User Management</h5>
                        <div>
                            <input type="text" id="userSearch" class="form-control" placeholder="Search users...">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($user['name']); ?>
                                                <?php if ($user['id'] == $_SESSION['user']['id']): ?>
                                                    <span class="badge bg-primary">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <select name="new_role" class="form-select form-select-sm" onchange="if(confirm('Are you sure you want to change this user\'s role?')) { this.form.submit(); }">
                                                        <option value="jobseeker" <?php echo $user['role'] === 'jobseeker' ? 'selected' : ''; ?>>Job Seeker</option>
                                                        <option value="company" <?php echo $user['role'] === 'company' ? 'selected' : ''; ?>>Company</option>
                                                        <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                    </select>
                                                    <input type="hidden" name="change_role" value="1">
                                                </form>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td class="action-btns">
                                                <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this user? This cannot be undone.')">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="User pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>#users" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>#users"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>#users" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Companies Tab -->
            <div class="tab-pane fade" id="companies" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Company Management</h5>
                        <div>
                            <input type="text" id="companySearch" class="form-control" placeholder="Search companies...">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Company Name</th>
                                        <th>Industry</th>
                                        <th>Owner</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($company = $companies->fetch_assoc()): ?>
                                        <tr class="<?php echo !$company['approved'] ? 'unapproved' : ''; ?>">
                                            <td><?php echo $company['id']; ?></td>
                                            <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                            <td><?php echo htmlspecialchars($company['industry']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($company['owner_name']); ?>
                                                <small class="d-block text-muted"><?php echo htmlspecialchars($company['email']); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($company['approved']): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($company['created_at'])); ?></td>
                                            <td class="action-btns">
                                                <?php if (!$company['approved']): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                        <button type="submit" name="approve_company" class="btn btn-sm btn-success">
                                                            <i class="bi bi-check-circle"></i> Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                    <button type="submit" name="delete_company" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this company and all its job postings?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <nav aria-label="Company pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>#companies" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $company_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>#companies"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $company_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>#companies" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    /**
     * Debounce function to limit the rate at which a function gets called.
     * @param {Function} func The function to debounce.
     * @param {number} delay The delay in milliseconds.
     * @returns {Function} The debounced function.
     */
    const debounce = (func, delay) => {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(this, args);
            }, delay);
        };
    };

    /**
     * Generic search and highlight functionality for a table.
     * @param {string} inputId The ID of the search input field.
     * @param {string} tableBodySelector The CSS selector for the table body to search within.
     */
    const setupTableSearch = (inputId, tableBodySelector) => {
        const searchInput = document.getElementById(inputId);
        const tableBody = document.querySelector(tableBodySelector);
        if (!searchInput || !tableBody) return;

        const allRows = Array.from(tableBody.querySelectorAll('tr'));
        let originalHTML = allRows.map(row => row.innerHTML);

        const handleSearch = (event) => {
            const searchTerm = event.target.value.toLowerCase().trim();

            // Restore original HTML to remove old highlights
            allRows.forEach((row, index) => {
                row.innerHTML = originalHTML[index];
            });

            if (searchTerm === '') {
                allRows.forEach(row => row.style.display = '');
                return;
            }

            const searchRegex = new RegExp(searchTerm.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&'), 'gi');

            allRows.forEach((row, index) => {
                const rowText = row.textContent.toLowerCase();
                if (rowText.includes(searchTerm)) {
                    row.style.display = '';
                    // Highlight the search term
                    row.innerHTML = row.innerHTML.replace(searchRegex, (match) => `<span class="highlight">${match}</span>`);
                } else {
                    row.style.display = 'none';
                }
            });
        };

        searchInput.addEventListener('input', debounce(handleSearch, 300));
    };

    // Initialize search for both tables
    setupTableSearch('userSearch', '#users tbody');
    setupTableSearch('companySearch', '#companies tbody');

    // Persist active tab on page reload after form submission
    if (location.hash) {
        const tabToActivate = document.querySelector(`button[data-bs-target="${location.hash}"]`);
        if (tabToActivate) {
            const tab = new bootstrap.Tab(tabToActivate);
            tab.show();
        }
    }

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>
</body>
</html>