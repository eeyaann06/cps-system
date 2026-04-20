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

$events = $pdo->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Events', 'events');
flash();
?>

<!-- Pass PHP events to JavaScript -->
<script>
const ALL_EVENTS = <?= json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="toolbar">
    <input class="search-input" id="evtSearch" type="text" placeholder="🔍 Search events...">
    <select id="filterCat" onchange="renderCalendar()" style="padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;">
        <option value="">All Categories</option>
        <option>Academic</option><option>Sports</option><option>Special Event</option>
        <option>Cultural</option><option>General</option>
    </select>
    <button class="btn btn-primary" onclick="openModal('addEvtModal')">+ Add Event</button>
</div>

<!-- Summary cards -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;">
    <?php
    $cats  = ['Academic','Sports','Special Event','General'];
    $icons = ['🎓','🏆','🎉','📌'];
    $colors = ['navy','green','gold','blue'];
    foreach (array_keys($cats) as $i):
        $cnt = count(array_filter($events, fn($e) => $e['category'] === $cats[$i]));
    ?>
    <div class="stat-card">
        <div class="stat-icon <?= $colors[$i] ?>"><?= $icons[$i] ?></div>
        <div class="stat-info"><strong><?= $cnt ?></strong><span><?= $cats[$i] ?></span></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Calendar Card ──────────────────────────────────────────────────────── -->
<div class="card" style="overflow:visible;">
    <!-- Calendar header -->
    <div class="card-header" style="align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="btn btn-outline btn-sm" id="prevMonth" onclick="changeMonth(-1)">‹</button>
            <h3 id="calMonthTitle" style="min-width:160px;text-align:center;font-size:16px;">—</h3>
            <button class="btn btn-outline btn-sm" id="nextMonth" onclick="changeMonth(1)">›</button>
        </div>
        <button class="btn btn-outline btn-sm" onclick="goToday()" style="font-size:12px;">Today</button>
    </div>

    <!-- Day-of-week headers -->
    <div id="calDowRow" style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1.5px solid var(--border);">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
        <div style="padding:8px 0;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);"><?= $d ?></div>
        <?php endforeach; ?>
    </div>

    <!-- Calendar grid (rendered by JS) -->
    <div id="calGrid" style="display:grid;grid-template-columns:repeat(7,1fr);min-height:420px;"></div>

    <!-- Legend -->
    <div style="padding:12px 20px;border-top:1.5px solid var(--border);display:flex;gap:18px;flex-wrap:wrap;">
        <?php
        $legend = ['Upcoming'=>'#22c55e','Ongoing'=>'#f59e0b','Completed'=>'#3b82f6','Cancelled'=>'#ef4444'];
        foreach ($legend as $lbl => $col): ?>
        <div style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted);">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= $col ?>;display:inline-block;"></span><?= $lbl ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Day Events Popup ───────────────────────────────────────────────────── -->
<div id="dayPopup" style="display:none;position:fixed;z-index:1100;background:var(--card);border:1.5px solid var(--border);border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:320px;max-height:420px;overflow-y:auto;">
    <div style="padding:14px 16px;border-bottom:1.5px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <strong id="popupDate" style="font-size:13px;"></strong>
        <button onclick="closePopup()" style="background:none;border:none;cursor:pointer;font-size:16px;color:var(--muted);">✕</button>
    </div>
    <div id="popupEvents" style="padding:10px 14px;"></div>
</div>
<div id="popupOverlay" style="display:none;position:fixed;inset:0;z-index:1099;" onclick="closePopup()"></div>

<!-- ── ADD MODAL ──────────────────────────────────────────────────────────── -->
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
                    <input type="text" name="title" id="add_title" required>
                </div>
                <div class="form-group">
                    <label>Event Date *</label>
                    <input type="date" name="event_date" id="add_date" required>
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

<!-- ── EDIT MODAL ─────────────────────────────────────────────────────────── -->
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

<!-- ── VIEW MODAL ─────────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="viewEvtModal">
    <div class="modal">
        <div class="modal-header"><h3>📅 Event Details</h3>
            <button class="modal-close" onclick="closeModal('viewEvtModal')">✕</button>
        </div>
        <div class="modal-body" id="viewEvtContent"></div>
    </div>
