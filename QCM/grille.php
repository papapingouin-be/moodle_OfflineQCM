<?php
// This PHP file serves as the entry point for the web application.
// It outputs a simple HTML page with embedded JavaScript to handle
// uploading a ZIP of PDF forms, extracting the list of PDFs, selecting a
// form, uploading a CSV of students and filling the form for each student.
// All heavy lifting (ZIP extraction, CSV parsing, PDF manipulation) is
// performed client‑side with modern JavaScript libraries. PHP is only
// used to deliver the page and could be extended for server‑side
// processing in the future.

// We disable any caching to ensure fresh content on each reload.
header('Cache‑Control: no‑store, no‑cache, must‑revalidate, max‑age=0');
header('Cache‑Control: post‑check=0, pre‑check=0', false);
header('Pragma: no‑cache');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Générateur de formulaires étudiants</title>
    <!-- Minimal styling using Tailwind from a CDN for rapid layout. Tailwind is
         utility‑first; feel free to replace with your preferred framework. -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- External JS libraries: JSZip for ZIP handling, pdf‑lib for PDF form
         manipulation, and PapaParse for CSV parsing. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
    <style>
        /* Custom styles for log area and file drop zones */
        .file‑drop {
            border: 2px dashed #cbd5e0;
            border‑radius: 0.375rem;
            padding: 1.5rem;
            text‑align: center;
            cursor: pointer;
        }
        .file‑drop.dragover {
            border‑color: #3182ce;
            background‑color: #ebf8ff;
        }
        #logArea {
            height: 10rem;
            overflow‑y: auto;
            white‑space: pre‑wrap;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 0.5rem;
            font‑family: monospace;
        }
    </style>
