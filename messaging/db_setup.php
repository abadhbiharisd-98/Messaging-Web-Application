<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration from Environment or defaults
$db_host = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: 'localhost');
$db_port = intval(getenv('DB_PORT') ?: 3306);
$db_user = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root');
$db_pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$db_name = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'messaging_db');
$db_ssl  = getenv('DB_SSL');

$use_ssl = false;
if ($db_ssl !== false && $db_ssl !== '') {
    $use_ssl = in_array(strtolower(trim($db_ssl)), ['true', '1', 'yes', 'require', 'on'], true);
} else {
    $use_ssl = ($db_port !== 3306) || (strpos($db_host, 'aivencloud.com') !== false);
}

// Check custom test parameters if submitted via POST
if (isset($_POST['action']) && $_POST['action'] === 'test_custom') {
    $db_host = trim($_POST['test_host'] ?? $db_host);
    $db_port = intval($_POST['test_port'] ?? $db_port);
    $db_user = trim($_POST['test_user'] ?? $db_user);
    $db_pass = $_POST['test_pass'] ?? $db_pass;
    $db_name = trim($_POST['test_name'] ?? $db_name);
    $use_ssl = isset($_POST['test_ssl']);
}

// Function to establish connection
function get_db_connection($host, $user, $pass, $db, $port, $ssl) {
    $conn = mysqli_init();
    if (!$conn) {
        return ['conn' => null, 'error' => 'mysqli_init failed'];
    }
    $flags = 0;
    if ($ssl) {
        if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
            @$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        }
        @$conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $flags |= MYSQLI_CLIENT_SSL;
    }
    @$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    $connected = @$conn->real_connect($host, $user, $pass, $db, $port, NULL, $flags);
    if (!$connected) {
        $error = $conn->connect_error ?: mysqli_connect_error();
        // Fallback for localhost
        if ($ssl && ($host === 'localhost' || $host === '127.0.0.1')) {
            $conn2 = mysqli_init();
            if (@$conn2->real_connect($host, $user, $pass, $db, $port)) {
                $conn2->set_charset("utf8mb4");
                return ['conn' => $conn2, 'error' => null, 'ssl_used' => false];
            }
        }
        return ['conn' => null, 'error' => $error];
    }
    @$conn->set_charset("utf8mb4");
    return ['conn' => $conn, 'error' => null, 'ssl_used' => $ssl];
}

$conn_result = get_db_connection($db_host, $db_user, $db_pass, $db_name, $db_port, $use_ssl);
$conn = $conn_result['conn'];
$conn_error = $conn_result['error'];

// Locate SQL File
$sql_file_paths = [
    __DIR__ . '/db/messaging_db.sql',
    __DIR__ . '/messaging/db/messaging_db.sql',
    __DIR__ . '/../db/messaging_db.sql'
];
$found_sql_file = null;
foreach ($sql_file_paths as $path) {
    if (file_exists($path)) {
        $found_sql_file = $path;
        break;
    }
}

// Handle 1-Click Import Action
$import_status = null;
$import_message = null;
$queries_count = 0;

if (isset($_POST['action']) && $_POST['action'] === 'import_sql') {
    if (!$conn) {
        $import_status = 'error';
        $import_message = "Cannot import: Database connection failed. Error: " . $conn_error;
    } elseif (!$found_sql_file) {
        $import_status = 'error';
        $import_message = "SQL Dump file (messaging_db.sql) could not be located on the server.";
    } else {
        $sql_content = file_get_contents($found_sql_file);
        
        // Temporarily disable foreign keys and auto-commit
        $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        $conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
        
        // Clean and parse SQL into individual statements
        $lines = explode("\n", $sql_content);
        $clean_sql = "";
        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Skip comments and empty lines
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '//')) {
                continue;
            }
            $clean_sql .= $line . "\n";
        }

        $statements = explode(";\n", $clean_sql);
        $errors = [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $q = @$conn->query($statement);
                if (!$q) {
                    // Ignore benign drops/mode errors
                    if (!str_contains($conn->error, 'already exists') && !str_contains($conn->error, 'Unknown system variable')) {
                        $errors[] = $conn->error;
                    }
                } else {
                    $queries_count++;
                }
            }
        }

        $conn->query("SET FOREIGN_KEY_CHECKS = 1;");

        if (count($errors) === 0 || $queries_count > 5) {
            $import_status = 'success';
            $import_message = "Database imported successfully! Executed " . $queries_count . " queries.";
        } else {
            $import_status = 'warning';
            $import_message = "Import completed with " . count($errors) . " notices. Queries executed: " . $queries_count . ". First notice: " . ($errors[0] ?? '');
        }
    }
}

// Inspect tables if connected
$existing_tables = [];
$admin_exists = false;
$user_count = 0;
$messages_count = 0;

