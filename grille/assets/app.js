// Main JavaScript for the form generator application.  It handles drag and
// drop of files, ZIP extraction of templates, student list parsing (CSV or
// PDF), filling out PDF forms using pdf-lib, and generating a ZIP of
// completed documents.  It also provides a verbose logging facility.

(() => {
  // ========== Helper and Logging Utilities ==========

  const logArea = document.getElementById('logArea');
  const verboseToggle = document.getElementById('verboseToggle');

  /**
   * Return a locale time string.  Use a series of fallbacks to avoid
   * RangeError when the locale tag contains invalid characters (e.g.,
   * non‑breaking hyphens from copy/paste).  The order ensures that an
   * appropriate locale (French Belgium) is used when possible.
   */
  function safeLocaleTime() {
    const candidates = ['fr-BE', 'fr-FR', 'en-GB', 'en-US'];
    for (const loc of candidates) {
      try {
        return new Date().toLocaleTimeString(loc);
      } catch (e) {
        // continue to next candidate
      }
    }
    // Fallback: extract HH:MM:SS from default toString()
    return new Date().toTimeString().split(' ')[0];
  }

  /**
   * Append a message to the log area.  The level determines the prefix.
   * Additional data (object) can be passed for debugging; it will be
   * formatted as JSON in the log.  Verbose messages (level='debug')
   * respect the verboseToggle checkbox.
   */
  function log(msg, { level = 'info', data = null } = {}) {
    if (!verboseToggle.checked && level === 'debug') {
      return;
    }
    let prefix;
    switch (level) {
      case 'error': prefix = '⛔'; break;
      case 'warn':  prefix = '⚠️'; break;
      case 'debug': prefix = '🛠️'; break;
      default:      prefix = 'ℹ️';
    }
    const timestamp = safeLocaleTime();
    let line = `[${timestamp}] ${prefix} ${msg}`;
    if (data) {
      line += `\n${JSON.stringify(data, null, 2)}`;
    }
    logArea.textContent += line + '\n';
    logArea.scrollTop = logArea.scrollHeight;
    // Mirror to console with appropriate method
    switch (level) {
      case 'error': console.error(line); break;
      case 'warn': console.warn(line); break;
      case 'debug': console.debug(line); break;
      default: console.log(line);
    }
  }

  // Capture unhandled promise rejections so they appear in our logs
  window.addEventListener('unhandledrejection', (event) => {
    log('Rejet de promesse non capturé', { level: 'error', data: { reason: String(event.reason) } });
  });

  // Note: Some browser extensions (e.g., passkeys, KeePassXC) may log
  // “message port closed” errors.  These do not affect our application.
  log('Remarque : des messages “message port closed…” peuvent provenir d’extensions du navigateur et sont sans impact.', { level: 'warn' });

  // ========== UI Element References ==========

  const zipDrop = document.getElementById('zipDrop');
  const zipInput = document.getElementById('zipInput');
  const zipStatus = document.getElementById('zipStatus');
  const zipList = document.getElementById('zipList');

  const formSelection = document.getElementById('formSelection');
  const formSelect = document.getElementById('formSelect');

  const studentsSection = document.getElementById('studentsSection');
  const studentsDrop = document.getElementById('studentsDrop');
  const studentsInput = document.getElementById('studentsInput');
  const studentsStatus = document.getElementById('studentsStatus');
  const studentsPreview = document.getElementById('studentsPreview');

  const processSection = document.getElementById('processSection');
  const processButton = document.getElementById('processButton');
  const downloadLinkContainer = document.getElementById('downloadLinkContainer');
  // Elements for overlay configuration UI (optional).  These inputs allow the
  // user to adjust positions and fonts of the overlay at runtime without
  // modifying the code.  They are populated and listeners are attached when
  // the form and student list are ready.
  const overlayConfigSection = document.getElementById('overlayConfigSection');
  const cfgNomX = document.getElementById('cfg-nom-x');
  const cfgNomY = document.getElementById('cfg-nom-y');
  const cfgNomFont = document.getElementById('cfg-nom-font');
  const cfgNomSize = document.getElementById('cfg-nom-size');
  const cfgPrenomX = document.getElementById('cfg-prenom-x');
  const cfgPrenomY = document.getElementById('cfg-prenom-y');
  const cfgPrenomFont = document.getElementById('cfg-prenom-font');
  const cfgPrenomSize = document.getElementById('cfg-prenom-size');
  const cfgIdX = document.getElementById('cfg-id-x');
  const cfgIdY = document.getElementById('cfg-id-y');
  const cfgIdFont = document.getElementById('cfg-id-font');
  const cfgIdSize = document.getElementById('cfg-id-size');

  // Toggle button for overlay configuration visibility
  const overlayConfigToggle = document.getElementById('overlayConfigToggle');
  if (overlayConfigToggle) {
    overlayConfigToggle.addEventListener('click', () => {
      overlayConfigSection.classList.toggle('hidden');
      if (!overlayConfigSection.dataset.initialised) {
        initOverlayConfig();
        overlayConfigSection.dataset.initialised = 'true';
      }
    });
  }

  // Toggle button for log area visibility.  The log area is hidden by
  // default.  Clicking this button toggles the 'hidden' class on the
  // log area and updates the button text accordingly.
  const logAreaToggle = document.getElementById('logAreaToggle');
  if (logAreaToggle) {
    logAreaToggle.addEventListener('click', () => {
      const logDiv = document.getElementById('logArea');
      if (!logDiv) return;
      const isHidden = logDiv.classList.toggle('hidden');
      // Update button text to reflect current state
      logAreaToggle.textContent = isHidden ? 'Afficher le journal' : 'Masquer le journal';
    });
  }

  // Overlay configuration: positions and fonts for fields when no PDF form
  // fields are present.  Each key corresponds to a field ('nom', 'prenom', 'id')
  // and defines an (x, y) position in PDF points (origin bottom‑left), along
  // with a font name and size.  The default positions and sizes are based on
  // the example Python script provided by the user (ZONES dict).  You can
  // adjust these values dynamically using the configuration UI added to the
  // page.  Supported font names are those exposed via pdf-lib StandardFonts:
  // 'Helvetica', 'TimesRoman', 'Courier', etc.
  const OVERLAY_POSITIONS = {
    nom:    { x: 77.0,  y: 743.0 },
    prenom: { x: 77.0,  y: 723.0 },
    // Default X coordinate for the ID overlay updated to 395 as per the
    // user's latest instructions.  You can still adjust this via the UI.
    id:     { x: 395.0, y: 730.0 }
  };
  const OVERLAY_FONTS = {
    nom:    { fontName: 'Helvetica', size: 15 },
    prenom: { fontName: 'Helvetica', size: 15 },
    id:     { fontName: 'Helvetica', size: 16, characterSpacing: 2 }
  };

  // ========== Application State ==========

  let zipFiles = null;          // JSZip instance containing the uploaded ZIP
  let templateFileName = null;  // Name of the selected PDF template within the ZIP
  let templateBytes = null;     // Uint8Array of the selected template PDF
  let studentList = [];         // List of student objects parsed from CSV or PDF
  // Indices of students selected for generation.  Populated when the list
  // of students is displayed (all selected by default).  Updated when
  // checkboxes are toggled.
  let selectedIndices = new Set();

  /**
   * Initialize drag‑and‑drop behavior for a given drop zone and file input.
   */
  function setupDrop(zone, input) {
    ['dragenter', 'dragover'].forEach(evt => zone.addEventListener(evt, (e) => {
      e.preventDefault();
      e.stopPropagation();
      zone.classList.add('dragover');
    }));
    ['dragleave', 'drop'].forEach(evt => zone.addEventListener(evt, (e) => {
      e.preventDefault();
      e.stopPropagation();
      zone.classList.remove('dragover');
    }));
    zone.addEventListener('drop', (e) => {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });
    zone.addEventListener('click', () => input.click());
  }

  // Initialize drop zones
  setupDrop(zipDrop, zipInput);
  setupDrop(studentsDrop, studentsInput);

  /**
   * Check if we have all necessary data loaded (template and student list) and
   * update the UI accordingly.
   */
  function readyCheck() {
    // A student list must be loaded, at least one student selected, and a
    // template must be loaded.  Without a selected template or selected
    // students, we cannot generate.
    const ready = !!templateBytes && Array.isArray(studentList) && studentList.length > 0 && selectedIndices.size > 0;
    processButton.disabled = !ready;
    processSection.classList.toggle('hidden', !ready);
    // Initialise overlay configuration values when ready, but do not show
    // the configuration section automatically.  The user can toggle it via
    // the overlayConfigToggle button.  We still ensure the inputs are
    // initialised only once.
    if (ready && !overlayConfigSection.dataset.initialised) {
      initOverlayConfig();
      overlayConfigSection.dataset.initialised = 'true';
    }
    log(`Vérification prêt : ${ready}`, { level: 'debug' });
  }

  /**
   * Initialise overlay configuration inputs with current values and attach
   * listeners to update the overlay positions and font settings.  This
   * function should be called once when the overlay configuration section
   * becomes visible.
   */
  function initOverlayConfig() {
    // Helper to sync input values to overlay configuration
    function syncInputs(fieldKey) {
      const pos = OVERLAY_POSITIONS[fieldKey];
      const fontSpec = OVERLAY_FONTS[fieldKey];
      const prefix = fieldKey;
      const xInput = document.getElementById(`cfg-${prefix}-x`);
      const yInput = document.getElementById(`cfg-${prefix}-y`);
      const fontSelect = document.getElementById(`cfg-${prefix}-font`);
      const sizeInput = document.getElementById(`cfg-${prefix}-size`);
      if (xInput) xInput.value = pos.x;
      if (yInput) yInput.value = pos.y;
      if (fontSelect) fontSelect.value = fontSpec.fontName;
      if (sizeInput) sizeInput.value = fontSpec.size;
      // Attach change listeners
      if (xInput) {
        xInput.addEventListener('input', () => {
          const v = parseFloat(xInput.value);
          if (!isNaN(v)) OVERLAY_POSITIONS[fieldKey].x = v;
        });
      }
      if (yInput) {
        yInput.addEventListener('input', () => {
          const v = parseFloat(yInput.value);
          if (!isNaN(v)) OVERLAY_POSITIONS[fieldKey].y = v;
        });
      }
      if (fontSelect) {
        fontSelect.addEventListener('change', () => {
          OVERLAY_FONTS[fieldKey].fontName = fontSelect.value;
        });
      }
      if (sizeInput) {
        sizeInput.addEventListener('input', () => {
          const v = parseFloat(sizeInput.value);
          if (!isNaN(v)) OVERLAY_FONTS[fieldKey].size = v;
        });
      }
    }
    // Sync for each field
    ['nom', 'prenom', 'id'].forEach(syncInputs);
  }

  // ========== ZIP Handling ==========
  zipInput.addEventListener('change', async () => {
    const file = zipInput.files && zipInput.files[0];
    if (!file) return;
    if (!file.name.toLowerCase().endsWith('.zip')) {
      zipStatus.textContent = 'Veuillez sélectionner un fichier .zip';
      return;
    }
    zipStatus.textContent = `Lecture de ${file.name}…`;
    log(`Chargement du ZIP « ${file.name} » (${file.size} octets)`, { level: 'info' });
    try {
      const arrayBuffer = await file.arrayBuffer();
      zipFiles = await JSZip.loadAsync(arrayBuffer);
      const pdfNames = [];
      zipFiles.forEach((relativePath, entry) => {
        if (!entry.dir && entry.name.toLowerCase().endsWith('.pdf')) {
          pdfNames.push(entry.name);
        }
      });
      log('Liste des fichiers PDF trouvés', { level: 'debug', data: pdfNames });
      if (pdfNames.length === 0) {
        zipStatus.textContent = 'Aucun PDF trouvé dans cette archive.';
        formSelection.classList.add('hidden');
        return;
      }
      // Populate the select element
      formSelect.innerHTML = '';
      pdfNames.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        formSelect.appendChild(opt);
      });
      zipList.textContent = pdfNames.map(n => `• ${n}`).join('\n');
      zipStatus.textContent = `${pdfNames.length} PDF(s) trouvé(s). Veuillez en choisir un.`;
      formSelection.classList.remove('hidden');
      studentsSection.classList.remove('hidden');

      // Sélection automatique du premier formulaire si l'utilisateur n'en choisit pas.
      // Cette pré‑sélection déclenche un événement 'change' pour charger le modèle
      // et permettre la génération sans interaction supplémentaire.
      if (pdfNames.length > 0) {
        formSelect.selectedIndex = 0;
        formSelect.dispatchEvent(new Event('change'));
        log(`Premier formulaire sélectionné automatiquement : ${formSelect.value}`, { level: 'debug' });
      }
    } catch (err) {
      zipStatus.textContent = 'Erreur lors de la lecture de l’archive.';
      log('Erreur lors du chargement du ZIP', { level: 'error', data: String(err) });
    }
  });

  // ========== Form selection within ZIP ==========
  formSelect.addEventListener('change', async () => {
    templateFileName = formSelect.value;
    if (!zipFiles || !templateFileName) return;
    try {
      log(`Formulaire sélectionné : ${templateFileName}`, { level: 'info' });
      templateBytes = await zipFiles.file(templateFileName).async('uint8array');
      log('Modèle chargé', { level: 'debug', data: { length: templateBytes.length } });

      // Inspect the PDF form to list available field names.  This helps users
      // understand which keys from the CSV or parsed list correspond to the
      // fields in the PDF.  The list is logged at debug level.
      try {
        const tempDoc = await PDFLib.PDFDocument.load(templateBytes);
        const tempForm = tempDoc.getForm();
        const fields = tempForm.getFields().map(f => f.getName());
        log('Champs disponibles dans le modèle', { level: 'debug', data: fields });
      } catch (err) {
        log('Impossible de lister les champs du modèle', { level: 'warn', data: String(err) });
      }
      readyCheck();
    } catch (err) {
      templateBytes = null;
      log('Erreur lors du chargement du modèle', { level: 'error', data: String(err) });
    }
  });

  // ========== Student list handling (CSV or PDF) ==========
  studentsInput.addEventListener('change', async () => {
    const file = studentsInput.files && studentsInput.files[0];
    if (!file) return;
    studentsStatus.textContent = `Lecture de ${file.name}…`;
    log(`Chargement de la liste des étudiants « ${file.name} » (${file.size} octets)`, { level: 'info' });
    // Reset previous results
    downloadLinkContainer.innerHTML = '';
    studentList = [];
    try {
      const ext = file.name.split('.').pop().toLowerCase();
      if (ext === 'csv') {
        await handleCSV(file);
      } else if (ext === 'pdf') {
        await handlePDFList(file);
      } else {
        studentsStatus.textContent = 'Format de liste non supporté (utilisez PDF ou CSV)';
        log('Format non supporté pour la liste des étudiants', { level: 'error', data: ext });
        return;
      }
      readyCheck();
    } catch (err) {
      studentsStatus.textContent = 'Erreur lors de la lecture de la liste.';
      log('Erreur lors du traitement de la liste des étudiants', { level: 'error', data: String(err) });
    }
  });

  /**
   * Parse a CSV file containing student data.  The first row must be
   * header names corresponding to PDF field names.  The parsed data
   * populates the global studentList.
   * @param {File} file
   */
  async function handleCSV(file) {
    return new Promise((resolve, reject) => {
      Papa.parse(file, {
        header: true,
        skipEmptyLines: true,
        complete: function (results) {
          studentList = results.data;
          studentsStatus.textContent = `${studentList.length} ligne(s) CSV`;
          log('CSV analysé', { level: 'debug', data: studentList.slice(0, 5) });
          renderStudentSelection();
          resolve();
        },
        error: function (error) {
          reject(error);
        }
      });
    });
  }

  /**
   * Parse a PDF file containing a list of students.  A simple heuristic is used:
   * all non-empty lines of text are considered names.  Each name becomes an
   * object { Nom: '…' } in studentList.
   * @param {File} file
   */
  async function handlePDFList(file) {
    const arrayBuffer = await file.arrayBuffer();
    // Use the pdf.js library provided by the CDN.  The 2.x branch of
    // pdf.js exposes a global object named `pdfjsLib` on `window`.  We
    // reference it via `window.pdfjsLib` to avoid `ReferenceError`
    // scenarios in module scopes.  To load a PDF we call
    // pdfjsLib.getDocument(...).promise as demonstrated in the
    // StackOverflow answer【652074480669700†L1034-L1046】.
    const pdf = await window.pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    let fullText = '';
    for (let p = 1; p <= pdf.numPages; p++) {
      const page = await pdf.getPage(p);
      const content = await page.getTextContent();
      const lines = content.items.map(i => i.str).join(' ');
      fullText += lines + '\n';
    }
    // The fullText may not contain line breaks between student entries.  We
    // therefore split on the numbering pattern "<num>." to extract each
    // participant.  The first split element (index 0) contains header
    // information and is discarded.  Each subsequent segment begins with
    // the student’s ID followed by their first and last name(s).
    // Split the text on numbered entries (e.g. "1.", "2.") and parse each entry.
    const segments = fullText.split(/\d+\.\s+/).slice(1);
    const parsedStudents = [];
    for (const seg of segments) {
      let s = seg.trim();
      // Match an identifier (digits) followed by at least one space and then the name
      const m = s.match(/^(\d+)\s+(.+)$/);
      if (!m) continue;
      const id = m[1];
      let name = m[2];
      // Remove trailing boilerplate like "Powered by TCPDF" or page numbers
      name = name.split(/Powered\s+by|Page\s+/i)[0].trim();
      // Split the name into last name and first name(s) when possible
      const parts = name.trim().split(/\s+/);
      const lastName = parts.shift() || '';
      const firstName = parts.join(' ');
      parsedStudents.push({ ID: id, Nom: lastName, Prenom: firstName });
    }
    studentList = parsedStudents;
    studentsStatus.textContent = `${studentList.length} étudiant(s) (PDF)`;
    log('PDF analysé', { level: 'debug', data: parsedStudents.slice(0, 15) });
    renderStudentSelection();
  }

  /**
   * Render the list of students with checkboxes for selection.  All students
   * are selected by default.  When the user toggles a checkbox, the
   * corresponding index is added or removed from selectedIndices, and the
   * readiness state is reevaluated.
   */
  function renderStudentSelection() {
    // Clear previous selection set
    selectedIndices = new Set();
    // Clear preview container
    studentsPreview.innerHTML = '';
    // If no students, nothing to render
    if (!Array.isArray(studentList) || studentList.length === 0) {
      return;
    }
    // Create a 'select/deselect all' toggle
    const controlsDiv = document.createElement('div');
    controlsDiv.className = 'mb-2 flex gap-4 items-center';
    // Select all button
    const selectAllBtn = document.createElement('button');
    selectAllBtn.textContent = 'Tout sélectionner';
    selectAllBtn.type = 'button';
    selectAllBtn.className = 'text-blue-600 underline text-sm';
    selectAllBtn.addEventListener('click', () => {
      studentList.forEach((_, idx) => selectedIndices.add(idx));
      // Check all checkboxes
      studentsPreview.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = true);
      readyCheck();
    });
    controlsDiv.appendChild(selectAllBtn);
    // Deselect all button
    const deselectAllBtn = document.createElement('button');
    deselectAllBtn.textContent = 'Tout désélectionner';
    deselectAllBtn.type = 'button';
    deselectAllBtn.className = 'text-blue-600 underline text-sm';
    deselectAllBtn.addEventListener('click', () => {
      selectedIndices.clear();
      studentsPreview.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
      readyCheck();
    });
    controlsDiv.appendChild(deselectAllBtn);
    studentsPreview.appendChild(controlsDiv);
    // Render each student row
    studentList.forEach((student, index) => {
      // Mark as selected initially
      selectedIndices.add(index);
      const rowDiv = document.createElement('div');
      rowDiv.className = 'flex items-center gap-2';
      const checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = true;
      checkbox.addEventListener('change', () => {
        if (checkbox.checked) {
          selectedIndices.add(index);
        } else {
          selectedIndices.delete(index);
        }
        readyCheck();
      });
      rowDiv.appendChild(checkbox);
      const label = document.createElement('span');
      const lastName = student.Nom || student.nom || student.Name || student.name || '';
      const firstName = student.Prenom || student.prenom || student['Prénom'] || student['prénom'] || '';
      const fullName = firstName ? `${lastName} ${firstName}`.trim() : lastName;
      const id = student.ID || student.id || '';
      label.textContent = id ? `${fullName} (${id})` : fullName;
      rowDiv.appendChild(label);
      studentsPreview.appendChild(rowDiv);
    });
  }

  // ========== Generate Completed Forms ==========
  processButton.addEventListener('click', async () => {
    if (!templateBytes || !studentList.length) return;
    processButton.disabled = true;
    downloadLinkContainer.innerHTML = '';
    log('Début de la génération des formulaires…', { level: 'info' });
    try {
      const zipOut = new JSZip();
      // Create a list of students based on selectedIndices.  Only these
      // students will be processed.
      const selectedStudents = Array.from(selectedIndices).map(idx => studentList[idx]);
      for (let i = 0; i < selectedStudents.length; i++) {
        const row = selectedStudents[i];
        // Prepare derived values: spaced ID and separated last/first names
        const rawId = String(row.ID || row.id || '');
        // Insert spaces between each digit of the ID for readability (e.g. "0014420" -> "0 0 1 4 4 2 0")
        // Insert **two** spaces between each digit of the ID for better spacing.
        const spacedId = rawId ? rawId.split('').join('  ') : '';
        let lastName = '';
        let firstName = '';
        const explicitLastName = (row.Nom || row.nom || row.Name || row.name || '').trim();
        const explicitFirstName = (row.Prenom || row.prenom || row['Prénom'] || row['prénom'] || '').trim();
        if (explicitFirstName) {
          // If explicit first name is provided, treat explicitLastName as the family name
          lastName = explicitLastName;
          firstName = explicitFirstName;
        } else if (explicitLastName) {
          // If no explicit first name, split the full name.  Assume format "Nom Prenom...".
          const parts = explicitLastName.split(/\s+/);
          if (parts.length === 1) {
            // Only one part: assign it to lastName
            lastName = parts[0];
            firstName = '';
          } else {
            // Two or more parts: first part is last name, rest constitute first name(s)
            lastName = parts[0];
            firstName = parts.slice(1).join(' ');
          }
        }
        const pdfDoc = await PDFLib.PDFDocument.load(templateBytes);
        const form = pdfDoc.getForm();
        const keys = Object.keys(row);
        // Flag to track if any field was successfully filled
        let hasFilledAny = false;

        // Attempt to fill fields for each key directly.  When encountering
        // name or ID fields, use derived values (lastName, firstName, spacedId)
        keys.forEach((key) => {
          try {
            const field = form.getField(key);
            if (field && typeof field.setText === 'function') {
              let value = row[key];
              // Map key names to derived values
              const lowerKey = key.toLowerCase();
              if (/\bid\b|identifiant|matricule|num|numero/.test(lowerKey)) {
                value = spacedId;
              } else if (/prenom|prénom/.test(lowerKey)) {
                value = firstName;
              } else if (/\bnom\b/.test(lowerKey) && !/prenom|prénom/.test(lowerKey)) {
                // avoid matching 'prénom'
                value = lastName;
              }
              field.setText(String(value ?? ''));
              hasFilledAny = true;
            }
          } catch (e) {
            // Log missing fields in debug mode
            log(`Champ manquant : ${key}`, { level: 'debug' });
          }
        });

        // Heuristic fallback for names when only 'Nom' property exists
        if (!hasFilledAny && row.Nom) {
          const nameValue = String(row.Nom);
          let filledName = false;
          // Try direct aliases
          const nameAliases = ['Nom', 'nom', 'Name', 'name', 'Student', 'Étudiant'];
          for (const alias of nameAliases) {
            try {
              const f = form.getField(alias);
              if (f && typeof f.setText === 'function') {
                f.setText(nameValue);
                filledName = true;
                hasFilledAny = true;
                break;
              }
            } catch (e) { /* ignore */ }
          }
          if (!filledName) {
            // Search all fields for keywords related to name
            try {
              const allFields = form.getFields();
              for (const field of allFields) {
                const fname = field.getName();
                if (/nom|name|student|étudiant/i.test(fname)) {
                  if (typeof field.setText === 'function') {
                    field.setText(nameValue);
                    filledName = true;
                    hasFilledAny = true;
                    log(`Champ détecté pour le nom : ${fname}`, { level: 'debug' });
                    break;
                  }
                }
              }
            } catch (e) { /* ignore */ }
          }
        }

        // Heuristic fallback for identification number fields
        if (row.ID) {
          const idValue = String(row.ID);
          let filledId = false;
          const idAliases = ['ID', 'id', 'Identifiant', 'identifiant', 'Matricule', 'Num', 'Numéro', 'numero', 'Num_identification'];
          for (const alias of idAliases) {
            try {
              const f = form.getField(alias);
              if (f && typeof f.setText === 'function') {
                f.setText(idValue);
                filledId = true;
                hasFilledAny = true;
                break;
              }
            } catch (e) { /* ignore */ }
          }
          if (!filledId) {
            // Search all fields for keywords related to ID
            try {
              const allFields = form.getFields();
              for (const field of allFields) {
                const fname = field.getName();
                if (/identifiant|id|matricule|num|numero/i.test(fname)) {
                  if (typeof field.setText === 'function') {
                    field.setText(idValue);
                    filledId = true;
                    hasFilledAny = true;
                    log(`Champ détecté pour l’identifiant : ${fname}`, { level: 'debug' });
                    break;
                  }
                }
              }
            } catch (e) { /* ignore */ }
          }
        }

        // Always overlay the name and first name using configured positions
        try {
          const pages = pdfDoc.getPages();
          const firstPage = pages[0];
          const nomValue = lastName;
          const prenomValue = firstName;
          // Helper to draw a field using configured positions and fonts
          async function drawField(fieldKey, text) {
            const pos = OVERLAY_POSITIONS[fieldKey];
            const fontSpec = OVERLAY_FONTS[fieldKey];
            if (!pos || !fontSpec || !text) return;
            const font = await pdfDoc.embedFont(PDFLib.StandardFonts[fontSpec.fontName]);
            const options = {
              x: pos.x,
              y: pos.y,
              size: fontSpec.size,
              font: font,
              color: PDFLib.rgb(0, 0, 0)
            };
            if (typeof fontSpec.characterSpacing === 'number') {
              options.characterSpacing = fontSpec.characterSpacing;
            }
            firstPage.drawText(text, options);
          }
          await drawField('nom', nomValue);
          await drawField('prenom', prenomValue);
          // Log insertion of overlay only when the template lacked fields.
          if (!hasFilledAny) {
            log('Aucun champ détecté, insertion en overlay (zones configurables)', { level: 'debug' });
          }
        } catch (e) {
          log('Erreur lors de l’insertion en overlay', { level: 'warn', data: String(e) });
        }

        // Always overlay the identification number with spacing to ensure readability.
        try {
          const pages = pdfDoc.getPages();
          const firstPage = pages[0];
          const idPos = OVERLAY_POSITIONS.id;
          const idFontSpec = OVERLAY_FONTS.id;
          const idFont = await pdfDoc.embedFont(PDFLib.StandardFonts[idFontSpec.fontName]);
          const idOptions = {
            x: idPos.x,
            y: idPos.y,
            size: idFontSpec.size,
            font: idFont,
            color: PDFLib.rgb(0, 0, 0)
          };
          if (typeof idFontSpec.characterSpacing === 'number') {
            idOptions.characterSpacing = idFontSpec.characterSpacing;
          }
          firstPage.drawText(spacedId, idOptions);
        } catch (e) {
          log('Erreur lors de l’overlay de l’identifiant', { level: 'warn', data: String(e) });
        }

        // Flatten to remove any form fields (if any) after filling or overlay
        form.flatten();
        const pdfBytes = await pdfDoc.save({ useObjectStreams: false });
        // Determine file name.  Always include lastName, firstName and ID
        let filename;
        const safeLast = lastName ? lastName.trim().replace(/[^\w\d\-]+/g, '_') : '';
        const safeFirst = firstName ? firstName.trim().replace(/[^\w\d\-]+/g, '_') : '';
        const safeId = rawId ? rawId.trim().replace(/[^\w\d\-]+/g, '_') : '';
        if (safeLast || safeFirst || safeId) {
          const parts = [];
          if (safeLast) parts.push(safeLast);
          if (safeFirst) parts.push(safeFirst);
          if (safeId) parts.push(safeId);
          filename = `${parts.join('_')}.pdf`;
        } else {
          filename = `etudiant_${String(i + 1).padStart(3, '0')}.pdf`;
        }
        zipOut.file(filename, pdfBytes);
        if ((i + 1) % 10 === 0 || i === selectedStudents.length - 1) {
          log(`Progression : ${i + 1}/${selectedStudents.length}`, { level: 'debug' });
        }
      }
      // Generate the output ZIP
      const blob = await zipOut.generateAsync({ type: 'blob' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'formulaires_generes.zip';
      link.textContent = 'Télécharger le ZIP des formulaires générés';
      link.className = 'text-blue-600 underline';
      downloadLinkContainer.appendChild(link);
      log('Génération terminée ✅', { level: 'info' });
    } catch (err) {
      log('Erreur lors de la génération des formulaires', { level: 'error', data: String(err) });
      alert('Une erreur est survenue pendant la génération (voir le journal).');
    } finally {
      processButton.disabled = false;
    }
  });

})();