<?php
declare(strict_types=1);

/**
 * Helpers généraux pour QCM PHP v6
 * - Rendu de vues (layout + partial)
 * - Réponses JSON
 * - Logs (+ tampon session pour affichage live)
 * - Utilitaires fichiers / chaînes
 * - Polyfills PHP < 8 (str_starts_with, str_ends_with, str_contains)
 * - pretty_html() et rewrite_media_srcs() tolérants à null
 */

/* ---------------------------- Rendu des vues ---------------------------- */

function view(string $tplFile, array $vars = []): void {
    // Variables disponibles dans le template
    extract($vars, EXTR_OVERWRITE);
    // Nom du fichier de vue (relatif à templates/)
    $tpl = $tplFile;
    // Layout principal
    require TEMPLATES_PATH . '/layout.php';
}

function render_partial(string $file, array $vars = []): string {
    extract($vars, EXTR_OVERWRITE);
    ob_start();
    require $file;
    return (string)ob_get_clean();
}

/* ---------------------------- Réponse JSON ------------------------------ */

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    exit;
}

/* --------------------------------- Logs -------------------------------- */

function now(): string {
    return date('Y-m-d H:i:s');
}

function log_debug(string $msg, array $ctx = []): void {
    $line = '[' . now() . '] ' . $msg;
    if (!empty($ctx)) {
        $line .= ' ' . json_encode(
            $ctx,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
    $line .= PHP_EOL;

    // Fichier de log (si défini)
    if (defined('LOGS_PATH')) {
        @file_put_contents(LOGS_PATH . '/app.log', $line, FILE_APPEND);
    }

    // Tampon session pour affichage dans l’UI
    if (!isset($_SESSION)) @session_start();
    $_SESSION['__debug'][] = htmlspecialchars(
        $line,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function get_debug(): array {
    if (!isset($_SESSION)) @session_start();
    return $_SESSION['__debug'] ?? [];
}

function clear_debug(): void {
    if (!isset($_SESSION)) @session_start();
    $_SESSION['__debug'] = [];
}

/* ------------------------- Utilitaires fichiers ------------------------- */

function safe_filename(string $name): string {
    $name = str_replace(['\\', '/'], '_', $name);
    $name = preg_replace('~[^a-zA-Z0-9._-]+~u', '_', $name);
    return trim($name, '_');
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/**
 * Supprime les dossiers de travail plus anciens que $maxAgeHours heures.
 */
function purge_old_work_dirs(int $maxAgeHours = 24): void {
    if (!defined('WORKING_PATH') || !is_dir(WORKING_PATH)) return;
    $now = time();
    foreach (glob(WORKING_PATH . '/*') as $dir) {
        if (!is_dir($dir)) continue;
        $age = $now - @filemtime($dir);
        if ($age > $maxAgeHours * 3600) {
            rrmdir($dir);
            log_debug('Purge work dir', ['dir'=>$dir]);
        }
    }
}

function first_existing(array $paths): ?string {
    foreach ($paths as $p) {
        if (file_exists($p)) return $p;
    }
    return null;
}

/* --------------------------- Utilitaires str ---------------------------- */

function str_starts_with_any(string $s, array $prefixes): bool {
    foreach ($prefixes as $p) {
        if (str_starts_with($s, $p)) return true;
    }
    return false;
}

/* ---------------------------- Polyfills PHP ----------------------------- */

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        if ($len === 0) return true;
        return substr($haystack, -$len) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        return strpos($haystack, $needle) !== false;
    }
}

/* ---------------------- Affichage / formatting HTML --------------------- */

/**
 * Indente du HTML fragment (tolère null).
 * Utilise DOMDocument quand possible, sinon fallback simple.
 */
// Remplacez l’ancienne pretty_html() par celle-ci (sans DOM)
function pretty_html(?string $html): string {
    $s = (string)($html ?? '');
    if ($s === '') return '';

    // 1) Normalise juste un peu les espaces entre balises
    $s = trim($s);
    $s = preg_replace('/>\s+</', ">\n<", $s);

    // 2) Indentation naïve (sans “réparation” DOM)
    $void = '(?:area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)';
    $lines = preg_split("/\r\n|\r|\n/", $s);
    $out = [];
    $indent = 0;

    foreach ($lines as $line) {
        $t = trim($line);

        // Ferme l'indent AVANT si la ligne commence par une balise fermante
        if (preg_match('/^<\/[a-zA-Z0-9]+>/', $t)) {
            $indent = max(0, $indent - 1);
        }

        $out[] = str_repeat('  ', $indent) . $t;

        // Ouvre l'indent APRÈS si balise ouvrante non auto-fermante / non void
        $isOpenTag   = preg_match('/^<([a-zA-Z0-9]+)\b[^>]*>$/', $t, $mOpen);
        $isCloseTag  = preg_match('/^<\/[a-zA-Z0-9]+>$/', $t);
        $isSelfClose = preg_match('/\/>$/', $t);
        $isVoid      = $isOpenTag && preg_match('/^' . $void . '$/i', $mOpen[1]);

        if ($isOpenTag && !$isCloseTag && !$isSelfClose && !$isVoid) {
            $indent++;
        }
    }
    return implode("\n", $out);
}

/**
 * Réécrit les URLs d’images `src="word/..."` en `?action=media&p=word/...`
 * pour permettre l’aperçu même quand intermediate.html n’est pas servi en statique.
 */
function rewrite_media_srcs(?string $html): string {
    $html = (string)($html ?? '');
    if ($html === '') return '';

    return preg_replace_callback(
        '~(src\s*=\s*["\'])([^"\']+)(["\'])~i',
        function ($m) {
            $prefix = $m[1];
            $url    = $m[2];
            $suffix = $m[3];

            // Si l’URL commence par word/ (cas DOCX -> word/media/...)
            if (stripos($url, 'word/') === 0) {
                $url = '?action=media&p=' . $url;
            }
            return $prefix . $url . $suffix;
        },
        $html
    );
}
