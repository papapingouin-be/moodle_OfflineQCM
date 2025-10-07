<?php
declare(strict_types=1);

final class Extractor
{
    /* ====================== Utils ====================== */

    private function bodyHtml(string $html): string {
        if (preg_match('~<body\b[^>]*>(.*?)</body>~is', $html, $m)) return $m[1];
        return $html;
    }
    private function ol1OpenRegex(): string {
        return '~<ol\b(?=[^>]*\btype\s*=\s*["\']?1["\']?)[^>]*>~i';
    }
    private function pointsPRegex(): string {
        $WS = '(?:\s|&nbsp;|\xC2\xA0|\xE2\x80\xAF)*';
        return '~<p\b[^>]*>' . $WS . '\(' . $WS . '\d+(?:[.,]\d+)?' . $WS . 'point(?:\(s\))?s?' . $WS . '\)' . $WS . '</p>~i';
    }
    private function olAlphaOpenRegex(): string {
        return '~<ol\b(?=[^>]*\btype\s*=\s*["\']?a["\']?)[^>]*>~i';
    }
    private function cleanText(string $s): string {
        $s = preg_replace('~[\x{00A0}\x{202F}]~u', ' ', $s);
        return preg_replace('~\s+~u', ' ', trim($s));
    }

    private function nextStrictSegment(string $body, int $offset): ?array {
        if (!preg_match($this->ol1OpenRegex(), $body, $mStart, PREG_OFFSET_CAPTURE, $offset)) return null;
        $start = $mStart[0][1];
        if (!preg_match($this->pointsPRegex(), $body, $mEnd, PREG_OFFSET_CAPTURE, $start)) return null;
        $end   = $mEnd[0][1] + strlen($mEnd[0][0]);
        $full  = substr($body, $start, $end - $start);
        $pHtml = $mEnd[0][0];
        return ['start'=>$start, 'end'=>$end, 'full'=>$full, 'points_html'=>$pHtml];
    }

    private function splitZones(string $full): array {
        $pStart = strlen($full);
        if (preg_match($this->pointsPRegex(), $full, $mp, PREG_OFFSET_CAPTURE)) $pStart = (int)$mp[0][1];
        $firstA = null;
        if (preg_match($this->olAlphaOpenRegex(), $full, $ma, PREG_OFFSET_CAPTURE)) $firstA = (int)$ma[0][1];
        if ($firstA !== null && $firstA < $pStart) {
            $qHtml = substr($full, 0, $firstA);
            $aHtml = substr($full, $firstA, $pStart - $firstA);
        } else {
            $qHtml = substr($full, 0, $pStart);
            $aHtml = '';
            $firstA = null;
        }
        // Cosméto
        $qHtml = preg_replace('~^\s*<ol\b[^>]*>\s*<li\b[^>]*>~i', '', $qHtml);
        $qHtml = ltrim($qHtml);
        $aHtml = preg_replace('~^\s*(?:</li>\s*</ol>\s*)+~i', '', $aHtml);
        return [$qHtml, $aHtml, $pStart, $firstA];
    }

    /** 🔥 Aplatisseur : supprime les <ol type="a"> et renvoie les <li> de 1er niveau (options) */
    private function answerItemsFlatten(string $answersHtml): array {
        $items = [];
        if ($answersHtml === '') return $items;

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="wrap">'.$answersHtml.'</div>');
        libxml_clear_errors();

        $wrap = $dom->getElementById('wrap');
        if (!$wrap) return $items;

        $insertAfter = function(DOMElement $parent, DOMNode $ref, DOMNode $node) {
            if ($ref->nextSibling) {
                $parent->insertBefore($node, $ref->nextSibling);
            } else {
                $parent->appendChild($node);
            }
        };

        while (true) {
            $alphaOl = null;
            foreach ($wrap->getElementsByTagName('ol') as $ol) {
                $type  = strtolower($ol->getAttribute('type') ?? '');
                $style = strtolower($ol->getAttribute('style') ?? '');
                if ($type === 'a' || strpos($style, 'lower-alpha') !== false) { $alphaOl = $ol; break; }
            }
            if (!$alphaOl) break;

            $liAncestor = null;
            $ancestor = $alphaOl->parentNode;
            while ($ancestor && $ancestor->nodeType === XML_ELEMENT_NODE && strtolower($ancestor->nodeName) === 'li') {
                $liAncestor = $ancestor;
                $ancestor = $ancestor->parentNode;
            }
            /** @var DOMElement $container */
            $container = ($ancestor instanceof DOMElement) ? $ancestor : $wrap;

            $ref = $liAncestor ?: $alphaOl;
            $toMove = [];
            foreach (iterator_to_array($alphaOl->childNodes) as $child) {
                if ($child instanceof DOMElement && strtolower($child->nodeName) === 'li') $toMove[] = $child;
            }
            foreach ($toMove as $li) {
                $moved = $li->parentNode->removeChild($li);
                $insertAfter($container, $ref, $moved);
                $ref = $moved;
            }
            if ($alphaOl->parentNode) $alphaOl->parentNode->removeChild($alphaOl);
        }

        // 📌 Certains DOCX produisent des <li> vides suivis d'un <p> contenant l'image de la réponse.
        //     On fusionne ces <p><img> dans le <li> précédent afin de garder l'association
        //     option ⇔ image.
        foreach (iterator_to_array($wrap->childNodes) as $n) {
            if (!($n instanceof DOMElement)) continue;
            if (strtolower($n->nodeName) !== 'li') continue;

            $hasImg = $n->getElementsByTagName('img')->length > 0;
            $text   = $this->cleanText($n->textContent ?? '');
            if ($hasImg || $text !== '') continue; // déjà une vraie option

            // Cherche le prochain <p> contenant une image,
            // en ignorant les <p> intermédiaires vides.
            $next = $n->nextSibling;
            while ($next) {
                if ($next instanceof DOMElement) {
                    $nodeName = strtolower($next->nodeName);
                    if ($nodeName === 'p') {
                        $imgNodes = $next->getElementsByTagName('img');
                        $textNext = $this->cleanText($next->textContent ?? '');
                        if ($imgNodes->length > 0) {
                            // Déplace tout le contenu du <p> dans le <li>
                            while ($next->firstChild) {
                                $n->appendChild($next->firstChild);
                            }
                            $tmp = $next;
                            $next = $next->nextSibling;
                            $tmp->parentNode->removeChild($tmp);
                            break;
                        }
                        if ($textNext === '') {
                            // <p> vide: on le supprime et on continue la recherche
                            $tmp = $next->nextSibling;
                            $next->parentNode->removeChild($next);
                            $next = $tmp;
                            continue;
                        }
                    }
                    break; // autre élément : abandon
                }
                $next = $next->nextSibling; // ignore les noeuds texte/commentaire
            }
        }

        foreach (iterator_to_array($wrap->childNodes) as $n) {
            if ($n instanceof DOMElement && strtolower($n->nodeName) === 'li') {
                $html = $dom->saveHTML($n);
                $text = $this->cleanText($n->textContent ?? '');
                $imgs = [];
                foreach ($n->getElementsByTagName('img') as $img) {
                    $src = $img->getAttribute('src') ?? '';
                    if ($src !== '') $imgs[] = $src;
                }
                $items[] = ['html'=>$html,'text'=>$text,'images'=>$imgs];
            }
        }
        return $items;
    }

