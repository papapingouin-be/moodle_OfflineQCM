<?php
declare(strict_types=1);

final class Inspector
{
    /* ===================== Utils & Regex ===================== */

    /** Retourne l'innerHTML du <body>. Si introuvable, renvoie le HTML brut. */
    private function bodyHtml(string $html): string {
        if (preg_match('~<body\b[^>]*>(.*?)</body>~is', $html, $m)) return $m[1];
        return $html;
    }

    /** Débuts de question: <ol type="1"> (strict). */
    private function ol1OpenRegex(): string {
        return '~<ol\b(?=[^>]*\btype\s*=\s*["\']?1["\']?)[^>]*>~i';
    }

    /** 1er paragraphe (points) – variations tolérées, NBSP inclus. */
    private function pointsPRegex(): string {
        $ws = '(?:\s|&nbsp;|\xC2\xA0|\xE2\x80\xAF)*';
        return '~<p\b[^>]*>' . $ws . '\(' . $ws . '\d+(?:[.,]\d+)?' . $ws . 'point(?:\(\s*s\s*\))?s?' . $ws . '\)' . $ws . '</p>~i';
    }

    /** Ouverture d’un <ol type="a"> (alpha explicite) */
    private function olAlphaOpenRegex(): string {
        return '~<ol\b(?=[^>]*\btype\s*=\s*["\']?a["\']?)[^>]*>~i';
    }

    /* =================== Découpe par segments =================== */

    /** Segment STRICT : [ <ol type="1"> ... <p>(… point …)</p> ] (inclus) */
    private function nextStrictSegment(string $body, int $offset): ?array {
        if (!preg_match($this->ol1OpenRegex(), $body, $mStart, PREG_OFFSET_CAPTURE, $offset)) {
            return null;
        }
        $start = (int)$mStart[0][1];

        if (!preg_match($this->pointsPRegex(), $body, $mEnd, PREG_OFFSET_CAPTURE, $start)) {
            return null; // pas de <p>(points) après → stop
        }
        $pStart = (int)$mEnd[0][1];
        $pEnd   = $pStart + strlen($mEnd[0][0]);

        $full   = substr($body, $start, $pEnd - $start);
        $pHtml  = $mEnd[0][0];

        return ['start'=>$start,'end'=>$pEnd,'full'=>$full,'points_html'=>$pHtml];
    }

    /**
     * Découpe depuis la source complète :
     * - Zone Q = avant la 1ʳᵉ <ol type="a">
     * - Zone R = de cette <ol type="a"> jusqu’au <p>(points) (exclu)
     */
    private function splitZonesFromFull(string $full): array {
        // index du <p>(points) DANS la source
        $pStart = strlen($full);
        if (preg_match($this->pointsPRegex(), $full, $mp, PREG_OFFSET_CAPTURE)) {
            $pStart = (int)$mp[0][1];
        }

        // 1ʳᵉ <ol type="a">
        $firstA = null;
        if (preg_match($this->olAlphaOpenRegex(), $full, $ma, PREG_OFFSET_CAPTURE)) {
            $firstA = (int)$ma[0][1];
        }

        if ($firstA !== null && $firstA < $pStart) {
            $qHtml = substr($full, 0, $firstA);
            $aHtml = substr($full, $firstA, $pStart - $firstA);
        } else {
            $qHtml = substr($full, 0, $pStart);
            $aHtml = '';
        }

        return [$qHtml, $aHtml, $pStart, $firstA];
    }

