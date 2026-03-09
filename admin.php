<?php
/**
 * IVR Lottery Registration - Admin Panel
 * View and manage registrations
 */

define('DATA_FILE', __DIR__ . '/data/registrations.json');
define('COUNTER_FILE', __DIR__ . '/data/counter.json');

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $registrations = [];
        if (file_exists(DATA_FILE)) {
            $registrations = json_decode(file_get_contents(DATA_FILE), true) ?: [];
        }
        $registrations = array_values(array_filter($registrations, function($r) use ($id) {
            return $r['id'] !== $id;
        }));
        file_put_contents(DATA_FILE, json_encode($registrations, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        header('Location: admin.php');
        exit;
    }
    if ($_POST['action'] === 'reset') {
        file_put_contents(DATA_FILE, '[]', LOCK_EX);
        file_put_contents(COUNTER_FILE, json_encode(['counter' => 0]), LOCK_EX);
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
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --danger: #ef476f;
    --success: #06d6a0;
    --bg: #0f0f1a;
    --card: #1a1a2e;
    --text: #e0e0e0;
    --muted: #8d99ae;
    --border: #2d2d44;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: -apple-system, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
}
.header {
    background: var(--card);
    border-bottom: 1px solid var(--border);
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header h1 { font-size: 22px; color: #fff; }
.header h1 span { color: var(--primary); }
.stats {
    display: flex;
    gap: 20px;
    align-items: center;
}
.stat-box {
    background: rgba(67,97,238,0.1);
    border: 1px solid rgba(67,97,238,0.3);
    border-radius: 10px;
    padding: 10px 20px;
    text-align: center;
}
.stat-num { font-size: 28px; font-weight: 700; color: var(--primary); }
.stat-label { font-size: 11px; color: var(--muted); }
.content { padding: 24px 30px; }
.table-wrapper {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
    overflow: hidden;
}
table {
    width: 100%;
    border-collapse: collapse;
}
thead th {
    background: rgba(67,97,238,0.08);
    padding: 14px 16px;
    text-align: right;
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}
tbody td {
    padding: 14px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 14px;
}
tbody tr:hover { background: rgba(255,255,255,0.03); }
tbody tr:last-child td { border-bottom: none; }
.confirm-badge {
    display: inline-block;
    background: rgba(6,214,160,0.15);
    color: var(--success);
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
.recording-link {
    color: var(--primary);
    font-size: 12px;
    text-decoration: none;
}
.recording-link:hover { text-decoration: underline; }
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
    background: rgba(239,71,111,0.1);
    color: var(--danger);
    border: 1px solid rgba(239,71,111,0.3);
}
.btn-delete:hover { background: rgba(239,71,111,0.25); }
.btn-reset {
    background: rgba(239,71,111,0.1);
    color: var(--danger);
    border: 1px solid rgba(239,71,111,0.3);
    padding: 8px 16px;
    font-size: 13px;
}
.btn-reset:hover { background: rgba(239,71,111,0.25); }
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}
.empty-state .icon { font-size: 48px; margin-bottom: 12px; }
.empty-state h3 { margin-bottom: 6px; color: var(--text); }
.api-info {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 20px;
}
.api-info h3 { font-size: 14px; margin-bottom: 10px; color: var(--primary); }
.api-url {
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 12px 16px;
    font-family: monospace;
    font-size: 13px;
    direction: ltr;
    color: var(--success);
    word-break: break-all;
}
.date-cell { white-space: nowrap; font-size: 13px; color: var(--muted); }
</style>
</head>
<body>
<div class="header">
    <h1>&#127922; ניהול הרשמות <span>להגרלה</span></h1>
    <div class="stats">
        <div class="stat-box">
            <div class="stat-num"><?= $totalCount ?></div>
            <div class="stat-label">נרשמים</div>
        </div>
        <form method="POST" onsubmit="return confirm('האם למחוק את כל הנתונים?')">
            <input type="hidden" name="action" value="reset">
            <button type="submit" class="btn btn-reset">&#128465; איפוס</button>
        </form>
    </div>
</div>

<div class="content">
    <div class="api-info">
        <h3>&#128279; כתובת API למרכזיה:</h3>
        <div class="api-url"><?= 'https://' . ($_SERVER['HTTP_HOST'] ?? 'YOUR_DOMAIN') . dirname($_SERVER['SCRIPT_NAME']) . '/api.php' ?></div>
    </div>

    <div class="table-wrapper">
    <?php if ($totalCount === 0): ?>
        <div class="empty-state">
            <div class="icon">&#128242;</div>
            <h3>אין הרשמות עדיין</h3>
            <p>כשמישהו יתקשר ויירשם להגרלה, הנתונים יופיעו כאן</p>
        </div>
    <?php else: ?>
        <table>
        <thead>
            <tr>
                <th>#</th>
                <th>מספר אישור</th>
                <th>מספר טלפון</th>
                <th>תאריך</th>
                <th>הקלטת שם</th>
                <th>הקלטת משניות</th>
                <th>פעולות</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($registrations as $i => $reg): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><span class="confirm-badge"><?= htmlspecialchars($reg['confirmNumber'] ?? '') ?></span></td>
                <td><span class="phone-num"><?= htmlspecialchars($reg['phone'] ?? '') ?></span></td>
                <td class="date-cell"><?= htmlspecialchars($reg['date'] ?? '') ?></td>
                <td><span class="recording-link">&#127908; <?= htmlspecialchars($reg['nameRecording'] ?? '-') ?></span></td>
                <td><span class="recording-link">&#127908; <?= htmlspecialchars($reg['mishnayotRecording'] ?? '-') ?></span></td>
                <td>
                    <form method="POST" style="display:inline" onsubmit="return confirm('למחוק הרשמה זו?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)($reg['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-delete">&#128465; מחק</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>
</body>
</html>
