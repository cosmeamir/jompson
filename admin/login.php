<?php
require_once __DIR__ . '/config.php';

$error = '';
if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Credenciais inválidas. Por favor, tente novamente.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Login | Área Administrativa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {background:#f8f9fa;}
        .login-wrapper {max-width:420px;margin:80px auto;padding:30px;background:#fff;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.08);} 
    </style>
</head>
<body>
<div class="login-wrapper">
    <h2 class="mb-4 text-center">Área Administrativa</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label for="username">Usuário</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
</div>
</body>
</html>