    /**
     * Aplatit la zone réponses en <li> de 1er niveau (suppression des <ol type="a"> imbriqués)
     */
    private function answerItemsFlatten(string $answersHtml): array {
        $items = [];
        if ($answersHtml === '') return $items;

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="wrap">'.$answersHtml.'</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        /** @var \DOMElement|null $wrap */
        $wrap = $dom->getElementById('wrap');
        if (!$wrap) return $items;

        // Helper: insérer $node après $ref dans $parent
        $insertAfter = function(\DOMElement $parent, \DOMNode $ref, \DOMNode $node): void {
            if ($ref->nextSibling) {
                $parent->insertBefore($node, $ref->nextSibling);
            } else {
                $parent->appendChild($node);
            }
        };

        // Boucle : tant qu'il reste des <ol type="a">, on "déshabille"
        while (true) {
            $alphaOl = null;
            foreach ($wrap->getElementsByTagName('ol') as $ol) {
                $type  = strtolower($ol->getAttribute('type') ?? '');
                $style = strtolower($ol->getAttribute('style') ?? '');
                if ($type === 'a' || strpos($style, 'lower-alpha') !== false) {
                    $alphaOl = $ol; break;
                }
            }
            if (!$alphaOl) break;

            // Trouver l’ancêtre LI (le plus proche), et l’ancêtre "non-LI" (où insérer)
            $liAncestor = null;
            $ancestor = $alphaOl->parentNode;
            while ($ancestor && $ancestor->nodeType === XML_ELEMENT_NODE && strtolower($ancestor->nodeName) === 'li') {
                $liAncestor = $ancestor;
                $ancestor = $ancestor->parentNode;
            }
            /** @var \DOMElement $container */
            $container = ($ancestor instanceof \DOMElement) ? $ancestor : $wrap;

            // Les LI enfants du <ol> : on les promeut au même niveau que le LI porteur
            $ref = $liAncestor ?: $alphaOl; // on insèrera APRÈS ce nœud
            $toMove = [];
            foreach (iterator_to_array($alphaOl->childNodes) as $child) {
                if ($child instanceof \DOMElement && strtolower($child->nodeName) === 'li') {
                    $toMove[] = $child;
                }
            }
            foreach ($toMove as $li) {
                $moved = $li->parentNode->removeChild($li);
                $insertAfter($container, $ref, $moved);
                $ref = $moved;
            }

            // Supprimer le <ol type="a"> désormais vide
            if ($alphaOl->parentNode) $alphaOl->parentNode->removeChild($alphaOl);
        }

        // Collecter UNIQUEMENT les <li> de 1er niveau (enfants directs de #wrap)
        foreach (iterator_to_array($wrap->childNodes) as $n) {
            if ($n instanceof \DOMElement && strtolower($n->nodeName) === 'li') {
                $html = $dom->saveHTML($n);
                $text = trim(preg_replace('~\s+~u', ' ', $n->textContent ?? ''));
                $imgs = [];
                foreach ($n->getElementsByTagName('img') as $img) {
                    $src = $img->getAttribute('src') ?? '';
                    if ($src !== '') $imgs[] = $src;
                }
                $items[] = ['html'=>$html, 'text'=>$text, 'images'=>$imgs];
            }
        }
        return $items;
    }

    /** Blocs de réponses = chaque <ol type="a"> successif dans la zone réponses */
    private function answerBlocks(string $answersHtml): array {
        $blocks = [];
        if ($answersHtml === '') return $blocks;

        // même motif que olAlphaOpenRegex(), mais on l'utilise directement ici
        $re = '~<ol\b(?=[^>]*(\btype\s*=\s*["\']?a["\']?|style\s*=\s*["\'][^"\']*lower-alpha[^"\']*["\']))[^>]*>~i';

        if (!preg_match_all($re, $answersHtml, $mm, PREG_OFFSET_CAPTURE)) {
            return $blocks;
        }
        $tags = $mm[0];
        $n = count($tags);
        for ($i = 0; $i < $n; $i++) {
            $start = (int)$tags[$i][1];
            $end   = ($i + 1 < $n) ? (int)$tags[$i + 1][1] : strlen($answersHtml);
            $chunk = substr($answersHtml, $start, $end - $start);
            $blocks[] = [
                'label' => chr(ord('A') + $i),
                'html'  => $chunk,
                'text'  => trim(preg_replace('~\s+~u', ' ', strip_tags($chunk))),
            ];
        }
        return $blocks;
    }

