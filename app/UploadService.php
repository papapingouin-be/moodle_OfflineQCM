<?php
declare(strict_types=1);
final class UploadService {
    public function handleUpload(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload raté: code=' . ($file['error'] ?? 'inconnu'));
        }
        $name = safe_filename($file['name'] ?? 'archive.zip');
        if (!preg_match('~\.zip$~i', $name)) throw new RuntimeException('Le fichier doit être un .zip');
        if (($file['size'] ?? 0) <= 0) throw new RuntimeException('Fichier vide');
        $lot = Pipeline::workingLotDir();
        $dest = $lot . '/uploads/' . $name;
        @mkdir(dirname($dest), 0775, true);
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            if (!copy($file['tmp_name'], $dest)) throw new RuntimeException('Impossible d’enregistrer le fichier.');
        }
        log_debug('Upload OK', ['dest'=>$dest]);
        $zip = new ZipArchive();
        if ($zip->open($dest) !== TRUE) throw new RuntimeException('Impossible d’ouvrir le ZIP.');
        $extractTo = $lot . '/zip';
        @mkdir($extractTo, 0775, true);
        if (!$zip->extractTo($extractTo)) throw new RuntimeException('Extraction ZIP impossible.');
        $zip->close();
        log_debug('Décompression OK', ['extractTo'=>$extractTo]);
        return ['lot'=>$lot, 'zip'=>$dest, 'root'=>$extractTo];
    }
}
