<?php
require_auth();

$licenses = load_licenses();
$plans = $GLOBALS['ls_plans'];

$activeCount = count(array_filter($licenses, fn($l) => $l['status'] === 'active'));
$expiredCount = count(array_filter($licenses, fn($l) => $l['status'] === 'expired'));
$revokedCount = count(array_filter($licenses, fn($l) => $l['status'] === 'revoked'));
$totalCount = count($licenses);

html_response('
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Server Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif; background: #0f0f1a; color: #e2e8f0; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: linear-gradient(135deg, #1a1a2e, #16213e); padding: 20px 0; border-bottom: 1px solid #2d2d4a; margin-bottom: 30px; }
        header .container { display: flex; justify-content: space-between; align-items: center; }
        h1 { font-size: 24px; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1)); border: 1px solid rgba(99,102,241,0.2); border-radius: 12px; padding: 20px; backdrop-filter: blur(10px); }
        .stat-card h3 { font-size: 14px; color: #94a3b8; margin-bottom: 8px; }
        .stat-card .number { font-size: 32px; font-weight: bold; }
        .stat-card.active .number { color: #22c55e; }
        .stat-card.expired .number { color: #ef4444; }
        .stat-card.revoked .number { color: #f59e0b; }
        .stat-card.total .number { color: #6366f1; }
        .btn { display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn:hover { opacity: 0.9; }
        .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); }
        table { width: 100%; border-collapse: collapse; background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; overflow: hidden; }
        th, td { padding: 12px 15px; text-align: right; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { background: rgba(99,102,241,0.1); font-size: 13px; color: #94a3b8; }
        td { font-size: 14px; }
        tr:hover { background: rgba(99,102,241,0.05); }
        .status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-active { background: rgba(34,197,94,0.2); color: #22c55e; }
        .status-expired { background: rgba(239,68,68,0.2); color: #ef4444; }
        .status-revoked { background: rgba(245,158,11,0.2); color: #f59e0b; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; background: rgba(99,102,241,0.2); color: #6366f1; }
        .actions { display: flex; gap: 8px; }
        .actions a { padding: 5px 12px; border-radius: 6px; font-size: 12px; text-decoration: none; }
        .nav { display: flex; gap: 15px; }
        .nav a { color: #94a3b8; text-decoration: none; font-size: 14px; }
        .nav a:hover { color: #fff; }
        .copy-btn { background: none; border: 1px solid rgba(99,102,241,0.3); color: #6366f1; padding: 2px 8px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .copy-btn:hover { background: rgba(99,102,241,0.1); }
        .actions-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .search-box { padding: 10px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; width: 300px; }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>🔐 License Server</h1>
            <nav class="nav">
                <a href="/admin">Dashboard</a>
                <a href="/admin/create">Create License</a>
                <a href="/admin/list">All Licenses</a>
                <a href="/admin/login?logout=1">Logout</a>
            </nav>
        </div>
    </header>
    <div class="container">
        <div class="stats">
            <div class="stat-card active"><h3>Active</h3><div class="number">' . $activeCount . '</div></div>
            <div class="stat-card expired"><h3>Expired</h3><div class="number">' . $expiredCount . '</div></div>
            <div class="stat-card revoked"><h3>Revoked</h3><div class="number">' . $revokedCount . '</div></div>
            <div class="stat-card total"><h3>Total</h3><div class="number">' . $totalCount . '</div></div>
        </div>

        <div class="actions-bar">
            <a href="/admin/create" class="btn">+ Generate License</a>
            <input type="text" class="search-box" placeholder="Search by key, customer, domain..." oninput="filterTable(this.value)">
        </div>

        <table>
            <thead>
                <tr>
                    <th>License Key</th>
                    <th>Customer</th>
                    <th>Plan</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Issued</th>
                    <th>Expires</th>
                    <th>Validations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                ' . renderLicenseRows($licenses) . '
            </tbody>
        </table>
    </div>
    <script>
    function filterTable(query) {
        const rows = document.querySelectorAll("tbody tr");
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query.toLowerCase()) ? "" : "none";
        });
    }
    function copyKey(key) {
        navigator.clipboard.writeText(key).then(() => {
            alert("License key copied!");
        });
    }
    </script>
</body>
</html>
');

function renderLicenseRows(array $licenses): string
{
    $html = '';
    foreach (array_reverse($licenses) as $lic) {
        $statusClass = 'status-' . $lic['status'];
        $expires = $lic['expires_at'] ?? 'Never';
        $keyShort = substr($lic['license_key'], 0, 20) . '...';
        $html .= '<tr>
            <td><code>' . htmlspecialchars($keyShort) . '</code> <button class="copy-btn" onclick="copyKey(\'' . htmlspecialchars($lic['license_key']) . '\')">Copy</button></td>
            <td>' . htmlspecialchars($lic['customer_name'] ?? 'N/A') . '<br><small>' . htmlspecialchars($lic['customer_id'] ?? '') . '</small></td>
            <td><span class="badge">' . htmlspecialchars($lic['plan']) . '</span></td>
            <td>' . htmlspecialchars($lic['domain'] ?? '*') . '</td>
            <td><span class="status ' . $statusClass . '">' . $lic['status'] . '</span></td>
            <td>' . htmlspecialchars($lic['issued_at']) . '</td>
            <td>' . htmlspecialchars($expires) . '</td>
            <td>' . ($lic['validation_count'] ?? 0) . '</td>
            <td class="actions">' .
            ($lic['status'] === 'active' ? '<a href="/admin/revoke?key=' . urlencode($lic['license_key']) . '" class="btn btn-danger" style="padding: 4px 10px; font-size: 11px;" onclick="return confirm(\'Revoke this license?\')">Revoke</a>' : '') .
            '</td>
        </tr>';
    }
    return $html ?: '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #666;">No licenses found. <a href="/admin/create">Create one</a></td></tr>';
}
