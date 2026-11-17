<?php
date_default_timezone_set('Asia/Manila');

// In PHP 8.1+, mysqli throws exceptions by default. Turn it off so queries return false instead of fatal crash.
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

if (!is_dir(__DIR__ . '/db')) {
    @mkdir(__DIR__ . '/db', 0777, true);
}

if (!defined('host')) define('host', getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: 'localhost'));
if (!defined('username')) define('username', getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root'));
if (!defined('password')) define('password', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''));
if (!defined('database')) define('database', getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'messaging_db'));
if (!defined('port')) define('port', intval(getenv('DB_PORT') ?: 3306));

class DBConnection {
    public $conn = null;
    public $error = null;
    protected $host;
    protected $username;
    protected $password;
    protected $database;
    protected $port;
    protected $use_ssl;

    public function __construct() {
        $this->host = getenv('DB_SERVER') ?: (getenv('DB_HOST') ?: (defined('host') ? host : 'localhost'));
        $this->username = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: (defined('username') ? username : 'root'));
        $this->password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : (defined('password') ? password : ''));
        $this->database = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: (defined('database') ? database : 'messaging_db'));
        $this->port = intval(getenv('DB_PORT') ?: (defined('port') ? port : 3306));
        
        $ssl_env = getenv('DB_SSL');
        if ($ssl_env !== false && $ssl_env !== '') {
            $this->use_ssl = in_array(strtolower(trim($ssl_env)), ['true', '1', 'yes', 'require', 'on'], true);
        } else {
            // Auto-detect Aiven or cloud MySQL custom ports (e.g. != 3306 or aivencloud.com)
            $this->use_ssl = ($this->port !== 3306) || (strpos($this->host, 'aivencloud.com') !== false);
        }

        $this->connect();
    }

    public function connect() {
        try {
            $this->conn = mysqli_init();
            if (!$this->conn) {
                $this->error = "mysqli_init initialization failed.";
                return false;
            }

            $flags = 0;
            if ($this->use_ssl) {
                if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
                    @$this->conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
                }
                @$this->conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
                $flags |= MYSQLI_CLIENT_SSL;
            }

            @$this->conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

            $connected = @$this->conn->real_connect(
                $this->host,
                $this->username,
                $this->password,
                $this->database,
                $this->port,
                NULL,
                $flags
            );

            if (!$connected) {
                $this->error = $this->conn->connect_error ?: (mysqli_connect_error() ?: "Unable to connect to database ({$this->host}:{$this->port})");
                // Fallback attempt without SSL if host is localhost/127.0.0.1
                if ($this->use_ssl && ($this->host === 'localhost' || $this->host === '127.0.0.1')) {
                    $this->conn = mysqli_init();
                    @$this->conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                    $fallback = @$this->conn->real_connect($this->host, $this->username, $this->password, $this->database, $this->port);
                    if ($fallback) {
                        $this->error = null;
                        $this->use_ssl = false;
                        @$this->conn->set_charset("utf8mb4");
                        return true;
                    }
                }
                return false;
            }

            @$this->conn->set_charset("utf8mb4");
            $this->error = null;
            return true;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function autoFixSchema() {
        if (!$this->conn) return false;
        $sql_paths = [
            __DIR__ . '/db/messaging_db.sql',
            __DIR__ . '/messaging/db/messaging_db.sql'
        ];
        $sql_file = null;
        foreach ($sql_paths as $p) {
            if (file_exists($p)) {
                $sql_file = $p;
                break;
            }
        }
        if (!$sql_file) return false;

        $sql_content = file_get_contents($sql_file);
        $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content);

        @$this->conn->query("SET FOREIGN_KEY_CHECKS = 0;");
        @$this->conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';");
        @$this->conn->query("DROP TABLE IF EXISTS `convo_list`;");
        @$this->conn->query("DROP TABLE IF EXISTS `messages`;");
        @$this->conn->query("DROP TABLE IF EXISTS `users`;");

        $lines = explode("\n", $sql_content);
        $clean_sql = "";
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $clean_sql .= $line . "\n";
        }
        $statements = preg_split('/;\s*(\n|$)/', $clean_sql);
        foreach ($statements as $st) {
            $st = trim($st);
            if (!empty($st)) {
                @$this->conn->query($st);
            }
        }
        @$this->conn->query("SET FOREIGN_KEY_CHECKS = 1;");
        return true;
    }

    public function isMobileDevice() {
        $aMobileUA = array(
            '/iphone/i' => 'iPhone', 
            '/ipod/i' => 'iPod', 
            '/ipad/i' => 'iPad', 
            '/android/i' => 'Android', 
            '/blackberry/i' => 'BlackBerry', 
            '/webos/i' => 'Mobile'
        );
    
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            foreach ($aMobileUA as $sMobileKey => $sMobileOS) {
                if (preg_match($sMobileKey, $_SERVER['HTTP_USER_AGENT'])) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getHost() { return $this->host; }
    public function getPort() { return $this->port; }
    public function getDatabase() { return $this->database; }
    public function getUsername() { return $this->username; }
    public function isSslEnabled() { return $this->use_ssl; }

    public function __destruct() {
        if ($this->conn && $this->conn instanceof mysqli && @$this->conn->ping()) {
            @$this->conn->close();
        }
    }
}

$mydb = new DBConnection();
$conn = $mydb->conn;
