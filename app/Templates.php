<?php
declare(strict_types=1);

final class Templates
{
    private const TEMPLATE_DIR = TEMPLATES_PATH . '/question_templates';

    private const BASE_TEMPLATE = <<<HTML
<div class="qblock" data-template="{{ template_name }}" data-type="{{ question_type }}">
  <div class="qtext"><span class="qnum">{{ question_index }}. </span>{{ statement_html }}</div>
  {{ images }}
  {{ answers_list }}
</div>
HTML;

    public static function renderQuestion(array $q): string
    {
        $answers = [];
        foreach ($q['answers'] ?? [] as $a) {
            $answers[] = preg_replace('~^<li\\b~', '<li class="ans"', $a['html'] ?? '');
        }

        $answersItems = implode('', $answers);
        $answersList  = $answersItems === '' ? '' : '<ol class="answers" type="a">' . $answersItems . '</ol>';
        $images       = self::images($q['statement']['images'] ?? []);

        $templateName = $q['template'] ?? ($q['type'] ?? 'T1');
        $template     = self::loadTemplateContent($templateName, $q['type'] ?? null);

        $map = self::buildReplacementMap($q, $templateName, $images, $answersList, $answersItems);

        return strtr($template, $map);
    }

    public static function images(array $srcs): string
    {
        if ($srcs === []) {
            return '';
        }

        $tags = array_map(
            static fn($s) => '<img src="' . htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="img" class="qimg">',
            $srcs
        );

        return '<div class="qimgs">' . implode('', $tags) . '</div>';
    }

    public static function availableTypes(): array
    {
        return [
            'T1' => [
                'label'       => 'Choix unique',
                'description' => 'Question à choix simple avec une seule réponse correcte.',
            ],
            'T2' => [
                'label'       => 'Choix multiple',
                'description' => 'Question permettant plusieurs réponses.',
            ],
            'T3' => [
                'label'       => 'Réponse libre',
                'description' => 'Question sans propositions (réponse à rédiger).',
            ],
            'T4' => [
                'label'       => 'Vrai / Faux',
                'description' => 'Question binaire avec deux propositions.',
            ],
        ];
    }

    public static function placeholderDocs(?string $type = null): array
    {
        $docs = [
            [
                'tag'         => '{{ template_name }}',
                'description' => 'Nom du template utilisé (utile pour du debug).',
            ],
            [
                'tag'         => '{{ question_type }}',
                'description' => 'Type détecté (T1, T2, T3, T4…).',
            ],
            [
                'tag'         => '{{ question_index }}',
                'description' => 'Numéro de la question.',
            ],
            [
                'tag'         => '{{ statement_html }}',
                'description' => 'Énoncé complet au format HTML.',
            ],
            [
                'tag'         => '{{ statement_text }}',
                'description' => 'Version texte de l’énoncé (sans balises).',
            ],
            [
                'tag'         => '{{ images }}',
                'description' => 'Bloc `<div>` contenant toutes les images (vide si aucune).',
            ],
            [
                'tag'         => '{{ answers_list }}',
                'description' => 'Liste `<ol>` déjà prête avec les propositions.',
                'types'       => ['T1', 'T2', 'T4'],
            ],
            [
                'tag'         => '{{ answers_items }}',
                'description' => 'Propositions seules (suite de `<li>`).',
                'types'       => ['T1', 'T2', 'T4'],
            ],
            [
                'tag'         => '{{ answers_count }}',
                'description' => 'Nombre de propositions détectées.',
            ],
            [
                'tag'         => '{{ answers_letters }}',
                'description' => 'Liste des lettres associées aux réponses (A, B, C…).',
                'types'       => ['T1', 'T2', 'T4'],
            ],
        ];

        if ($type === null) {
            return $docs;
        }

        $type = self::sanitizeName($type);

        return array_values(array_filter(
            $docs,
            static function (array $entry) use ($type): bool {
                if (!isset($entry['types'])) {
                    return true;
                }
                return in_array($type, $entry['types'], true);
            }
        ));
    }

    public static function normalizeName(?string $name): string
    {
        return self::sanitizeName($name);
    }

    public static function loadTemplateContent(string $name, ?string $fallbackType = null): string
    {
        $clean = self::sanitizeName($name);
        $path  = self::templatePath($clean);

        if (is_readable($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                return $content;
            }
        }

        $fallback = $fallbackType ? self::sanitizeName($fallbackType) : $clean;

        return self::defaultTemplate($fallback);
    }

    public static function saveTemplateContent(string $name, string $content): void
    {
        $clean = self::sanitizeName($name);
        $types = self::availableTypes();
        if (!array_key_exists($clean, $types)) {
            throw new InvalidArgumentException('Type de template inconnu.');
        }

        self::ensureTemplateDir();
        $path = self::templatePath($clean);

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException('Impossible d\'écrire le template sur le disque.');
        }
    }

    private static function buildReplacementMap(
        array $q,
        string $templateName,
        string $images,
        string $answersList,
        string $answersItems
    ): array {
        $index     = (string) ($q['index'] ?? '');
        $type      = (string) ($q['type'] ?? '');
        $statement = $q['statement'] ?? [];
        $answers   = $q['answers'] ?? [];

        $letters = [];
        $count   = count($answers);
        for ($i = 0; $i < $count; $i++) {
            $letters[] = chr(65 + ($i % 26));
        }

        return [
            '{{ template_name }}'   => htmlspecialchars($templateName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{ question_type }}'   => htmlspecialchars($type, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{ question_index }}'  => htmlspecialchars($index, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{ statement_html }}'  => $statement['html'] ?? '',
            '{{ statement_text }}'  => htmlspecialchars((string) ($statement['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            '{{ images }}'          => $images,
            '{{ answers_list }}'    => $answersList,
            '{{ answers_items }}'   => $answersItems,
            '{{ answers_count }}'   => (string) $count,
            '{{ answers_letters }}' => $letters === [] ? '' : implode(', ', $letters),
        ];
    }

    private static function sanitizeName(?string $name): string
    {
        $sanitized = strtoupper(preg_replace('~[^A-Za-z0-9_-]+~', '', (string) $name));
        if ($sanitized === 'Q1') {
            $sanitized = 'T1';
        }
        if ($sanitized === '') {
            $sanitized = 'T1';
        }
        return $sanitized;
    }

    private static function templatePath(string $name): string
    {
        return self::TEMPLATE_DIR . '/' . $name . '.html';
    }

    private static function ensureTemplateDir(): void
    {
        $dir = self::TEMPLATE_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function defaultTemplate(string $name): string
    {
        return match ($name) {
            'T3' => <<<HTML
<div class="qblock" data-template="{{ template_name }}" data-type="{{ question_type }}">
  <div class="qtext"><span class="qnum">{{ question_index }}. </span>{{ statement_html }}</div>
  {{ images }}
</div>
HTML,
            default => self::BASE_TEMPLATE,
        };
    }
}
