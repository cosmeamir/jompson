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
$courseId = field('course_id');
$coursePrice = field('course_price');
$formaPagamento = field('forma_pagamento');
$mensagem = field('mensagem');
$comprovativo = $_FILES['comprovativo'] ?? null;
$comprovativoMeta = null;

$data = load_data();
$coursesData = $data['courses'] ?? [];

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

if ($courseId === '') {
    $errors[] = 'Selecciona um curso válido antes de enviar a pré-inscrição.';
}

$allowedPayments = ['Transferência Bancária'];
if ($formaPagamento === '') {
    $errors[] = 'Selecciona a forma de pagamento preferencial.';
} elseif (!in_array($formaPagamento, $allowedPayments, true)) {
    $errors[] = 'A forma de pagamento seleccionada é inválida.';
} else {
    $formaPagamento = $allowedPayments[0];
}

$matchedCourse = null;
if ($courseId !== '') {
    foreach ($coursesData as $courseEntry) {
        if (($courseEntry['id'] ?? '') === $courseId) {
            $matchedCourse = $courseEntry;
            break;
        }
    }
}

if ($matchedCourse === null) {
    $errors[] = 'O curso seleccionado já não está disponível. Actualiza a página e tenta novamente.';
} else {
    $curso = $matchedCourse['title'] ?? $curso;
    $coursePrice = $matchedCourse['price'] ?? $coursePrice;
}

$allowedProofMimes = [
    'application/pdf' => 'pdf',
    'application/x-pdf' => 'pdf',
    'application/acrobat' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/jpg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
];

if (!isset($comprovativo) || !is_array($comprovativo) || ($comprovativo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $errors[] = 'Anexa o comprovativo da transferência.';
} else {
    $proofSize = (int) ($comprovativo['size'] ?? 0);
    if ($proofSize <= 0) {
        $errors[] = 'O comprovativo enviado é inválido.';
    } elseif ($proofSize > 1048576) {
        $errors[] = 'O comprovativo deve ter no máximo 1MB.';
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($comprovativo['tmp_name']);
        $finfo->close();
        if (!isset($allowedProofMimes[$mimeType])) {
            $errors[] = 'O comprovativo deve ser um ficheiro PDF, JPG ou PNG.';
        } else {
            $comprovativoMeta = [
                'tmp_name' => $comprovativo['tmp_name'],
                'extension' => $allowedProofMimes[$mimeType],
                'mime' => $mimeType,
                'original_name' => $comprovativo['name'] ?? '',
            ];
        }
    }
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => implode(' ', $errors)
    ]);
    exit;
}

$uploadRelativePath = '';
if ($comprovativoMeta !== null) {
    $uploadDir = __DIR__ . '/uploads/comprovativos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível preparar o diretório de comprovativos.'
        ]);
        exit;
    }

    $proofFilename = 'comprovativo-' . bin2hex(random_bytes(12)) . '.' . $comprovativoMeta['extension'];
    $targetPath = $uploadDir . '/' . $proofFilename;

    if (!move_uploaded_file($comprovativoMeta['tmp_name'], $targetPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível guardar o comprovativo enviado. Tenta novamente.'
        ]);
        exit;
    }

    $uploadRelativePath = 'uploads/comprovativos/' . $proofFilename;
}

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
    'course_id' => $courseId,
    'course_price' => $coursePrice,
    'forma_pagamento' => $formaPagamento,
    'mensagem' => $mensagem,
    'comprovativo' => $uploadRelativePath,
    'comprovativo_mime' => $comprovativoMeta['mime'] ?? '',
    'comprovativo_original' => $comprovativoMeta['original_name'] ?? '',
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
    'ID do curso: ' . ($courseId !== '' ? $courseId : '—'),
    'Preço indicado: ' . ($coursePrice !== '' ? $coursePrice : '—'),
    'Forma de pagamento: ' . $formaPagamento,
    'Comprovativo: ' . ($uploadRelativePath !== '' ? $uploadRelativePath : '—'),
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
