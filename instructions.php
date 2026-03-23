<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo    = getDB();
$tab    = $_GET['tab'] ?? 'syllabus';
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);
$type   = $_GET['type'] ?? 'syllabus';

// ── DELETE ──
if ($action === 'delete' && $id) {
    $table = in_array($type, ['syllabus','curriculum','lessons']) ? $type : 'syllabus';
    $pdo->prepare("DELETE FROM $table WHERE id=?")->execute([$id]);
    redirect("instructions.php?tab=$type", ucfirst($type) . ' deleted.');
}

// ── SAVE/UPDATE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postType = $_POST['_type'] ?? 'syllabus';
    $pid = intval($_POST['_id'] ?? 0);

    if ($postType === 'syllabus') {
        $fields = ['subject_code','subject_name','department','course','year_level','semester','units','description','objectives','grading_system','faculty_id'];
        $data = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;
        if (!$data['subject_code'] || !$data['subject_name']) {
            $_SESSION['flash'] = ['msg'=>'Subject code and name required.','type'=>'error'];
        } else {
            if ($pid) {
                $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
                $pdo->prepare("UPDATE syllabus SET $set WHERE id=?")->execute([...array_values($data),$pid]);
                redirect('instructions.php?tab=syllabus','Syllabus updated.');
            } else {
                $cols = implode(',', $fields);
                $ph   = implode(',', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO syllabus ($cols) VALUES ($ph)")->execute(array_values($data));
                redirect('instructions.php?tab=syllabus','Syllabus added.');
            }
        }
    } elseif ($postType === 'curriculum') {
        $fields = ['curriculum_name','course','department','effective_year','description','total_units','status'];
        $data = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;
        if (!$data['curriculum_name'] || !$data['course']) {
            $_SESSION['flash'] = ['msg'=>'Curriculum name and course required.','type'=>'error'];
        } else {
            if ($pid) {
                $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
                $pdo->prepare("UPDATE curriculum SET $set WHERE id=?")->execute([...array_values($data),$pid]);
                redirect('instructions.php?tab=curriculum','Curriculum updated.');
            } else {
                $cols = implode(',', $fields);
                $ph   = implode(',', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO curriculum ($cols) VALUES ($ph)")->execute(array_values($data));
                redirect('instructions.php?tab=curriculum','Curriculum added.');
            }
        }
    } elseif ($postType === 'lessons') {
        $fields = ['title','subject_code','topic','content','objectives','materials','duration','lesson_type','week_number','faculty_id'];
        $data = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;
        if (!$data['title']) {
            $_SESSION['flash'] = ['msg'=>'Lesson title required.','type'=>'error'];
        } else {
            if ($pid) {
                $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
                $pdo->prepare("UPDATE lessons SET $set WHERE id=?")->execute([...array_values($data),$pid]);
                redirect('instructions.php?tab=lessons','Lesson updated.');
            } else {
                $cols = implode(',', $fields);
                $ph   = implode(',', array_fill(0, count($fields), '?'));
                $pdo->prepare("INSERT INTO lessons ($cols) VALUES ($ph)")->execute(array_values($data));
                redirect('instructions.php?tab=lessons','Lesson added.');
            }
        }
    }
}

$syllabi    = $pdo->query("SELECT s.*, COALESCE(f.first_name||' '||f.last_name,'—') as faculty_name FROM syllabus s LEFT JOIN faculty f ON s.faculty_id=f.id ORDER BY s.subject_code")->fetchAll();
$curricula  = $pdo->query("SELECT * FROM curriculum ORDER BY effective_year DESC, curriculum_name")->fetchAll();
$lessons    = $pdo->query("SELECT l.*, COALESCE(f.first_name||' '||f.last_name,'—') as faculty_name FROM lessons l LEFT JOIN faculty f ON l.faculty_id=f.id ORDER BY l.week_number, l.title")->fetchAll();
$facultyList= $pdo->query("SELECT id, first_name, last_name FROM faculty ORDER BY last_name")->fetchAll();

$activePages = ['syllabus' => 'syllabus', 'curriculum' => 'curriculum', 'lessons' => 'lessons'];
$activePage  = $activePages[$tab] ?? 'syllabus';

renderHeader('Instructions', $activePage);
flash();
?>

<div class="tab-group">
<div class="tabs">
    <button class="tab-btn <?= $tab==='syllabus'?'active':'' ?>" data-tab="tab-syllabus">📋 Syllabus</button>
    <button class="tab-btn <?= $tab==='curriculum'?'active':'' ?>" data-tab="tab-curriculum">📚 Curriculum</button>
    <button class="tab-btn <?= $tab==='lessons'?'active':'' ?>" data-tab="tab-lessons">📝 Lessons</button>
</div>

<!-- ═══════════════ SYLLABUS TAB ═══════════════ -->
<div id="tab-syllabus" class="tab-pane <?= $tab==='syllabus'?'active':'' ?>">
    <div class="toolbar">
        <input class="search-input" id="sylSearch" type="text" placeholder="🔍 Search syllabi...">
        <button class="btn btn-primary" onclick="openModal('addSylModal')">+ Add Syllabus</button>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>📋 Subject Syllabi</h3>
            <span style="font-size:13px;color:var(--muted);"><?= count($syllabi) ?> syllabi</span>
        </div>
        <div class="tbl-wrap">
            <table id="sylTable">
                <thead>
                    <tr><th>Code</th><th>Subject Name</th><th>Course / Year</th><th>Units</th><th>Semester</th><th>Faculty</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($syllabi)): ?>
                <tr><td colspan="7"><div class="empty-state"><div class="icon">📋</div><p>No syllabi yet.</p></div></td></tr>
                <?php else: foreach ($syllabi as $s): ?>
                <tr>
                    <td><code style="font-size:12px;background:var(--cream);padding:2px 7px;border-radius:4px;"><?= e($s['subject_code']) ?></code></td>
                    <td><strong><?= e($s['subject_name']) ?></strong></td>
                    <td><?= e($s['course']) ?> · <?= e($s['year_level']) ?></td>
                    <td><strong><?= $s['units'] ?></strong> units</td>
                    <td><?= e($s['semester']) ?></td>
                    <td><?= e($s['faculty_name']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-outline btn-sm" onclick='viewSyl(<?= json_encode($s) ?>)'>👁</button>
                            <button class="btn btn-primary btn-sm" onclick='editSyl(<?= json_encode($s) ?>)'>✏️</button>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete('instructions.php?action=delete&type=syllabus&id=<?= $s['id'] ?>', '<?= e($s['subject_code']) ?>')">🗑</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ═══════════════ CURRICULUM TAB ═══════════════ -->
<div id="tab-curriculum" class="tab-pane <?= $tab==='curriculum'?'active':'' ?>">
    <div class="toolbar">
        <input class="search-input" id="curSearch" type="text" placeholder="🔍 Search curricula...">
        <button class="btn btn-primary" onclick="openModal('addCurModal')">+ Add Curriculum</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
        <?php if (empty($curricula)): ?>
        <div class="card"><div class="card-body"><div class="empty-state"><div class="icon">📚</div><p>No curricula yet.</p></div></div></div>
        <?php else: foreach ($curricula as $c): ?>
        <div class="card" style="border-top:4px solid var(--gold);">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
                    <div>
                        <h3 style="font-family:'Playfair Display',serif;font-size:15px;color:var(--navy);line-height:1.3;"><?= e($c['curriculum_name']) ?></h3>
                        <p style="font-size:12px;color:var(--muted);margin-top:3px;"><?= e($c['course']) ?></p>
                    </div>
                    <span class="badge <?= $c['status']==='Active'?'badge-success':'badge-warning' ?>"><?= e($c['status']) ?></span>
                </div>
                <?php if ($c['description']): ?>
                <p style="font-size:12.5px;color:var(--text);line-height:1.5;margin-bottom:12px;"><?= e(mb_strimwidth($c['description'],0,120,'...')) ?></p>
                <?php endif; ?>
                <div style="display:flex;gap:16px;margin-bottom:12px;">
                    <div><span style="font-size:11px;color:var(--muted);">Department</span><p style="font-size:12.5px;font-weight:500;"><?= e($c['department']) ?></p></div>
                    <div><span style="font-size:11px;color:var(--muted);">Effective</span><p style="font-size:12.5px;font-weight:500;"><?= e($c['effective_year']) ?></p></div>
                    <div><span style="font-size:11px;color:var(--muted);">Total Units</span><p style="font-size:12.5px;font-weight:600;color:var(--navy);"><?= $c['total_units'] ?></p></div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button class="btn btn-outline btn-sm" onclick='viewCur(<?= json_encode($c) ?>)'>👁 View</button>
                    <button class="btn btn-primary btn-sm" onclick='editCur(<?= json_encode($c) ?>)'>✏️ Edit</button>
                    <button class="btn btn-danger btn-sm" onclick="confirmDelete('instructions.php?action=delete&type=curriculum&id=<?= $c['id'] ?>', '<?= e($c['curriculum_name']) ?>')">🗑</button>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<!-- ═══════════════ LESSONS TAB ═══════════════ -->
<div id="tab-lessons" class="tab-pane <?= $tab==='lessons'?'active':'' ?>">
    <div class="toolbar">
        <input class="search-input" id="lesSearch" type="text" placeholder="🔍 Search lessons...">
        <select id="filterLesType" onchange="filterLessons()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
            <option value="">All Types</option>
            <option>Lecture</option><option>Laboratory</option>
            <option>Discussion</option><option>Workshop</option>
        </select>
        <button class="btn btn-primary" onclick="openModal('addLesModal')">+ Add Lesson</button>
    </div>
    <div class="card">
        <div class="card-header">
            <h3>📝 Lesson Plans</h3>
            <span style="font-size:13px;color:var(--muted);"><?= count($lessons) ?> lessons</span>
        </div>
        <div class="tbl-wrap">
            <table id="lesTable">
                <thead>
                    <tr><th>Week</th><th>Title</th><th>Subject</th><th>Topic</th><th>Duration</th><th>Type</th><th>Faculty</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($lessons)): ?>
                <tr><td colspan="8"><div class="empty-state"><div class="icon">📝</div><p>No lessons yet.</p></div></td></tr>
                <?php else: foreach ($lessons as $l): ?>
                <tr data-ltype="<?= e($l['lesson_type']) ?>">
                    <td style="text-align:center;"><span style="background:var(--navy);color:white;padding:3px 8px;border-radius:20px;font-size:11px;font-weight:600;">Wk <?= $l['week_number'] ?: '—' ?></span></td>
                    <td><strong><?= e($l['title']) ?></strong></td>
                    <td><code style="font-size:11.5px;background:var(--cream);padding:2px 6px;border-radius:3px;"><?= e($l['subject_code']) ?></code></td>
                    <td style="font-size:12.5px;"><?= e(mb_strimwidth($l['topic']??'',0,40,'...')) ?></td>
                    <td><?= $l['duration'] ? $l['duration'].' min' : '—' ?></td>
                    <td><span class="badge badge-info"><?= e($l['lesson_type']) ?></span></td>
                    <td style="font-size:12px;"><?= e($l['faculty_name']) ?></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-outline btn-sm" onclick='viewLes(<?= json_encode($l) ?>)'>👁</button>
                            <button class="btn btn-primary btn-sm" onclick='editLes(<?= json_encode($l) ?>)'>✏️</button>
                            <button class="btn btn-danger btn-sm" onclick="confirmDelete('instructions.php?action=delete&type=lessons&id=<?= $l['id'] ?>', '<?= e($l['title']) ?>')">🗑</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- .tab-group -->

