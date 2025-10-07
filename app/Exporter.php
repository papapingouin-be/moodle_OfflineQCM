<?php
declare(strict_types=1);
final class Exporter {
    public static function renderQuestions(array $extract, array $groups): string {
        $questions = $extract['questions'] ?? [];
        $blocks = Grouper::build($questions, $groups);
        $htmlBlocks = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'single') {
                $q = $b['question'];
                $htmlBlocks[] = '<article data-q="'.$q['index'].'">'.Templates::renderQuestion($q).'</article>';
            } else {
                $inner = [];
                foreach ($b['questions'] as $q) {
                    $inner[] = '<article data-q="'.$q['index'].'">'.Templates::renderQuestion($q).'</article>';
                }
                $htmlBlocks[] = '<section class="group layout-'.$b['layout'].'"><div class="gqs">'.implode('', $inner).'</div></section>';
            }
        }
        return '<div class="qwrap">'.implode("\n", $htmlBlocks).'</div>';
    }

    public static function buildHeader(array $meta): string {
        $title  = htmlspecialchars($meta['title'] ?? 'QCM', ENT_QUOTES);
        $letter = htmlspecialchars($meta['letter'] ?? '', ENT_QUOTES);
        return '<div class="qcm-header">'
            .'<img src="https://esh-herve.be/images/ESH_logo.png" alt="Logo Institut de la Providence" class="logo-small">'
            .'<div class="school-name">Institut de la Providence</div>'
            .'<h1>'. $title .'</h1>'
            .'<div class="qcm-letter">Questionnaire  <span>'. $letter .'</span></div>'
            .'<p class="note">Une seule réponse possible par question.<br>Veuillez ne rien écrire sur ce questionnaire et vérifier de remplir la grille à part sans vous tromper dans le numéro de la question. </p>'
            .'</div>';
    }

    public static function printCss(): string {
        return <<<CSS
@page { size: A4 portrait; margin: 5mm; }
body { margin:0; padding:10mm; font-family: Arial, sans-serif; background:#fff; }
.container { max-width:100%; padding:0; background:none; }
.qbox, .gbox { border:none; box-shadow:none; background:none; margin:0; padding:0; }
.qblock { background-color:#f2f2f2; padding:8px; border-radius:4px; margin-bottom:12px; page-break-inside:avoid !important; break-inside:avoid-page !important; orphans:2; widows:2; -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
.qblock .qtext { margin:0 0 6px 0; line-height:1.4; font-weight:600; }
.qblock .answers { margin:0 0 6px 0; line-height:1.4; list-style-type: lower-alpha; padding-left:20px; }
table { width:100%; border-collapse:collapse; margin:8px 0; border:none; }
th, td { padding:6px; vertical-align:top; border:none; }
td + td { border-left:1px dashed #ddd; }
hr { border:none; height:1px; background:#ddd; margin:16px 0; }
@media print { body { padding:5mm; } td + td { border-left-color:#000 !important; } }
.qcm-header { text-align:center; margin-bottom:20px; position:relative; }
.qcm-header .logo-small { position:absolute; top:0; left:0; width:200px; height:auto; }
.qcm-header .school-name { position:absolute; top:0; right:0; font-size:0.85em; color:#666; }
.qcm-header h1 { font-size:1.333em; margin:0; padding:5px 10px; display:inline-block; background:#eaeaea; border-radius:4px; }
.qcm-header .qcm-letter { margin:0 40px; margin-top:5px; font-size:1em; padding:5px 15px; border:2px solid #c00; display:inline-block; border-radius:4px; color:#c00; }
.qcm-header .note { margin-top:10px; font-size:0.9em; color:#333; }
.qwrap { display:flex; flex-direction:column; gap:8px; }
.qwrap > article, .qwrap > section { margin-top:8px; padding-top:8px; }
.qwrap > article:first-child, .qwrap > section:first-child { margin-top:0; padding-top:0; }
.qimgs { display:flex; gap:8px; flex-wrap:wrap; margin:8px 0; }
.qimg { max-width:280px; max-height:180px; object-fit:contain; }
.answers { list-style-type: lower-alpha; padding-left:20px; margin:6px 0; }
.answers .ans { margin:6px 0; }
.qnum { margin-right:6px; }
.group { padding:12px; border:1px solid #e5e7eb; border-radius:12px; background:#fff; }
.group.layout-horizontal .gqs { display:flex; gap:16px; }
.group.layout-vertical .gqs { display:flex; flex-direction:column; gap:8px; }
.group.layout-vertical .gqs > article { margin-top:8px; }
.group.layout-vertical .gqs > article:first-child { margin-top:0; }
CSS;
    }

    public function exportHtml(array $extract, array $groups, string $lotDir, array $meta): string {
        $questionsHtml = self::renderQuestions($extract, $groups);
        $body = self::buildHeader($meta) . $questionsHtml;
        $page = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>'
            .htmlspecialchars($meta['title'] ?? 'QCM', ENT_QUOTES)
            .'</title><style>' . self::printCss() . '</style></head><body>' . $body . '</body></html>';
        $outDir = $lotDir . '/render'; @mkdir($outDir, 0775, true);
        $dest = $outDir . '/render.html'; file_put_contents($dest, $page);
        log_debug('Export HTML OK', ['file'=>$dest]); return $dest;
    }
}
