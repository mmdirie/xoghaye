<?php
// ==========================================================
// XOGHAYE – RESULT MANAGER
// University Student & Result Management System
// Database Configuration & Connection (PDO)
// ==========================================================

// Official Application Information
define('APP_NAME', 'XOGHAYE – RESULT MANAGER');
define('APP_SHORT_NAME', 'XOGHAYE');
define('APP_TAGLINE', 'Smart Education, Better Management.');
define('APP_VERSION', '2.0.0');

// Database Credentials (Default XAMPP setup)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'campuspro_db'); // Existing MySQL database
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Get the PDO Database Connection instance (Singleton pattern)
 * 
 * @return PDO
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Database Connection Error - <?php echo APP_NAME; ?></title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body class="bg-light d-flex align-items-center min-vh-100">
                <div class="container">
                    <div class="row justify-content-center">
                        <div class="col-md-7">
                            <div class="card shadow-sm border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h4 class="mb-0"><i class="bi bi-exclamation-triangle-fill"></i> Database Connection Failed</h4>
                                </div>
                                <div class="card-body">
                                    <p class="lead">Could not connect to the database <strong><?php echo htmlspecialchars(DB_NAME); ?></strong>.</p>
                                    <hr>
                                    <h6>Troubleshooting Steps for XAMPP:</h6>
                                    <ol>
                                        <li>Ensure <strong>Apache</strong> and <strong>MySQL</strong> are started in the XAMPP Control Panel.</li>
                                        <li>Verify database exists. Import <code>database/xoghaye_db.sql</code> via phpMyAdmin or MySQL CLI if needed.</li>
                                        <li>Check <code>config/database.php</code> credentials (default: user <code>root</code> with empty password).</li>
                                    </ol>
                                    <div class="alert alert-secondary mt-3">
                                        <small><strong>Error details:</strong> <?php echo htmlspecialchars($e->getMessage()); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }

    return $pdo;
}

/**
 * Get dynamic Base URL for reliable asset and route referencing
 * Works seamlessly across root, subfolders, virtual hosts, and junctions
 * 
 * @param string $path
 * @return string
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $knownDirs = ['/admin/', '/teacher/', '/student/', '/auth/', '/includes/', '/config/', '/database/', '/assets/'];
    $subPath = null;
    
    foreach ($knownDirs as $dir) {
        $pos = strpos($script, $dir);
        if ($pos !== false) {
            $subPath = substr($script, 0, $pos);
            break;
        }
    }
    
    if ($subPath === null && !empty($script)) {
        $dir = str_replace('\\', '/', dirname($script));
        $subPath = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }
    
    $subPath = $subPath ?? '';
    $base = rtrim($protocol . $host . $subPath, '/');
    $path = ltrim($path, '/');
    
    return $path === '' ? $base : $base . '/' . $path;
}