</div>

<style>
/* Calendar cell */
.cal-cell {
    border-right: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    min-height: 100px;
    padding: 6px 8px;
    cursor: pointer;
    transition: background .15s;
    position: relative;
}
.cal-cell:hover { background: color-mix(in srgb, var(--primary, #1a2e5a) 5%, transparent); }
.cal-cell:nth-child(7n) { border-right: none; }
.cal-cell.other-month .cal-day-num { color: var(--muted); opacity: .45; }
.cal-cell.today { background: color-mix(in srgb, var(--gold, #c9a84c) 10%, transparent); }
.cal-cell.today .cal-day-num {
    background: var(--navy, #1a2e5a);
    color: var(--gold, #c9a84c);
    border-radius: 50%;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700;
}
.cal-day-num {
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
}
.cal-event-chip {
    font-size: 10.5px;
    padding: 2px 6px;
    border-radius: 4px;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
    font-weight: 500;
    cursor: pointer;
}
.cal-more {
    font-size: 10px;
    color: var(--muted);
    padding: 1px 4px;
    cursor: pointer;
}
/* Popup event row */
.popup-evt-row {
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 10px;
    align-items: flex-start;
}
.popup-evt-row:last-child { border-bottom: none; }
.popup-evt-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-top: 4px;
    flex-shrink: 0;
}
</style>

<script>
// ── State ─────────────────────────────────────────────────────────────────────
let calYear  = new Date().getFullYear();
let calMonth = new Date().getMonth(); // 0-indexed
const today  = new Date();
today.setHours(0,0,0,0);

const STATUS_COLORS = {
    'Upcoming' : '#22c55e',
    'Ongoing'  : '#f59e0b',
    'Completed': '#3b82f6',
    'Cancelled': '#ef4444',
};

// ── Helpers ───────────────────────────────────────────────────────────────────
function statusColor(s) { return STATUS_COLORS[s] || '#6b7280'; }

function fmtTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    return `${hr % 12 || 12}:${m} ${hr < 12 ? 'AM' : 'PM'}`;
}

function getFilteredEvents() {
    const cat = document.getElementById('filterCat').value;
    const q   = (document.getElementById('evtSearch').value || '').toLowerCase().trim();
    return ALL_EVENTS.filter(e => {
        const matchCat = !cat || e.category === cat;
        const matchQ   = !q   || e.title.toLowerCase().includes(q)
                               || (e.description||'').toLowerCase().includes(q)
                               || (e.location||'').toLowerCase().includes(q)
                               || (e.organizer||'').toLowerCase().includes(q);
        return matchCat && matchQ;
    });
}

// ── Render calendar ───────────────────────────────────────────────────────────
function renderCalendar() {
    const grid  = document.getElementById('calGrid');
    const title = document.getElementById('calMonthTitle');
    const evts  = getFilteredEvents();

    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    title.textContent = `${monthNames[calMonth]} ${calYear}`;

    // Build event map: 'YYYY-MM-DD' → [events]
    const evtMap = {};
    evts.forEach(e => {
        const k = e.event_date.slice(0,10);
        if (!evtMap[k]) evtMap[k] = [];
        evtMap[k].push(e);
    });

    // First day of month (0=Sun)
    const firstDay  = new Date(calYear, calMonth, 1).getDay();
    const daysInMon = new Date(calYear, calMonth + 1, 0).getDate();
    const daysInPrev= new Date(calYear, calMonth, 0).getDate();

    let html = '';
    let totalCells = Math.ceil((firstDay + daysInMon) / 7) * 7;

    for (let i = 0; i < totalCells; i++) {
        let dayNum, dateStr, isOther = false;

        if (i < firstDay) {
            // Previous month
            dayNum  = daysInPrev - firstDay + i + 1;
            const d = new Date(calYear, calMonth - 1, dayNum);
            dateStr = d.toISOString().slice(0,10);
            isOther = true;
        } else if (i >= firstDay + daysInMon) {
            // Next month
            dayNum  = i - firstDay - daysInMon + 1;
            const d = new Date(calYear, calMonth + 1, dayNum);
            dateStr = d.toISOString().slice(0,10);
            isOther = true;
        } else {
            dayNum  = i - firstDay + 1;
            const d = new Date(calYear, calMonth, dayNum);
            dateStr = d.toISOString().slice(0,10);
        }

        const cellDate = new Date(calYear,
            isOther ? (i < firstDay ? calMonth - 1 : calMonth + 1) : calMonth,
            dayNum);
        cellDate.setHours(0,0,0,0);
        const isToday = cellDate.getTime() === today.getTime();

        const dayEvts  = evtMap[dateStr] || [];
        const maxShow  = 2;
        const shown    = dayEvts.slice(0, maxShow);
        const moreCount= dayEvts.length - maxShow;

        let chipsHtml = shown.map(e =>
            `<div class="cal-event-chip" style="background:${statusColor(e.status)};"
                  title="${e.title}">${e.title}</div>`
        ).join('');
        if (moreCount > 0) {
            chipsHtml += `<div class="cal-more">+${moreCount} more</div>`;
        }

        const evtsJson = JSON.stringify(dayEvts).replace(/'/g, "&#39;");
        const labelStr = cellDate.toLocaleDateString('en-PH', {weekday:'long',year:'numeric',month:'long',day:'numeric'});

        html += `
        <div class="cal-cell${isOther ? ' other-month' : ''}${isToday ? ' today' : ''}"
             onclick="openDayPopup(event, '${dateStr}', '${labelStr.replace(/'/g,"&#39;")}', ${dayEvts.length > 0 ? "'"+evtsJson.replace(/'/g,"\\'")+"'" : "'[]'"})">
            <div class="cal-day-num">${dayNum}</div>
            ${chipsHtml}
        </div>`;
    }

    grid.innerHTML = html;
}

// ── Month navigation ──────────────────────────────────────────────────────────
function changeMonth(dir) {
    calMonth += dir;
    if (calMonth > 11) { calMonth = 0;  calYear++; }
    if (calMonth < 0)  { calMonth = 11; calYear--; }
    renderCalendar();
}

function goToday() {
    calYear  = new Date().getFullYear();
    calMonth = new Date().getMonth();
    renderCalendar();
}

// ── Day popup ─────────────────────────────────────────────────────────────────
function openDayPopup(event, dateStr, label, evtsJsonStr) {
    event.stopPropagation();
    const evts   = JSON.parse(evtsJsonStr);
    const popup  = document.getElementById('dayPopup');
    const overlay= document.getElementById('popupOverlay');

    document.getElementById('popupDate').textContent = label;

    if (evts.length === 0) {
        document.getElementById('popupEvents').innerHTML =
            `<p style="font-size:12.5px;color:var(--muted);padding:6px 0;">No events on this day.
             <span style="color:var(--primary);cursor:pointer;"
                   onclick="closePopup();prefillDate('${dateStr}');openModal('addEvtModal')">+ Add one</span></p>`;
    } else {
        document.getElementById('popupEvents').innerHTML = evts.map(e => `
            <div class="popup-evt-row">
                <div class="popup-evt-dot" style="background:${statusColor(e.status)};"></div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:13px;font-weight:600;color:var(--text);margin:0 0 2px;">${e.title}</p>
                    ${e.event_time ? `<p style="font-size:11.5px;color:var(--muted);margin:0;">⏰ ${fmtTime(e.event_time)}${e.end_time ? ' – '+fmtTime(e.end_time):''}</p>` : ''}
                    ${e.location   ? `<p style="font-size:11.5px;color:var(--muted);margin:0;">📍 ${e.location}</p>` : ''}
                    <span class="badge" style="font-size:10px;margin-top:4px;background:${statusColor(e.status)}20;color:${statusColor(e.status)};border:1px solid ${statusColor(e.status)}40;">${e.status}</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;">
                    <button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 7px;"
                            onclick="closePopup();viewEvent(${JSON.stringify(e).replace(/"/g,'&quot;')})">👁</button>
                    <button class="btn btn-primary btn-sm" style="font-size:11px;padding:3px 7px;"
                            onclick="closePopup();editEvent(${JSON.stringify(e).replace(/"/g,'&quot;')})">✏️</button>
                </div>
            </div>
        `).join('');
    }

    // Position near click but keep within viewport
    popup.style.display = 'block';
    overlay.style.display = 'block';
    const vw = window.innerWidth, vh = window.innerHeight;
    let x = event.clientX + 10, y = event.clientY + 10;
    if (x + 330 > vw) x = event.clientX - 340;
    if (y + popup.offsetHeight + 20 > vh) y = event.clientY - popup.offsetHeight - 10;
    popup.style.left = Math.max(8, x) + 'px';
    popup.style.top  = Math.max(8, y) + 'px';
}

function closePopup() {
    document.getElementById('dayPopup').style.display = 'none';
    document.getElementById('popupOverlay').style.display = 'none';
}

function prefillDate(dateStr) {
    document.getElementById('add_date').value = dateStr;
}

// ── Search live filter ────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('evtSearch').addEventListener('input', renderCalendar);
    renderCalendar();
});

// ── Edit / View ───────────────────────────────────────────────────────────────
function editEvent(ev) {
    document.getElementById('ee_title').value = ev.title;
    document.getElementById('ee_date').value  = ev.event_date;
    document.getElementById('ee_loc').value   = ev.location   || '';
    document.getElementById('ee_time').value  = ev.event_time || '';
    document.getElementById('ee_etime').value = ev.end_time   || '';
    document.getElementById('ee_org').value   = ev.organizer  || '';
    document.getElementById('ee_max').value   = ev.max_participants || '';
    document.getElementById('ee_desc').value  = ev.description || '';
    setSelectVal('ee_cat',    ev.category);
    setSelectVal('ee_status', ev.status);
    document.getElementById('editEvtForm').action = 'events.php?action=edit&id=' + ev.id;
    openModal('editEvtModal');
}

function viewEvent(ev) {
    document.getElementById('viewEvtContent').innerHTML = `
        <div style="background:var(--navy);padding:20px 24px;border-radius:10px;margin-bottom:20px;display:flex;gap:14px;align-items:center;">
            <div style="background:var(--gold);color:var(--navy);padding:10px 14px;border-radius:10px;text-align:center;min-width:52px;">
                <strong style="font-size:22px;font-family:'Playfair Display',serif;display:block;">${new Date(ev.event_date+'T00:00:00').getDate()}</strong>
                <span style="font-size:10px;text-transform:uppercase;">${new Date(ev.event_date+'T00:00:00').toLocaleString('default',{month:'short'})}</span>
            </div>
            <div>
                <h2 style="font-family:'Playfair Display',serif;font-size:18px;color:white;">${ev.title}</h2>
                <p style="color:rgba(255,255,255,0.6);font-size:12.5px;margin-top:4px;">${ev.organizer || ''}</p>
            </div>
        </div>
        ${ev.description ? `<p style="font-size:13.5px;line-height:1.6;color:var(--text);margin-bottom:18px;">${ev.description}</p>` : ''}
        <div class="detail-grid">
            <div class="detail-item"><label>Date</label><p>${ev.event_date}</p></div>
            <div class="detail-item"><label>Time</label><p>${fmtTime(ev.event_time)||'—'}${ev.end_time?' – '+fmtTime(ev.end_time):''}</p></div>
            <div class="detail-item"><label>Location</label><p>${ev.location||'—'}</p></div>
            <div class="detail-item"><label>Category</label><p>${ev.category||'—'}</p></div>
            <div class="detail-item"><label>Max Participants</label><p>${ev.max_participants||'Open'}</p></div>
            <div class="detail-item"><label>Status</label><p>${ev.status}</p></div>
        </div>
        <div style="margin-top:16px;display:flex;gap:8px;">
            <button class="btn btn-primary btn-sm" onclick="closeModal('viewEvtModal');editEvent(${JSON.stringify(ev).replace(/"/g,'&quot;')})">✏️ Edit</button>
            <button class="btn btn-danger btn-sm" onclick="confirmDelete('events.php?action=delete&id=${ev.id}','${ev.title.replace(/'/g,"\\'")}')">🗑 Delete</button>
        </div>
    `;
    openModal('viewEvtModal');
}

function setSelectVal(id, val) {
    const sel = document.getElementById(id);
    if (!sel || !val) return;
    for (let opt of sel.options) {
        if (opt.value === val || opt.text === val) { sel.value = opt.value; break; }
    }
}
</script>

<?php renderFooter(); ?>