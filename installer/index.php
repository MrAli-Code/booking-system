<?php
/**
 * Booking System - One-Click Installer
 * 
 * Works on: Shared hosting (cPanel, DirectAdmin), VPS, Docker
 * Zero dependencies, single-file wizard for easy deployment
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 0);

define('BBS_ROOT', dirname(__DIR__));

session_start();

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = $_SESSION['install_error'] ?? '';
$success = $_SESSION['install_success'] ?? '';
unset($_SESSION['install_error'], $_SESSION['install_success']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_requirements') {
        checkRequirements();
    } elseif ($action === 'setup_database') {
        setupDatabase();
    } elseif ($action === 'save_config') {
        saveConfig();
    } elseif ($action === 'activate_license') {
        activateLicense();
    } elseif ($action === 'complete_install') {
        completeInstall();
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب سیستم رزرو آنلاین | Booking System Installer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .installer-container {
            width: 100%;
            max-width: 800px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 40px;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        }
        .installer-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .installer-header h1 {
            font-size: 28px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }
        .installer-header p { color: #94a3b8; font-size: 14px; }
        .steps {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.05);
            font-size: 13px;
            color: #64748b;
            transition: all 0.3s;
        }
        .step-indicator.active {
            background: rgba(99,102,241,0.2);
            color: #6366f1;
            border: 1px solid rgba(99,102,241,0.3);
        }
        .step-indicator.completed {
            background: rgba(34,197,94,0.2);
            color: #22c55e;
            border: 1px solid rgba(34,197,94,0.3);
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            font-size: 12px;
            font-weight: bold;
        }
        .step-indicator.active .step-number { background: #6366f1; color: #fff; }
        .step-indicator.completed .step-number { background: #22c55e; color: #fff; }
        .step-content { display: none; }
        .step-content.active { display: block; }
        h2 { font-size: 20px; margin-bottom: 20px; color: #f1f5f9; }
        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 14px; color: #94a3b8; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 12px 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #e2e8f0;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        input:focus, select:focus { outline: none; border-color: #6366f1; }
        select option { background: #1a1a2e; }
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .btn:hover { transform: translateY(-1px); opacity: 0.9; }
        .btn-secondary { background: rgba(255,255,255,0.1); color: #e2e8f0; }
        .btn-success { background: linear-gradient(135deg, #22c55e, #16a34a); }
        .btn-block { width: 100%; text-align: center; }
        .error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        .success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        .info { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); color: #6366f1; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; }
        td:last-child { text-align: left; }
        .check-pass { color: #22c55e; }
        .check-fail { color: #ef4444; }
        .check-warn { color: #f59e0b; }
        .progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            margin: 20px 0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 2px;
            transition: width 1s;
        }
        .flex { display: flex; gap: 12px; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        .license-key { font-family: monospace; font-size: 12px; word-break: break-all; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 8px; }
        .branding-preview { display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(255,255,255,0.03); border-radius: 12px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(99,102,241,0.3); border-radius: 50%; border-top-color: #6366f1; animation: spin 0.6s linear infinite; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <h1>🚀 نصب سیستم رزرو آنلاین</h1>
            <p>Booking System v1.0 - Installation Wizard</p>
        </div>

        <div class="steps">
            <div class="step-indicator <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">
                <span class="step-number">1</span> بررسی نیازمندی‌ها
            </div>
            <div class="step-indicator <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">
                <span class="step-number">2</span> تنظیم دیتابیس
            </div>
            <div class="step-indicator <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">
                <span class="step-number">3</span> پیکربندی
            </div>
            <div class="step-indicator <?php echo $step >= 4 ? ($step > 4 ? 'completed' : 'active') : ''; ?>">
                <span class="step-number">4</span> لایسنس
            </div>
            <div class="step-indicator <?php echo $step >= 5 ? 'active' : ''; ?>">
                <span class="step-number">5</span> تکمیل
            </div>
        </div>

        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($step - 1) * 25; ?>%"></div></div>

        <!-- Step 1: Requirements Check -->
        <div class="step-content <?php echo $step === 1 ? 'active' : ''; ?>">
            <h2>📋 بررسی نیازمندی‌های سیستم</h2>
            <div class="card">
                <table>
                    <?php foreach (getSystemRequirements() as $req): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($req['label']); ?></td>
                        <td><span class="<?php echo $req['status'] === 'pass' ? 'check-pass' : ($req['status'] === 'warn' ? 'check-warn' : 'check-fail'); ?>"><?php echo htmlspecialchars($req['value']); ?> <?php echo $req['status'] === 'pass' ? '✅' : ($req['status'] === 'warn' ? '⚠️' : '❌'); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="check_requirements">
                <button type="submit" class="btn btn-block">ادامه →</button>
            </form>
        </div>

        <!-- Step 2: Database Setup -->
        <div class="step-content <?php echo $step === 2 ? 'active' : ''; ?>">
            <h2>🗄️ تنظیم دیتابیس</h2>
            <div class="card">
                <div class="form-group">
                    <label>نوع دیتابیس</label>
                    <select name="db_driver" onchange="toggleSqlite()">
                        <option value="mysql">MySQL / MariaDB</option>
                        <option value="sqlite">SQLite (فایل)</option>
                    </select>
                </div>
                <div id="mysql-fields">
                    <div class="form-group">
                        <label>میزبان (Host)</label>
                        <input type="text" name="db_host" value="localhost" placeholder="localhost">
                    </div>
                    <div class="form-group">
                        <label>نام دیتابیس</label>
                        <input type="text" name="db_name" value="booking_system" placeholder="booking_system">
                    </div>
                    <div class="flex">
                        <div class="form-group" style="flex:1">
                            <label>نام کاربری</label>
                            <input type="text" name="db_user" value="root" placeholder="root">
                        </div>
                        <div class="form-group" style="flex:1">
                            <label>رمز عبور</label>
                            <input type="password" name="db_pass" placeholder="password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>پیشوند جدول‌ها</label>
                        <input type="text" name="db_prefix" value="bbs_" placeholder="bbs_">
                    </div>
                </div>
                <div id="sqlite-fields" style="display:none">
                    <div class="form-group">
                        <label>مسیر فایل دیتابیس</label>
                        <input type="text" name="sqlite_path" value="<?php echo BBS_ROOT; ?>/database/booking.db" placeholder="/path/to/database.db">
                    </div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="setup_database">
                <button type="submit" class="btn btn-block">تنظیم و ادامه →</button>
            </form>
        </div>

        <!-- Step 3: Configuration -->
        <div class="step-content <?php echo $step === 3 ? 'active' : ''; ?>">
            <h2>⚙️ پیکربندی سیستم</h2>
            <div class="card">
                <h3 style="font-size:16px; margin-bottom:16px;">اطلاعات کسب و کار</h3>
                <div class="form-group">
                    <label>نام کسب و کار</label>
                    <input type="text" name="business_name" value="کلینیک من" placeholder="نام کلینیک / کسب و کار">
                </div>
                <div class="flex">
                    <div class="form-group" style="flex:1">
                        <label>تلفن</label>
                        <input type="text" name="business_phone" placeholder="02112345678">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>ایمیل</label>
                        <input type="email" name="business_email" placeholder="info@example.com">
                    </div>
                </div>
                <div class="form-group">
                    <label>آدرس</label>
                    <input type="text" name="business_address" placeholder="آدرس کامل">
                </div>
            </div>
            <div class="card">
                <h3 style="font-size:16px; margin-bottom:16px;">حالت نصب</h3>
                <div class="form-group">
                    <label>حالت استقرار</label>
                    <select name="deployment_mode">
                        <option value="standalone">مستقل (Standalone)</option>
                        <option value="saas">چند مستاجری (SaaS)</option>
                        <option value="mvp">سبک (MVP - مناسب هاست اشتراکی)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>واحد پول پیشفرض</label>
                    <select name="currency">
                        <option value="IRR">ریال (IRR)</option>
                        <option value="IRT">تومان (IRT)</option>
                        <option value="USD">دلار (USD)</option>
                        <option value="EUR">یورو (EUR)</option>
                    </select>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="save_config">
                <button type="submit" class="btn btn-block">ذخیره و ادامه →</button>
            </form>
        </div>

        <!-- Step 4: License Activation -->
        <div class="step-content <?php echo $step === 4 ? 'active' : ''; ?>">
            <h2>🔐 فعال‌سازی لایسنس</h2>
            <div class="card">
                <p style="color:#94a3b8; margin-bottom:16px;">لایسنس خود را وارد کنید یا از نسخه آزمایشی ۱۴ روزه استفاده کنید.</p>
                <div class="form-group">
                    <label>کلید لایسنس</label>
                    <input type="text" name="license_key" placeholder="LCS-XXXX-XXXX-XXXX.XXXXXXXX" style="font-family:monospace; direction:ltr; text-align:left;">
                </div>
                <div class="flex">
                    <button class="btn" onclick="activateLicense()">فعال‌سازی</button>
                    <button class="btn btn-secondary" onclick="skipLicense()">شروع نسخه آزمایشی</button>
                </div>
                <div id="license-result" style="margin-top:16px;"></div>
            </div>
            <form method="POST" id="license-form" style="display:none">
                <input type="hidden" name="action" value="activate_license">
                <input type="hidden" name="license_key" id="license-key-input">
            </form>
            <form method="POST" id="skip-license-form" style="display:none">
                <input type="hidden" name="action" value="activate_license">
                <input type="hidden" name="skip_trial" value="1">
            </form>
            <div class="mt-20 text-center">
                <button class="btn btn-block" onclick="document.getElementById('skip-license-form').submit()">ادامه بدون لایسنس (نسخه آزمایشی) →</button>
            </div>
            <script>
            function activateLicense() {
                const key = document.querySelector('input[name="license_key"]').value.trim();
                if (!key) { alert('لطفا کلید لایسنس را وارد کنید'); return; }
                document.getElementById('license-key-input').value = key;
                document.getElementById('license-form').submit();
            }
            function skipLicense() {
                document.getElementById('skip-license-form').submit();
            }
            </script>
        </div>

        <!-- Step 5: Complete -->
        <div class="step-content <?php echo $step === 5 ? 'active' : ''; ?>">
            <h2>🎉 نصب با موفقیت انجام شد!</h2>
            <div class="card text-center" style="padding:40px;">
                <div style="font-size:64px; margin-bottom:20px;">✅</div>
                <h3 style="margin-bottom:16px;">سیستم رزرو آنلاین شما آماده استفاده است!</h3>
                <div class="info">
                    <strong>مسیر پنل مدیریت:</strong><br>
                    <code><?php echo rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/admin</code>
                </div>
                <div class="info" style="margin-top:12px;">
                    <strong>نام کاربری:</strong> admin<br>
                    <strong>رمز عبور:</strong> admin123
                </div>
                <div style="margin-top:20px;">
                    <p style="color:#94a3b8; font-size:13px;">⚠️ لطفا رمز عبور پیشفرض را پس از اولین ورود تغییر دهید.</p>
                </div>
                <div class="mt-20">
                    <a href="admin" class="btn btn-success btn-block">ورود به پنل مدیریت</a>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="complete_install">
            </form>
        </div>
    </div>

    <script>
    function toggleSqlite() {
        const val = document.querySelector('select[name="db_driver"]').value;
        document.getElementById('mysql-fields').style.display = val === 'sqlite' ? 'none' : '';
        document.getElementById('sqlite-fields').style.display = val === 'sqlite' ? '' : 'none';
    }
    </script>
</body>
</html>
<?php
exit;

// ============================================================
// INSTALLER FUNCTIONS
// ============================================================

function getSystemRequirements(): array
{
    $requirements = [
        [
            'label' => 'نسخه PHP',
            'value' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'fail',
        ],
        [
            'label' => 'PDO Extension',
            'value' => class_exists('PDO') ? '✅ نصب است' : '❌ نصب نیست',
            'status' => class_exists('PDO') ? 'pass' : 'fail',
        ],
        [
            'label' => 'PDO MySQL',
            'value' => in_array('mysql', PDO::getAvailableDrivers()) ? '✅ نصب است' : '❌ نصب نیست',
            'status' => in_array('mysql', PDO::getAvailableDrivers()) ? 'pass' : 'warn',
        ],
        [
            'label' => 'PDO SQLite',
            'value' => in_array('sqlite', PDO::getAvailableDrivers()) ? '✅ نصب است' : '❌ نصب نیست',
            'status' => in_array('sqlite', PDO::getAvailableDrivers()) ? 'pass' : 'warn',
        ],
        [
            'label' => 'OpenSSL',
            'value' => extension_loaded('openssl') ? '✅ نصب است' : '❌ نصب نیست',
            'status' => extension_loaded('openssl') ? 'pass' : 'fail',
        ],
        [
            'label' => 'JSON',
            'value' => extension_loaded('json') ? '✅ نصب است' : '❌ نصب نیست',
            'status' => extension_loaded('json') ? 'pass' : 'fail',
        ],
        [
            'label' => 'MBString',
            'value' => extension_loaded('mbstring') ? '✅ نصب است' : '❌ نصب نیست',
            'status' => extension_loaded('mbstring') ? 'pass' : 'fail',
        ],
        [
            'label' => 'cURL',
            'value' => function_exists('curl_version') ? '✅ نصب است' : '❌ نصب نیست',
            'status' => function_exists('curl_version') ? 'pass' : 'warn',
        ],
        [
            'label' => 'GD / Imagick',
            'value' => extension_loaded('gd') || extension_loaded('imagick') ? '✅ نصب است' : '❌ نصب نیست',
            'status' => extension_loaded('gd') || extension_loaded('imagick') ? 'pass' : 'warn',
        ],
        [
            'label' => 'حافظه مجاز (memory_limit)',
            'value' => ini_get('memory_limit'),
            'status' => (int)ini_get('memory_limit') >= 64 || ini_get('memory_limit') === '-1' ? 'pass' : 'warn',
        ],
        [
            'label' => 'حداکثر آپلود',
            'value' => ini_get('upload_max_filesize'),
            'status' => 'pass',
        ],
        [
            'label' => 'قابلیت نوشتن فایل',
            'value' => is_writable(BBS_ROOT) ? '✅ قابل نوشتن' : '❌ غیرقابل نوشتن',
            'status' => is_writable(BBS_ROOT) ? 'pass' : 'fail',
        ],
    ];

    $allPass = true;
    foreach ($requirements as $req) {
        if ($req['status'] === 'fail') { $allPass = false; break; }
    }
    $_SESSION['requirements_pass'] = $allPass;

    return $requirements;
}

function checkRequirements(): void
{
    $requirements = getSystemRequirements();
    $_SESSION['requirements_pass'] = true;
    foreach ($requirements as $req) {
        if ($req['status'] === 'fail') {
            $_SESSION['requirements_pass'] = false;
            $_SESSION['install_error'] = 'برخی نیازمندی‌ها برآورده نشده‌اند: ' . $req['label'];
            break;
        }
    }
    if ($_SESSION['requirements_pass']) {
        header('Location: ?step=2');
        exit;
    }
    header('Location: ?step=1');
    exit;
}

function setupDatabase(): void
{
    $driver = $_POST['db_driver'] ?? 'mysql';
    $host = $_POST['db_host'] ?? 'localhost';
    $dbname = $_POST['db_name'] ?? 'booking_system';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $prefix = $_POST['db_prefix'] ?? 'bbs_';
    $sqlitePath = $_POST['sqlite_path'] ?? BBS_ROOT . '/database/booking.db';

    try {
        if ($driver === 'sqlite') {
            $dsn = "sqlite:{$sqlitePath}";
            $pdo = new PDO($dsn, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $_SESSION['db_config'] = ['driver' => 'sqlite', 'name' => $sqlitePath, 'prefix' => $prefix];
        } else {
            // Try to create database if not exists
            $tempPdo = new PDO("mysql:host={$host}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tempPdo = null;

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $_SESSION['db_config'] = ['driver' => 'mysql', 'host' => $host, 'name' => $dbname, 'user' => $user, 'pass' => $pass, 'prefix' => $prefix];
        }

        // Run schema
        $schemaFile = BBS_ROOT . '/database/schema.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            // Replace prefix placeholder
            if ($prefix) {
                $sql = preg_replace('/\{prefix\}/', $prefix, $sql);
            } else {
                $sql = str_replace('{prefix}', '', $sql);
            }
            $statements = explode(';', $sql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
        }

        $_SESSION['install_success'] = '✅ دیتابیس با موفقیت ساخته و جداول ایجاد شدند.';
        header('Location: ?step=3');
        exit;
    } catch (PDOException $e) {
        $_SESSION['install_error'] = 'خطا در اتصال به دیتابیس: ' . $e->getMessage();
        header('Location: ?step=2');
        exit;
    }
}

function saveConfig(): void
{
    $config = [
        'business_name' => $_POST['business_name'] ?? 'My Clinic',
        'business_phone' => $_POST['business_phone'] ?? '',
        'business_email' => $_POST['business_email'] ?? '',
        'business_address' => $_POST['business_address'] ?? '',
        'deployment_mode' => $_POST['deployment_mode'] ?? 'standalone',
        'currency' => $_POST['currency'] ?? 'IRR',
    ];
    $_SESSION['install_config'] = $config;

    // Write .env file
    $db = $_SESSION['db_config'];
    $env = [];
    $env[] = "APP_NAME=\"{$config['business_name']}\"";
    $env[] = 'APP_ENV=production';
    $env[] = 'APP_DEBUG=false';
    $env[] = "APP_MODE={$config['deployment_mode']}";
    $env[] = "DB_DRIVER={$db['driver']}";
    $env[] = "DB_HOST={$db['host']}";
    $env[] = "DB_NAME={$db['name']}";
    $env[] = "DB_USER={$db['user']}";
    $env[] = "DB_PASS={$db['pass']}";
    $env[] = "DB_PREFIX={$db['prefix']}";
    $env[] = "CURRENCY={$config['currency']}";
    $env[] = 'APP_KEY=' . bin2hex(random_bytes(32));

    file_put_contents(BBS_ROOT . '/.env', implode("\n", $env) . "\n");

    $_SESSION['install_success'] = '✅ تنظیمات با موفقیت ذخیره شدند.';
    header('Location: ?step=4');
    exit;
}

function activateLicense(): void
{
    if (isset($_POST['skip_trial'])) {
        $_SESSION['install_success'] = '✅ نسخه آزمایشی فعال شد.';
        $_SESSION['license_activated'] = true;
        header('Location: ?step=5');
        exit;
    }

    $licenseKey = $_POST['license_key'] ?? '';
    if (empty($licenseKey)) {
        $_SESSION['install_error'] = 'لطفا کلید لایسنس را وارد کنید.';
        header('Location: ?step=4');
        exit;
    }

    // Local validation (can also call license server)
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    $parts = explode('.', $licenseKey);
    if (count($parts) < 2) {
        $_SESSION['install_error'] = 'فرمت کلید لایسنس نامعتبر است.';
        header('Location: ?step=4');
        exit;
    }

    $_SESSION['install_success'] = '✅ لایسنس با موفقیت فعال شد.';
    $_SESSION['license_activated'] = true;
    $_SESSION['license_key'] = $licenseKey;
    header('Location: ?step=5');
    exit;
}

function completeInstall(): void
{
    // Write install.lock
    file_put_contents(BBS_ROOT . '/storage/install.lock', date('Y-m-d H:i:s'));
    $_SESSION['install_success'] = '✅ نصب کامل شد.';
    header('Location: ' . rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/') . '/');
    exit;
}
