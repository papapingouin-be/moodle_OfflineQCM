<?php
require __DIR__ . '/../app/bootstrap.php';

$action = $_GET['action'] ?? 'home';
try {
    switch ($action) {
        case 'home':
            view('pages/home.php', ['title'=>'QCM PHP v6']);
            break;

        case 'upload':
            $up = new UploadService();
            $info = $up->handleUpload($_FILES['zip'] ?? []);
            $_SESSION['last_upload'] = $info;
            json_response(['ok'=>true,'info'=>$info]);
            break;

        case 'scan_docx_sync':
            $root = $_SESSION['last_upload']['root'] ?? null;
            if (!$root) throw new RuntimeException('Pas de lot en cours');
            $f = new DocxFinder();
            $_SESSION['docx'] = $f->findDocx($root);
            view('pages/home.php', ['title'=>'QCM PHP v6']);
            break;

        case 'scan_docx':
            $root = $_SESSION['last_upload']['root'] ?? null;
            if (!$root) throw new RuntimeException('Pas de lot en cours');
            $f = new DocxFinder();
            $docx = $f->findDocx($root);
            $_SESSION['docx'] = $docx;
            json_response(['ok'=>true,'docx'=>$docx]);
            break;

        case 'convert_html_sync':
            $docx = $_POST['docx'] ?? '';
            if (!$docx) throw new RuntimeException('Aucun DOCX sélectionné');
            $lot = Pipeline::workingLotDir();
            $conv = new DocxToHtml();
            $file = $conv->convertMany([$docx], $lot);
            $_SESSION['intermediate_html'] = $file;
            view('pages/home.php', ['title'=>'QCM PHP v6']);
            break;

        case 'convert_html':
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $docx = $payload['docx'] ?? '';
            if (!$docx) throw new RuntimeException('Aucun DOCX sélectionné');
            $lot = Pipeline::workingLotDir();
            $conv = new DocxToHtml();
            $file = $conv->convertMany([$docx], $lot);
            $_SESSION['intermediate_html'] = $file;
            json_response(['ok'=>true,'file'=>$file]);
            break;

        case 'extract':
            $file = $_SESSION['intermediate_html'] ?? null;
            if (!$file) throw new RuntimeException('HTML intermédiaire manquant');
            $lot = Pipeline::workingLotDir();
            $ext = new Extractor();
            $data = $ext->extract($file, $lot);
            $_SESSION['extract'] = $data;
            json_response(['ok'=>true,'extract'=>$data]);
            break;

        case 'editor':
            view('pages/editor.php', ['title'=>'Éditeur & Template']);
            break;

        case 'template_editor':
            $type = $_GET['type'] ?? 'T1';
            view('pages/template_editor.php', [
                'title' => 'Éditeur de template',
                'type'  => $type,
            ]);
            break;

        case 'load_template':
            $type = $_GET['type'] ?? '';
            $normalized = Templates::normalizeName($type);
            $content = Templates::loadTemplateContent($normalized, $normalized);
            $types = Templates::availableTypes();
            $meta = $types[$normalized] ?? ['label'=>$normalized, 'description'=>''];
            json_response([
                'ok'           => true,
                'type'         => $normalized,
                'content'      => $content,
                'label'        => $meta['label'] ?? $normalized,
                'description'  => $meta['description'] ?? '',
                'placeholders' => Templates::placeholderDocs($normalized),
            ]);
            break;

        case 'save_template':
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $type = $payload['type'] ?? '';
            $content = $payload['content'] ?? '';
            Templates::saveTemplateContent($type, $content);
            json_response(['ok'=>true]);
            break;

        case 'save_groups':
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $_SESSION['groups'] = $payload['groups'] ?? [];
            json_response(['ok'=>true]);
            break;

        case 'save_meta':
            $payload = json_decode(file_get_contents('php://input'), true) ?? [];
            $_SESSION['meta'] = [
                'title'  => $payload['title']  ?? '',
                'letter' => $payload['letter'] ?? ''
            ];
            json_response(['ok'=>true]);
            break;

        case 'preview':
            $extract = $_SESSION['extract'] ?? null;
            if (!$extract) throw new RuntimeException('Extraction requise');
            $groups = $_SESSION['groups'] ?? [];
            $meta   = $_SESSION['meta'] ?? ['title'=>'','letter'=>''];
            $questions = Exporter::renderQuestions($extract, $groups);
            $header = Exporter::buildHeader($meta);
            $css = Exporter::printCss();
            $out = '<style>'.$css.'</style>'.$header.$questions;
            echo rewrite_media_srcs($out); exit;

        case 'export':
            $extract = $_SESSION['extract'] ?? null;
            if (!$extract) throw new RuntimeException('Extraction requise');
            $groups = $_SESSION['groups'] ?? [];
            $lot = Pipeline::workingLotDir();
            $meta = $_SESSION['meta'] ?? ['title'=>'','letter'=>''];
            $exp = new Exporter();
            $file = $exp->exportHtml($extract, $groups, $lot, $meta);
            $_SESSION['last_export'] = $file;
            view('pages/export.php', ['title'=>'Export HTML', 'file'=>$file]);
            break;

        case 'generate_grids':
            $root = $_SESSION['last_upload']['root'] ?? null;
            if (!$root) throw new RuntimeException('Pas de lot en cours');
            $lot = Pipeline::workingLotDir();
            $grid = new GridModule();
            try {
                $paths = $grid->generate($root, $lot);
            } catch (GridModuleUnavailableException $e) {
                json_response(['ok'=>false, 'error'=>$e->getMessage()], 503);
            }
            $_SESSION['grid_files'] = $paths;
            json_response(['ok'=>true, 'files'=>$paths]);
            break;

        case 'cleanup':
            $what = $_POST['what'] ?? '';
            $targets = [
                'uploads' => UPLOADS_PATH,
                'working' => WORKING_PATH,
                'outputs' => OUTPUTS_PATH,
                'logs'    => LOGS_PATH,
                'lot'     => Pipeline::workingLotDir()
            ];
            if (isset($targets[$what])) {
                rrmdir($targets[$what]); @mkdir($targets[$what], 0775, true);
                if ($what==='lot') Pipeline::resetLot();
                log_debug('Nettoyage', ['target'=>$what]);
                json_response(['ok'=>true]);
            } else {
                json_response(['ok'=>false, 'error'=>'cible inconnue'], 400);
            }
            break;

        case 'convert_stats':
            $lot = Pipeline::workingLotDir(); $stats = $lot . '/html/convert_stats.json';
            if (!is_readable($stats)) json_response(['ok'=>false,'error'=>'stats absentes'],404);
            json_response(['ok'=>true,'stats'=>json_decode(file_get_contents($stats), true)]);
            break;

        case 'extract_debug':
            $lot = Pipeline::workingLotDir(); $dbg = $lot . '/extraction_debug.json';
            if (!is_readable($dbg)) json_response(['ok'=>false,'error'=>'debug absent'],404);
            json_response(['ok'=>true,'debug'=>json_decode(file_get_contents($dbg), true)]);
            break;

        case 'debug_log':
            $clear = isset($_GET['clear']);
            if ($clear) clear_debug();
            json_response(['ok'=>true,'log'=>get_debug()]);
            break;

			
        case 'open_intermediate': {
            $file = $_SESSION['intermediate_html'] ?? null;
            if (!$file || !is_readable($file)) {
                http_response_code(404);
                header('Content-Type: text/plain; charset=utf-8');
                echo "intermediate.html introuvable\n";
                exit;
            }
            $html = file_get_contents($file);
            // Réécrit les liens d'images pour passage via ?action=media&p=
            if ($html !== false) {
                $html = rewrite_media_srcs($html);
            }
            header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
        }

        case 'open_export': {
            $file = $_SESSION['last_export'] ?? null;
            if (!$file || !is_readable($file)) {
                http_response_code(404);
                header('Content-Type: text/plain; charset=utf-8');
                echo "render.html introuvable\n";
                exit;
            }
            $html = file_get_contents($file);
            if ($html !== false) {
                $html = rewrite_media_srcs($html);
            }
            header('Content-Type: text/html; charset=utf-8'); echo $html; exit;
        }

        case 'media': {
            $p = $_GET['p'] ?? '';
            if ($p === '') { http_response_code(400); exit('missing p'); }
            $baseHtmlDir = dirname($_SESSION['intermediate_html'] ?? '') ?: '';
            if ($baseHtmlDir === '' || !is_dir($baseHtmlDir)) { http_response_code(404); exit('no lot'); }
            $full = realpath($baseHtmlDir . '/' . ltrim($p, '/'));
            $baseReal = realpath($baseHtmlDir);
            if (!$full || !$baseReal || strpos($full, $baseReal) !== 0 || !is_file($full)) { http_response_code(404); exit('not found'); }
            $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
            $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','bmp'=>'image/bmp','webp'=>'image/webp','svg'=>'image/svg+xml'][$ext] ?? 'application/octet-stream';
            header('Content-Type: '.$mime); readfile($full); exit;
        }

        case 'inspect':
            view('pages/inspect.php', ['title'=>'Inspecteur']);
            break;
 
		case 'grille':
            view('../grille/index-grille.php', ['title'=>'grille']);
            break;
		case 'convcsv':
            view('../QCM/index.php', ['title'=>'convcsv']);
            break;						

        case 'run_inspect':
            $file = $_SESSION['intermediate_html'] ?? null;
            if (!$file) throw new RuntimeException('HTML intermédiaire manquant');
            $lot = Pipeline::workingLotDir();
            require_once APP_PATH . '/Inspector.php';
            $ins = new Inspector();
            $_SESSION['inspection'] = $ins->inspect($file, $lot);
            view('pages/inspect.php', ['title'=>'Inspecteur']);
            break;
					
        default:
            http_response_code(404); echo 'Not found';
    }
} catch (Throwable $e) {
    log_debug('ERREUR', ['message'=>$e->getMessage(), 'trace'=>$e->getTraceAsString()]);
    json_response(['ok'=>false, 'error'=>$e->getMessage()], 500);
}
