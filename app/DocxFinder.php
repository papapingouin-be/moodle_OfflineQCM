<?php
declare(strict_types=1);
final class DocxFinder {
    public function findDocx(string $root): array {
        $out = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $path => $info) {
            if ($info->isFile() && preg_match('~\.docx$~i', $path)) $out[] = $path;
        }
        sort($out);
        log_debug('DOCX trouvés', ['count'=>count($out)]);
        return $out;
    }
}
