<?php
function debug_log($message) {
    file_put_contents(__DIR__ . '/conversion_debug.log', $message . PHP_EOL, FILE_APPEND);
}

function to_utf8($text) {
    // Nettoyage encodage
    return mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
}

/**
 * Si le contenu contient du HTML, on le renvoie tel quel (pour conserver SVG, balises, MathJax, etc.).
 * Sinon, on protège et on emballe dans <p dir="ltr">...</p>.
 */
function wrap_html_or_text(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '<p dir="ltr"></p>';
    }
    // Détection d’une balise HTML quelconque
    if (preg_match('~<[^>]+>~', $text)) {
        return $text; // garder brut
    }
    // Texte nu → on protège
    return '<p dir="ltr">' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csvtext'])) {
    $outputFile = __DIR__ . '/quiz_moodle_export.xml';
    $logFile = __DIR__ . '/conversion_debug.log';

    file_put_contents($logFile, '');

    $csvContent = to_utf8($_POST['csvtext']);

    $lines = explode(PHP_EOL, $csvContent);
    $lines = array_filter($lines, function($line) { return trim($line) !== ''; });

    if (count($lines) < 2) {
        debug_log("CSV trop court");
        die("Aucune donnée détectée.");
    }

    // On enlève l’entête
    $header = str_getcsv(array_shift($lines), ";");

    // Correspondance des colonnes attendues :
    // ID;Titre;Question;Pénalité;Réponse1;Feedback1;Réponse2;Feedback2;Réponse3;Feedback3;Réponse4;Feedback4
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;
    $quiz = $xml->createElement('quiz');
    $xml->appendChild($quiz);

    $count = 0;
    foreach ($lines as $num => $line) {
        $fields = str_getcsv($line, ";");
        if (count($fields) < 12) {
            debug_log("Ligne $num: champs manquants (" . implode(" | ", $fields) . ")");
            continue;
        }

        list($id, $titre, $question, $penalty, $r1, $fb1, $r2, $fb2, $r3, $fb3, $r4, $fb4) = $fields;
        $count++;
        debug_log("Question $count: $titre");

        $questionEl = $xml->createElement('question');
        $questionEl->setAttribute('type', 'multichoice');

        // <name>
        $name = $xml->createElement('name');
        $nametext = $xml->createElement('text', htmlspecialchars($titre, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8'));
        $name->appendChild($nametext);
        $questionEl->appendChild($name);

        // <questiontext format="html">
        $qtext = $xml->createElement('questiontext');
        $qtext->setAttribute('format', 'html');
        $qtexttext = $xml->createElement('text');
        $qtexttext->appendChild($xml->createCDATASection(wrap_html_or_text($question)));
        $qtext->appendChild($qtexttext);
        $questionEl->appendChild($qtext);

        // <generalfeedback format="html"> — vide par défaut
        $gfb = $xml->createElement('generalfeedback');
        $gfb->setAttribute('format', 'html');
        $gfb->appendChild($xml->createElement('text'));
        $questionEl->appendChild($gfb);

        $questionEl->appendChild($xml->createElement('defaultgrade', '1'));
        $questionEl->appendChild($xml->createElement('penalty', $penalty));
        $questionEl->appendChild($xml->createElement('hidden', '0'));
        $questionEl->appendChild($xml->createElement('idnumber', $id));
        $questionEl->appendChild($xml->createElement('single', 'true'));
        $questionEl->appendChild($xml->createElement('shuffleanswers', 'true'));
        $questionEl->appendChild($xml->createElement('answernumbering', 'abc'));
        $questionEl->appendChild($xml->createElement('showstandardinstruction', '0'));

        // Feedbacks par défaut (texte simple, pas besoin de CDATA)
        $fbfields = [
            'correctfeedback' => 'Votre réponse est correcte.',
            'partiallycorrectfeedback' => 'Votre réponse est partiellement correcte.',
            'incorrectfeedback' => 'Votre réponse est incorrecte.',
        ];
        foreach ($fbfields as $fbtag => $defaultText) {
            $el = $xml->createElement($fbtag);
            $el->setAttribute('format', 'html');
            $txt = $xml->createElement('text', $defaultText);
            $el->appendChild($txt);
            $questionEl->appendChild($el);
        }

        $questionEl->appendChild($xml->createElement('shownumcorrect'));

        // Answers
        for ($i = 0; $i < 4; $i++) {
            $answerText   = ${'r'.($i+1)};
            $feedbackText = ${'fb'.($i+1)};

            $answer = $xml->createElement('answer');
            $answer->setAttribute('fraction', $i == 0 ? '100' : '0'); // Premier = bonne réponse
            $answer->setAttribute('format', 'html');

            $atext = $xml->createElement('text');
            // Plus d’injection de <p><br></p> automatique : on respecte le contenu
            $atext->appendChild($xml->createCDATASection(wrap_html_or_text($answerText)));
            $answer->appendChild($atext);

            $feedback = $xml->createElement('feedback');
            $feedback->setAttribute('format', 'html');
            $ftext = $xml->createElement('text');
            $ftext->appendChild($xml->createCDATASection(wrap_html_or_text($feedbackText)));
            $feedback->appendChild($ftext);

            $answer->appendChild($feedback);
            $questionEl->appendChild($answer);
        }

        $quiz->appendChild($questionEl);
    }

    $xml->save($outputFile);

    ob_clean();
    flush();
    header('Content-Description: File Transfer');
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="quiz_moodle_export.xml"');
    header('Content-Length: ' . filesize($outputFile));
    readfile($outputFile);
    unlink($outputFile);
    exit;
}
?>

<form method="post">
    <h3>Coller le CSV QCM (en-tête obligatoire, séparateur : ;)</h3>
    <textarea name="csvtext" rows="10" cols="120" placeholder="ID;Titre;Question;Pénalité;Réponse1;Feedback1;Réponse2;Feedback2;Réponse3;Feedback3;Réponse4;Feedback4"></textarea>
    <br>
    <button type="submit">Convertir en XML Moodle</button>
</form>
