<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php#inscricoes');
    exit;
}

$registrationId = trim($_POST['registration_id'] ?? '');

if ($registrationId === '') {
    $_SESSION['admin_error'] = 'Registo inválido seleccionado para eliminar.';
    header('Location: dashboard.php#inscricoes');
    exit;
}

$data = load_data();
$registrations = $data['course_registrations'] ?? [];

$initialCount = count($registrations);
$registrations = array_values(array_filter($registrations, static function (array $registration) use ($registrationId) {
    return ($registration['id'] ?? '') !== $registrationId;
}));

if ($initialCount === count($registrations)) {
    $_SESSION['admin_error'] = 'Não foi possível encontrar a inscrição seleccionada.';
} else {
    $data['course_registrations'] = $registrations;
    save_data($data);
    $_SESSION['admin_success'] = 'Inscrição removida com sucesso.';
}

header('Location: dashboard.php#inscricoes');
exit;
