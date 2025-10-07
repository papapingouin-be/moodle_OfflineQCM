<?php
declare(strict_types=1);

final class Grouper {
    /**
     * Construit des blocs de questions à partir de définitions de groupes.
     * Chaque définition contient :
     *   - from : numéro de question de début (inclus)
     *   - to   : numéro de question de fin (inclus)
     *   - layout : 'horizontal' ou 'vertical'
     * Les questions non couvertes par une définition sont retournées individuellement.
     *
     * @param array $questions Liste complète des questions (avec index).
     * @param array $defs      Définitions des groupes.
     * @return array Liste de blocs ('single' ou 'group').
     */
    public static function build(array $questions, array $defs): array {
        $blocks = [];
        $indexMap = [];
        foreach ($questions as $q) {
            $indexMap[$q['index']] = $q;
        }

        // marquer les questions utilisées par des groupes
        $used = [];
        usort($defs, fn($a, $b) => ($a['from'] ?? 0) <=> ($b['from'] ?? 0));
        $gidx = 0;
        foreach ($defs as $def) {
            $from = (int)($def['from'] ?? 0);
            $to   = (int)($def['to']   ?? $from);
            $layout = ($def['layout'] ?? 'horizontal') === 'vertical' ? 'vertical' : 'horizontal';
            $groupQs = [];
            for ($i = $from; $i <= $to; $i++) {
                if (isset($indexMap[$i])) {
                    $groupQs[] = $indexMap[$i];
                    $used[$i] = true;
                }
            }
            if ($groupQs) {
                $blocks[] = [
                    'type'     => 'group',
                    'questions'=> $groupQs,
                    'layout'   => $layout,
                    'from'     => $from,
                    'to'       => $to,
                    'index'    => $gidx,
                ];
                $gidx++;
            }
        }

        // questions restantes en blocs simples
        foreach ($questions as $q) {
            if (!isset($used[$q['index']])) {
                $blocks[] = ['type' => 'single', 'question' => $q];
            }
        }

        // trier les blocs par numéro de question
        usort($blocks, function ($a, $b) {
            $aIdx = $a['type'] === 'group' ? ($a['from'] ?? 0) : ($a['question']['index'] ?? 0);
            $bIdx = $b['type'] === 'group' ? ($b['from'] ?? 0) : ($b['question']['index'] ?? 0);
            return $aIdx <=> $bIdx;
        });

        return $blocks;
    }
}
