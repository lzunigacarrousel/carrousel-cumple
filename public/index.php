<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/Web.php';
Web::headers();
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="Carrousel Cumple: registro de clientes, regalos de cumpleaños y control de canjes.">
  <meta name="robots" content="noindex, nofollow">
  <title>Cumpleaños | Carrousel</title>
  <link rel="icon" href="assets/logo-carrousel.png" type="image/png">
  <meta name="color-scheme" content="light dark">
  <link rel="stylesheet" href="assets/helpdesk/app.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/layout-fixes.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/ux-v2.css">
  <link rel="stylesheet" href="assets/helpdesk/ui-refresh.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/content-priority.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/shell-v2.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/components.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/visual-system.css">
  <link rel="stylesheet" href="assets/helpdesk/auth-v2.css">
  <link rel="stylesheet" href="assets/helpdesk/dark-refinement.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/help-tour-contrast.css">
  <link rel="stylesheet" href="cumple.css?v=helpdesk-20260925">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/layout-density-v25.css">
  <link data-shell-style rel="stylesheet" href="assets/helpdesk/data-tables.css">
  <script src="theme.js"></script>
  <script src="vendor/qrcode.min.js" defer></script>
  <script src="help.js?v=helpdesk-20260925" defer></script>
  <script src="app.js?v=helpdesk-20260925" defer></script>
</head>
<body>
  <div id="app"><p style="padding:3rem;font-family:system-ui">Preparando Carrousel Cumple…</p></div>
  <noscript>Activa JavaScript para utilizar Carrousel Cumple.</noscript>
</body>
</html>
