<?php
require_auth();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = $_POST['customer_name'] ?? '';
    $customerId = $_POST['customer_id'] ?? generate_license_id();
    $plan = $_POST['plan'] ?? 'basic';
    $domain = $_POST['domain'] ?? '*';
    $expiryDays = (int)($_POST['expiry_days'] ?? LS_DEFAULT_EXPIRY_DAYS);
    $customFeatures = $_POST['custom_features'] ?? '';

    if (empty($customerName)) {
        $error = 'Customer name is required';
    } else {
        $features = null;
        if (!empty($customFeatures)) {
            $features = array_map('trim', explode(',', $customFeatures));
        }

        // Forward to generate API
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [];
        $input = json_encode([
            'customer_id' => $customerId,
            'customer_name' => $customerName,
            'plan' => $plan,
            'domain' => $domain,
            'features' => $features,
            'expiry_days' => $expiryDays,
        ]);

        // Simulate API call via internal include
        ob_start();
        $GLOBALS['_API_INPUT'] = json_decode($input, true);
        require LS_ROOT . '/api/generate.php';
        $output = ob_get_clean();
        $result = json_decode($output, true);

        if ($result && isset($result['success'])) {
            $message = 'License generated successfully! Key: ' . $result['license_key'];
        } else {
            $error = $result['error'] ?? 'Failed to generate license';
        }
    }
}

$plans = $GLOBALS['ls_plans'];
$featuresList = [];
foreach ($plans as $pk => $pv) {
    if (is_array($pv['features'])) {
        $featuresList = array_merge($featuresList, $pv['features']);
    }
}
$featuresList = array_unique($featuresList);

html_response('
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create License - License Server</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif; background: #0f0f1a; color: #e2e8f0; }
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        h1 { font-size: 24px; margin-bottom: 30px; background: linear-gradient(135deg, #6366f1, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card { background: linear-gradient(135deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-size: 14px; color: #94a3b8; margin-bottom: 6px; }
        input, select, textarea { width: 100%; padding: 10px 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-size: 14px; }
        input:focus, select:focus { outline: none; border-color: #6366f1; }
        select option { background: #1a1a2e; }
        .btn { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        .btn:hover { opacity: 0.9; }
        .message { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .message-success { background: rgba(34,197,94,0.2); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .message-error { background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
        .back { color: #6366f1; text-decoration: none; font-size: 14px; display: inline-block; margin-bottom: 20px; }
        .back:hover { text-decoration: underline; }
        .features-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin: 10px 0; }
        .features-grid label { font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer; }
        .features-grid input[type="checkbox"] { width: auto; }
        .help { font-size: 12px; color: #666; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin" class="back">← Back to Dashboard</a>
        <h1>Generate New License</h1>
        ' . ($message ? '<div class="message message-success">' . htmlspecialchars($message) . '</div>' : '') . '
        ' . ($error ? '<div class="message message-error">' . htmlspecialchars($error) . '</div>' : '') . '
        <div class="card">
            <form method="POST" action="/admin/create">
                <div class="form-group">
                    <label>Customer Name *</label>
                    <input type="text" name="customer_name" required placeholder="e.g. Acme Clinic">
                </div>
                <div class="form-group">
                    <label>Customer ID (optional, auto-generated if empty)</label>
                    <input type="text" name="customer_id" placeholder="Leave empty for auto-generate">
                </div>
                <div class="form-group">
                    <label>Plan *</label>
                    <select name="plan" id="planSelect" onchange="updateFeatures()">
                        ' . implode('', array_map(fn($pk, $pv) => '<option value="' . $pk . '">' . htmlspecialchars($pv['name']) . ' ($' . $pv['price'] . ')</option>', array_keys($plans), $plans)) . '
                    </select>
                </div>
                <div class="form-group">
                    <label>Domain (comma-separated, * for any)</label>
                    <input type="text" name="domain" value="*" placeholder="e.g. clinic.com, *.clinic.com">
                </div>
                <div class="form-group">
                    <label>Expiry (days, 0 = never)</label>
                    <input type="number" name="expiry_days" value="' . LS_DEFAULT_EXPIRY_DAYS . '" min="0" max="36500">
                </div>
                <div class="form-group">
                    <label>Custom Features (comma-separated, leave empty for plan defaults)</label>
                    <input type="text" name="custom_features" placeholder="e.g. booking,payment,crm,wallet">
                    <div class="help">Available: ' . implode(', ', $featuresList) . '</div>
                </div>
                <button type="submit" class="btn">Generate License Key</button>
            </form>
        </div>
    </div>
    <script>
    function updateFeatures() {
        // Could auto-fill based on plan
    }
    </script>
</body>
</html>
');