</head>
<body class="bg‑gray‑50 min‑h‑screen py‑6 flex flex‑col items‑center justify‑start">
    <h1 class="text‑2xl font‑bold mb‑4">Générateur de formulaires étudiants</h1>
    <div class="w‑11/12 max‑w‑3xl space‑y‑4">
        <!-- Section for uploading the ZIP of PDF forms -->
        <div>
            <label class="block font‑medium mb‑2">1. Chargez l'archive ZIP contenant les formulaires PDF :</label>
            <div id="zipDrop" class="file‑drop">
                <input type="file" id="zipInput" accept=".zip" class="hidden" />
                <p id="zipInstruction">Déposez le fichier ZIP ici ou cliquez pour sélectionner.</p>
            </div>
            <div id="zipStatus" class="mt‑2 text‑sm text‑gray‑600"></div>
        </div>

        <!-- Section for selecting a specific PDF form from the ZIP -->
        <div id="formSelection" class="hidden">
            <label class="block font‑medium mb‑2">2. Choisissez le formulaire à utiliser :</label>
            <select id="formSelect" class="w‑full border border‑gray‑300 rounded px‑2 py‑1">
            </select>
        </div>

        <!-- Section for uploading the CSV of students -->
        <div id="studentsSection" class="hidden">
            <label class="block font‑medium mb‑2">3. Chargez la liste des étudiants (CSV) :</label>
            <div id="studentsDrop" class="file‑drop">
                <input type="file" id="studentsInput" accept=".csv" class="hidden" />
                <p id="studentsInstruction">Déposez le fichier CSV ici ou cliquez pour sélectionner.</p>
            </div>
            <div id="studentsStatus" class="mt‑2 text‑sm text‑gray‑600"></div>
        </div>

        <!-- Action button to generate the output -->
        <div id="processSection" class="hidden">
            <button id="processButton" class="bg‑blue‑600 text‑white px‑4 py‑2 rounded hover:bg‑blue‑700 disabled:bg‑gray‑400 disabled:cursor‑not‑allowed" disabled>Générer les formulaires complétés</button>
            <div id="downloadLinkContainer" class="mt‑2"></div>
        </div>

        <!-- Debug/log area -->
        <div>
            <label class="block font‑medium mb‑2">Journal / Débogage :</label>
            <div id="logArea"></div>
        </div>
    </div>

    <script>
        // Elements references
        const zipInput = document.getElementById('zipInput');
        const zipDrop = document.getElementById('zipDrop');
        const zipInstruction = document.getElementById('zipInstruction');
        const zipStatus = document.getElementById('zipStatus');
        const formSelection = document.getElementById('formSelection');
        const formSelect = document.getElementById('formSelect');
        const studentsSection = document.getElementById('studentsSection');
        const studentsInput = document.getElementById('studentsInput');
        const studentsDrop = document.getElementById('studentsDrop');
        const studentsInstruction = document.getElementById('studentsInstruction');
        const studentsStatus = document.getElementById('studentsStatus');
        const processSection = document.getElementById('processSection');
        const processButton = document.getElementById('processButton');
        const downloadLinkContainer = document.getElementById('downloadLinkContainer');
        const logArea = document.getElementById('logArea');

        // Variables to hold data in memory
        let zipFiles = null;              // JSZip instance with extracted files
        let templateFileName = null;       // Selected template PDF filename
        let templateBytes = null;          // Uint8Array of the template PDF
        let studentRows = null;            // Array of student objects from CSV

        // Utility: log messages to the debug area
        function log(message) {
            const time = new Date().toLocaleTimeString('fr‑BE');
            logArea.textContent += `[${time}] ${message}\n`;
            logArea.scrollTop = logArea.scrollHeight;
        }

        // Utility: handle drag‑and‑drop style highlighting
        function setupDragAndDrop(dropZone, inputElement, instruction) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('dragover');
                });
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('dragover');
                });
            });
            dropZone.addEventListener('drop', (e) => {
                if (e.dataTransfer.files && e.dataTransfer.files.length) {
                    inputElement.files = e.dataTransfer.files;
                    const event = new Event('change');
                    inputElement.dispatchEvent(event);
                }
            });
            dropZone.addEventListener('click', () => inputElement.click());
        }

        // Initialise drag‑and‑drop behaviour
        setupDragAndDrop(zipDrop, zipInput, zipInstruction);
        setupDragAndDrop(studentsDrop, studentsInput, studentsInstruction);

        // Handler for ZIP file selection
        zipInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;
            if (!file.name.endsWith('.zip')) {
                zipStatus.textContent = 'Veuillez sélectionner un fichier ZIP valide.';
                return;
            }
            zipStatus.textContent = `Lecture de l'archive ${file.name}...`;
            log(`Chargement du ZIP « ${file.name} »`);
            try {
                const arrayBuffer = await file.arrayBuffer();
                zipFiles = await JSZip.loadAsync(arrayBuffer);
                // Extraire la liste des fichiers PDF
                const pdfNames = [];
                zipFiles.forEach((relativePath, zipEntry) => {
                    if (!zipEntry.dir && zipEntry.name.toLowerCase().endsWith('.pdf')) {
                        pdfNames.push(zipEntry.name);
                    }
                });
                if (pdfNames.length === 0) {
                    zipStatus.textContent = 'Aucun formulaire PDF trouvé dans l’archive.';
                    formSelection.classList.add('hidden');
                    studentsSection.classList.add('hidden');
                    processSection.classList.add('hidden');
                    processButton.disabled = true;
                    return;
                }
                // Peupler la sélection
                formSelect.innerHTML = '';
                pdfNames.forEach(name => {
                    const opt = document.createElement('option');
                    opt.value = name;
                    opt.textContent = name;
                    formSelect.appendChild(opt);
                });
                // Mettre à jour l’interface
                zipStatus.textContent = `${pdfNames.length} formulaire(s) trouvé(s).`;
                formSelection.classList.remove('hidden');
                studentsSection.classList.remove('hidden');
                processSection.classList.add('hidden');
                processButton.disabled = true;
                templateFileName = null;
                templateBytes = null;
                studentRows = null;
            } catch (error) {
                log('Erreur lors de la lecture du ZIP : ' + error.message);
                zipStatus.textContent = 'Erreur lors du chargement de l’archive.';
            }
        });

        // Handler for PDF form selection
        formSelect.addEventListener('change', async (e) => {
            templateFileName = e.target.value;
            if (!zipFiles || !templateFileName) return;
            log(`Formulaire sélectionné : ${templateFileName}`);
            try {
                const fileData = await zipFiles.file(templateFileName).async('uint8array');
                templateBytes = fileData;
                log(`Taille du modèle sélectionné : ${fileData.length} octets`);
                // Enable process button only if students list is ready
                if (studentRows && studentRows.length > 0) {
                    processButton.disabled = false;
                    processSection.classList.remove('hidden');
                }
            } catch (error) {
                log('Erreur lors du chargement du modèle : ' + error.message);
                templateBytes = null;
            }
        });

        // Handler for CSV students file selection
        studentsInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;
            if (!file.name.toLowerCase().endsWith('.csv')) {
                studentsStatus.textContent = 'Veuillez sélectionner un fichier CSV valide.';
                return;
            }
            studentsStatus.textContent = `Lecture du fichier ${file.name}...`;
            log(`Chargement de la liste des étudiants « ${file.name} »`);
            // Utiliser PapaParse pour lire le CSV
            Papa.parse(file, {
                header: true,
                skipEmptyLines: true,
                complete: function(results) {
                    studentRows = results.data;
                    log(`${studentRows.length} ligne(s) d’étudiants chargée(s).`);
                    studentsStatus.textContent = `${studentRows.length} étudiant(s) trouvé(s).`;
                    if (templateBytes) {
                        processButton.disabled = false;
                        processSection.classList.remove('hidden');
                    }
                },
                error: function(error) {
                    log('Erreur lors du traitement du CSV : ' + error.message);
                    studentsStatus.textContent = 'Erreur lors de la lecture du fichier CSV.';
                    studentRows = null;
                }
            });
        });

        // Click handler for generating filled forms
        processButton.addEventListener('click', async () => {
            if (!templateBytes || !studentRows || studentRows.length === 0) {
                alert('Veuillez sélectionner un formulaire et charger la liste des étudiants.');
                return;
            }
            processButton.disabled = true;
            downloadLinkContainer.innerHTML = '';
            log('Démarrage de la génération des formulaires complétés...');
            try {
                const zipOut = new JSZip();
                // Traiter chaque ligne du CSV
                for (let i = 0; i < studentRows.length; i++) {
                    const row = studentRows[i];
                    const pdfDoc = await PDFLib.PDFDocument.load(templateBytes);
                    const form = pdfDoc.getForm();
                    // Pour chaque champ du CSV qui correspond à un champ du formulaire
                    Object.keys(row).forEach(key => {
                        try {
                            // pdf‑lib lève une exception si le champ n’existe pas.
                            const field = form.getField(key);
                            // Vérifier que le champ est un champ texte avant de définir la valeur.
                            // D’autres types de champs pourraient nécessiter des méthodes spécifiques.
                            if (field && typeof field.setText === 'function') {
                                field.setText(row[key]);
                            }
                        } catch (err) {
                            // Si le champ n’existe pas ou n’est pas textuel, on ignore.
                        }
                    });
                    form.flatten();
                    const pdfBytes = await pdfDoc.save();
                    // Déterminer un nom de fichier pour l’étudiant
                    let filename = `etudiant_${i+1}.pdf`;
                    // Si une colonne « Nom » existe, l’utiliser pour le nom du fichier
                    if (row.Nom || row.nom || row.name) {
                        const base = (row.Nom || row.nom || row.name).toString().trim().replace(/[^\w\d\-]+/g, '_');
                        if (base) {
                            filename = `${base}.pdf`;
                        }
                    }
                    zipOut.file(filename, pdfBytes);
                    log(`Formulaire généré pour « ${filename} »`);
                }
                // Générer l’archive ZIP de sortie
                const outputBlob = await zipOut.generateAsync({ type: 'blob' });
                const url = URL.createObjectURL(outputBlob);
                const link = document.createElement('a');
                link.href = url;
                link.download = 'formulaires_etudiants.zip';
                link.textContent = 'Télécharger l’archive ZIP des formulaires générés';
                link.className = 'text‑blue‑600 underline';
                downloadLinkContainer.appendChild(link);
                log('Génération terminée. Lien de téléchargement prêt.');
            } catch (error) {
                log('Erreur pendant la génération : ' + error.message);
                alert('Une erreur est survenue lors de la génération des formulaires. Vérifiez les fichiers et réessayez.');
            } finally {
                processButton.disabled = false;
            }
        });
    </script>
</body>
</html>