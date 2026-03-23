<?php
require_once 'config.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errors    = [];
$success   = '';
$savedRole = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Collect inputs ────────────────────────────────────
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';
    $role      = $_POST['role']           ?? 'admin';

    // ── Validate full name ────────────────────────────────
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    } elseif (strlen($full_name) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    } elseif (strlen($full_name) > 150) {
        $errors[] = 'Full name must not exceed 150 characters.';
    }

    // ── Validate username ─────────────────────────────────
    if ($username === '') {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 4) {
        $errors[] = 'Username must be at least 4 characters.';
    } elseif (strlen($username) > 50) {
        $errors[] = 'Username must not exceed 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, and underscores.';
    }

    // ── Validate password ─────────────────────────────────
    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    // ── Validate confirm password ─────────────────────────
    if ($password !== '' && $password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    // ── Sanitize role ─────────────────────────────────────
    $allowedRoles = ['admin', 'staff', 'viewer'];
    if (!in_array($role, $allowedRoles, true)) {
        $role = 'admin';
    }

    // ── Check duplicate username ──────────────────────────
    if (empty($errors)) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $errors[] = 'Username "' . e($username) . '" is already taken. Please choose another.';
        }
    }

    // ── Insert account ────────────────────────────────────
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$full_name, $username, $hash, $role]);

        $success   = $full_name;
        $savedRole = ucfirst($role);
        $username  = '';
        $full_name = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — CCS Profiling System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/login.css">
</head>
<body>

<!-- ======================================================
     LEFT — Branding Panel
     ====================================================== -->
<div class="auth-left">

    <div class="ring"></div>

    <!-- Brand -->
    <div class="brand-mark">
        <div class="icon">CCS</div>
        <div class="brand-text">
            <span class="brand-name">CCS Profiling System</span>
            <span class="brand-sub">College of Computing Studies</span>
        </div>
    </div>

    <!-- College badge -->
    <div class="college-badge">
        <span>🎓 Comprehensive Profiling System</span>
    </div>

    <!-- Hero heading -->
    <div class="hero-text">
        <h1>Create Your<br><em>Account</em> and<br>Get Started.</h1>
        <p>
            Register a new account to manage student and faculty profiles,
            academic schedules, research records, and the full curriculum
            management suite of CCS Profiling System.
        </p>
    </div>

    <!-- Feature cards -->
    <div class="feature-grid">
        <div class="feature-card">
            <span class="fc-icon">🔐</span>
            <div class="fc-text">
                <strong>Secure Access</strong>
                <span>bcrypt-hashed passwords</span>
            </div>
        </div>
        <div class="feature-card">
            <span class="fc-icon">🛡️</span>
            <div class="fc-text">
                <strong>Role Control</strong>
                <span>Admin, Staff, or Viewer</span>
            </div>
        </div>
        <div class="feature-card">
            <span class="fc-icon">⚡</span>
            <div class="fc-text">
                <strong>Instant Access</strong>
                <span>Login right after signup</span>
            </div>
        </div>
        <div class="feature-card">
            <span class="fc-icon">📊</span>
            <div class="fc-text">
                <strong>Full Dashboard</strong>
                <span>All modules included</span>
            </div>
        </div>
    </div>

    <!-- Tagline -->
    <div class="auth-tagline">
        <p><strong>CCS Profiling System</strong> — Empowering academic excellence through data.</p>
    </div>

</div>


<!-- ======================================================
     RIGHT — Register Form
     ====================================================== -->
