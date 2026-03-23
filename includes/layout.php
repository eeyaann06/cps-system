<?php
/**
 * includes/layout.php
 *
 * Shared page shell for every authenticated page.
 *
 * Usage:
 *   renderHeader('Page Title', 'active_page_key');
 *   ... your page HTML ...
 *   renderFooter();
 */

// ── renderHeader ─────────────────────────────────────────────────────────────
// $title      : <title> tag text and topbar heading
// $activePage : key matching a nav-item (dashboard, students, faculty, etc.)

function renderHeader(string $title = 'Dashboard', string $activePage = 'dashboard'): void
{
    // Show full name in sidebar; fall back to username if not set
    $fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin';
    $role     = ucfirst($_SESSION['role'] ?? 'Admin');

    // Build initials from full name (up to 2 letters)
    $nameParts = explode(' ', trim($fullName));
    $initials  = strtoupper(substr($nameParts[0], 0, 1));
    if (isset($nameParts[1])) {
        $initials .= strtoupper(substr($nameParts[1], 0, 1));
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> — CCS Profiling System</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

    <!-- Main stylesheet -->
    <link rel="stylesheet" href="styles/main.css">
</head>
<body>

<!-- ====================================================
     SIDEBAR
     ==================================================== -->
<nav class="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="logo-wrap">
            <div class="logo-icon">CCS</div>
            <div class="logo-text">
                <span class="logo-name">CCS Profiling System</span>
                <span class="logo-sub">College of Computing Studies</span>
            </div>
        </div>
    </div>

    <!-- Overview -->
    <div class="sidebar-section">
        <div class="sidebar-label">Overview</div>
        <a href="dashboard.php"
           class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">🏠</span> Dashboard
        </a>
    </div>

    <!-- Profiles -->
    <div class="sidebar-section">
        <div class="sidebar-label">Profiles</div>
        <a href="student_profile.php"
           class="nav-item <?= $activePage === 'students' ? 'active' : '' ?>">
            <span class="nav-icon">🎓</span> Student Profiles
        </a>
        <a href="faculty_profile.php"
           class="nav-item <?= $activePage === 'faculty' ? 'active' : '' ?>">
            <span class="nav-icon">👩‍🏫</span> Faculty Profiles
        </a>
    </div>

    <!-- Academic -->
    <div class="sidebar-section">
        <div class="sidebar-label">Academic</div>
        <a href="events.php"
           class="nav-item <?= $activePage === 'events' ? 'active' : '' ?>">
            <span class="nav-icon">📅</span> Events
        </a>
        <a href="scheduling.php"
           class="nav-item <?= $activePage === 'scheduling' ? 'active' : '' ?>">
            <span class="nav-icon">🗓️</span> Scheduling
        </a>
        <a href="college_research.php"
           class="nav-item <?= $activePage === 'research' ? 'active' : '' ?>">
            <span class="nav-icon">🔬</span> College Research
        </a>
    </div>

    <!-- Instructions -->
    <div class="sidebar-section">
        <div class="sidebar-label">Instructions</div>
        <a href="instructions.php?tab=syllabus"
           class="nav-item <?= $activePage === 'syllabus' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Syllabus
        </a>
        <a href="instructions.php?tab=curriculum"
           class="nav-item <?= $activePage === 'curriculum' ? 'active' : '' ?>">
            <span class="nav-icon">📚</span> Curriculum
        </a>
        <a href="instructions.php?tab=lessons"
           class="nav-item <?= $activePage === 'lessons' ? 'active' : '' ?>">
            <span class="nav-icon">📝</span> Lessons
        </a>
    </div>

    <!-- User card & logout -->
    <div class="sidebar-bottom">
        <div class="user-card">
            <div class="user-avatar"><?= e($initials) ?></div>
            <div class="user-info">
                <strong title="<?= e($fullName) ?>"><?= e($fullName) ?></strong>
                <span><?= e($role) ?></span>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">⎋ Sign Out</a>
    </div>

</nav>
<!-- /sidebar -->


<!-- ====================================================
     MAIN CONTENT
     ==================================================== -->
<div class="main">

    <!-- Top bar -->
    <div class="topbar">
        <div class="topbar-left">
            <h2><?= e($title) ?></h2>
            <p><?= date('l, F j, Y') ?></p>
        </div>
        <div class="topbar-right">
            <span class="badge-pill badge-gold">AY 2025–2026</span>
            <span class="badge-pill badge-navy">2nd Semester</span>
        </div>
    </div>
    <!-- /topbar -->

    <!-- Page content -->
    <div class="content">

<?php
} // end renderHeader()


// ── renderFooter ─────────────────────────────────────────────────────────────

function renderFooter(): void
{
?>
    </div><!-- /.content -->
</div><!-- /.main -->


<!-- ====================================================
     GLOBAL JAVASCRIPT
     ==================================================== -->
<script>

    // ── Modal helpers ──────────────────────────────────────
    function openModal(id) {
        document.getElementById(id).classList.add('open');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('open');
    }

    // Close modal when clicking the dark overlay
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('open');
        }
    });


    // ── Tab switching ──────────────────────────────────────
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = this.closest('.tab-group');

            group.querySelectorAll('.tab-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            group.querySelectorAll('.tab-pane').forEach(function (p) {
                p.classList.remove('active');
            });

            this.classList.add('active');
            document.getElementById(this.dataset.tab).classList.add('active');

            history.replaceState(null, '', '?tab=' + this.dataset.tab);
        });
    });


    // ── Delete confirmation ────────────────────────────────
    function confirmDelete(url, name) {
        if (confirm('Delete "' + name + '"? This action cannot be undone.')) {
            window.location.href = url;
        }
    }


    // ── Live table search ──────────────────────────────────
    // Usage: tableSearch('inputId', 'tableId')
    function tableSearch(inputId, tableId) {
        var input = document.getElementById(inputId);
        if (!input) return;

        input.addEventListener('input', function () {
            var query = this.value.toLowerCase();
            document
                .querySelectorAll('#' + tableId + ' tbody tr')
                .forEach(function (row) {
                    row.style.display =
                        row.textContent.toLowerCase().includes(query) ? '' : 'none';
                });
        });
    }

</script>
</body>
</html>
<?php
} // end renderFooter()