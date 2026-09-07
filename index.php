<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load config early so BASE_URL is available for redirects and HTML
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

function normalizeLoginRole($role)
{
    $role_aliases = [
        'Manager' => 'HR Manager',
        'Supervisor' => 'HR Supervisor',
        'Staff' => 'HR Staff',
    ];

    return $role_aliases[$role] ?? $role;
}

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    $_SESSION['role'] = normalizeLoginRole($_SESSION['role'] ?? '');

    switch ($_SESSION['role']) {
        case 'Admin':
            header("Location: " . BASE_URL . "/admin/dashboard.php");
            break;
        case 'HR Manager':
            header("Location: " . BASE_URL . "/manager/dashboard.php");
            break;
        case 'HR Supervisor':
            header("Location: " . BASE_URL . "/supervisor/dashboard.php");
            break;
        case 'HR Staff':
            header("Location: " . BASE_URL . "/staff/dashboard.php");
            break;
        case 'Employee':
            header("Location: " . BASE_URL . "/employee/dashboard.php");
            break;
        default:
            session_unset();
            session_destroy();
            header("Location: " . BASE_URL . "/index.php");
            break;
    }
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Nasa plugin/functions.php yong time duration for bruteforce 
    $lockout_seconds = checkLoginBruteForce($conn, $username, $ip);
    if ($lockout_seconds > 0) {
        $error = "Too many failed login attempts. Please try again in $lockout_seconds seconds.";
    } elseif (empty($username)) {
        $error = 'Please enter your username.';
    } elseif (empty($password)) {
        $error = 'Please enter your password.';
    } else {
        // Query user by username
        $stmt = $conn->prepare("SELECT user_id, employee_id, username, email, password_hash, full_name, role, branch_id, is_active, first_login_completed FROM users WHERE BINARY username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user['role'] = normalizeLoginRole($user['role'] ?? '');

            // Check if account is active
            if (!$user['is_active']) {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } elseif (password_verify($password, $user['password_hash'])) {
                if (($user['role'] ?? '') === 'Employee') {
                    $error = 'Employee accounts must sign in through the Employee Self-Service Portal.';
                    $stmt->close();
                    goto render_login;
                }

                // Handle Remember Me functionality
                if (!empty($_POST['remember'])) {
                    setcookie('remember_username', $username, time() + (30 * 24 * 60 * 60), '/');
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        session_id(),
                        time() + (30 * 24 * 60 * 60),
                        $params["path"],
                        $params["domain"],
                        $params["secure"],
                        $params["httponly"]
                    );
                } else {
                    setcookie('remember_username', '', time() - 3600, '/');
                }

                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['branch_id'] = $user['branch_id'];
                $_SESSION['first_login_completed'] = (bool) ($user['first_login_completed'] ?? false);

                // Clear brute force attempts on successful login
                clearLoginAttempts($conn, $username, $ip);

                // Notify Admins of successful login
                $adminStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND is_active = 1");
                $adminStmt->execute();
                $admins = $adminStmt->get_result();
                while ($admin = $admins->fetch_assoc()) {
                    createNotification($conn, $admin['user_id'], 'Successful Login', $user['full_name'] . ' (' . $user['role'] . ') has logged in successfully from IP ' . $ip);
                }
                $adminStmt->close();

                // Redirect based on role
                switch ($user['role']) {
                    case 'Admin':
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                        break;
                    case 'HR Manager':
                        header("Location: " . BASE_URL . "/manager/dashboard.php");
                        break;
                    case 'HR Supervisor':
                        header("Location: " . BASE_URL . "/supervisor/dashboard.php");
                        break;
                    case 'HR Staff':
                        header("Location: " . BASE_URL . "/staff/dashboard.php");
                        break;
                    case 'Employee':
                        header("Location: " . BASE_URL . "/employee/dashboard.php");
                        break;
                }
                exit();
            } else {
                $error = 'Invalid username or password.';

                // Register failed attempt
                registerLoginAttempt($conn, $username, $ip);

                // Notify Admins of failed login (wrong password)
                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $adminStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND is_active = 1");
                $adminStmt->execute();
                $admins = $adminStmt->get_result();
                while ($admin = $admins->fetch_assoc()) {
                    createNotification($conn, $admin['user_id'], 'Security Alert: Failed Login', 'Failed login attempt for ' . $user['username'] . ' (' . $user['role'] . '). Incorrect password entry. IP: ' . $ip);
                }
                $adminStmt->close();
            }
        } else {
            $error = 'Invalid username or password.';

            // Register failed attempt
            registerLoginAttempt($conn, $username, $ip);

            // Notify Admins of failed login (invalid account)
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $adminStmt = $conn->prepare("SELECT user_id FROM users WHERE role = 'Admin' AND is_active = 1");
            $adminStmt->execute();
            $admins = $adminStmt->get_result();
            $failed_username = htmlspecialchars($_POST['username'] ?? 'Unknown');
            while ($admin = $admins->fetch_assoc()) {
                createNotification($conn, $admin['user_id'], 'Security Alert: Failed Login', 'Failed login attempt for ' . $failed_username . ' (Unknown Account). IP: ' . $ip);
            }
            $adminStmt->close();
        }
        $stmt->close();
    }
}