    private function decideType(string $qHtml, array $answersItems): string {
        $qtext = $this->cleanText(strip_tags($qHtml));
        $low   = function_exists('mb_strtolower') ? mb_strtolower($qtext, 'UTF-8') : strtolower($qtext);

        $n = count($answersItems);
        if ($n === 0) return 'T3';
        if ($n === 2) {
            $joined = strtolower(implode(' ', array_map(fn($a) => $a['text'] ?? '', $answersItems)));
            if (preg_match('~\b(vrai|true)\b~', $joined) && preg_match('~\b(faux|false)\b~', $joined)) return 'T4';
        }
        if (preg_match('~plusieurs|cocher|cochez|sélectionnez plusieurs|choisir deux|deux réponses|au moins deux|(choisissez|sélectionnez)\s+\d+~u', $low)) {
            return 'T2';
        }
        return 'T1';
    }

    /* ====================== API principale ====================== */

    public function extract(string $intermediateHtml, string $lotDir): array {
        $raw = file_get_contents($intermediateHtml);
        if ($raw === false) throw new RuntimeException('Lecture HTML intermédiaire impossible');
        $body = $this->bodyHtml($raw);

        $questions = [];
        $debugQ = [];

        $offset = 0; $idx = 0;
        while (true) {
            $seg = $this->nextStrictSegment($body, $offset);
            if (!$seg) break;

            $full   = $seg['full'];
            // Découpe Q/R
            [$qHtml, $aHtml, $pStart, $firstA] = $this->splitZones($full);

            // 🔥 Aplatisseur : options = <li> de 1er niveau (après suppression des <ol type="a">)
            $answers = $this->answerItemsFlatten($aHtml);

            // Statement
            $stmtText = $this->cleanText(strip_tags($qHtml));
            $stmtImgs = [];
            if ($qHtml !== '' && preg_match_all('~<img\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']~i', $qHtml, $mImg)) {
                foreach ($mImg[1] as $src) $stmtImgs[] = $src;
                $qHtml = preg_replace('~<img\b[^>]*>~i', '', $qHtml);
                $qHtml = preg_replace('~<p\b[^>]*>\s*</p>~i', '', $qHtml);
            }

            $type = $this->decideType($qHtml, $answers);
            $template = 'Q1';

            $idx++;
            $questions[] = [
                'index'     => $idx,
                'type'      => $type,
                'template'  => $template,
                'statement' => [
                    'html'   => $qHtml,
                    'text'   => $stmtText,
                    'images' => $stmtImgs,
                ],
                'answers'   => $answers, // ✅ options finales
                'raw'       => [
                    'full_source_html' => $full,
                    'answers_html'     => $aHtml,
                ],
            ];

            $debugQ[] = [
                'q_index'       => $idx,
                'len_full'      => strlen($full),
                'len_q'         => strlen($qHtml),
                'len_a'         => strlen($aHtml),
                'firstA_rel'    => $firstA,
                'answers_count' => count($answers),
            ];

            $offset = $seg['end'];
        }

        $result = [
            'questions'  => $questions,
            'created_at' => date('c'),
        ];
        @file_put_contents($lotDir . '/extraction.json', json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        @file_put_contents($lotDir . '/extraction_debug.json', json_encode(['phase'=>'flattened top-level <li>','foundQuestions'=>count($questions),'items'=>$debugQ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

        $_SESSION['extract'] = $result;
        return $result;
    }
}
