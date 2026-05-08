<?php
/**
 * Centralized error & crash handling.
 *
 * Loaded from config.php on every page. Intent:
 *   - Notices / Warnings get logged but never break the user-facing page
 *     (PHP defaults to displaying them, which can corrupt JSON responses,
 *     broken redirects via header() after stray output, etc.).
 *   - Fatal errors and uncaught exceptions render a friendly error card
 *     instead of leaving the user with a blank white screen.
 *   - AJAX/JSON callers get a JSON error envelope instead of HTML.
 *   - Everything is appended to logs/php_errors.log for post-mortem.
 */

if (defined('PLACEMENT_ERROR_HANDLER_INSTALLED')) return;
define('PLACEMENT_ERROR_HANDLER_INSTALLED', true);

// Always log; never display in the page body. The shutdown handler renders a
// dedicated card for fatals so users see a controlled message.
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
error_reporting(E_ALL);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }
@ini_set('error_log', $logDir . '/php_errors.log');

function _placement_is_ajax_request(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
}

function _placement_render_error_page(string $headline, string $detail = '', bool $friendly = false): void {
    if (headers_sent()) {
        echo "\n<!-- placement error: $headline -->\n";
    }
    if (_placement_is_ajax_request()) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['error' => $headline, 'detail' => $detail]);
        return;
    }
    if (!headers_sent()) {
        http_response_code($friendly ? 503 : 500);
        header('Content-Type: text/html; charset=utf-8');
        if ($friendly) header('Retry-After: 30');
    }
    $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
    $safeDetail   = htmlspecialchars($detail,   ENT_QUOTES, 'UTF-8');
    $title = $friendly ? 'Service temporarily unavailable' : 'Something went wrong';
    echo "<!doctype html><html lang='en'><head><meta charset='utf-8'>
        <title>$title</title>
        <meta name='viewport' content='width=device-width,initial-scale=1'>
        <style>
            body{font-family:system-ui,Segoe UI,Arial,sans-serif;background:#faf7f7;margin:0;padding:40px;color:#222}
            .card{max-width:640px;margin:60px auto;background:#fff;border:1px solid #e7d4d4;border-radius:10px;padding:32px;box-shadow:0 4px 18px rgba(0,0,0,0.06)}
            h1{color:#650000;margin:0 0 12px;font-size:22px}
            p{line-height:1.55;margin:0 0 12px}
            .muted{color:#666;font-size:14px}
            .detail{margin-top:12px;padding:10px 12px;background:#fff5f5;border-left:4px solid #650000;border-radius:4px;font-family:Menlo,Consolas,monospace;font-size:12px;color:#5a2020;white-space:pre-wrap;word-break:break-word}
            .btn{display:inline-block;margin-top:18px;margin-right:8px;padding:8px 18px;background:#650000;color:#fff;text-decoration:none;border:0;border-radius:6px;font-size:14px;cursor:pointer}
            .btn:hover{background:#7d0000}
            .btn.secondary{background:#fff;color:#650000;border:1px solid #650000}
            .btn.secondary:hover{background:#fff5f5}
        </style></head><body>
        <div class='card'>";
    if ($friendly) {
        echo "<h1>$safeHeadline</h1>";
        if ($safeDetail !== '') {
            echo "<p>$safeDetail</p>";
        }
        echo "<p class='muted'>This usually clears up within a few seconds.</p>
            <button class='btn' onclick='location.reload()'>Refresh page</button>
            <a class='btn secondary' href='dashboard.php'>Back to Dashboard</a>";
    } else {
        echo "<h1>Something went wrong on this page.</h1>
            <p>The team has been notified and the error was logged. You can try refreshing the page or going back to the dashboard.</p>";
        if ($safeDetail !== '') {
            echo "<div class='detail'>$safeHeadline\n\n$safeDetail</div>";
        } else {
            echo "<div class='detail'>$safeHeadline</div>";
        }
        echo "<a class='btn' href='dashboard.php'>Back to Dashboard</a>";
    }
    echo "</div></body></html>";
}

// Convert non-fatal errors to log entries; suppressed (@) errors are skipped
// because PHP signals them via error_reporting() == 0.
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    $type = [
        E_NOTICE             => 'NOTICE',
        E_WARNING            => 'WARNING',
        E_USER_NOTICE        => 'USER_NOTICE',
        E_USER_WARNING       => 'USER_WARNING',
        E_DEPRECATED         => 'DEPRECATED',
        E_USER_DEPRECATED    => 'USER_DEPRECATED',
        E_STRICT             => 'STRICT',
        E_RECOVERABLE_ERROR  => 'RECOVERABLE_ERROR',
    ][$severity] ?? "ERROR($severity)";
    error_log("[$type] $message in $file:$line");
    // For severe-but-recoverable, escalate to an exception so the catch chain
    // gets a chance to handle it cleanly.
    if ($severity === E_RECOVERABLE_ERROR) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return true; // we handled it; don't pass to PHP's default handler
});

set_exception_handler(function (\Throwable $e) {
    error_log('[UNCAUGHT] ' . get_class($e) . ': ' . $e->getMessage()
              . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    _placement_render_error_page(
        'An unexpected error occurred',
        get_class($e) . ': ' . $e->getMessage()
    );
});

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) return;
    error_log('[FATAL] ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    _placement_render_error_page(
        'The page hit a fatal error',
        $err['message'] . "\nin " . $err['file'] . ':' . $err['line']
    );
});
