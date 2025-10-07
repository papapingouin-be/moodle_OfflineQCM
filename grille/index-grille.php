<?php
// Main page for the form generator.  It includes the head and foot partials
// and defines the user interface for uploading the ZIP of forms, selecting
// a form, uploading the list of students (CSV or PDF) and triggering the
// generation of completed forms.
require __DIR__ . '/partials/head.php';
?>

<div class="space-y-6">
    <!-- Section 1: ZIP of PDF forms -->
    <section>
        <h2 class="font-semibold mb-2">1) Chargez le ZIP des formulaires PDF</h2>
        <div id="zipDrop" class="file-drop">
            <input id="zipInput" type="file" accept=".zip" class="hidden" />
            <p>Déposez l’archive ZIP ou cliquez ici</p>
        </div>
        <p id="zipStatus" class="text-sm text-gray-600 mt-2">En attente…</p>
        <div id="zipList" class="mt-2 text-sm mono"></div>
    </section>

    <!-- Section 2: Choose a form from the ZIP -->
    <section id="formSelection" class="hidden">
        <h2 class="font-semibold mb-2">2) Choisissez le formulaire à utiliser</h2>
        <select id="formSelect" class="w-full border rounded px-2 py-1"></select>
    </section>

    <!-- Section 3: Upload the list of students (CSV or PDF) -->
    <section id="studentsSection" class="hidden">
        <h2 class="font-semibold mb-2">3) Chargez la liste des étudiants (PDF ou CSV)</h2>
        <div id="studentsDrop" class="file-drop">
            <input id="studentsInput" type="file" accept=".pdf,.csv" class="hidden" />
            <p>Déposez le PDF/CSV ou cliquez ici</p>
        </div>
        <p id="studentsStatus" class="text-sm text-gray-600 mt-2">En attente…</p>
        <div id="studentsPreview" class="mt-2 text-sm mono"></div>
    </section>

    <!-- Section 4: Generate filled forms -->
    <section id="processSection" class="hidden">
        <button id="processButton" disabled
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:bg-gray-400">
            4) Générer les formulaires complétés
        </button>
        <div id="downloadLinkContainer" class="mt-3"></div>
    </section>

    <!-- Section 5: Debug/Log area -->
    <section>
        <div class="flex items-center gap-2">
            <h2 class="font-semibold mb-2">Journal / Débogage</h2>
            <label class="text-sm flex items-center gap-1">
                <input type="checkbox" id="verboseToggle" checked />
                Mode verbeux
            </label>
            <!-- Button to toggle overlay configuration visibility -->
            <button id="overlayConfigToggle" type="button" class="text-blue-600 underline text-sm ml-4">
                Réglages overlay
            </button>
            <!-- Button to toggle log area visibility -->
            <button id="logAreaToggle" type="button" class="text-blue-600 underline text-sm ml-4">
                Afficher le journal
            </button>
        </div>
        <!-- Hidden by default; toggled via logAreaToggle -->
        <div id="logArea" class="mono hidden"></div>
    </section>

    <!-- Section 6: Réglage des zones et polices (optionnel) -->
    <section id="overlayConfigSection" class="hidden">
        <h2 class="font-semibold mb-2">5) Réglage des zones et polices (optionnel)</h2>
        <div class="space-y-2 text-sm">
            <!-- Configuration pour le nom -->
            <div class="flex flex-wrap items-center gap-2">
                <strong>Nom</strong>
                x:<input id="cfg-nom-x" type="number" class="w-20 border rounded px-1 py-0.5" step="0.1" />
                y:<input id="cfg-nom-y" type="number" class="w-20 border rounded px-1 py-0.5" step="0.1" />
                Police:
                <select id="cfg-nom-font" class="border rounded px-1 py-0.5">
                    <option value="Helvetica">Helvetica</option>
                    <option value="TimesRoman">Times Roman</option>
                    <option value="Courier">Courier</option>
                </select>
                Taille:<input id="cfg-nom-size" type="number" class="w-14 border rounded px-1 py-0.5" step="0.1" />
            </div>
            <!-- Configuration pour le prénom -->
            <div class="flex flex-wrap items-center gap-2">
                <strong>Prénom</strong>
                x:<input id="cfg-prenom-x" type="number" class="w-20 border rounded px-1 py-0.5" step="0.1" />
                y:<input id="cfg-prenom-y" type="number" class="w-20 border rounded px-1 py-0.5" step="0.1" />
                Police:
                <select id="cfg-prenom-font" class="border rounded px-1 py-0.5">
                    <option value="Helvetica">Helvetica</option>
                    <option value="TimesRoman">Times Roman</option>
                    <option value="Courier">Courier</option>
                </select>
                Taille:<input id="cfg-prenom-size" type="number" class="w-14 border rounded px-1 py-0.5" step="0.1" />
            </div>
            <!-- Configuration pour l’identifiant -->
            <div class="flex flex-wrap items-center gap-2">
                <strong>ID</strong>
                x:<input id="cfg-id-x" type="number" class="w-20 border rounded px-1 py-0.5" step="0.1" />
                y:<input id="cfg-id-y" type="number" class="w-20 border rounded px-1 py-0.5" step="0.1" />
                Police:
                <select id="cfg-id-font" class="border rounded px-1 py-0.5">
                    <option value="Helvetica">Helvetica</option>
                    <option value="TimesRoman">Times Roman</option>
                    <option value="Courier">Courier</option>
                </select>
                Taille:<input id="cfg-id-size" type="number" class="w-14 border rounded px-1 py-0.5" step="0.1" />
            </div>
            <p class="text-xs text-gray-500 mt-1">Modifiez ces valeurs pour ajuster la position et la police lorsque le PDF ne comporte pas de champs interactifs.</p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/foot.php'; ?>