document.addEventListener('DOMContentLoaded', () => {
  const $ = (s, r=document) => r.querySelector(s);
  const $$ = (s, r=document) => [...r.querySelectorAll(s)];

  const upForm = $('#upload-form');
  if (upForm) {
    upForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(upForm);
      const res = await fetch('?action=upload', { method:'POST', body:fd });
      const js = await res.json();
      if (!js.ok) return alert(js.error||'Erreur upload');
      serviceStates.upload = true; updateServiceStatus();
      step('upload'); await scanDocx();
    });
  }


  const serviceStates = {
    upload: false,
    docx: false,
    html: false,
    extract: false,
    log: false
  };

  function updateServiceStatus() {
    const list = $('#service-status');
    if (!list) return;
    const entries = [
      ['Upload', serviceStates.upload],
      ['Scan DOCX', serviceStates.docx],
      ['Conversion HTML', serviceStates.html],
      ['Extraction', serviceStates.extract],
      ['Log debug', serviceStates.log],
    ];
    list.innerHTML = entries
      .map(([name, ok]) => `<li>${name}: <strong>${ok ? 'actif' : 'inactif'}</strong></li>`)
      .join('');
  }

  async function scanDocx() {
    const res = await fetch('?action=scan_docx');
    const js = await res.json();
    if (!js.ok) return alert(js.error||'Erreur scan');
    const list = $('#docx-list');
    list.innerHTML = `<div class="row" id="docx-items">` + js.docx.map(p=>`<label class="btn ghost"><input type="radio" name="docx" value="${p}" ${p===js.docx[0]?'checked':''}> ${p.split('/').pop()}</label>`).join('') + `</div>`;
    serviceStates.docx = true; updateServiceStatus();
    step('docx');
  }

  $('#btn-convert')?.addEventListener('click', async ()=>{
    const selected = document.querySelector('#docx-items input[name="docx"]:checked');
    if (!selected) { alert('Sélectionnez un DOCX'); return; }
    const res = await fetch('?action=convert_html', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({docx: selected.value}) });
    const js = await res.json();
    if (!js.ok) return alert(js.error||'Erreur convert');
    $('#html-path').textContent = js.file;
    serviceStates.html = true; updateServiceStatus();
    step('html');
  });

  $('#btn-extract')?.addEventListener('click', async ()=>{
    const res = await fetch('?action=extract');
    const js = await res.json();
    if (!js.ok) return alert(js.error||'Erreur extract');
    renderExtract(js.extract);
    serviceStates.extract = true; updateServiceStatus();
    step('extract');
  });

  function renderExtract(extract) {
    const tbody = $('#q-table tbody'); tbody.innerHTML = '';
    extract.questions.forEach(q => {
      const row = document.createElement('tr');
      row.innerHTML = `<td>${(q.statement.images||[]).length}</td><td>${q.answers.length}</td><td><a class="btn" href="?action=editor">Ouvrir</a></td>`;
      tbody.appendChild(row);
    });
    $('[data-step="editor"]')?.classList.remove('hidden');
  }

  $('#btn-log')?.addEventListener('click', async ()=>{
    const res = await fetch('?action=debug_log'); const js = await res.json();
    if (js.ok) {
      const text = (js.log||[]).join('');
      $('#log').textContent = text;
      $('#raw-log').textContent = text || 'Aucun log brut disponible.';
      serviceStates.log = true;
      updateServiceStatus();
    }
  });
  $('#btn-log-clear')?.addEventListener('click', async ()=>{
    const res = await fetch('?action=debug_log&clear=1'); const js = await res.json();
    if (js.ok) {
      $('#log').textContent = '';
      $('#raw-log').textContent = 'Aucun log brut chargé.';
      serviceStates.log = false;
      updateServiceStatus();
    }
  });

  $$('#cleanup form').forEach(f => f.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(f);
    const res = await fetch('?action=cleanup', { method:'POST', body:fd });
    const js = await res.json(); if (js.ok) alert('Nettoyé'); else alert(js.error||'Erreur');
  }));

  updateServiceStatus();

  function step(k){
    $$('[data-status]').forEach(b=>b.classList.remove('badge--ok'));
    if (k==='upload') $('#st-upload')?.classList.add('badge--ok');
    if (k==='docx') $('#st-docx')?.classList.add('badge--ok');
    if (k==='html') $('#st-html')?.classList.add('badge--ok');
    if (k==='extract') $('#st-extract')?.classList.add('badge--ok');
  }
});
