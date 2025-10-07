<?php
// Shared head partial for the form generator application.  This file is included
// at the beginning of each page and sets HTTP headers to prevent caching,
// defines the document structure and loads external dependencies (CSS and
// JavaScript).  It also configures PDF.js to allow client-side PDF parsing.

// Disable caching to ensure that new uploads refresh properly.  Use
// only ASCII characters in header names to avoid issues with
// mis-encoded hyphens.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Générateur de formulaires – Grille</title>
    <!-- TailwindCSS for quick layout and styling -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- JSZip: read and create ZIP files -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <!-- pdf-lib: manipulate PDF forms client-side -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
    <!-- PapaParse: parse CSV data client-side -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
    <!-- PDF.js: extract text from PDF lists of students (if needed).
         We use the 2.7.570 version because it exposes a global `pdfjsLib`
         object, which simplifies client‑side usage.  Newer 3.x/4.x builds
         require ESM import patterns and do not create a global, leading to
         undefined references in the browser environment. -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.7.570/pdf.min.js"></script>
    <script>
        // Configure the worker for PDF.js.  The pdf.js library exposes a global
        // `pdfjsLib` object when loaded from the CDN.  We set the workerSrc
        // property on that object so that PDF.js can locate its web worker.
        // See: https://stackoverflow.com/a/66974404 for details on using the
        // CDN build of PDF.js.  Without this configuration, calls to
        // pdfjsLib.getDocument() will fail because no worker is set.
        if (typeof window.pdfjsLib !== 'undefined' && window.pdfjsLib.GlobalWorkerOptions) {
            window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.7.570/pdf.worker.min.js';
        }
    </script>
    <!-- Custom styling for file drop zones and log area -->
    <style>
        .file-drop {
            border: 2px dashed #cbd5e0;
            border-radius: 0.375rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
        }
        .file-drop.dragover {
            border-color: #3182ce;
            background-color: #ebf8ff;
        }
        #logArea {
            height: 14rem;
            overflow-y: auto;
            white-space: pre-wrap;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            padding: 0.5rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen py-6">
<div class="max-w-4xl mx-auto px-4">
    <h1 class="text-2xl font-bold mb-4">Générateur de formulaires – Grille</h1>