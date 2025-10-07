<?php /** @var string $title */ ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'QCM', ENT_QUOTES) ?></title>
  <link rel="stylesheet" href="assets/style.css">
	<link rel="stylesheet" href="https://unpkg.com/prismjs/themes/prism.min.css">
	<script defer src="https://unpkg.com/prismjs/components/prism-core.min.js"></script>
	<script defer src="https://unpkg.com/prismjs/plugins/autoloader/prism-autoloader.min.js"></script>

</head>
<body>
  <div class="container">
    <div class="row" style="justify-content:space-between;align-items:center">
      <h2>QCM PHP v6</h2>
      <div>
        <a href="?action=home" class="btn">Accueil</a>
        <a href="?action=editor" class="btn">Éditeur</a>
        <a href="?action=template_editor" class="btn">Templates</a>
        <a href="?action=export" class="btn">Exporter</a>
		<a href="?action=convcsv" class="btn">QCM chatGPT</a>
		<a href="?action=grille" class="btn primary">Grille</a>
      </div>
    </div>
    <div class="card">
      <?php require __DIR__ . '/' . $tpl; ?>
    </div>
    <div class="footer">v6 • <?= date('Y-m-d H:i') ?> • Europe/Brussels</div>
  </div>
  <script src="assets/app.js"></script>
</body>
</html>