    /** Typage minimal (T1 par défaut, T2 indice multi, T3 sans réponses, T4 Vrai/Faux) */
    private function decideType(string $qHtml, array $answersItems): array {
        $why = [];
        $qtext = trim(preg_replace('~\s+~u',' ', strip_tags($qHtml)));
        $low   = function_exists('mb_strtolower') ? mb_strtolower($qtext, 'UTF-8') : strtolower($qtext);

        $type = 'T1';
        $n = count($answersItems);
        if ($n === 0) { $type = 'T3'; $why[] = '0 réponse → T3'; }
        elseif ($n === 2) {
            $joined = strtolower(implode(' ', array_map(fn($a)=>$a['text']??'', $answersItems)));
            if (preg_match('~\b(vrai|true)\b~', $joined) && preg_match('~\b(faux|false)\b~', $joined)) { $type = 'T4'; $why[] = 'Vrai/Faux → T4'; }
        }
        if ($type === 'T1' && preg_match('~plusieurs|cocher|cochez|sélectionnez plusieurs|choisir deux|deux réponses|au moins deux|(choisissez|sélectionnez)\s+\d+~u', $low)) {
            $type = 'T2'; $why[] = 'Indice multi-sélection dans l’énoncé';
        }
        if (!$why) $why[] = 'Par défaut → T1';
        return ['type'=>$type, 'why'=>$why];
    }

    /* ======================== Entrée principale ======================== */

    public function inspect(string $intermediateHtml, string $lotDir): array {
        $raw = file_get_contents($intermediateHtml);
        if ($raw === false) throw new \RuntimeException('Lecture HTML intermédiaire impossible');

        $body = $this->bodyHtml($raw);

        $rows   = [];
        $offset = 0;
        $idx    = 0;

        while (true) {
            // 1) Segment strict : <ol type="1"> ... <p>(points)</p>
            $seg = $this->nextStrictSegment($body, $offset);
            if (!$seg) break;

            $full  = $seg['full'];        // source complète BRUTE
            $pHtml = $seg['points_html']; // pour affichage / debug

            // 2) Découpe Q/R selon la règle (1er <ol type="a"> → points)
            [$qHtml, $aHtml, $pStart, $firstA] = $this->splitZonesFromFull($full);

            // 3) Aplatir la zone réponses en LI de 1er niveau
            $answerItems = $this->answerItemsFlatten($aHtml);

            // 4) Blocs réels (1 bloc par <ol type="a">) — optionnel/debug
            $answerBlocks = $this->answerBlocks($aHtml);

            // 5) Typage
            $decision = $this->decideType($qHtml, $answerItems);

            $idx++;
            $rows[] = [
                'index'            => $idx,
                'full_source_html' => $full,
                'question_html'    => $qHtml,
                'answers_html'     => $aHtml,
                'points_html'      => $pHtml,
                'answer_items'     => $answerItems,
                'answers_count'    => count($answerItems),
                'answer_blocks'    => $answerBlocks,
                'decision'         => $decision,
                'source_offsets'   => [
                    'start'      => $seg['start'],
                    'end'        => $seg['end'],
                    'points_rel' => $pStart,
                    'firstA_rel' => $firstA,
                ],
            ];

            // avancer après ce segment
            $offset = $seg['end'];
        }

        $out = ['inspected_at'=>date('c'), 'count'=>count($rows), 'questions'=>$rows];

        @file_put_contents($lotDir . '/inspection.json', json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
        @file_put_contents($lotDir . '/inspection_debug.json', json_encode([
            'note'     => 'answers: flattened top-level <li> (alpha OL removed)',
            'segments' => count($rows),
            'body_len' => strlen($body),
        ], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

        return $out;
    }
}
