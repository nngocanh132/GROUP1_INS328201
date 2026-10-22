<?php
/**
 * Database Connection — PDO singleton
 */
require_once __DIR__ . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (DEV_MODE) {
            error_log('DB connection failed: ' . $e->getMessage());
        }
        // Render a clean error instead of a raw PHP exception
        http_response_code(503);
        die(render_db_error());
    }
    return $pdo;
}

function render_db_error(): string {
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Database Unavailable — Vinamilk CDM</title>
<style>body{font-family:system-ui,sans-serif;background:#f4f6f9;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}
.box{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:48px;max-width:480px;text-align:center}
h2{color:#1e3a5f;margin:0 0 12px}p{color:#64748b;font-size:14px}</style>
</head><body><div class="box">
<h2>Database Unavailable</h2>
<p>The application cannot connect to the database. Please ensure MySQL is running in XAMPP and the database <strong>vinamilk_clm</strong> has been created.</p>
</div></body></html>';
}

/**
 * Convenience: run a query with bound params, return statement.
 */
function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
