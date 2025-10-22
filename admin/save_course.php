<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

function redirect_with_message(string $type, string $message): void
{
    $_SESSION[$type] = $message;
    header('Location: dashboard.php#courses');
    exit;
}

$mode = $_POST['mode'] ?? 'create';
$courseId = trim($_POST['course_id'] ?? '');
$category = trim($_POST['category'] ?? '');
$subcategory = trim($_POST['subcategory'] ?? '');
$title = trim($_POST['title'] ?? '');
$headline = trim($_POST['headline'] ?? '');
$overview = trim($_POST['overview'] ?? '');
$generalObjectives = trim($_POST['general_objectives'] ?? '');
$specificObjectives = trim($_POST['specific_objectives'] ?? '');
$contents = trim($_POST['contents'] ?? '');
$details = trim($_POST['details'] ?? '');
$pdfUrl = trim($_POST['pdf_url'] ?? '');

if ($category === '' || $subcategory === '' || $title === '') {
    redirect_with_message('admin_error', 'Indica pelo menos a categoria, subcategoria e título do curso.');
}

if ($mode === 'update' && $courseId === '') {
    redirect_with_message('admin_error', 'Curso inválido seleccionado para edição.');
}

$data = load_data();
$courses = $data['courses'] ?? [];

$now = date('c');

if ($mode === 'update') {
    $updated = false;
    foreach ($courses as &$course) {
        if (($course['id'] ?? '') === $courseId) {
            $course['category'] = $category;
            $course['subcategory'] = $subcategory;
            $course['title'] = $title;
            $course['headline'] = $headline;
            $course['overview'] = $overview;
            $course['general_objectives'] = $generalObjectives;
            $course['specific_objectives'] = $specificObjectives;
            $course['contents'] = $contents;
            $course['details'] = $details;
            $course['pdf_url'] = $pdfUrl;
            $course['updated_at'] = $now;
            if (empty($course['created_at'])) {
                $course['created_at'] = $now;
            }
            $updated = true;
            break;
        }
    }
    unset($course);

    if (!$updated) {
        redirect_with_message('admin_error', 'Não foi possível encontrar o curso seleccionado.');
    }

    $message = 'Curso actualizado com sucesso.';
} else {
    $id = 'course-' . bin2hex(random_bytes(6));
    $courses[] = [
        'id' => $id,
        'category' => $category,
        'subcategory' => $subcategory,
        'title' => $title,
        'headline' => $headline,
        'overview' => $overview,
        'general_objectives' => $generalObjectives,
        'specific_objectives' => $specificObjectives,
        'contents' => $contents,
        'details' => $details,
        'pdf_url' => $pdfUrl,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $message = 'Curso criado com sucesso.';
}

usort($courses, static function (array $a, array $b): int {
    $categoryComparison = strcasecmp($a['category'] ?? '', $b['category'] ?? '');
    if ($categoryComparison !== 0) {
        return $categoryComparison;
    }

    $subcategoryComparison = strcasecmp($a['subcategory'] ?? '', $b['subcategory'] ?? '');
    if ($subcategoryComparison !== 0) {
        return $subcategoryComparison;
    }

    return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
});

$data['courses'] = array_values($courses);
save_data($data);

redirect_with_message('admin_success', $message);
