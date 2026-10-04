<?php
// Prevent direct web access if executed outside of an entry point
if (php_sapi_name() !== 'cli' && basename($_SERVER['PHP_SELF']) === 'bootstrap.php') {
    http_response_code(403);
    exit('Forbidden');
}

// 1. Set base constant
define('APP_ROOT', dirname(__DIR__));

// 2. Load Configuration
$configPath = APP_ROOT . '/config/config.php';
if (!file_exists($configPath)) {
    die("Configuration file missing. Please create config/config.php from config.example.php");
}
$config = require $configPath;

// Helper function to easily access config values
if (!function_exists('config')) {
    function config(string $key, $default = null) {
        global $config;
        $parts = explode('.', $key);
        $val = $config;
        foreach ($parts as $part) {
            if (!is_array($val) || !array_key_exists($part, $val)) {
                return $default;
            }
            $val = $val[$part];
        }
        return $val;
    }
}

// 3. Set Timezone
date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

// 4. Configure Error Handling
if (config('app.env') === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    // You could also set up a custom error logger here
}

// 5. Instantiate Shared PDO Connection
try {
    $dbHost = config('db.host', '127.0.0.1');
    $dbName = config('db.name', 'fika_unified');
    $dbUser = config('db.user', 'root');
    $dbPass = config('db.pass', '');
    $dbCharset = config('db.charset', 'utf8mb4');

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
    
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
    ]);
} catch (\PDOException $e) {
    if (config('app.env') === 'local') {
        die("Database Connection Error: " . $e->getMessage());
    } else {
        die("Database Connection Error. Please check your configuration.");
    }
}

// 6. Set up basic PSR-4 style Autoloader for the `app/` directory
spl_autoload_register(function ($class) {
    // We assume classes are directly under `app/` and follow PSR-4 naming loosely
    // e.g., 'Core\Security' -> 'app/core/Security.php'
    // 'Payroll\SalaryCalculator' -> 'app/payroll/SalaryCalculator.php'
    
    // Normalize namespace to directory separator
    $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    // Lowercase the top-level directory names to match our folder structure (core, payroll, pos, inventory)
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    if (count($parts) > 1) {
        $parts[0] = strtolower($parts[0]);
        $path = implode(DIRECTORY_SEPARATOR, $parts);
    }
    
    $file = APP_ROOT . '/app/' . $path . '.php';
    $coreFile = APP_ROOT . '/app/core/' . $path . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    } elseif (file_exists($coreFile)) {
        require_once $coreFile;
    }
});