render_login:
$sys_logo = getSetting($conn, 'system_logo', 'assets/img/logo/logo.png');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Login - Raquel Pawnshop HRIS</title>
  <meta name="description" content="Login to Raquel Pawnshop Human Resource Information System">
  <!-- Browser Tab Icon (Favicon) -->
  <link rel="icon" type="image/png" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
  <link rel="shortcut icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
  <link rel="apple-touch-icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.34.0/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/raquel-hris-login.css?v=<?php echo filemtime(__DIR__ . '/assets/css/raquel-hris-login.css'); ?>">
  <noscript>
    <style>
      body::before {
        content: 'JavaScript is required to use this login page.';
        display: block;
        text-align: center;
        padding: 2rem;
        font-family: sans-serif;
      }
    </style>
  </noscript>
</head>

<body>

  <div class="root">
    <div class="track" id="track">

      <!-- ══ HRIS SCREEN ══ -->
      <div class="screen">
        <div class="brand page-hero">
          <div class="cr tl"></div>
          <div class="cr tr"></div>
          <div class="cr bl"></div>
          <div class="cr br"></div>
          <div class="bcon">
            <div class="logo-ring">
              <img src="<?php echo BASE_URL; ?>/assets/img/logo/logo.png" alt="Raquel Pawnshop Logo"
                onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
              <span class="mono" style="display:none">R</span>
            </div>
            <div>
              <p class="b-label">Human Resource Department</p>
              <div class="g-line"></div>
              <h1 class="b-name">Raquel Pawnshop</h1>
            </div>
            <p class="b-tag"><b>Palagay ang Loob Ko!</b></p>
          </div>
        </div>

        <!-- ESS brand panel (positioned absolute desktop / toggled mobile sibling) -->
        <div class="ess-brand page-hero">
          <div class="cr tl"></div>
          <div class="cr tr"></div>
          <div class="cr bl"></div>
          <div class="cr br"></div>
          <div class="ess-bcon">
            <div class="ess-ring">
              <img src="<?php echo BASE_URL; ?>/assets/img/logo/logo.png" alt="Raquel Pawnshop Logo">
            </div>
            <div>
              <p class="e-label">Employee Portal</p>
              <div class="e-line"></div>
              <p class="e-name">Raquel Pawnshop</p>
            </div>
            <p class="e-tag"><b>Palagay ang Loob Ko!</b></p>
          </div>
        </div>

        <!-- Mobile Tab Switcher (Floating in the middle section) -->
        <div class="mob-tabs-wrapper">
          <div class="mob-tabs">
            <button class="mob-tab active-gold" id="mobTabHRIS" onclick="showHRIS()">HRIS Portal</button>
            <button class="mob-tab" id="mobTabESS" onclick="showESS()">Employee Portal</button>
          </div>
        </div>

        <div class="form-panel">
          <!-- ── HRIS Login Card ── -->
          <div class="form-inner hris" id="hrisCard">
            <p class="eyebrow ey-gold"><i class="ti ti-briefcase" aria-hidden="true"></i> HRIS Portal</p>
            <h2 class="form-title">Welcome Back!</h2>
            <p class="form-sub">Login to access your HR management account</p>

            <!-- Error container -->
            <div class="login-error" id="hrisError" style="<?php echo $error ? 'display: flex;' : 'display: none;'; ?>">
              <i class="ti ti-alert-circle"></i>
              <span id="hrisErrorMsg"><?php echo htmlspecialchars($error); ?></span>
            </div>

            <form method="POST" action="index.php" id="hrisForm">
              <?php echo csrfField(); ?>
              <div class="field">
                <label for="hris-username">Username</label>
                <div class="iwrap">
                  <i class="ti ti-user iico" aria-hidden="true"></i>
                  <input type="text" id="hris-username" name="username" placeholder="Enter your username"
                    autocomplete="username" value="<?php echo htmlspecialchars($_COOKIE['remember_username'] ?? $_POST['username'] ?? ''); ?>">
                </div>
              </div>

              <div class="field">
                <label for="hp">Password</label>
                <div class="iwrap">
                  <i class="ti ti-lock iico" aria-hidden="true"></i>
                  <input type="password" id="hp" name="password" placeholder="Enter your password"
                    autocomplete="current-password">
                  <button type="button" class="eyebtn" onclick="tpw('hp','he')" aria-label="Toggle password visibility">
                    <i class="ti ti-eye" id="he"></i>
                  </button>
                </div>
              </div>

              <div class="optrow">
                <label class="rmb"><input type="checkbox" name="remember" <?php echo isset($_COOKIE['remember_username']) ? 'checked' : ''; ?>> Remember me</label>
                <a href="#" class="fgt">Forgot password?</a>
              </div>

              <button type="submit" class="btn-p btn-gold">
                <i class="ti ti-login" aria-hidden="true"></i>
                Sign In
              </button>
            </form>

            <button class="btn-s" onclick="showESS()">
              <i class="ti ti-id-badge-2" aria-hidden="true"></i>
              Employee Portal Login
            </button>

            <p class="ffoot">© <?php echo date('Y'); ?> Raquel Pawnshop. All rights reserved.</p>
          </div>

          <!-- ── ESS Login Card ── -->
          <div class="form-inner essf">
            <p class="eyebrow ey-green"><i class="ti ti-user-circle" aria-hidden="true"></i> Employee Portal</p>
            <h2 class="form-title">Welcome Back!</h2>
            <p class="form-sub">Login to access your employee account</p>

            <div class="login-error" id="essError" style="display:none">
              <i class="ti ti-alert-circle"></i>
              <span id="essErrorMsg"></span>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/employee/index.php" id="essForm">
              <?php echo csrfField(); ?>
              <div class="field">
                <label for="ess-username">Username</label>
                <div class="iwrap">
                  <i class="ti ti-user iico" aria-hidden="true"></i>
                  <input type="text" id="ess-username" name="username" placeholder="Enter your username"
                    autocomplete="username">
                </div>
              </div>

              <div class="field">
                <label for="ep">Password</label>
                <div class="iwrap">
                  <i class="ti ti-lock iico" aria-hidden="true"></i>
                  <input type="password" id="ep" name="password" placeholder="Enter your password"
                    autocomplete="current-password">
                  <button type="button" class="eyebtn" onclick="tpw('ep','ee')" aria-label="Toggle password visibility">
                    <i class="ti ti-eye" id="ee"></i>
                  </button>
                </div>
              </div>

              <div class="optrow">
                <label class="rmb"><input type="checkbox" name="remember"> Remember me</label>
                <a href="#" class="fgt">Forgot password?</a>
              </div>

              <button type="submit" class="btn-p btn-green">
                <i class="ti ti-login" aria-hidden="true"></i>
                Sign In
              </button>
            </form>

            <button class="btn-s" onclick="showHRIS()">
              <i class="ti ti-briefcase" aria-hidden="true"></i>
              HR Management Login
            </button>

            <p class="ffoot">© <?php echo date('Y'); ?> Raquel Pawnshop. All rights reserved.</p>
          </div>
        </div>
      </div><!-- /HRIS screen -->

    </div><!-- /track -->
  </div><!-- /root -->

  <script src="<?php echo BASE_URL; ?>/assets/js/raquel-hris-login.js" defer></script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
        const errorEl = document.getElementById("hrisErrorMsg");
        if (errorEl) {
            const text = errorEl.textContent;
            const match = text.match(/Please try again in (\d+) seconds/);
            if (match) {
                let seconds = parseInt(match[1], 10);
                const errorContainer = document.getElementById("hrisError");
                const interval = setInterval(function() {
                    seconds--;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        if (errorContainer) errorContainer.style.display = "none";
                        errorEl.textContent = "";
                    } else {
                        errorEl.textContent = `Too many failed login attempts. Please try again in ${seconds} seconds.`;
                    }
                }, 1000);
            }
        }
    });
  </script>

</body>

</html>