<!-- ══════════ SYLLABUS MODALS ══════════ -->
<div class="modal-overlay" id="addSylModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>+ Add Syllabus</h3><button class="modal-close" onclick="closeModal('addSylModal')">✕</button></div>
        <form method="POST" action="instructions.php">
        <input type="hidden" name="_type" value="syllabus">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group"><label>Subject Code *</label><input type="text" name="subject_code" required></div>
                <div class="form-group"><label>Subject Name *</label><input type="text" name="subject_name" required></div>
                <div class="form-group"><label>Department</label><input type="text" name="department"></div>
                <div class="form-group"><label>Course</label><input type="text" name="course"></div>
                <div class="form-group"><label>Year Level</label>
                    <select name="year_level"><option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option></select>
                </div>
                <div class="form-group"><label>Semester</label>
                    <select name="semester"><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select>
                </div>
                <div class="form-group"><label>Units</label><input type="number" name="units" min="1" max="9" value="3"></div>
                <div class="form-group"><label>Faculty</label>
                    <select name="faculty_id"><option value="">— Assign Later —</option>
                    <?php foreach ($facultyList as $f): ?><option value="<?= $f['id'] ?>"><?= e($f['first_name'].' '.$f['last_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full"><label>Description</label><textarea name="description"></textarea></div>
                <div class="form-group full"><label>Objectives (one per line)</label><textarea name="objectives" rows="4"></textarea></div>
                <div class="form-group full"><label>Grading System</label><input type="text" name="grading_system" placeholder="Midterm 40%, Finals 40%, Activities 20%"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addSylModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Syllabus</button>
        </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editSylModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>✏️ Edit Syllabus</h3><button class="modal-close" onclick="closeModal('editSylModal')">✕</button></div>
        <form method="POST" id="editSylForm">
        <input type="hidden" name="_type" value="syllabus">
        <input type="hidden" name="_id" id="esyl_id">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group"><label>Subject Code *</label><input type="text" name="subject_code" id="esyl_code" required></div>
                <div class="form-group"><label>Subject Name *</label><input type="text" name="subject_name" id="esyl_name" required></div>
                <div class="form-group"><label>Department</label><input type="text" name="department" id="esyl_dept"></div>
                <div class="form-group"><label>Course</label><input type="text" name="course" id="esyl_course"></div>
                <div class="form-group"><label>Year Level</label>
                    <select name="year_level" id="esyl_yl"><option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option></select>
                </div>
                <div class="form-group"><label>Semester</label>
                    <select name="semester" id="esyl_sem"><option>1st Semester</option><option>2nd Semester</option><option>Summer</option></select>
                </div>
                <div class="form-group"><label>Units</label><input type="number" name="units" id="esyl_units" min="1" max="9"></div>
                <div class="form-group"><label>Faculty</label>
                    <select name="faculty_id" id="esyl_fac"><option value="">— Assign Later —</option>
                    <?php foreach ($facultyList as $f): ?><option value="<?= $f['id'] ?>"><?= e($f['first_name'].' '.$f['last_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full"><label>Description</label><textarea name="description" id="esyl_desc"></textarea></div>
                <div class="form-group full"><label>Objectives</label><textarea name="objectives" id="esyl_obj" rows="4"></textarea></div>
                <div class="form-group full"><label>Grading System</label><input type="text" name="grading_system" id="esyl_grade"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editSylModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Syllabus</button>
        </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="viewSylModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>📋 Syllabus Details</h3><button class="modal-close" onclick="closeModal('viewSylModal')">✕</button></div>
        <div class="modal-body" id="viewSylContent"></div>
    </div>
</div>

<!-- ══════════ CURRICULUM MODALS ══════════ -->
<div class="modal-overlay" id="addCurModal">
    <div class="modal">
        <div class="modal-header"><h3>+ Add Curriculum</h3><button class="modal-close" onclick="closeModal('addCurModal')">✕</button></div>
        <form method="POST" action="instructions.php">
        <input type="hidden" name="_type" value="curriculum">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full"><label>Curriculum Name *</label><input type="text" name="curriculum_name" required></div>
                <div class="form-group"><label>Course *</label><input type="text" name="course" required></div>
                <div class="form-group"><label>Department</label><input type="text" name="department"></div>
                <div class="form-group"><label>Effective Year</label><input type="text" name="effective_year" placeholder="2022"></div>
                <div class="form-group"><label>Total Units</label><input type="number" name="total_units" min="1"></div>
                <div class="form-group"><label>Status</label>
                    <select name="status"><option>Active</option><option>Inactive</option><option>Pending</option></select>
                </div>
                <div class="form-group full"><label>Description</label><textarea name="description"></textarea></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addCurModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Curriculum</button>
        </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editCurModal">
    <div class="modal">
        <div class="modal-header"><h3>✏️ Edit Curriculum</h3><button class="modal-close" onclick="closeModal('editCurModal')">✕</button></div>
        <form method="POST" id="editCurForm">
        <input type="hidden" name="_type" value="curriculum">
        <input type="hidden" name="_id" id="ecur_id">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full"><label>Curriculum Name *</label><input type="text" name="curriculum_name" id="ecur_name" required></div>
                <div class="form-group"><label>Course *</label><input type="text" name="course" id="ecur_course" required></div>
                <div class="form-group"><label>Department</label><input type="text" name="department" id="ecur_dept"></div>
                <div class="form-group"><label>Effective Year</label><input type="text" name="effective_year" id="ecur_year"></div>
                <div class="form-group"><label>Total Units</label><input type="number" name="total_units" id="ecur_units" min="1"></div>
                <div class="form-group"><label>Status</label>
                    <select name="status" id="ecur_status"><option>Active</option><option>Inactive</option><option>Pending</option></select>
                </div>
                <div class="form-group full"><label>Description</label><textarea name="description" id="ecur_desc"></textarea></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editCurModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Curriculum</button>
        </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="viewCurModal">
    <div class="modal">
        <div class="modal-header"><h3>📚 Curriculum Details</h3><button class="modal-close" onclick="closeModal('viewCurModal')">✕</button></div>
        <div class="modal-body" id="viewCurContent"></div>
    </div>
</div>

<!-- ══════════ LESSONS MODALS ══════════ -->
<div class="modal-overlay" id="addLesModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>+ Add Lesson Plan</h3><button class="modal-close" onclick="closeModal('addLesModal')">✕</button></div>
        <form method="POST" action="instructions.php">
        <input type="hidden" name="_type" value="lessons">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full"><label>Lesson Title *</label><input type="text" name="title" required></div>
                <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code"></div>
                <div class="form-group"><label>Lesson Type</label>
                    <select name="lesson_type"><option>Lecture</option><option>Laboratory</option><option>Discussion</option><option>Workshop</option><option>Seminar</option></select>
                </div>
                <div class="form-group"><label>Week Number</label><input type="number" name="week_number" min="1" max="18"></div>
                <div class="form-group"><label>Duration (mins)</label><input type="number" name="duration" min="1"></div>
                <div class="form-group"><label>Faculty</label>
                    <select name="faculty_id"><option value="">— Assign Later —</option>
                    <?php foreach ($facultyList as $f): ?><option value="<?= $f['id'] ?>"><?= e($f['first_name'].' '.$f['last_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full"><label>Topic</label><input type="text" name="topic"></div>
                <div class="form-group full"><label>Content / Lesson Body</label><textarea name="content" rows="4"></textarea></div>
                <div class="form-group full"><label>Learning Objectives</label><textarea name="objectives" rows="3"></textarea></div>
                <div class="form-group full"><label>Materials / Resources</label><input type="text" name="materials"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addLesModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Lesson</button>
        </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editLesModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>✏️ Edit Lesson</h3><button class="modal-close" onclick="closeModal('editLesModal')">✕</button></div>
        <form method="POST" id="editLesForm">
        <input type="hidden" name="_type" value="lessons">
        <input type="hidden" name="_id" id="eles_id">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full"><label>Lesson Title *</label><input type="text" name="title" id="eles_title" required></div>
                <div class="form-group"><label>Subject Code</label><input type="text" name="subject_code" id="eles_code"></div>
                <div class="form-group"><label>Lesson Type</label>
                    <select name="lesson_type" id="eles_type"><option>Lecture</option><option>Laboratory</option><option>Discussion</option><option>Workshop</option><option>Seminar</option></select>
                </div>
                <div class="form-group"><label>Week Number</label><input type="number" name="week_number" id="eles_week" min="1" max="18"></div>
                <div class="form-group"><label>Duration (mins)</label><input type="number" name="duration" id="eles_dur" min="1"></div>
                <div class="form-group"><label>Faculty</label>
                    <select name="faculty_id" id="eles_fac"><option value="">— Assign Later —</option>
                    <?php foreach ($facultyList as $f): ?><option value="<?= $f['id'] ?>"><?= e($f['first_name'].' '.$f['last_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full"><label>Topic</label><input type="text" name="topic" id="eles_topic"></div>
                <div class="form-group full"><label>Content</label><textarea name="content" id="eles_content" rows="4"></textarea></div>
                <div class="form-group full"><label>Learning Objectives</label><textarea name="objectives" id="eles_obj" rows="3"></textarea></div>
                <div class="form-group full"><label>Materials</label><input type="text" name="materials" id="eles_mat"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editLesModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Lesson</button>
        </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="viewLesModal">
    <div class="modal modal-lg">
        <div class="modal-header"><h3>📝 Lesson Plan Details</h3><button class="modal-close" onclick="closeModal('viewLesModal')">✕</button></div>
        <div class="modal-body" id="viewLesContent"></div>
    </div>
</div>

<script>
tableSearch('sylSearch','sylTable');
tableSearch('lesSearch','lesTable');

function filterLessons() {
    const val = document.getElementById('filterLesType').value.toLowerCase();
    document.querySelectorAll('#lesTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.ltype?.toLowerCase() === val) ? '' : 'none';
    });
}

// ── Syllabus ──
function editSyl(s) {
    document.getElementById('esyl_id').value    = s.id;
    document.getElementById('esyl_code').value  = s.subject_code;
    document.getElementById('esyl_name').value  = s.subject_name;
    document.getElementById('esyl_dept').value  = s.department||'';
    document.getElementById('esyl_course').value= s.course||'';
    document.getElementById('esyl_units').value = s.units||3;
    document.getElementById('esyl_desc').value  = s.description||'';
    document.getElementById('esyl_obj').value   = s.objectives||'';
    document.getElementById('esyl_grade').value = s.grading_system||'';
    setSelectVal('esyl_yl', s.year_level);
    setSelectVal('esyl_sem', s.semester);
    if (s.faculty_id) document.getElementById('esyl_fac').value = s.faculty_id;
    document.getElementById('editSylForm').action = 'instructions.php?tab=syllabus';
    openModal('editSylModal');
}
function viewSyl(s) {
    document.getElementById('viewSylContent').innerHTML = `
        <div style="background:var(--navy);padding:18px 20px;border-radius:10px;margin-bottom:18px;">
            <code style="color:var(--gold);font-size:13px;">${s.subject_code}</code>
            <h2 style="font-family:'Playfair Display',serif;color:white;margin-top:6px;">${s.subject_name}</h2>
            <p style="color:rgba(255,255,255,0.55);font-size:12px;margin-top:4px;">${s.course||''} · ${s.year_level||''} · ${s.semester||''}</p>
        </div>
        ${s.description?`<p style="font-size:13.5px;line-height:1.6;margin-bottom:16px;">${s.description}</p>`:''}
        ${s.objectives?`<div style="margin-bottom:16px;"><p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:var(--muted);margin-bottom:6px;">Objectives</p><pre style="font-size:13px;white-space:pre-wrap;font-family:'DM Sans',sans-serif;line-height:1.6;">${s.objectives}</pre></div>`:''}
        <div class="detail-grid">
            <div class="detail-item"><label>Units</label><p>${s.units||'—'}</p></div>
            <div class="detail-item"><label>Department</label><p>${s.department||'—'}</p></div>
            <div class="detail-item"><label>Faculty</label><p>${s.faculty_name||'—'}</p></div>
            <div class="detail-item"><label>Grading</label><p>${s.grading_system||'—'}</p></div>
        </div>
    `;
    openModal('viewSylModal');
}

// ── Curriculum ──
function editCur(c) {
    document.getElementById('ecur_id').value    = c.id;
    document.getElementById('ecur_name').value  = c.curriculum_name;
    document.getElementById('ecur_course').value= c.course;
    document.getElementById('ecur_dept').value  = c.department||'';
    document.getElementById('ecur_year').value  = c.effective_year||'';
    document.getElementById('ecur_units').value = c.total_units||'';
    document.getElementById('ecur_desc').value  = c.description||'';
    setSelectVal('ecur_status', c.status);
    document.getElementById('editCurForm').action = 'instructions.php?tab=curriculum';
    openModal('editCurModal');
}
function viewCur(c) {
    document.getElementById('viewCurContent').innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
            <div><h2 style="font-family:'Playfair Display',serif;font-size:19px;color:var(--navy);">${c.curriculum_name}</h2>
            <p style="color:var(--muted);font-size:13px;margin-top:3px;">${c.course}</p></div>
            <span class="badge ${c.status==='Active'?'badge-success':'badge-warning'}">${c.status}</span>
        </div>
        ${c.description?`<p style="font-size:13.5px;line-height:1.6;margin-bottom:16px;">${c.description}</p>`:''}
        <div class="detail-grid">
            <div class="detail-item"><label>Department</label><p>${c.department||'—'}</p></div>
            <div class="detail-item"><label>Effective Year</label><p>${c.effective_year||'—'}</p></div>
            <div class="detail-item"><label>Total Units</label><p><strong style="font-size:18px;color:var(--navy);">${c.total_units||'—'}</strong></p></div>
        </div>
    `;
    openModal('viewCurModal');
}

// ── Lessons ──
function editLes(l) {
    document.getElementById('eles_id').value     = l.id;
    document.getElementById('eles_title').value  = l.title;
    document.getElementById('eles_code').value   = l.subject_code||'';
    document.getElementById('eles_week').value   = l.week_number||'';
    document.getElementById('eles_dur').value    = l.duration||'';
    document.getElementById('eles_topic').value  = l.topic||'';
    document.getElementById('eles_content').value= l.content||'';
    document.getElementById('eles_obj').value    = l.objectives||'';
    document.getElementById('eles_mat').value    = l.materials||'';
    setSelectVal('eles_type', l.lesson_type);
    if (l.faculty_id) document.getElementById('eles_fac').value = l.faculty_id;
    document.getElementById('editLesForm').action = 'instructions.php?tab=lessons';
    openModal('editLesModal');
}
function viewLes(l) {
    document.getElementById('viewLesContent').innerHTML = `
        <div style="display:flex;gap:12px;align-items:center;margin-bottom:18px;">
            <div style="background:var(--navy);color:var(--gold);padding:8px 12px;border-radius:8px;text-align:center;min-width:50px;">
                <p style="font-size:9px;text-transform:uppercase;opacity:.6;">Week</p>
                <strong style="font-size:20px;font-family:'Playfair Display',serif;">${l.week_number||'?'}</strong>
            </div>
            <div>
                <span class="badge badge-info">${l.lesson_type}</span>
                <h2 style="font-family:'Playfair Display',serif;font-size:18px;color:var(--navy);margin-top:5px;">${l.title}</h2>
                <p style="color:var(--muted);font-size:12.5px;">${l.subject_code||''} · ${l.faculty_name||''} · ${l.duration?l.duration+' min':''}</p>
            </div>
        </div>
        ${l.topic?`<div style="margin-bottom:14px;"><p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:4px;">Topic</p><p style="font-size:13.5px;">${l.topic}</p></div>`:''}
        ${l.content?`<div style="margin-bottom:14px;"><p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:4px;">Content</p><p style="font-size:13.5px;line-height:1.7;">${l.content}</p></div>`:''}
        ${l.objectives?`<div style="margin-bottom:14px;"><p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:4px;">Objectives</p><pre style="font-size:13px;white-space:pre-wrap;font-family:'DM Sans',sans-serif;line-height:1.6;">${l.objectives}</pre></div>`:''}
        ${l.materials?`<div><p style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:4px;">Materials</p><p style="font-size:13.5px;">${l.materials}</p></div>`:''}
    `;
    openModal('viewLesModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) { if (opt.value===val||opt.text===val) { sel.value=opt.value; break; } }
}
</script>

<?php renderFooter(); ?>