if ($conn) {
    $tables_res = $conn->query("SHOW TABLES");
    if ($tables_res && $tables_res instanceof mysqli_result) {
        while ($trow = $tables_res->fetch_array()) {
            $existing_tables[] = $trow[0];
        }
    }

    if (in_array('users', $existing_tables)) {
        $ures = $conn->query("SELECT COUNT(*) FROM `users`");
        if ($ures && $ures instanceof mysqli_result) {
            $user_count = $ures->fetch_row()[0];
        }
        $admin_check = $conn->query("SELECT * FROM `users` WHERE `email` = 'jsmith@sample.com'");
        if ($admin_check && $admin_check instanceof mysqli_result && $admin_check->num_rows > 0) {
            $admin_exists = true;
        }
    }

    if (in_array('messages', $existing_tables)) {
        $mres = $conn->query("SELECT COUNT(*) FROM `messages`");
        if ($mres && $mres instanceof mysqli_result) {
            $messages_count = $mres->fetch_row()[0];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aiven MySQL & Database Setup | Messaging App</title>
    <link rel="stylesheet" href="./fontawesome/css/all.min.css">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .header-bar {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: #fff;
            padding: 24px 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin-bottom: 24px;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #edf2f7;
            font-weight: 600;
            padding: 16px 20px;
        }
        .badge-pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .code-pill {
            background-color: #e9ecef;
            padding: 2px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9em;
        }
    </style>
</head>
<body>

<div class="header-bar mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h2 class="h4 mb-1"><i class="fa fa-database text-warning me-2"></i> Aiven MySQL Cloud Database Setup</h2>
            <small class="opacity-75">1-Click Database Installer & Connectivity Health Check for Render</small>
        </div>
        <div>
            <a href="./index.php" class="btn btn-outline-light btn-sm"><i class="fa fa-arrow-left me-1"></i> Back to App</a>
        </div>
    </div>
</div>

<div class="container pb-5">
    <?php if ($import_status === 'success'): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <h5 class="alert-heading"><i class="fa fa-check-circle me-2"></i> Import Succeeded!</h5>
            <p class="mb-0"><?php echo htmlspecialchars($import_message); ?></p>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <span>All default tables and the default admin user are ready.</span>
                <a href="./login.php" class="btn btn-success btn-sm"><i class="fa fa-sign-in-alt me-1"></i> Proceed to Login</a>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($import_status === 'warning'): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm" role="alert">
            <h5 class="alert-heading"><i class="fa fa-exclamation-triangle me-2"></i> Notice during Import</h5>
            <p class="mb-0"><?php echo htmlspecialchars($import_message); ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($import_status === 'error'): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <h5 class="alert-heading"><i class="fa fa-times-circle me-2"></i> Import Failed</h5>
            <p class="mb-0"><?php echo htmlspecialchars($import_message); ?></p>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Connection Status Card -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-server text-primary me-2"></i> Database Connection Status</span>
                    <?php if ($conn): ?>
                        <span class="badge bg-success"><i class="fa fa-check-circle me-1"></i> Connected</span>
                    <?php else: ?>
                        <span class="badge bg-danger badge-pulse"><i class="fa fa-exclamation-circle me-1"></i> Disconnected</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$conn): ?>
                        <div class="alert alert-danger mb-3">
                            <strong>Connection Error:</strong><br>
                            <code><?php echo htmlspecialchars($conn_error ?: 'Unknown error'); ?></code>
                            <div class="mt-2 small">
                                <strong>Troubleshooting Tips:</strong>
                                <ul class="mb-0 ps-3">
                                    <li>Check if your Aiven MySQL cluster is running and public access is enabled.</li>
                                    <li>Verify environment variables: <code>DB_SERVER</code>, <code>DB_PORT</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>, <code>DB_NAME</code>.</li>
                                    <li>Aiven requires SSL: Ensure <code>DB_SSL=true</code>.</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <table class="table table-bordered table-sm mb-3">
                        <tbody>
                            <tr>
                                <th class="w-40 bg-light">Host (DB_SERVER)</th>
                                <td><span class="code-pill"><?php echo htmlspecialchars($db_host); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Port (DB_PORT)</th>
                                <td><span class="code-pill"><?php echo htmlspecialchars($db_port); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Database (DB_NAME)</th>
                                <td><span class="code-pill"><?php echo htmlspecialchars($db_name); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Username (DB_USERNAME)</th>
                                <td><span class="code-pill"><?php echo htmlspecialchars($db_user); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light">Password</th>
                                <td><?php echo !empty($db_pass) ? '<span class="text-success"><i class="fa fa-check"></i> Set</span>' : '<span class="text-muted"><i class="fa fa-times"></i> Empty</span>'; ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light">SSL Encryption</th>
                                <td>
                                    <?php if ($use_ssl): ?>
                                        <span class="badge bg-primary"><i class="fa fa-lock me-1"></i> Enabled (MYSQLI_CLIENT_SSL)</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fa fa-unlock me-1"></i> Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($conn): ?>
                            <tr>
                                <th class="bg-light">Server Version</th>
                                <td><span class="text-muted"><?php echo htmlspecialchars($conn->server_info); ?></span></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="d-flex gap-2">
                        <a href="./db_setup.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-sync-alt me-1"></i> Refresh Status</a>
                        <?php if ($conn): ?>
                            <a href="./login.php" class="btn btn-outline-primary btn-sm"><i class="fa fa-external-link-alt me-1"></i> Open Login Page</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1-Click Import Card -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-file-import text-success me-2"></i> 1-Click Database Setup</span>
                    <span class="badge bg-info text-dark">SQL Importer</span>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <p class="text-muted small">
                            Automatically import the initial database tables (<code>users</code>, <code>messages</code>, <code>convo_list</code>) and default accounts into your Aiven MySQL cloud instance.
                        </p>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">SQL Dump File:</label>
                            <?php if ($found_sql_file): ?>
                                <div class="p-2 border rounded bg-light small">
                                    <i class="fa fa-file-code text-primary me-2"></i>
                                    <code><?php echo htmlspecialchars(basename($found_sql_file)); ?></code>
                                    <span class="badge bg-secondary ms-2"><?php echo round(filesize($found_sql_file) / 1024, 1); ?> KB</span>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger py-2 small mb-0">
                                    <i class="fa fa-exclamation-triangle me-1"></i> SQL file <code>messaging_db.sql</code> not found in <code>db/</code> folder.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Existing Tables in Database:</label>
                            <div>
                                <?php if (count($existing_tables) > 0): ?>
                                    <?php foreach ($existing_tables as $table): ?>
                                        <span class="badge bg-success me-1 mb-1"><i class="fa fa-table me-1"></i> <?php echo htmlspecialchars($table); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">No tables found (Database is empty).</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($admin_exists): ?>
                            <div class="alert alert-success py-2 small mb-3">
                                <i class="fa fa-user-shield me-1"></i> <strong>Admin User Verified:</strong> <code>jsmith@sample.com</code> is present in the database!
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <form method="POST" onsubmit="return confirm('Do you want to import/reset the database with the schema dump?');">
                            <input type="hidden" name="action" value="import_sql">
                            <button type="submit" class="btn btn-success w-100 py-2 fw-bold" <?php echo (!$conn || !$found_sql_file) ? 'disabled' : ''; ?>>
                                <i class="fa fa-cloud-upload-alt me-2"></i> 1-Click Import Database to Aiven
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Default Credentials and Live Testing -->
    <div class="row mt-2">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white">
                    <i class="fa fa-key text-warning me-2"></i> Default Application Credentials
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Once imported, you can log in with these pre-seeded demo/admin credentials:</p>
                    <table class="table table-sm table-bordered mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Role</th>
                                <th>Email / Username</th>
                                <th>Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-primary">Admin / User 1</span></td>
                                <td><code>jsmith@sample.com</code></td>
                                <td><code>jsmith</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-secondary">User 2</span></td>
                                <td><code>cblake@sample.com</code></td>
                                <td><code>cblake</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-secondary">User 3</span></td>
                                <td><code>slou@sample.com</code></td>
                                <td><code>slou</code></td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-secondary">User 4</span></td>
                                <td><code>mwilliams@sample.com</code></td>
                                <td><code>mwilliams</code></td>
                            </tr>
                        </tbody>
                    </table>
                    <a href="./login.php" class="btn btn-primary btn-sm"><i class="fa fa-sign-in-alt me-1"></i> Go to Login Page</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white">
                    <i class="fa fa-cogs text-info me-2"></i> Live Connection Tester (Manual Override)
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test_custom">
                        <div class="row g-2 mb-2">
                            <div class="col-8">
                                <label class="form-label small">Host</label>
                                <input type="text" name="test_host" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_host); ?>" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label small">Port</label>
                                <input type="number" name="test_port" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_port); ?>" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label small">Database</label>
                                <input type="text" name="test_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_name); ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small">Username</label>
                                <input type="text" name="test_user" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_user); ?>" required>
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-8">
                                <label class="form-label small">Password</label>
                                <input type="password" name="test_pass" class="form-control form-control-sm" value="<?php echo htmlspecialchars($db_pass); ?>">
                            </div>
                            <div class="col-4 d-flex align-items-end">
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="test_ssl" id="test_ssl" <?php echo $use_ssl ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="test_ssl">Use SSL</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-info btn-sm w-100"><i class="fa fa-plug me-1"></i> Test This Connection</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center py-3 text-muted small border-top bg-white">
    Messaging Web Application &bull; Render & Aiven Cloud Deployment Ready &bull; PHP 8.x Compatible
</footer>

<script src="./js/bootstrap.min.js"></script>
</body>
</html>
