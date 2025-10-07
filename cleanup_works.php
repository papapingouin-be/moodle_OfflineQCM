<?php
require __DIR__ . '/app/bootstrap.php';

// Nombre d'heures avant suppression, par défaut 24h
$hours = isset($argv[1]) ? (int)$argv[1] : 24;
if ($hours < 0) $hours = 0;

purge_old_work_dirs($hours);

echo "Purged work directories older than $hours hours." . PHP_EOL;

