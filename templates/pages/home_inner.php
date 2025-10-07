<div class="row">
  <div>
    <div class="badge" id="st-upload" data-status>Upload</div>
    <div class="badge" id="st-docx" data-status>DOCX</div>
    <div class="badge" id="st-html" data-status>Interm. HTML</div>
    <div class="badge" id="st-extract" data-status>Extraction</div>
  </div>
</div>

<h3>1) Upload du ZIP</h3>
<form id="upload-form" class="row" enctype="multipart/form-data" method="post" action="?action=upload">
  <input type="file" name="zip" accept=".zip" required>
  <button class="btn primary" type="submit">Uploader</button>
</form>

<h3>2) DOCX détectés</h3>
<form id="scan-sync" class="row" method="post" action="?action=scan_docx_sync">
  <button class="btn">Scanner (serveur)</button>
  <span class="badge">Utilisez ceci si la liste ne s’affiche pas correctement.</span>
</form>

<div id="docx-list">
<?php if (!empty($_SESSION['docx'])): ?>
  <div class="row" id="docx-items">
    <?php foreach ($_SESSION['docx'] as $i=>$p): $bn = basename($p); ?>
      <label class="btn ghost"><input type="radio" name="docx" value="<?= htmlspecialchars($p, ENT_QUOTES) ?>" <?= $i===0?'checked':''; ?>> <?= htmlspecialchars($bn, ENT_QUOTES) ?></label>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <em>Aucun DOCX en session (utilisez “Scanner (serveur)” ou le bouton “Scan” au-dessus qui fonctionne en JS).</em>
<?php endif; ?>
</div>

<h3>3) Conversion vers HTML intermédiaire</h3>
<div class="row">
  <button class="btn" id="btn-convert">Convertir</button>
  <span>Fichier: <code id="html-path"></code></span>
</div>

<h3>4) Extraction des questions</h3>
<div class="row"><button class="btn" id="btn-extract">Extraire</button></div>

<table class="table" id="q-table">
  <thead><tr><th>Imgs Q</th><th>Réponses</th><th>Action</th></tr></thead>
  <tbody></tbody>
</table>

<div class="hidden" data-step="editor">
  <h3>5) Éditeur & prévisualisation</h3>
  <a class="btn" href="?action=editor">Ouvrir l’éditeur</a>
</div>

<h3>Éditeur de templates de questions</h3>
<p class="text-muted">Utilisez cet éditeur pour personnaliser l’affichage HTML des différentes formes de questions (T1, T2, …). Il peut être ouvert à tout moment.</p>
<a class="btn ghost" href="?action=template_editor" target="_blank">Ouvrir l’éditeur de templates</a>

<h3>Debug</h3>
<div class="toolbox">
  <a class="btn" href="?action=open_intermediate" target="_blank">Voir intermediate.html</a>
  <a class="btn" href="?action=convert_stats" target="_blank">Stats conversion</a>
  <a class="btn" href="?action=extract_debug" target="_blank">Debug extraction</a>
  <a class="btn" href="?action=inspect">Inspecteur</a>
  <button class="btn" id="btn-log">Voir log</button>
  <button class="btn" id="btn-log-clear">Vider log</button>
</div>
<pre class="log" id="log"></pre>
