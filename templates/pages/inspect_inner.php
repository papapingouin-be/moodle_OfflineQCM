<?php
$inspection = $_SESSION['inspection'] ?? ['count' => 0, 'questions' => []];
?>

<h3>Inspecteur – Source ➜ Découpe (Q/R) ➜ Réponses ➜ JSON</h3>
<form class="row" method="post" action="?action=run_inspect">
  <button class="btn">Recalculer</button>
</form>
<?php if (empty($inspection['questions'])): ?>
  <div class="card">Aucune question détectée. Cliquez “Recalculer”.</div>
<?php endif; ?>

<?php foreach (($inspection['questions'] ?? []) as $q): ?>
  <?php
    $full   = (string)($q['full_source_html'] ?? '');
    $qhtml  = (string)($q['question_html']    ?? '');
    $ahtml  = (string)($q['answers_html']     ?? '');
    $why    = $q['decision']['why'] ?? []; if (!is_array($why)) $why = [];
    $srcOfs = $q['source_offsets'] ?? [];
  ?>
  <div class="card" style="margin-bottom:18px;">
    <details>
      <summary>
        Question #<?= (int)($q['index'] ?? 0) ?> —
        Type: <strong><?= htmlspecialchars($q['decision']['type'] ?? '-', ENT_QUOTES) ?></strong>
      </summary>
      <p>
        <span class="badge"><?= htmlspecialchars(implode(' | ', $why), ENT_QUOTES) ?></span>
        <?php if (!empty($srcOfs)): ?>
          <span class="badge">offsets: start=<?= (int)($srcOfs['start'] ?? -1) ?>
            / end=<?= (int)($srcOfs['end'] ?? -1) ?>
            / points_rel=<?= (int)($srcOfs['points_rel'] ?? -1) ?>
            / firstA_rel=<?= isset($srcOfs['firstA_rel']) ? (int)$srcOfs['firstA_rel'] : -1 ?></span>
        <?php endif; ?>
      </p>

      <div class="grid-2">
        <!-- Colonne gauche : code source -->
        <aside class="card sticky">
          <h4>Code source</h4>
          <details>
            <summary>Preview (brut + images)</summary>
            <div class="card"><?= $full !== '' ? rewrite_media_srcs($full) : '<span class="muted">(vide)</span>' ?></div>
          </details>
          <details>
            <summary>Code HTML (RAW — aucun traitement)</summary>
            <pre><code class="language-html"><?= htmlspecialchars($full, ENT_QUOTES) ?></code></pre>
          </details>
          <details>
            <summary>Code HTML (indenté — sans DOM)</summary>
            <pre><code class="language-html"><?= htmlspecialchars(pretty_html($full), ENT_QUOTES) ?></code></pre>
          </details>
        </aside>

        <!-- Colonne droite : étapes de détection -->
        <main>
          <h4>Étapes de détection</h4>
          <details>
            <summary>Zone question — Preview</summary>
            <div class="card"><?= $qhtml !== '' ? rewrite_media_srcs($qhtml) : '<span class="muted">(vide)</span>' ?></div>
          </details>
          <details>
            <summary>Zone question — Code</summary>
            <pre><code class="language-html"><?= htmlspecialchars(pretty_html($qhtml), ENT_QUOTES) ?></code></pre>
          </details>

          <details style="margin-top:8px;">
            <summary>Zone réponses — Preview</summary>
            <div class="card"><?= $ahtml !== '' ? rewrite_media_srcs($ahtml) : '<span class="muted">(vide)</span>' ?></div>
          </details>
          <details>
            <summary>Zone réponses — Code</summary>
            <pre><code class="language-html"><?= htmlspecialchars(pretty_html($ahtml), ENT_QUOTES) ?></code></pre>
          </details>

          <h5 style="margin-top:12px;">Questions détectées</h5>
          <div class="card">
            <?php foreach (($q['answer_items'] ?? []) as $i => $opt): ?>
              <?php $label = chr(ord('A') + (int)$i); ?>
              <div class="answer-block">
                <b>Option <?= htmlspecialchars($label, ENT_QUOTES) ?> :</b>
                <details>
                  <summary>Preview</summary>
                  <div><?= rewrite_media_srcs($opt['html'] ?? '') ?></div>
                </details>
                <details>
                  <summary>Texte + Code</summary>
                  <p><em>Texte :</em> <?= htmlspecialchars($opt['text'] ?? '', ENT_QUOTES) ?></p>
                  <pre><code class="language-html"><?= htmlspecialchars(pretty_html($opt['html'] ?? ''), ENT_QUOTES) ?></code></pre>
                </details>
              </div>
            <?php endforeach; ?>
            <?php if (empty($q['answer_items'])): ?>
              <em class="muted">Aucune option détectée (LI 1er niveau).</em>
            <?php endif; ?>
          </div>

          <h5 style="margin-top:12px;">Blocs de réponses (<code>&lt;ol type=\"a\"&gt;</code> successifs) — DEBUG</h5>
          <div class="card">
            <?php foreach (($q['answer_blocks'] ?? []) as $i => $blk): ?>
              <?php $label = $blk['label'] ?? chr(ord('A') + (int)$i); $html = (string)($blk['html'] ?? ''); ?>
              <div class="answer-block">
                <b>Bloc <?= htmlspecialchars($label, ENT_QUOTES) ?> :</b>
                <details>
                  <summary>Preview</summary>
                  <div><?= rewrite_media_srcs($html) ?></div>
                </details>
                <details>
                  <summary>Code (indenté)</summary>
                  <pre><code class="language-html"><?= htmlspecialchars(pretty_html($html), ENT_QUOTES) ?></code></pre>
                </details>
              </div>
            <?php endforeach; ?>
            <?php if (empty($q['answer_blocks'])): ?>
              <em class="muted">Aucun bloc de réponses détecté.</em>
            <?php endif; ?>
          </div>
        </main>
      </div>

      <h4 style="margin-top:12px;">JSON (question)</h4>
      <pre><code class="language-json"><?= htmlspecialchars(
        json_encode($q ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ENT_QUOTES
        ) ?></code></pre>
        </details>
      </div>
<?php endforeach; ?>



<link rel="stylesheet" href="https://unpkg.com/prismjs/themes/prism.min.css">

<script defer src="https://unpkg.com/prismjs/components/prism-core.min.js"></script>

<script defer src="https://unpkg.com/prismjs/plugins/autoloader/prism-autoloader.min.js"></script>


