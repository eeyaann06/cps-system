<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM schedules WHERE id=?")->execute([$id]);
    redirect('scheduling.php', 'Schedule deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['subject_code','subject_name','faculty_id','day_of_week','start_time','end_time','room','course','year_level','section','semester','school_year'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '') ?: null;
    if (!$data['subject_code'] || !$data['subject_name'] || !$data['day_of_week']) {
        $_SESSION['flash'] = ['msg'=>'Subject code, name, and day are required.','type'=>'error'];
    } else {
        if ($id) {
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $pdo->prepare("UPDATE schedules SET $set WHERE id=?")->execute([...array_values($data),$id]);
            redirect('scheduling.php','Schedule updated.');
        } else {
            $cols = implode(',', $fields);
            $ph   = implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO schedules ($cols) VALUES ($ph)")->execute(array_values($data));
            redirect('scheduling.php','Schedule added.');
        }
    }
}

$schedules = $pdo->query("
    SELECT s.*, COALESCE(f.first_name||' '||f.last_name, 'TBA') as faculty_name
    FROM schedules s
    LEFT JOIN faculty f ON s.faculty_id = f.id
    ORDER BY CASE s.day_of_week
        WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3
        WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END,
    s.start_time
")->fetchAll();

$facultyList = $pdo->query("SELECT id, first_name, last_name FROM faculty ORDER BY last_name")->fetchAll();

// Build timetable
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
$timetable = [];
foreach ($schedules as $sch) {
    $timetable[$sch['day_of_week']][] = $sch;
}

renderHeader('Scheduling', 'scheduling');
flash();
?>

<div class="toolbar">
    <input class="search-input" id="schSearch" type="text" placeholder="🔍 Search schedules...">
    <button class="btn btn-outline" id="btnList" onclick="showView('list')">📋 List View</button>
    <button class="btn btn-outline" id="btnGrid" onclick="showView('grid')">🗓️ Timetable View</button>
    <button class="btn btn-primary" onclick="openModal('addSchModal')">+ Add Schedule</button>
</div>

<!-- LIST VIEW -->
<div id="viewList">
<div class="card">
    <div class="card-header">
        <h3>🗓️ Class Schedules</h3>
        <span style="font-size:13px;color:var(--muted);"><?= count($schedules) ?> schedules</span>
    </div>
    <div class="tbl-wrap">
        <table id="schTable">
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Name</th>
                    <th>Faculty</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Course / Section</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($schedules)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="icon">🗓️</div><p>No schedules found.</p></div></td></tr>
            <?php else: foreach ($schedules as $sch): ?>
            <tr>
                <td><code style="font-size:12px;background:var(--cream);padding:2px 7px;border-radius:4px;"><?= e($sch['subject_code']) ?></code></td>
                <td><strong><?= e($sch['subject_name']) ?></strong></td>
                <td><?= e($sch['faculty_name']) ?></td>
                <td><span class="badge badge-navy" style="background:rgba(13,27,46,0.08);color:var(--navy);"><?= e($sch['day_of_week']) ?></span></td>
                <td style="font-size:12.5px;"><?= $sch['start_time'] ?> – <?= $sch['end_time'] ?></td>
                <td><?= e($sch['room']) ?></td>
                <td><?= e($sch['course']) ?> <span style="color:var(--muted);"><?= e($sch['year_level']) ?> <?= e($sch['section']) ?></span></td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-primary btn-sm" onclick='editSch(<?= json_encode($sch) ?>)'>✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('scheduling.php?action=delete&id=<?= $sch['id'] ?>', '<?= e($sch['subject_code']) ?>')">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- TIMETABLE VIEW -->
<div id="viewGrid" style="display:none;">
<div class="card">
    <div class="card-header"><h3>📆 Weekly Timetable</h3></div>
    <div class="card-body" style="padding:16px; overflow-x:auto;">
        <table class="timetable">
            <thead>
                <tr>
                    <th style="width:90px;">Time Slot</th>
                    <?php foreach ($days as $day): ?>
                    <th><?= $day ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php
            $timeSlots = ['07:00–09:00','09:00–10:30','10:30–12:00','13:00–14:30','14:30–16:00','16:00–17:30'];
            foreach ($timeSlots as $slot):
                [$slotStart] = explode('–', $slot);
            ?>
            <tr>
                <td style="background:var(--cream);font-size:11px;font-weight:600;color:var(--muted);text-align:center;"><?= $slot ?></td>
                <?php foreach ($days as $day):
                    $daySched = array_filter($timetable[$day] ?? [], function($s) use ($slotStart) {
                        return $s['start_time'] >= $slotStart && $s['start_time'] < date('H:i', strtotime($slotStart)+5400);
                    });
                ?>
                <td>
                    <?php foreach ($daySched as $s): ?>
                    <div class="slot-card">
                        <strong><?= e($s['subject_code']) ?></strong>
                        <span><?= e($s['subject_name']) ?></span><br>
                        <span style="font-size:10px;">🚪 <?= e($s['room']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addSchModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>+ Add Class Schedule</h3>
            <button class="modal-close" onclick="closeModal('addSchModal')">✕</button>
        </div>
        <form method="POST" action="scheduling.php">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Subject Code *</label>
                    <input type="text" name="subject_code" placeholder="CS301" required>
                </div>
                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" required>
                </div>
                <div class="form-group">
                    <label>Faculty</label>
                    <select name="faculty_id">
                        <option value="">— TBA —</option>
                        <?php foreach ($facultyList as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= e($f['first_name'].' '.$f['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Day of Week *</label>
                    <select name="day_of_week">
                        <?php foreach ($days as $d): ?>
                        <option><?= $d ?></option>
                        <?php endforeach; ?>
                        <option>Sunday</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time">
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time">
                </div>
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" placeholder="Room 301">
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" name="course" placeholder="BS Computer Science">
                </div>
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year_level">
                        <option>1st Year</option><option>2nd Year</option>
                        <option>3rd Year</option><option>4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" placeholder="CS3A">
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester">
                        <option>1st Semester</option><option>2nd Semester</option><option>Summer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>School Year</label>
                    <input type="text" name="school_year" placeholder="2024-2025" value="2024-2025">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addSchModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Schedule</button>
        </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editSchModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3>✏️ Edit Schedule</h3>
            <button class="modal-close" onclick="closeModal('editSchModal')">✕</button>
        </div>
        <form method="POST" id="editSchForm">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>Subject Code *</label>
                    <input type="text" name="subject_code" id="es_code" required>
                </div>
                <div class="form-group">
                    <label>Subject Name *</label>
                    <input type="text" name="subject_name" id="es_name" required>
                </div>
                <div class="form-group">
                    <label>Faculty</label>
                    <select name="faculty_id" id="es_fac">
                        <option value="">— TBA —</option>
                        <?php foreach ($facultyList as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= e($f['first_name'].' '.$f['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Day of Week *</label>
                    <select name="day_of_week" id="es_day">
                        <?php foreach ($days as $d): ?>
                        <option><?= $d ?></option>
                        <?php endforeach; ?>
                        <option>Sunday</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="start_time" id="es_st">
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" id="es_et">
                </div>
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" id="es_room">
                </div>
                <div class="form-group">
                    <label>Course</label>
                    <input type="text" name="course" id="es_course">
                </div>
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year_level" id="es_yl">
                        <option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" id="es_sec">
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" id="es_sem">
                        <option>1st Semester</option><option>2nd Semester</option><option>Summer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>School Year</label>
                    <input type="text" name="school_year" id="es_sy">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editSchModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Schedule</button>
        </div>
        </form>
    </div>
</div>

<script>
tableSearch('schSearch','schTable');

function showView(v) {
    document.getElementById('viewList').style.display = v==='list' ? '' : 'none';
    document.getElementById('viewGrid').style.display = v==='grid' ? '' : 'none';
    document.getElementById('btnList').className = 'btn btn-' + (v==='list'?'primary':'outline');
    document.getElementById('btnGrid').className = 'btn btn-' + (v==='grid'?'primary':'outline');
}
showView('list');

function editSch(s) {
    document.getElementById('es_code').value  = s.subject_code;
    document.getElementById('es_name').value  = s.subject_name;
    document.getElementById('es_st').value    = s.start_time||'';
    document.getElementById('es_et').value    = s.end_time||'';
    document.getElementById('es_room').value  = s.room||'';
    document.getElementById('es_course').value= s.course||'';
    document.getElementById('es_sec').value   = s.section||'';
    document.getElementById('es_sy').value    = s.school_year||'';
    setSelectVal('es_day', s.day_of_week);
    setSelectVal('es_yl', s.year_level);
    setSelectVal('es_sem', s.semester);
    const fac = document.getElementById('es_fac');
    if (fac && s.faculty_id) fac.value = s.faculty_id;
    document.getElementById('editSchForm').action = 'scheduling.php?action=edit&id=' + s.id;
    openModal('editSchModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) { if (opt.value===val||opt.text===val) { sel.value=opt.value; break; } }
}
</script>

<?php renderFooter(); ?>
