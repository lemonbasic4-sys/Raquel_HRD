<?php
/**
 * Employee Self-Service Portal - Dedicated Login
 */
require_once '../config/database.php';
require_once '../includes/functions.php';

// If already logged in as an Employee account, skip to dashboard
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Employee' && (int) ($_SESSION['employee_id'] ?? 0) > 0) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $lockout_seconds = checkLoginBruteForce($conn, $username, $ip);
    if ($lockout_seconds > 0) {
        $error = "Too many failed login attempts. Please try again in $lockout_seconds seconds.";
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("
            SELECT u.user_id, u.employee_id, u.username, u.email, u.password_hash, u.full_name, u.role, u.branch_id, u.is_active, u.first_login_completed, e.is_active as emp_is_active, e.employment_status
            FROM users u
            LEFT JOIN employees e ON u.employee_id = e.employee_id
            WHERE BINARY u.username = ? 
              AND u.employee_id IS NOT NULL
              AND u.role = 'Employee'
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $is_emp_inactive = ($user['emp_is_active'] !== null && (int)$user['emp_is_active'] === 0) || (strcasecmp($user['employment_status'] ?? '', 'Inactive') === 0);
            if (!$user['is_active'] || $is_emp_inactive) {
                $error = 'Your account has been deactivated.';
                registerLoginAttempt($conn, $username, $ip);
            } elseif (password_verify($password, $user['password_hash'])) {
                // Clear attempts on successful login
                clearLoginAttempts($conn, $username, $ip);

                // Handle Remember Me functionality
                if (!empty($_POST['remember'])) {
                    setcookie('remember_employee_username', $username, time() + (30 * 24 * 60 * 60), '/');
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
                    setcookie('remember_employee_username', '', time() - 3600, '/');
                }

                // Set session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_login_completed'] = (bool) ($user['first_login_completed'] ?? false);

                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid credentials.';
                registerLoginAttempt($conn, $username, $ip);
            }
        } else {
            $error = 'Only Employee accounts can access the Employee Portal.';
            registerLoginAttempt($conn, $username, $ip);
        }
    }
}
$sys_logo = getSetting($conn, 'system_logo', 'assets/img/logo/logo.png');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Employee Login - Raquel Pawnshop HRIS</title>
  <meta name="description" content="Employee Self-Service login to Raquel Pawnshop Human Resource Information System">
  <!-- Browser Tab Icon (Favicon) -->
  <link rel="icon" type="image/png" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
  <link rel="shortcut icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
  <link rel="apple-touch-icon" href="<?php echo BASE_URL . '/' . htmlspecialchars($sys_logo); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.34.0/dist/tabler-icons.min.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/raquel-hris-login.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/raquel-hris-login.css'); ?>">
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

<body class="show-ess">

  <div class="root">
    <div class="track show-ess" id="track">

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
            <button class="mob-tab" id="mobTabHRIS" onclick="showHRIS()">HRIS Portal</button>
            <button class="mob-tab active-green" id="mobTabESS" onclick="showESS()">Employee Portal</button>
          </div>
        </div>

        <div class="form-panel">
          <!-- ── HRIS Login Card ── -->
          <div class="form-inner hris" id="hrisCard">
            <p class="eyebrow ey-gold"><i class="ti ti-briefcase" aria-hidden="true"></i> HRIS Portal</p>
            <h2 class="form-title">Welcome Back!</h2>
            <p class="form-sub">Login to access your HR management account</p>

            <!-- Error container -->
            <div class="login-error" id="hrisError" style="display:none">
              <i class="ti ti-alert-circle"></i>
              <span id="hrisErrorMsg"></span>
            </div>

            <form method="POST" action="<?php echo BASE_URL; ?>/index.php" id="hrisForm">
              <?php echo csrfField(); ?>
              <div class="field">
                <label for="hris-username">Username</label>
                <div class="iwrap">
                  <i class="ti ti-user iico" aria-hidden="true"></i>
                  <input type="text" id="hris-username" name="username" placeholder="Enter your username"
                    autocomplete="username" value="<?php echo htmlspecialchars($_COOKIE['remember_username'] ?? ''); ?>">
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

            <div class="login-error" id="essError" style="<?php echo $error ? 'display: flex;' : 'display: none;'; ?>">
              <i class="ti ti-alert-circle"></i>
              <span id="essErrorMsg"><?php echo htmlspecialchars($error); ?></span>
            </div>

            <form method="POST" action="index.php" id="essForm">
              <?php echo csrfField(); ?>
              <div class="field">
                <label for="ess-username">Employee ID / Username</label>
                <div class="iwrap">
                  <i class="ti ti-user iico" aria-hidden="true"></i>
                  <input type="text" id="ess-username" name="username" placeholder="e.g. 024-001"
                    autocomplete="username" value="<?php echo e($_COOKIE['remember_employee_username'] ?? $_POST['username'] ?? ''); ?>">
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
                <label class="rmb"><input type="checkbox" name="remember" <?php echo isset($_COOKIE['remember_employee_username']) ? 'checked' : ''; ?>> Remember me</label>
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
        const hrisErrorEl = document.getElementById("hrisErrorMsg");
        const essErrorEl = document.getElementById("essErrorMsg");
        
        function handleCountdown(errorEl, containerId) {
            if (!errorEl) return;
            const text = errorEl.textContent;
            const match = text.match(/Please try again in (\d+) seconds/);
            if (match) {
                let seconds = parseInt(match[1], 10);
                const errorContainer = document.getElementById(containerId);
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
        
        handleCountdown(hrisErrorEl, "hrisError");
        handleCountdown(essErrorEl, "essError");
    });
  </script>

</body>

</html>
