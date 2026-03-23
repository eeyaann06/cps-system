<?php
require_once 'config.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']       ?? '';

    if ($username && $password) {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];   // ← store full name
            $_SESSION['role']      = $user['role'];

            redirect('dashboard.php', 'Welcome back, ' . $user['full_name'] . '!');
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — CCS Profiling System</title>
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
        <h1>One Platform<br>for <em>Every</em><br>Student Record.</h1>
        <p>
            The CCS Profiling System manages student and faculty profiles,
            academic schedules, research archives, syllabus, curriculum,
            and lesson plans — all in one secure place.
        </p>
    </div>

    <!-- Feature cards -->
    <div class="feature-grid">
        <div class="feature-card">
            <span class="fc-icon">🎓</span>
            <div class="fc-text">
                <strong>Student Profiles</strong>
                <span>Enrollment, GPA &amp; records</span>
            </div>
        </div>
        <div class="feature-card">
            <span class="fc-icon">👩‍🏫</span>
            <div class="fc-text">
                <strong>Faculty Profiles</strong>
                <span>Staff info &amp; specializations</span>
            </div>
        </div>
        <div class="feature-card">
            <span class="fc-icon">📅</span>
            <div class="fc-text">
                <strong>Scheduling</strong>
                <span>Classes, rooms &amp; events</span>
            </div>
        </div>
        <div class="feature-card">
            <span class="fc-icon">🔬</span>
            <div class="fc-text">
                <strong>Research &amp; Curriculum</strong>
                <span>Theses, syllabi &amp; lessons</span>
            </div>
        </div>
    </div>

    <!-- Tagline -->
    <div class="auth-tagline">
        <p><strong>CCS Profiling System</strong> — Empowering academic excellence through data.</p>
    </div>

</div>


<!-- ======================================================
     RIGHT — Login Form
     ====================================================== -->
<div class="auth-right">
    <div class="auth-box">

        <p class="page-eyebrow">Secure Portal</p>
        <h2>Welcome back</h2>
        <p class="subtitle">
            Sign in to your CCS Profiling System account<br>to access the dashboard.
        </p>

        <?php flash(); ?>

        <?php if ($error): ?>
            <div class="error-msg"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= e($_POST['username'] ?? '') ?>"
                    placeholder="Enter your username"
                    autocomplete="username"
                    autofocus
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn-submit">Sign In &rarr;</button>

        </form>

        <hr class="auth-divider">

        <p class="auth-link">
            Don&apos;t have an account?
            <a href="register.php">Create one here</a>
        </p>

    </div>
</div>

</body>
</html>