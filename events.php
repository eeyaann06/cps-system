<?php
require_once 'config.php';
require_once 'includes/layout.php';
requireLogin();

$pdo    = getDB();
$action = $_GET['action'] ?? 'list';
$id     = intval($_GET['id'] ?? 0);

if ($action === 'delete' && $id) {
    $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$id]);
    redirect('events.php', 'Event deleted.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = ['title','description','event_date','event_time','end_time','location','category','status','organizer','max_participants'];
    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');
    if (!$data['title'] || !$data['event_date']) {
        $_SESSION['flash'] = ['msg' => 'Title and event date are required.', 'type' => 'error'];
    } else {
        if ($id) {
            $set = implode(', ', array_map(fn($f) => "$f=?", $fields));
            $pdo->prepare("UPDATE events SET $set WHERE id=?")->execute([...array_values($data), $id]);
            redirect('events.php', 'Event updated.');
        } else {
            $cols = implode(',', $fields);
            $ph   = implode(',', array_fill(0, count($fields), '?'));
            $pdo->prepare("INSERT INTO events ($cols) VALUES ($ph)")->execute(array_values($data));
            redirect('events.php', 'Event added.');
        }
    }
}

$events   = $pdo->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();
$upcoming = array_filter($events, fn($e) => $e['status'] === 'Upcoming');
$past     = array_filter($events, fn($e) => $e['status'] === 'Completed');

renderHeader('Events', 'events');
flash();
?>

