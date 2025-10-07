<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Europe/Brussels');

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('TEMPLATES_PATH', BASE_PATH . '/templates');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOADS_PATH', STORAGE_PATH . '/uploads');
define('WORKING_PATH', STORAGE_PATH . '/working');
define('OUTPUTS_PATH', STORAGE_PATH . '/outputs');
define('LOGS_PATH', STORAGE_PATH . '/logs');
define('GRID_MODULE_PATH', BASE_PATH . '/grille_module');

foreach ([STORAGE_PATH, UPLOADS_PATH, WORKING_PATH, OUTPUTS_PATH, LOGS_PATH, GRID_MODULE_PATH] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/Pipeline.php';
require_once APP_PATH . '/UploadService.php';
require_once APP_PATH . '/DocxFinder.php';
require_once APP_PATH . '/DocxToHtml.php';
require_once APP_PATH . '/Extractor.php';
require_once APP_PATH . '/Templates.php';
require_once APP_PATH . '/Grouper.php';
require_once APP_PATH . '/Exporter.php';
require_once APP_PATH . '/GridModule.php';
