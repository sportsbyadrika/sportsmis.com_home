<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>403 – Access Denied | SportsMIS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Inter', sans-serif; background: #f8fafc; display:flex; align-items:center; justify-content:center; min-height:100vh; }
  .err-code { font-size: 7rem; font-weight:800; color:#e2e8f0; line-height:1; }
  .err-icon { font-size:3rem; color:#ef4444; }
</style>
</head>
<body>
<div class="text-center p-4">
  <div class="err-code">403</div>
  <div class="err-icon mb-3"><i class="bi bi-shield-exclamation"></i></div>
  <h2 class="fw-bold mb-2">Access Denied</h2>
  <p class="text-muted mb-4">You don't have permission to view this page.</p>
  <a href="javascript:history.back()" class="btn btn-outline-secondary me-2">
    <i class="bi bi-arrow-left me-1"></i>Go Back
  </a>
  <a href="/login" class="btn btn-primary">
    <i class="bi bi-box-arrow-in-right me-1"></i>Login
  </a>
</div>
</body>
</html>
