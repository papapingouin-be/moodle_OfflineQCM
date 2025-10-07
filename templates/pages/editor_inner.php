<?php
$extract = $_SESSION['extract'] ?? ['questions'=>[]];
$groups  = $_SESSION['groups'] ?? [];
$blocks  = Grouper::build($extract['questions'], $groups);
$meta    = $_SESSION['meta'] ?? ['title' => '', 'letter' => ''];
$typesUsed = [];
foreach ($extract['questions'] as $q) {
  $t = $q['type'] ?? '';
  if ($t !== '') $typesUsed[$t] = true;
}
$typesUsed = array_keys($typesUsed);
?>

<h3>Paramètres du questionnaire</h3>
<div class="row" style="margin-bottom:16px; gap:8px; align-items:flex-end;">
  <label>Titre
    <input type="text" id="metaTitle" value="<?= htmlspecialchars($meta['title'] ?? '', ENT_QUOTES) ?>">
  </label>
  <label>Lettre
    <input type="text" id="metaLetter" style="width:80px" value="<?= htmlspecialchars($meta['letter'] ?? '', ENT_QUOTES) ?>">
  </label>
  <button class="btn" id="saveMeta">Sauvegarder</button>
</div>

<h3>Éditeur des groupes</h3>
<div class="row">
  <div class="card" style="flex:1; min-width:320px">
    <h4>Groupes</h4>
    <table class="table">
      <thead><tr><th>De</th><th>À</th><th>Disposition</th><th>Actions</th></tr></thead>
      <tbody id="groupTable">
        <?php foreach ($groups as $i=>$g): ?>
        <tr>
          <td><input type="number" class="js-from" value="<?= (int)($g['from'] ?? 0) ?>"></td>
          <td><input type="number" class="js-to" value="<?= (int)($g['to'] ?? 0) ?>"></td>
          <td>
            <select class="js-layout">
              <option value="horizontal" <?= ($g['layout'] ?? '')==='horizontal'?'selected':'' ?>>Horizontal</option>
              <option value="vertical" <?= ($g['layout'] ?? '')==='vertical'?'selected':'' ?>>Vertical</option>
            </select>
          </td>
          <td><button class="btn btn-mini js-del">Supprimer</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
      <div class="row">
        <button class="btn" id="addGroup">Ajouter</button>
        <button class="btn" id="saveGroups">Sauvegarder</button>
      </div>
      <hr>
      <h5>Sélection rapide</h5>
      <div id="questionList" class="row" style="flex-wrap:wrap; gap:4px; margin-bottom:8px;">
        <?php foreach ($extract['questions'] as $q): ?>
          <label class="btn ghost"><input type="checkbox" value="<?= (int)($q['index'] ?? 0) ?>"> Q<?= (int)($q['index'] ?? 0) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="row">
        <select id="quickLayout">
          <option value="horizontal">Horizontal</option>
          <option value="vertical">Vertical</option>
        </select>
        <button class="btn" id="createGroup">Créer</button>
      </div>
    </div>
  <div class="card" style="flex:2; min-width:380px">
    <h4>Prévisualisation</h4>
    <div class="preview" id="preview">—</div>
    <div class="row">
      <a class="btn" href="?action=export">Exporter (rendu final)</a>
      <button class="btn ghost" id="editTemplate" data-types='<?= htmlspecialchars(json_encode($typesUsed, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>'>Éditer les templates</button>
    </div>
    <p class="text-muted small">Le bouton «&nbsp;Éditer les templates&nbsp;» ouvre l’éditeur dans une nouvelle fenêtre. Vous pouvez également y accéder via le bouton «&nbsp;Templates&nbsp;» dans la barre supérieure.</p>
  </div>
</div>
<script>
let groups = <?= json_encode($groups, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

function renderTable(){
  const tbody = document.getElementById('groupTable');
  tbody.innerHTML = '';
  groups.forEach(g=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="number" class="js-from" value="${g.from||0}"></td>
      <td><input type="number" class="js-to" value="${g.to||0}"></td>
      <td><select class="js-layout">
            <option value="horizontal"${g.layout==='horizontal'?' selected':''}>Horizontal</option>
            <option value="vertical"${g.layout==='vertical'?' selected':''}>Vertical</option>
          </select></td>
      <td><button class="btn btn-mini js-del">Supprimer</button></td>
    `;
    tbody.appendChild(tr);
  });
}

function collectGroups(){
  groups = [];
  document.querySelectorAll('#groupTable tr').forEach(tr=>{
    groups.push({
      from: parseInt(tr.querySelector('.js-from').value,10)||0,
      to: parseInt(tr.querySelector('.js-to').value,10)||0,
      layout: tr.querySelector('.js-layout').value
    });
  });
}

document.getElementById('addGroup').addEventListener('click', ()=>{
  groups.push({from:1,to:1,layout:'horizontal'});
  renderTable();
});

document.getElementById('groupTable').addEventListener('click', e=>{
  if(e.target.classList.contains('js-del')){
    e.target.closest('tr').remove();
    collectGroups();
  }
});

document.getElementById('createGroup').addEventListener('click', ()=>{
  const checked = Array.from(document.querySelectorAll('#questionList input[type=checkbox]:checked')).map(cb=>parseInt(cb.value,10));
  if(checked.length===0) return;
  const from = Math.min(...checked);
  const to = Math.max(...checked);
  const layout = document.getElementById('quickLayout').value;
  groups.push({from,to,layout});
  renderTable();
  document.querySelectorAll('#questionList input[type=checkbox]').forEach(cb=>cb.checked=false);
});

document.getElementById('saveGroups').addEventListener('click', async ()=>{
  collectGroups();
  const res = await fetch('?action=save_groups', {method:'POST', body: JSON.stringify({groups})});
  const js = await res.json();
  if(js.ok) alert('Sauvegardé'); else alert(js.error||'Erreur');
  previewAll();
});

document.getElementById('saveMeta').addEventListener('click', async ()=>{
  const title = document.getElementById('metaTitle').value;
  const letter = document.getElementById('metaLetter').value;
  const res = await fetch('?action=save_meta', {method:'POST', body: JSON.stringify({title, letter})});
  const js = await res.json();
  if(js.ok) alert('Sauvegardé'); else alert(js.error||'Erreur');
  previewAll();
});

async function previewAll(){
  const res = await fetch('?action=preview');
  const html = await res.text();
  document.getElementById('preview').innerHTML = html;
}

renderTable();
previewAll();

const editBtn = document.getElementById('editTemplate');
if (editBtn) {
  const types = (() => {
    try {
      return JSON.parse(editBtn.dataset.types || '[]');
    } catch (e) {
      return [];
    }
  })();
  editBtn.addEventListener('click', () => {
    const targetType = types.length ? types[0] : 'T1';
    const features = 'width=1100,height=800,menubar=no,toolbar=no,location=no';
    window.open(`?action=template_editor&type=${encodeURIComponent(targetType)}`, '_blank', features);
  });
}
</script>
