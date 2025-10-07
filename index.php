<?php
declare(strict_types=1);

// Front controller that centralizes error handling before delegating
// to the public/index.php entry point of the application.
error_reporting(E_ALL);
ini_set('display_errors', '0');

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
    error_log(sprintf('[%s] Unable to create log directory at %s', date('c'), $logDir));
}
$logFile = $logDir . '/app.log';

/**
 * Generates a short identifier to correlate the technical log and the public error message.
 */
$generateErrorId = static function (): string {
    try {
        return bin2hex(random_bytes(8));
    } catch (\Throwable $e) {
        return substr(hash('sha256', uniqid('', true)), 0, 16);
    }
};

set_error_handler(static function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (\Throwable $throwable) use ($logFile, $generateErrorId): void {
    $errorId = $generateErrorId();
    $code = $throwable->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 500;
    }
    http_response_code($code);

    $logEntry = sprintf(
        "[%s] (%s) %s in %s:%d\nStack trace:\n%s\n\n",
        date('c'),
        $errorId,
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine(),
        $throwable->getTraceAsString()
    );
    error_log($logEntry, 3, $logFile);

    header('Content-Type: text/html; charset=utf-8');
    header('X-Error-Id: ' . $errorId);
    echo '<h1>Une erreur est survenue</h1>';
    echo '<p>Veuillez réessayer plus tard ou contacter un administrateur.</p>';
    echo '<p>Code erreur : ' . htmlspecialchars($errorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    echo '<p>Consultez le journal dans <code>storage/logs/app.log</code> pour plus de détails.</p>';
});

register_shutdown_function(static function () use ($logFile, $generateErrorId): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $errorId = $generateErrorId();
        $logEntry = sprintf(
            "[%s] (%s) Fatal error: %s in %s:%d\n\n",
            date('c'),
            $errorId,
            $error['message'],
            $error['file'],
            $error['line']
        );
        error_log($logEntry, 3, $logFile);
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Error-Id: ' . $errorId);
        echo '<h1>Une erreur critique est survenue</h1>';
        echo '<p>Veuillez réessayer plus tard ou contacter un administrateur.</p>';
        echo '<p>Code erreur : ' . htmlspecialchars($errorId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        echo '<p>Consultez le journal dans <code>storage/logs/app.log</code> pour plus de détails.</p>';
    }
});

$entryPoints = [
    __DIR__ . '/public/index.php' => 'php',
    __DIR__ . '/public/index.html' => 'html',
    __DIR__ . '/public/index.htm' => 'html',
];

foreach ($entryPoints as $path => $type) {
    if (!is_file($path)) {
        continue;
    }

    if ($type === 'php') {
        require $path;
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    readfile($path);
    return;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Page d'accueil introuvable.";
