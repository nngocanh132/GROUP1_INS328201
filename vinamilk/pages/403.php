<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Access Denied — Vinamilk CDM</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css">
</head>
<body>
<div style="min-height:100vh; display:flex; align-items:center; justify-content:center; background:var(--color-bg); padding:24px;">
  <div style="text-align:center; max-width:400px;">
    <div style="font-size:64px; font-weight:800; color:var(--color-brand-navy); margin-bottom:8px;">403</div>
    <h2 style="font-size:20px; font-weight:700; margin-bottom:8px; color:var(--color-text-primary);">Access Denied</h2>
    <p style="color:var(--color-text-secondary); font-size:14px; margin-bottom:24px;">
      You don't have permission to access this page.
    </p>
    <a href="<?= BASE_URL ?>/" class="btn btn-primary">Return to Dashboard</a>
  </div>
</div>
</body>
</html>
