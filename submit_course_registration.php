<?php
require_once __DIR__ . '/admin/config.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método inválido.'
    ]);
    exit;
}

function field(string $key): string
{
    return trim($_POST[$key] ?? '');
}

$empresa = field('empresa');
$nome = field('nome');
$pais = field('pais');
$email = field('email');
$telefone = field('telefone');
$documento = field('documento');
$profissao = field('profissao');
$curso = field('curso');
$formaPagamento = field('forma_pagamento');
$mensagem = field('mensagem');

$errors = [];

if ($nome === '') {
    $errors[] = 'Indica o teu nome.';
}

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Fornece um email válido.';
}

if ($telefone === '') {
    $errors[] = 'Indica um número de telefone.';
}

if ($documento === '') {
    $errors[] = 'Indica o nº de BI ou NIF.';
}

if ($curso === '') {
    $errors[] = 'Selecciona um curso antes de enviar a pré-inscrição.';
}

if ($formaPagamento === '') {
    $errors[] = 'Selecciona a forma de pagamento preferencial.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => implode(' ', $errors)
    ]);
    exit;
}

$data = load_data();
$registrations = $data['course_registrations'] ?? [];

$registration = [
    'id' => 'registration-' . bin2hex(random_bytes(6)),
    'empresa' => $empresa,
    'nome' => $nome,
    'pais' => $pais,
    'email' => $email,
    'telefone' => $telefone,
    'documento' => $documento,
    'profissao' => $profissao,
    'curso' => $curso,
    'forma_pagamento' => $formaPagamento,
    'mensagem' => $mensagem,
    'submitted_at' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];

$registrations[] = $registration;
$data['course_registrations'] = $registrations;
save_data($data);

$to = 'geral@jompson.com';
$subject = 'Nova pré-inscrição - ' . $curso;

$lines = [
    'Nova pré-inscrição recebida a partir do site da JOMPSON.',
    '',
    'Curso: ' . $curso,
    'Nome: ' . $nome,
    'Empresa: ' . ($empresa !== '' ? $empresa : '—'),
    'País: ' . ($pais !== '' ? $pais : '—'),
    'Email: ' . $email,
    'Telefone: ' . $telefone,
    'Nº de BI/NIF: ' . $documento,
    'Profissão: ' . ($profissao !== '' ? $profissao : '—'),
    'Forma de pagamento: ' . $formaPagamento,
    '',
    'Mensagem:',
    $mensagem !== '' ? $mensagem : '—',
    '',
    'Registado em: ' . date('d/m/Y H:i'),
    'IP: ' . ($registration['ip'] ?: '—'),
];

$body = implode("\n", $lines);

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: noreply@jompson.com',
    'Reply-To: ' . $email,
];

$emailSent = @mail($to, $subject, $body, implode("\r\n", $headers));

http_response_code(200);

echo json_encode([
    'success' => true,
    'emailSent' => $emailSent,
]);
