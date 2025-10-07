<?php
/** @var string $type */
$types = Templates::availableTypes();
$normalized = Templates::normalizeName($type ?? 'T1');
if (!isset($types[$normalized])) {
    $keys = array_keys($types);
    $normalized = $keys[0] ?? 'T1';
}
$templateContent = Templates::loadTemplateContent($normalized, $normalized);
$placeholders = Templates::placeholderDocs($normalized);
$currentMeta = $types[$normalized] ?? ['label' => $normalized, 'description' => ''];
?>

<div class="template-header">
  <div>
    <h3>Éditeur de template de question</h3>
    <p class="text-muted">Personnalisez la structure HTML utilisée lors du rendu des questions dans la prévisualisation et l'export.</p>
  </div>
</div>

<div class="template-toolbar row" style="align-items:flex-end;">
  <label class="template-select">
    Type de question
    <select id="templateType">
      <?php foreach ($types as $code => $meta): ?>
        <option value="<?= htmlspecialchars($code, ENT_QUOTES) ?>" <?= $code === $normalized ? 'selected' : '' ?>><?= htmlspecialchars($code . ' — ' . ($meta['label'] ?? $code), ENT_QUOTES) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button class="btn primary" id="saveTemplate">Enregistrer le template</button>
  <span id="templateStatus" class="text-muted"></span>
</div>

<div class="template-editor">
  <div class="editor-pane">
    <div class="template-meta">
      <h4 id="templateTitle"><?= htmlspecialchars($normalized . ' — ' . ($currentMeta['label'] ?? $normalized), ENT_QUOTES) ?></h4>
      <p id="templateDescription" class="text-muted"><?= htmlspecialchars($currentMeta['description'] ?? '', ENT_QUOTES) ?></p>
    </div>
    <div id="templateEditor" class="code-editor" aria-label="Éditeur de template"></div>
  </div>
  <aside class="docs-pane">
    <h4>Balises disponibles</h4>
    <p class="text-muted small">Ces balises seront remplacées automatiquement lors du rendu.</p>
    <ul id="placeholderList" class="placeholder-list">
      <?php foreach ($placeholders as $entry): ?>
        <li>
          <code><?= htmlspecialchars($entry['tag'] ?? '', ENT_QUOTES) ?></code>
          <p><?= htmlspecialchars($entry['description'] ?? '', ENT_QUOTES) ?></p>
        </li>
      <?php endforeach; ?>
    </ul>
  </aside>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.23.4/ace.js" integrity="sha512-u4q7mDRu1qY07ZhE8LwQ2TWwPEBUacfjYLAQAHr53Tx5qr4uIX067OJHUz+2cmgJ8e6mpjqpQi9SgSdLD9gxXQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function(){
  const initialContent = <?= json_encode($templateContent, JSON_UNESCAPED_UNICODE) ?>;
  const initialPlaceholders = <?= json_encode($placeholders, JSON_UNESCAPED_UNICODE) ?>;
  const metaByType = <?= json_encode($types, JSON_UNESCAPED_UNICODE) ?>;
  const normalized = <?= json_encode($normalized, JSON_UNESCAPED_UNICODE) ?>;

  function updatePlaceholderList(listEl, entries){
    listEl.innerHTML = '';
    if (!Array.isArray(entries) || entries.length === 0) {
      const li = document.createElement('li');
      li.textContent = 'Aucune balise spécifique pour ce type.';
      li.classList.add('text-muted');
      listEl.appendChild(li);
      return;
    }
    entries.forEach(entry => {
      const li = document.createElement('li');
      const code = document.createElement('code');
      code.textContent = entry.tag || '';
      const desc = document.createElement('p');
      desc.textContent = entry.description || '';
      li.appendChild(code);
      li.appendChild(desc);
      listEl.appendChild(li);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const typeSelect = document.getElementById('templateType');
    const statusEl = document.getElementById('templateStatus');
    const placeholderList = document.getElementById('placeholderList');
    const titleEl = document.getElementById('templateTitle');
    const descEl = document.getElementById('templateDescription');

    const editorHost = document.getElementById('templateEditor');
    let getContent = () => initialContent || '';
    let setContent = () => {};

    function activateFallback(message){
      const textarea = document.createElement('textarea');
      textarea.id = 'templateEditorFallback';
      textarea.className = 'code-editor template-editor-fallback';
      textarea.setAttribute('aria-label', "Éditeur de template (mode texte simple)");
      textarea.spellcheck = false;
      textarea.value = initialContent || '';
      if (editorHost && editorHost.parentNode) {
        editorHost.parentNode.replaceChild(textarea, editorHost);
      }
      statusEl.textContent = message || "Ace Editor n'a pas pu être chargé. Passage en éditeur texte.";
      statusEl.classList.remove('error', 'success');
      statusEl.classList.add('warning');
      setContent = value => { textarea.value = value || ''; };
      getContent = () => textarea.value;
    }

    if (typeof ace !== 'undefined') {
      try {
        const editor = ace.edit('templateEditor');
        editor.setTheme('ace/theme/textmate');
        editor.session.setMode('ace/mode/html');
        editor.session.setUseWrapMode(true);
        editor.setOptions({ fontSize: '14px', showPrintMargin: false });
        editor.setValue(initialContent || '', -1);
        setContent = value => { editor.setValue(value || '', -1); };
        getContent = () => editor.getValue();
      } catch (err) {
        console.error('Ace initialisation failed', err);
        activateFallback("Ace Editor a rencontré une erreur. Passage en éditeur texte.");
      }
    } else {
      activateFallback("Ace Editor n'a pas pu être chargé. Passage en éditeur texte.");
    }

    updatePlaceholderList(placeholderList, initialPlaceholders);

    function applyMeta(typeCode){
      const meta = metaByType[typeCode] || { label: typeCode, description: '' };
      titleEl.textContent = `${typeCode} — ${meta.label || typeCode}`;
      descEl.textContent = meta.description || '';
    }

    applyMeta(normalized);

    typeSelect.addEventListener('change', async () => {
      const type = typeSelect.value;
      statusEl.textContent = '';
      statusEl.classList.remove('success', 'error', 'warning');
      try {
        const res = await fetch(`?action=load_template&type=${encodeURIComponent(type)}`);
        if (!res.ok) throw new Error('Chargement impossible');
        const js = await res.json();
        if (!js.ok) throw new Error(js.error || 'Erreur lors du chargement');
        setContent(js.content || '');
        updatePlaceholderList(placeholderList, js.placeholders || []);
        applyMeta(js.type || type);
      } catch (err) {
        statusEl.textContent = err.message || 'Erreur inconnue';
        statusEl.classList.add('error');
      }
    });

    document.getElementById('saveTemplate').addEventListener('click', async () => {
      const type = typeSelect.value;
      statusEl.textContent = 'Enregistrement…';
      statusEl.classList.remove('error', 'success', 'warning');
      try {
        const res = await fetch('?action=save_template', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ type, content: getContent() })
        });
        const js = await res.json();
        if (!res.ok || !js.ok) throw new Error(js.error || 'Erreur lors de la sauvegarde');
        statusEl.textContent = 'Template enregistré.';
        statusEl.classList.add('success');
        if (window.opener && typeof window.opener.previewAll === 'function') {
          window.opener.previewAll();
        }
      } catch (err) {
        statusEl.textContent = err.message || 'Erreur inconnue';
        statusEl.classList.add('error');
      }
    });
  });
})();
</script>
