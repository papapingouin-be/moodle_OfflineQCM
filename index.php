<?php
declare(strict_types=1);

// Front controller that centralizes error handling before delegating
// to the public/index.php entry point of the application.
error_reporting(E_ALL);
ini_set('display_errors', '0');

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/app.log';

set_error_handler(static function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (\Throwable $throwable) use ($logFile): void {
    $code = $throwable->getCode();
    if ($code < 400 || $code >= 600) {
        $code = 500;
    }
    http_response_code($code);

    $logEntry = sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s\n\n",
        date('c'),
        $throwable->getMessage(),
        $throwable->getFile(),
        $throwable->getLine(),
        $throwable->getTraceAsString()
    );
    error_log($logEntry, 3, $logFile);

    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Une erreur est survenue</h1>';
    echo '<p>Veuillez réessayer plus tard ou contacter un administrateur.</p>';
});

register_shutdown_function(static function () use ($logFile): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $logEntry = sprintf(
            "[%s] Fatal error: %s in %s:%d\n\n",
            date('c'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        error_log($logEntry, 3, $logFile);
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>Une erreur critique est survenue</h1>';
        echo '<p>Veuillez réessayer plus tard ou contacter un administrateur.</p>';
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
