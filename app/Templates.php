<?php
declare(strict_types=1);

final class Templates {
    public static function renderQuestion(array $q): string {
        $answers = [];
        foreach ($q['answers'] as $a) {
            $answers[] = preg_replace('~^<li\\b~', '<li class="ans"', $a['html']);
        }
        $imgs = self::images($q['statement']['images']);
        $qText = '<span class="qnum">'.$q['index'].'. </span>'.$q['statement']['html'];
        return '<div class="qblock"><div class="qtext">'.$qText.'</div>'.$imgs.'<ol class="answers" type="a">'.implode('', $answers).'</ol></div>';
    }

    public static function images(array $srcs): string {
        if (!$srcs) return '';
        $tags = array_map(fn($s)=>'<img src="'.htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8').'" alt="img" class="qimg">', $srcs);
        return '<div class="qimgs">'.implode('', $tags).'</div>';
    }
}