<div class="toolbar">
    <input class="search-input" id="evtSearch" type="text" placeholder="🔍 Search events...">
    <select id="filterCat" onchange="filterEvents()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
        <option value="">All Categories</option>
        <option>Academic</option><option>Sports</option><option>Special Event</option>
        <option>Cultural</option><option>General</option>
    </select>
    <button class="btn btn-primary" onclick="openModal('addEvtModal')">+ Add Event</button>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;">
    <?php
    $cats = ['Academic','Sports','Special Event','General'];
    $icons = ['🎓','🏆','🎉','📌'];
    $i = 0;
    foreach ($cats as $cat):
        $cnt = count(array_filter($events, fn($e) => $e['category'] === $cat));
    ?>
    <div class="stat-card">
        <div class="stat-icon <?= ['navy','green','gold','blue'][$i] ?>"><?= $icons[$i] ?></div>
        <div class="stat-info"><strong><?= $cnt ?></strong><span><?= $cat ?></span></div>
    </div>
    <?php $i++; endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3>📅 Events Calendar</h3>
        <span style="font-size:13px;color:var(--muted);"><?= count($events) ?> total events</span>
    </div>
    <div class="tbl-wrap">
        <table id="evtTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Event</th>
                    <th>Time</th>
                    <th>Location</th>
                    <th>Category</th>
                    <th>Organizer</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($events)): ?>
            <tr><td colspan="8"><div class="empty-state"><div class="icon">📅</div><p>No events yet.</p></div></td></tr>
            <?php else: foreach ($events as $ev): ?>
            <tr data-cat="<?= e($ev['category']) ?>">
                <td>
                    <div style="background:var(--navy);color:var(--gold);padding:6px 10px;border-radius:8px;text-align:center;display:inline-block;min-width:44px;">
                        <strong style="font-size:16px;font-family:'Playfair Display',serif;"><?= date('d', strtotime($ev['event_date'])) ?></strong>
                        <p style="font-size:9px;text-transform:uppercase;"><?= date('M Y', strtotime($ev['event_date'])) ?></p>
                    </div>
                </td>
                <td>
                    <strong><?= e($ev['title']) ?></strong>
                    <?php if ($ev['description']): ?>
                    <p style="font-size:11.5px;color:var(--muted);margin-top:2px;"><?= e(mb_strimwidth($ev['description'],0,60,'...')) ?></p>
                    <?php endif; ?>
                </td>
                <td style="font-size:12.5px;"><?= $ev['event_time'] ?><?= $ev['end_time'] ? ' – '.$ev['end_time'] : '' ?></td>
                <td><?= e($ev['location']) ?></td>
                <td><span class="badge badge-info"><?= e($ev['category']) ?></span></td>
                <td style="font-size:12.5px;"><?= e($ev['organizer']) ?></td>
                <td>
                    <span class="badge <?= match($ev['status']) {
                        'Upcoming'  => 'badge-success',
                        'Ongoing'   => 'badge-warning',
                        'Completed' => 'badge-info',
                        'Cancelled' => 'badge-danger',
                        default     => 'badge-default'
                    } ?>"><?= e($ev['status']) ?></span>
                </td>
                <td>
                    <div style="display:flex;gap:6px;">
                        <button class="btn btn-outline btn-sm" onclick='viewEvent(<?= json_encode($ev) ?>)'>👁</button>
                        <button class="btn btn-primary btn-sm" onclick='editEvent(<?= json_encode($ev) ?>)'>✏️</button>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete('events.php?action=delete&id=<?= $ev['id'] ?>', '<?= e($ev['title']) ?>')">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addEvtModal">
    <div class="modal">
        <div class="modal-header">
            <h3>+ Add New Event</h3>
            <button class="modal-close" onclick="closeModal('addEvtModal')">✕</button>
        </div>
        <form method="POST" action="events.php">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Event Title *</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Event Date *</label>
                    <input type="date" name="event_date" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location">
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="event_time">
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option>Academic</option><option>Sports</option>
                        <option>Special Event</option><option>Cultural</option><option>General</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option>Upcoming</option><option>Ongoing</option>
                        <option>Completed</option><option>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Organizer</label>
                    <input type="text" name="organizer">
                </div>
                <div class="form-group">
                    <label>Max Participants</label>
                    <input type="number" name="max_participants" min="1">
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('addEvtModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Event</button>
        </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editEvtModal">
    <div class="modal">
        <div class="modal-header">
            <h3>✏️ Edit Event</h3>
            <button class="modal-close" onclick="closeModal('editEvtModal')">✕</button>
        </div>
        <form method="POST" id="editEvtForm">
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Event Title *</label>
                    <input type="text" name="title" id="ee_title" required>
                </div>
                <div class="form-group">
                    <label>Event Date *</label>
                    <input type="date" name="event_date" id="ee_date" required>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" id="ee_loc">
                </div>
                <div class="form-group">
                    <label>Start Time</label>
                    <input type="time" name="event_time" id="ee_time">
                </div>
                <div class="form-group">
                    <label>End Time</label>
                    <input type="time" name="end_time" id="ee_etime">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="ee_cat">
                        <option>Academic</option><option>Sports</option>
                        <option>Special Event</option><option>Cultural</option><option>General</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="ee_status">
                        <option>Upcoming</option><option>Ongoing</option>
                        <option>Completed</option><option>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Organizer</label>
                    <input type="text" name="organizer" id="ee_org">
                </div>
                <div class="form-group">
                    <label>Max Participants</label>
                    <input type="number" name="max_participants" id="ee_max" min="1">
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="description" id="ee_desc"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('editEvtModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Event</button>
        </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewEvtModal">
    <div class="modal">
        <div class="modal-header"><h3>📅 Event Details</h3>
            <button class="modal-close" onclick="closeModal('viewEvtModal')">✕</button>
        </div>
        <div class="modal-body" id="viewEvtContent"></div>
    </div>
</div>

<script>
tableSearch('evtSearch','evtTable');

function filterEvents() {
    const val = document.getElementById('filterCat').value.toLowerCase();
    document.querySelectorAll('#evtTable tbody tr').forEach(row => {
        row.style.display = (!val || row.dataset.cat?.toLowerCase().includes(val)) ? '' : 'none';
    });
}

function editEvent(ev) {
    document.getElementById('ee_title').value = ev.title;
    document.getElementById('ee_date').value  = ev.event_date;
    document.getElementById('ee_loc').value   = ev.location||'';
    document.getElementById('ee_time').value  = ev.event_time||'';
    document.getElementById('ee_etime').value = ev.end_time||'';
    document.getElementById('ee_org').value   = ev.organizer||'';
    document.getElementById('ee_max').value   = ev.max_participants||'';
    document.getElementById('ee_desc').value  = ev.description||'';
    setSelectVal('ee_cat', ev.category);
    setSelectVal('ee_status', ev.status);
    document.getElementById('editEvtForm').action = 'events.php?action=edit&id=' + ev.id;
    openModal('editEvtModal');
}

function viewEvent(ev) {
    document.getElementById('viewEvtContent').innerHTML = `
        <div style="background:var(--navy);padding:20px 24px;border-radius:10px;margin-bottom:20px;display:flex;gap:14px;align-items:center;">
            <div style="background:var(--gold);color:var(--navy);padding:10px 14px;border-radius:10px;text-align:center;min-width:52px;">
                <strong style="font-size:22px;font-family:'Playfair Display',serif;display:block;">${new Date(ev.event_date).getDate()}</strong>
                <span style="font-size:10px;text-transform:uppercase;">${new Date(ev.event_date+'T00:00:00').toLocaleString('default',{month:'short'})}</span>
            </div>
            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:18px;color:white;">${ev.title}</h2>
                <p style="color:rgba(255,255,255,0.6);font-size:12.5px;margin-top:4px;">${ev.organizer||''}</p>
            </div>
        </div>
        ${ev.description ? `<p style="font-size:13.5px;line-height:1.6;color:var(--text);margin-bottom:18px;">${ev.description}</p>` : ''}
        <div class="detail-grid">
            <div class="detail-item"><label>Date</label><p>${ev.event_date}</p></div>
            <div class="detail-item"><label>Time</label><p>${ev.event_time||'—'}${ev.end_time?' – '+ev.end_time:''}</p></div>
            <div class="detail-item"><label>Location</label><p>${ev.location||'—'}</p></div>
            <div class="detail-item"><label>Category</label><p>${ev.category||'—'}</p></div>
            <div class="detail-item"><label>Max Participants</label><p>${ev.max_participants||'Open'}</p></div>
            <div class="detail-item"><label>Status</label><p>${ev.status}</p></div>
        </div>
    `;
    openModal('viewEvtModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) { if (opt.value===val||opt.text===val) { sel.value=opt.value; break; } }
}
</script>

<?php renderFooter(); ?>
