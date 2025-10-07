<?php
declare(strict_types=1);
final class Pipeline {
    public static function workingLotDir(): string {
        if (!isset($_SESSION['lot_dir'])) {
            $_SESSION['lot_dir'] = WORKING_PATH . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
            @mkdir($_SESSION['lot_dir'], 0775, true);
        }
        return $_SESSION['lot_dir'];
    }
    public static function resetLot(): void {
        unset($_SESSION['lot_dir'], $_SESSION['extract'], $_SESSION['assign'], $_SESSION['groups']);
    }
}
