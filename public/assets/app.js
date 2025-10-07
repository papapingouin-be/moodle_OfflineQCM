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
      step('upload'); await scanDocx();
    });
  }

  async function scanDocx() {
    const res = await fetch('?action=scan_docx');
    const js = await res.json();
    if (!js.ok) return alert(js.error||'Erreur scan');
    const list = $('#docx-list');
    list.innerHTML = `<div class="row" id="docx-items">` + js.docx.map(p=>`<label class="btn ghost"><input type="radio" name="docx" value="${p}" ${p===js.docx[0]?'checked':''}> ${p.split('/').pop()}</label>`).join('') + `</div>`;
    step('docx');
  }

  $('#btn-convert')?.addEventListener('click', async ()=>{
    const selected = document.querySelector('#docx-items input[name="docx"]:checked');
    if (!selected) { alert('Sélectionnez un DOCX'); return; }
    const res = await fetch('?action=convert_html', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({docx: selected.value}) });
    const js = await res.json();
    if (!js.ok) return alert(js.error||'Erreur convert');
    $('#html-path').textContent = js.file; step('html');
  });

  $('#btn-extract')?.addEventListener('click', async ()=>{
    const res = await fetch('?action=extract');
    const js = await res.json();
    if (!js.ok) return alert(js.error||'Erreur extract');
    renderExtract(js.extract); step('extract');
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
    if (js.ok) $('#log').textContent = (js.log||[]).join('');
  });
  $('#btn-log-clear')?.addEventListener('click', async ()=>{
    const res = await fetch('?action=debug_log&clear=1'); const js = await res.json();
    if (js.ok) $('#log').textContent = '';
  });

  $$('#cleanup form').forEach(f => f.addEventListener('submit', async (e)=>{
    e.preventDefault(); const fd = new FormData(f);
    const res = await fetch('?action=cleanup', { method:'POST', body:fd });
    const js = await res.json(); if (js.ok) alert('Nettoyé'); else alert(js.error||'Erreur');
  }));

  function step(k){
    $$('[data-status]').forEach(b=>b.classList.remove('badge--ok'));
    if (k==='upload') $('#st-upload')?.classList.add('badge--ok');
    if (k==='docx') $('#st-docx')?.classList.add('badge--ok');
    if (k==='html') $('#st-html')?.classList.add('badge--ok');
    if (k==='extract') $('#st-extract')?.classList.add('badge--ok');
  }
});
