<?php $app = !empty($_SESSION['admin']); $active = $active ?? ''; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Notice Broadcast') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Mukta:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<?= $extra_head ?? '' ?>
</head>
<body class="<?= $app ? 'app' : 'bare' ?>">
<?php if ($app): ?>
<aside class="side">
  <div class="brand"><?= e($CFG['college']) ?><small>Notice Broadcast</small></div>
  <nav>
    <a href="index.php" class="<?= $active === 'dash' ? 'on' : '' ?>">Dashboard</a>
    <a href="templates.php" class="<?= $active === 'templates' ? 'on' : '' ?>">Templates</a>
    <a href="broadcast.php" class="<?= $active === 'broadcast' ? 'on' : '' ?>">Template Broadcast</a>
    <a href="notices.php" class="<?= $active === 'notices' ? 'on' : '' ?>">All Notices</a>
    <a href="logout.php">Log Out</a>
  </nav>
</aside>
<?php endif; ?>
<main class="main">
<?php foreach (($_SESSION['flash'] ?? []) as [$c, $m]): ?>
  <div class="flash <?= e($c) ?>"><?= e($m) ?></div>
<?php endforeach; unset($_SESSION['flash']); ?>