<div class="auth-right register">
    <div class="auth-box">

        <?php if ($success): ?>

            <!-- ── SUCCESS SCREEN ──────────────────────── -->
            <div style="text-align: center; padding: 24px 0;">

                <div style="
                    width: 72px;
                    height: 72px;
                    background: rgba(26,122,74,0.10);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 34px;
                    margin: 0 auto 20px;
                ">✅</div>

                <h2 style="margin-bottom: 8px;">Account Created!</h2>
                <p class="subtitle">Your CCS Profiling System account is ready.</p>

                <div class="success-msg" style="text-align: left; margin: 20px 0; line-height: 1.9;">
                    <strong>Account Details</strong><br><br>
                    Full Name: <strong><?= e($success) ?></strong><br>
                    Role:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong><?= e($savedRole) ?></strong>
                </div>

                <a href="index.php" class="btn-goto">Go to Sign In &rarr;</a>

            </div>

        <?php else: ?>

            <!-- ── REGISTER FORM ───────────────────────── -->
            <p class="page-eyebrow">New Account</p>
            <h2>Create Account</h2>
            <p class="subtitle">Fill in the details below to register your account.</p>

            <?php if (!empty($errors)): ?>
                <ul class="error-list">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- Full Name -->
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        value="<?= e($_POST['full_name'] ?? '') ?>"
                        placeholder="e.g. Juan Dela Cruz"
                        autocomplete="name"
                        autofocus
                        required
                        maxlength="150"
                    >
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= e($_POST['username'] ?? '') ?>"
                        placeholder="e.g. juan_delacruz"
                        autocomplete="username"
                        required
                        minlength="4"
                        maxlength="50"
                    >
                </div>

                <!-- Role -->
                <div class="form-group">
                    <label for="role">Account Role</label>
                    <select id="role" name="role">
                        <option value="admin"  <?= (($_POST['role'] ?? 'admin') === 'admin')  ? 'selected' : '' ?>>
                            Admin — Full system access
                        </option>
                        <option value="staff"  <?= (($_POST['role'] ?? '') === 'staff')  ? 'selected' : '' ?>>
                            Staff — Limited access
                        </option>
                        <option value="viewer" <?= (($_POST['role'] ?? '') === 'viewer') ? 'selected' : '' ?>>
                            Viewer — Read only
                        </option>
                    </select>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Minimum 6 characters"
                        autocomplete="new-password"
                        required
                        minlength="6"
                        oninput="updateStrength(this.value)"
                    >
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-label" id="strengthLabel">Enter a password</span>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="confirm">Confirm Password</label>
                    <input
                        type="password"
                        id="confirm"
                        name="confirm"
                        placeholder="Re-enter your password"
                        autocomplete="new-password"
                        required
                        minlength="6"
                        oninput="checkMatch()"
                    >
                    <span class="strength-label" id="matchLabel"></span>
                </div>

                <button type="submit" class="btn-submit">Create Account &rarr;</button>

            </form>

            <hr class="auth-divider">

            <p class="auth-link">
                Already have an account?
                <a href="index.php">Sign in here</a>
            </p>

        <?php endif; ?>

    </div>
</div>


<!-- ======================================================
     JAVASCRIPT — Password strength & match checker
     ====================================================== -->
<script>

    // ── Password strength meter ────────────────────────────
    function updateStrength(value) {
        var fill  = document.getElementById('strengthFill');
        var label = document.getElementById('strengthLabel');

        var score = 0;
        if (value.length >= 6)           score++;
        if (value.length >= 10)          score++;
        if (/[A-Z]/.test(value))         score++;
        if (/[0-9]/.test(value))         score++;
        if (/[^a-zA-Z0-9]/.test(value)) score++;

        var levels = [
            { pct: '0%',   color: '#dde3ec', text: 'Enter a password' },
            { pct: '20%',  color: '#b5293a', text: 'Weak' },
            { pct: '45%',  color: '#c77a0a', text: 'Fair' },
            { pct: '68%',  color: '#1565a8', text: 'Good' },
            { pct: '85%',  color: '#1a7a4a', text: 'Strong' },
            { pct: '100%', color: '#1a7a4a', text: 'Very Strong ✓' },
        ];

        var level = (value.length === 0) ? levels[0] : levels[Math.min(score, 5)];

        fill.style.width      = level.pct;
        fill.style.background = level.color;
        label.textContent     = level.text;
        label.style.color     = (level.color === '#dde3ec') ? '#6b7c93' : level.color;

        checkMatch();
    }

    // ── Confirm password match indicator ──────────────────
    function checkMatch() {
        var pw      = document.getElementById('password').value;
        var confirm = document.getElementById('confirm').value;
        var label   = document.getElementById('matchLabel');

        if (confirm === '') {
            label.textContent = '';
            return;
        }

        if (pw === confirm) {
            label.textContent = '✓ Passwords match';
            label.style.color = '#1a7a4a';
        } else {
            label.textContent = '✗ Passwords do not match';
            label.style.color = '#b5293a';
        }
    }

</script>

</body>
</html>