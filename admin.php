<?php
/**
 * IVR Lottery Registration - Admin Panel
 * View and manage registrations
 */

define('DATA_FILE', __DIR__ . '/data/registrations.json');
define('COUNTER_FILE', __DIR__ . '/data/counter.json');

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $registrations = [];
    if (file_exists(DATA_FILE)) {
        $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
    }
    usort($registrations, function($a, $b) {
        return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
    });

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="registrations_' . date('Y-m-d') . '.csv"');
    $bom = "\xEF\xBB\xBF";
    echo $bom;
    $out = fopen('php://output', 'w');
    fputcsv($out, ['#', 'מספר אישור', 'מספר טלפון', 'תאריך']);
    foreach ($registrations as $i => $reg) {
        fputcsv($out, [
            $i + 1,
            $reg['confirmNumber'] ?? '',
            $reg['phone'] ?? '',
            $reg['date'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_selected' && isset($_POST['ids']) && is_array($_POST['ids'])) {
        $idsToDelete = array_map('intval', $_POST['ids']);
        $registrations = [];
        if (file_exists(DATA_FILE)) {
            $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
        }
        $registrations = array_values(array_filter($registrations, function($r) use ($idsToDelete) {
            return !in_array($r['id'], $idsToDelete);
        }));
        file_put_contents(DATA_FILE, json_encode($registrations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        header('Location: admin.php');
        exit;
    }
}

// Load registrations
$registrations = [];
if (file_exists(DATA_FILE)) {
    $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
}

// Sort newest first
usort($registrations, function($a, $b) {
    return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
});

$totalCount = count($registrations);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ניהול הרשמות להגרלה</title>
<style>
:root {
    --primary: #7c5cbf;
    --primary-light: #9b7fd4;
    --danger: #e74c5a;
    --success: #2ecc71;
    --bg: #1a1a2e;
    --card: #16213e;
    --card2: #0f3460;
    --text: #eaeaea;
    --muted: #a0aec0;
    --border: #2a2a4a;
    --gold: #e2b655;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    color: var(--text);
    min-height: 100vh;
}
.header {
    background: rgba(15, 52, 96, 0.8);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(124, 92, 191, 0.3);
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header h1 { font-size: 22px; color: #fff; }
.header h1 span { color: var(--gold); }
.header-actions {
    display: flex;
    gap: 15px;
    align-items: center;
}
.stat-box {
    background: rgba(124, 92, 191, 0.15);
    border: 1px solid rgba(124, 92, 191, 0.35);
    border-radius: 10px;
    padding: 10px 20px;
    text-align: center;
}
.stat-num { font-size: 28px; font-weight: 700; color: var(--gold); }
.stat-label { font-size: 11px; color: var(--muted); }
.btn-export {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(46, 204, 113, 0.15);
    color: var(--success);
    border: 1px solid rgba(46, 204, 113, 0.35);
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    font-family: inherit;
    transition: all 0.2s;
}
.btn-export:hover { background: rgba(46, 204, 113, 0.3); }
.content { padding: 24px 30px; }
.table-wrapper {
    background: rgba(22, 33, 62, 0.9);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow: hidden;
    backdrop-filter: blur(5px);
}
table {
    width: 100%;
    border-collapse: collapse;
}
thead th {
    background: rgba(124, 92, 191, 0.12);
    padding: 14px 16px;
    text-align: right;
    font-size: 13px;
    font-weight: 700;
    color: var(--primary-light);
    border-bottom: 1px solid var(--border);
}
tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 14px;
}
tbody tr:hover { background: rgba(124, 92, 191, 0.08); }
tbody tr:last-child td { border-bottom: none; }
.confirm-badge {
    display: inline-block;
    background: rgba(226, 182, 85, 0.15);
    color: var(--gold);
    padding: 4px 12px;
    border-radius: 6px;
    font-weight: 700;
    font-size: 14px;
    font-family: monospace;
}
.phone-num {
    font-family: monospace;
    font-size: 14px;
    color: #fff;
    direction: ltr;
    unicode-bidi: bidi-override;
}
.btn {
    padding: 7px 14px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    transition: all 0.2s;
}
.btn-delete {
    background: rgba(231, 76, 90, 0.15);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 90, 0.3);
    padding: 8px 16px;
    font-size: 13px;
    display: none;
}
.btn-delete:hover { background: rgba(231, 76, 90, 0.3); }
.btn-delete.visible { display: inline-flex; align-items: center; gap: 6px; }
input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}
thead th:first-child, tbody td:first-child { text-align: center; width: 40px; }
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}
.empty-state .icon { font-size: 48px; margin-bottom: 12px; }
.empty-state h3 { margin-bottom: 6px; color: var(--text); }
.date-cell { white-space: nowrap; font-size: 13px; color: var(--muted); }
</style>
</head>
<body>
<div class="header">
    <h1>&#127922; ניהול הרשמות <span>להגרלה</span></h1>
    <div class="header-actions">
        <?php if ($totalCount > 0): ?>
        <a href="admin.php?export=excel" class="btn-export">&#128202; ייצוא אקסל</a>
        <?php endif; ?>
        <div class="stat-box">
            <div class="stat-num"><?= $totalCount ?></div>
            <div class="stat-label">נרשמים</div>
        </div>
    </div>
</div>

<div class="content">
    <div class="table-wrapper">
    <?php if ($totalCount === 0): ?>
        <div class="empty-state">
            <div class="icon">&#128242;</div>
            <h3>אין הרשמות עדיין</h3>
            <p>כשמישהו יתקשר ויירשם להגרלה, הנתונים יופיעו כאן</p>
        </div>
    <?php else: ?>
        <form id="bulkForm" method="POST">
        <input type="hidden" name="action" value="delete_selected">
        <div style="padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
            <span id="selectedCount" style="color: var(--muted); font-size: 13px;"></span>
            <button type="submit" id="bulkDeleteBtn" class="btn btn-delete" onclick="return confirm('למחוק את ההרשמות שנבחרו?')">&#128465; מחק נבחרים</button>
        </div>
        <table>
        <thead>
            <tr>
                <th><input type="checkbox" id="selectAll"></th>
                <th>#</th>
                <th>מספר אישור</th>
                <th>מספר טלפון</th>
                <th>תאריך</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($registrations as $i => $reg): ?>
            <tr>
                <td><input type="checkbox" name="ids[]" value="<?= (int)($reg['id'] ?? 0) ?>" class="row-cb"></td>
                <td><?= $i + 1 ?></td>
                <td><span class="confirm-badge"><?= htmlspecialchars($reg['confirmNumber'] ?? '') ?></span></td>
                <td><span class="phone-num"><?= htmlspecialchars($reg['phone'] ?? '') ?></span></td>
                <td class="date-cell"><?= htmlspecialchars($reg['date'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
        </form>
        <script>
        var cbs = document.querySelectorAll('.row-cb');
        var selAll = document.getElementById('selectAll');
        var btn = document.getElementById('bulkDeleteBtn');
        var countEl = document.getElementById('selectedCount');
        function update() {
            var c = document.querySelectorAll('.row-cb:checked').length;
            btn.className = 'btn btn-delete' + (c > 0 ? ' visible' : '');
            countEl.textContent = c > 0 ? c + ' נבחרו' : '';
            selAll.checked = c === cbs.length && c > 0;
        }
        selAll.addEventListener('change', function() {
            cbs.forEach(function(cb) { cb.checked = selAll.checked; });
            update();
        });
        cbs.forEach(function(cb) { cb.addEventListener('change', update); });
        </script>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
