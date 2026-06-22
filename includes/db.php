<?php
/**
 * MySQL connection + prepared-statement helpers.
 *
 * Every read/write in the app flows through these helpers.
 * Zero raw mysqli_query() calls anywhere else in the codebase.
 */

declare(strict_types=1);

/* ---------------- configuration ---------------- */

// Prefer app-specific DB_* variables, then Railway's native MySQL variables,
// and finally the local XAMPP defaults.
if (!defined('DB_HOST'))  define('DB_HOST',  getenv('DB_HOST') ?: getenv('MYSQLHOST') ?: '127.0.0.1');
if (!defined('DB_USER'))  define('DB_USER',  getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root');
if (!defined('DB_PASS'))  define('DB_PASS',  getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: '');
if (!defined('DB_NAME'))  define('DB_NAME',  getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'csf_portal');
if (!defined('DB_PORT'))  define('DB_PORT',  (int)(getenv('DB_PORT') ?: getenv('MYSQLPORT') ?: 3306));
if (!defined('APP_ENV'))  define('APP_ENV',  getenv('APP_ENV')  ?: 'local'); // 'local' | 'production'

/* ---------------- singleton connection ---------------- */

/**
 * Returns the process-wide mysqli connection (lazy-init).
 */
function db(): mysqli
{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if (!$conn) {
        // Never leak credentials in the error message
        $err  = 'Database connection failed.';
        $code = mysqli_connect_errno();
        if (APP_ENV === 'local') {
            $err .= ' (' . $code . ': ' . htmlspecialchars(mysqli_connect_error()) . ')';
        }
        error_log('[db] connect failed: ' . $code . ' ' . mysqli_connect_error());
        http_response_code(500);
        exit($err);
    }

    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_query($conn, "SET time_zone = '+05:30'");
    mysqli_query($conn, "SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");

    return $conn;
}

/* ---------------- parameter binding helpers ---------------- */

/**
 * Build the type string for bind_param() from a list of values.
 * Supports: int, float, string, null, bool, blob (b).
 */
function db_types(array $params): string
{
    $types = '';
    foreach ($params as $p) {
        if (is_int($p))         $types .= 'i';
        elseif (is_float($p))   $types .= 'd';
        elseif (is_bool($p))    $types .= 'i';
        elseif (is_null($p))    $types .= 's'; // nulls are bound as string 'NULL'
        elseif (is_string($p))  $types .= 's';
        else                    $types .= 's';
    }
    return $types;
}

/**
 * Prepare + bind + execute. Returns the mysqli_stmt on success.
 * Throws RuntimeException on failure (caller decides how to respond).
 */
function db_query(string $sql, array $params = [], ?string $types = null): mysqli_stmt
{
    $stmt = mysqli_prepare(db(), $sql);
    if (!$stmt) {
        $err = mysqli_error(db());
        error_log("[db] prepare failed: $err | sql=$sql");
        throw new RuntimeException('Database error (prepare).');
    }
    if (!empty($params)) {
        $types  = $types ?? db_types($params);
        // spread by reference is required by bind_param
        $bind = [$types];
        foreach ($params as $k => $v) $bind[] = &$params[$k];
        if (!@call_user_func_array([$stmt, 'bind_param'], $bind)) {
            $err = $stmt->error ?: 'unknown bind_param error';
            error_log("[db] bind_param failed: $err | sql=$sql");
            mysqli_stmt_close($stmt);
            throw new RuntimeException('Database error (bind).');
        }
    }
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        error_log("[db] execute failed: $err | sql=$sql");
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Database error (execute).');
    }
    return $stmt;
}

/**
 * SELECT — returns an array of associative rows.
 */
function db_select(string $sql, array $params = [], ?string $types = null): array
{
    $stmt = db_query($sql, $params, $types);
    $res  = mysqli_stmt_get_result($stmt);
    $rows = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * SELECT — returns one associative row, or null.
 */
function db_one(string $sql, array $params = [], ?string $types = null): ?array
{
    // Strip trailing whitespace + semicolons before checking for a LIMIT clause,
    // so a query that already ends in LIMIT 1 doesn't get a second one appended.
    $trimmed = rtrim(trim($sql), ';');
    if (!preg_match('/\bLIMIT\s+\d+/i', $trimmed)) {
        $trimmed .= ' LIMIT 1';
    }
    $rows = db_select($trimmed, $params, $types);
    return $rows[0] ?? null;
}

/**
 * INSERT / UPDATE / DELETE — returns affected rows.
 */
function db_execute(string $sql, array $params = [], ?string $types = null): int
{
    $stmt = db_query($sql, $params, $types);
    $n    = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/**
 * INSERT — returns last insert id.
 */
function db_insert(string $sql, array $params = [], ?string $types = null): int
{
    $stmt = db_query($sql, $params, $types);
    $id   = mysqli_insert_id(db());
    mysqli_stmt_close($stmt);
    return $id;
}
