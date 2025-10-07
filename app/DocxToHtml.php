<?php
declare(strict_types=1);
/**
 * DOCX -> HTML robuste: numbering.xml, images w:drawing + v:imagedata, stats JSON.
 */
final class DocxToHtml {
    public function convertMany(array $docxPaths, string $lotDir): string {
        $htmlDir = $lotDir . '/html'; @mkdir($htmlDir, 0775, true);
        $parts = []; $allStats = [];
        foreach ($docxPaths as $i => $docx) {
            [$part, $stats] = $this->convertOne($docx, $htmlDir, $i+1);
            $parts[] = $part; $allStats[] = $stats;
        }
        $html = "<!doctype html><html><head><meta charset='utf-8'><title>Intermediate</title></head><body>" . implode("\n<hr/>\n", $parts) . "</body></html>";
        $dest = $htmlDir . '/intermediate.html'; file_put_contents($dest, $html);
        file_put_contents($htmlDir . '/convert_stats.json', json_encode($allStats, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        log_debug('HTML intermédiaire généré', ['file'=>$dest]);
        return $dest;
    }

    private function convertOne(string $docxPath, string $htmlDir, int $idx): array {
        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== TRUE) throw new RuntimeException('DOCX invalide: ' . $docxPath);

        $documentXml = $zip->getFromName('word/document.xml'); if ($documentXml === false) throw new RuntimeException('document.xml manquant');
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        $numberingXml = $zip->getFromName('word/numbering.xml');

        $rels = []; $numFmt = ['abs'=>[], 'num'=>[]];
        $stats = ['docx'=>basename($docxPath), 'rels'=>0, 'numFmts'=>0, 'pTotal'=>0,'pNumbered'=>0,'level0'=>0,'level1'=>0,'imagesDrawing'=>0,'imagesVML'=>0,'tables'=>0];

        if ($relsXml !== false) {
            $relsDoc = new DOMDocument(); $relsDoc->loadXML($relsXml);
            foreach ($relsDoc->getElementsByTagName('Relationship') as $el) {
                $rels[$el->getAttribute('Id')] = $el->getAttribute('Target');
            }
            $stats['rels'] = count($rels);
        }
        // Extract media
        for ($i=0; $i<$zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            if (str_starts_with($st['name'], 'word/media/')) {
                $data = $zip->getFromIndex($i);
                if ($data !== false) { $dst = $htmlDir . '/' . $st['name']; @mkdir(dirname($dst), 0775, true); file_put_contents($dst, $data); }
            }
        }
        // Parse numbering
        if ($numberingXml !== false) {
            $numDoc = new DOMDocument(); $numDoc->loadXML($numberingXml);
            $xp = new DOMXPath($numDoc); $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $abs = [];
            foreach ($xp->query('//w:abstractNum') as $a) {
                $aid = $a->getAttribute('w:abstractNumId');
                $abs[$aid] = [];
                foreach ($xp->query('.//w:lvl', $a) as $lvl) {
                    $ilvl = $lvl->getAttribute('w:ilvl');
                    $fmt = $xp->query('./w:numFmt', $lvl)->item(0);
                    if ($fmt) $abs[$aid][$ilvl] = $fmt->getAttribute('w:val');
                }
            }
            $num = [];
            foreach ($xp->query('//w:num') as $n) {
                $nid = $n->getAttribute('w:numId');
                $link = $xp->query('./w:abstractNumId', $n)->item(0);
                if ($link) $num[$nid] = $link->getAttribute('w:val');
            }
            $numFmt = ['abs'=>$abs,'num'=>$num];
            $stats['numFmts'] = count($abs);
        }
        $zip->close();

        $doc = new DOMDocument(); $doc->loadXML($documentXml);
        $xp = new DOMXPath($doc);
        $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xp->registerNamespace('a','http://schemas.openxmlformats.org/drawingml/2006/main');
        $xp->registerNamespace('r','http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $xp->registerNamespace('wp','http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        $xp->registerNamespace('v','urn:schemas-microsoft-com:vml');

        // ordre du document
        $bodyNodes = $xp->query('//w:body//*[self::w:p or self::w:tbl]');
        $out = []; $listStack = [];
        $flush = function(int $target) use (&$listStack,&$out) { while (count($listStack) > $target) { $out[]='</li></ol>'; array_pop($listStack);} };
        $openAt = function(int $level, string $type) use (&$listStack,&$out) { $out[] = '<ol'.($type? ' type="'.$type.'"' : '').'><li>'; $listStack[] = $type; };
        $li = function() use (&$out) { $out[] = '</li><li>'; };

        foreach ($bodyNodes as $node) {
            if ($node->nodeName === 'w:p') {
                $stats['pTotal']++;
                $ilvl = $xp->query('./w:pPr/w:numPr/w:ilvl', $node)->item(0);
                $numId = $xp->query('./w:pPr/w:numPr/w:numId', $node)->item(0);
                if ($ilvl && $numId) {
                    $stats['pNumbered']++;
                    $level = (int)$ilvl->getAttribute('w:val');
                    $nid = $numId->getAttribute('w:val');
                    $fmt = '';
                    if (!empty($numFmt['num'])) {
                        $absId = $numFmt['num'][$nid] ?? null;
                        if ($absId !== null) $fmt = $numFmt['abs'][$absId][(string)$level] ?? '';
                    }
                    // type de liste: niveau 0 => '1', niveau 1 => 'a' (fallback si pas d'info)
                    $type = ($fmt==='decimal' or $level===0) ? '1' : (($fmt==='lowerLetter' or $level===1) ? 'a' : '');
                    if ($level===0) $stats['level0']++; if ($level===1) $stats['level1']++;
                    $cur = count($listStack) - 1;
                    if ($cur < 0) {
                        $openAt(0, $type);
                    } elseif ($level > $cur) {
                        while ($cur < $level) {
                            $cur++;
                            $t = ($cur===0)?'1':(($cur===1)?'a':'');
                            $openAt($cur, $t);
                        }
                    } elseif ($level < $cur) {
                        $flush($level+1); $li();
                    } else {
                        $li();
                    }
                    $out[] = $this->renderParagraphInline($node, $xp, $rels, $stats);
                    continue;
                } else {
                    $flush(0); $listStack=[];
                    $buf = $this->renderParagraphInline($node, $xp, $rels, $stats);
                    if ($buf!=='') $out[] = '<p>'.$buf.'</p>';
                    continue;
                }
            }
            if ($node->nodeName === 'w:tbl') {
                $flush(0); $listStack=[]; $stats['tables']++;
                $out[] = $this->renderTable($node, $xp, $rels, $stats);
                continue;
            }
        }
        while (count($listStack)) { $out[]='</li></ol>'; array_pop($listStack); }

        $safeName = htmlspecialchars(basename($docxPath), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
        $section = "<section data-docx=\"{$safeName}\">\n" . implode("\n", array_filter($out)) . "\n</section>";
        return [$section, $stats];
    }

    private function renderParagraphInline(DOMElement $p, DOMXPath $xp, array $rels, array &$stats): string {
        $buf='';
        $runs = $xp->query('./w:r|./w:hyperlink/w:r', $p);
        foreach ($runs as $r) {
            foreach ($r->childNodes as $child) {
                if ($child->nodeName==='w:t') {
                    $txt = htmlspecialchars($child->textContent, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                    $rPr = $xp->query('./w:rPr', $r)->item(0);
                    if ($rPr) {
                        $b = $xp->query('./w:b', $rPr)->length>0;
                        $i = $xp->query('./w:i', $rPr)->length>0;
                        $u = $xp->query('./w:u', $rPr)->length>0;
                        if ($b) $txt = '<b>'.$txt.'</b>';
                        if ($i) $txt = '<i>'.$txt.'</i>';
                        if ($u) $txt = '<u>'.$txt.'</u>';
                    }
                    $buf .= $txt;
                } elseif ($child->nodeName==='w:drawing') {
                    $img = $this->renderDrawingImage($child, $xp, $rels);
                    if ($img) { $buf .= $img; $stats['imagesDrawing']++; }
                } elseif ($child->nodeName==='w:pict') {
                    $img = $this->renderVMLImage($child, $xp, $rels);
                    if ($img) { $buf .= $img; $stats['imagesVML']++; }
                } elseif ($child->nodeName==='w:tab') {
                    $buf .= '&emsp;';
                }
            }
        }
        return $buf;
    }
    private function renderDrawingImage(DOMElement $drawing, DOMXPath $xp, array $rels): string {
        $blip = $xp->query('.//a:blip', $drawing)->item(0);
        if ($blip) {
            $rid = $blip->getAttribute('r:embed');
            if ($rid && isset($rels[$rid])) {
                $src = htmlspecialchars('word/' . ltrim($rels[$rid], '/'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                return '<img src="' . $src . '" alt="image"/>';
            }
        }
        return '';
    }
    private function renderVMLImage(DOMElement $pict, DOMXPath $xp, array $rels): string {
        $im = $xp->query('.//v:imagedata', $pict)->item(0);
        if ($im) {
            $rid = $im->getAttribute('r:id');
            if ($rid && isset($rels[$rid])) {
                $src = htmlspecialchars('word/' . ltrim($rels[$rid], '/'), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
                return '<img src="' . $src . '" alt="image"/>';
            }
        }
        return '';
    }
    private function renderTable(DOMElement $tbl, DOMXPath $xp, array $rels, array &$stats): string {
        $rows = $xp->query('.//w:tr', $tbl); $rowsHtml=[];
        foreach ($rows as $tr) {
            $cells = $xp->query('.//w:tc', $tr); $cellsHtml=[];
            foreach ($cells as $tc) {
                $p = $xp->query('./w:p', $tc); $cell=[];
                foreach ($p as $pp) $cell[] = $this->renderParagraphInline($pp, $xp, $rels, $stats);
                $cellsHtml[] = '<td>'.implode('<br/>', $cell).'</td>';
            }
            $rowsHtml[] = '<tr>'.implode('', $cellsHtml).'</tr>';
        }
        return '<table border="1" cellspacing="0" cellpadding="4">'.implode('', $rowsHtml).'</table>';
    }
}
