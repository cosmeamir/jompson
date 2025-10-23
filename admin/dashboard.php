<?php
require_once __DIR__ . '/config.php';
ensure_logged_in();
$data = load_data();
$stats = array_merge([
    'services' => 0,
    'clients' => 0,
    'experience' => 0,
], $data['stats']);
$blogs = $data['blogs'];
$courses = $data['courses'];
$courseRegistrations = $data['course_registrations'];
$courseCategories = $data['course_categories'] ?? [];
$courseSubcategories = $data['course_subcategories'] ?? [];
$emailConfig = defined('EMAIL_CONFIG') && is_array(EMAIL_CONFIG) ? EMAIL_CONFIG : [];
$smtpConfig = $emailConfig['smtp'] ?? [];
$imapConfig = $emailConfig['imap'] ?? [];

usort($courseCategories, static function (array $a, array $b): int {
    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$categoryMap = [];
foreach ($courseCategories as $category) {
    if (!isset($category['id'])) {
        continue;
    }
    $categoryMap[$category['id']] = $category['name'] ?? '';
}

usort($courseSubcategories, static function (array $a, array $b) use ($categoryMap): int {
    $categoryA = $categoryMap[$a['category_id'] ?? ''] ?? '';
    $categoryB = $categoryMap[$b['category_id'] ?? ''] ?? '';
    $categoryComparison = strcasecmp($categoryA, $categoryB);
    if ($categoryComparison !== 0) {
        return $categoryComparison;
    }

    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
});

$subcategoryMap = [];
$subcategoriesByCategory = [];
foreach ($courseSubcategories as $subcategory) {
    if (!isset($subcategory['id'])) {
        continue;
    }
    $subcategoryMap[$subcategory['id']] = $subcategory;
    $categoryId = $subcategory['category_id'] ?? '';
    if (!isset($subcategoriesByCategory[$categoryId])) {
        $subcategoriesByCategory[$categoryId] = [];
    }
    $subcategoriesByCategory[$categoryId][] = $subcategory;
}

foreach ($courses as &$course) {
    $categoryId = $course['category_id'] ?? '';
    if ($categoryId !== '' && isset($categoryMap[$categoryId])) {
        $course['category'] = $categoryMap[$categoryId];
    }

    $subcategoryId = $course['subcategory_id'] ?? '';
    if ($subcategoryId !== '' && isset($subcategoryMap[$subcategoryId])) {
        $course['subcategory'] = $subcategoryMap[$subcategoryId]['name'] ?? '';
    }
}
unset($course);

$categoryCourseCounts = [];
$subcategoryCourseCounts = [];
foreach ($courses as $courseEntry) {
    $categoryId = $courseEntry['category_id'] ?? '';
    if ($categoryId !== '') {
        if (!isset($categoryCourseCounts[$categoryId])) {
            $categoryCourseCounts[$categoryId] = 0;
        }
        $categoryCourseCounts[$categoryId]++;
    }

    $subcategoryId = $courseEntry['subcategory_id'] ?? '';
    if ($subcategoryId !== '') {
        if (!isset($subcategoryCourseCounts[$subcategoryId])) {
            $subcategoryCourseCounts[$subcategoryId] = 0;
        }
        $subcategoryCourseCounts[$subcategoryId]++;
    }
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

usort($courseRegistrations, static function (array $a, array $b): int {
    return strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? '');
});
$successMessage = $_SESSION['admin_success'] ?? null;
$errorMessage = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

function admin_asset(string $path): string
{
    if ($path === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path) === 1 || strpos($path, 'data:') === 0 || strpos($path, '//') === 0) {
        return $path;
    }

    return '../' . ltrim($path, '/');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel | Área Administrativa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --surface: #ffffff;
            --surface-alt: #f8faff;
            --text-default: #101631;
            --text-muted: #6b7285;
            --primary: #2563eb;
            --primary-soft: rgba(37, 99, 235, 0.1);
            --shadow-soft: 0 18px 45px rgba(15, 23, 42, 0.08);
            --radius-lg: 18px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #e5edff 0%, #fdf4ff 100%);
            color: var(--text-default);
        }

        a {
            color: inherit;
        }

        .dashboard-shell {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .dashboard-header-shell {
            position: sticky;
            top: 0;
            z-index: 1040;
            backdrop-filter: blur(18px);
            background: linear-gradient(160deg, rgba(15, 23, 42, 0.95) 0%, rgba(29, 78, 216, 0.85) 80%);
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.22);
        }

        .dashboard-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 26px clamp(20px, 5vw, 56px) 18px;
            color: #f8fbff;
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-brand .brand-logo {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.16);
            font-size: 20px;
        }

        .topbar-brand strong {
            font-size: 19px;
            letter-spacing: 0.02em;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .topbar-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.35);
            color: inherit;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .topbar-link:hover {
            background: rgba(255, 255, 255, 0.18);
            transform: translateY(-1px);
        }

        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 16px;
            border-radius: 999px;
            font-weight: 500;
            backdrop-filter: blur(6px);
        }

        .user-chip i {
            color: #f8fbff;
            font-size: 18px;
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 999px;
            background: #f8fafc;
            color: #0f172a;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
        }

        .dashboard-nav {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 clamp(20px, 5vw, 56px) 18px;
            background: rgba(248, 250, 252, 0.98);
            border-top: 1px solid rgba(148, 163, 184, 0.18);
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            overflow-x: auto;
        }

        .dashboard-nav a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 14px;
            color: var(--text-muted);
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-nav a i {
            font-size: 16px;
        }

        .dashboard-nav a:hover,
        .dashboard-nav a.active {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 14px 24px rgba(37, 99, 235, 0.25);
        }

        .dashboard-main {
            flex: 1;
            padding: 40px clamp(20px, 5vw, 56px) 60px;
        }

        .welcome-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }

        .welcome-banner h1 {
            font-size: clamp(26px, 3vw, 34px);
            font-weight: 600;
            margin: 0;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }

        .metric-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }

        .metric-card .metric-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
            margin-bottom: 18px;
            font-size: 20px;
        }

        .metric-card h3 {
            font-size: 14px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .metric-card strong {
            font-size: 30px;
            font-weight: 700;
        }

        .metric-card span {
            display: block;
            margin-top: 4px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .content-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 28px;
            margin-bottom: 48px;
        }

        .content-section--single {
            display: block;
        }

        .alert-stack {
            display: grid;
            gap: 12px;
            margin-bottom: 36px;
        }

        .module-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 28px;
            box-shadow: var(--shadow-soft);
        }

        .module-card header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
        }

        .module-card header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        .module-card header span {
            color: var(--text-muted);
            font-size: 13px;
        }

        .config-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 22px;
        }

        .config-group {
            background: var(--surface-alt);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(37, 99, 235, 0.12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.4);
        }

        .config-group h3 {
            margin: 0 0 12px;
            font-size: 16px;
            font-weight: 600;
            color: var(--primary);
        }

        .config-list {
            margin: 0;
        }

        .config-item {
            display: grid;
            grid-template-columns: minmax(120px, 160px) 1fr;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .config-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .config-item:first-child {
            padding-top: 0;
        }

        .config-item dt {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .config-item dd {
            margin: 0;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-default);
        }

        .config-note {
            margin-top: 18px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .form-control,
        .form-control:focus,
        .custom-select,
        .custom-select:focus,
        .form-control-file {
            border-radius: 12px;
            border-color: rgba(148, 163, 184, 0.4);
        }

        .form-group label {
            font-weight: 500;
            color: var(--text-muted);
        }

        textarea.form-control {
            min-height: 160px;
        }

        .btn-primary,
        .btn-success,
        .btn-outline-danger,
        .btn-outline-primary {
            border-radius: 999px;
            padding: 10px 22px;
            font-weight: 600;
        }

        .btn-primary,
        .btn-success {
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.18);
        }

        .btn-primary:hover,
        .btn-success:hover {
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 14px;
            padding: 16px 22px;
            border: none;
            box-shadow: var(--shadow-soft);
        }

        .alert .close {
            outline: none;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .content-section {
            margin-top: 48px;
        }

        .course-admin-grid {
            display: grid;
            gap: 26px;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        }

        .course-admin-grid + .course-admin-grid {
            margin-top: 26px;
        }

        .course-admin-grid .module-card header {
            margin-bottom: 22px;
        }

        .course-admin-grid .module-card header h2 {
            font-size: 20px;
            margin-bottom: 6px;
        }

        .course-admin-grid .module-card header span {
            color: var(--text-muted);
            font-size: 14px;
        }

        .course-list {
            width: 100%;
            border-collapse: collapse;
        }

        .course-taxonomy-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .course-taxonomy-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }

        .course-taxonomy-list li:last-child {
            border-bottom: none;
        }

        .course-taxonomy-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .course-taxonomy-actions form {
            display: inline-block;
            margin: 0;
        }

        .course-taxonomy-empty {
            padding: 16px;
            border-radius: 12px;
            background: var(--surface-alt);
            color: var(--text-muted);
            text-align: center;
        }

        .course-taxonomy-count {
            font-size: 13px;
            color: var(--text-muted);
        }

        .course-list thead {
            background: var(--surface-alt);
        }

        .course-list th,
        .course-list td {
            padding: 12px 14px;
            font-size: 14px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
            vertical-align: top;
        }

        .course-list tbody tr:hover {
            background: rgba(37, 99, 235, 0.05);
        }

        .course-badge {
            display: inline-flex;
            align-items: center;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .course-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .course-actions form {
            display: inline;
        }

        .course-empty {
            padding: 24px;
            background: var(--surface-alt);
            border-radius: 16px;
            text-align: center;
            color: var(--text-muted);
        }

        .registration-table {
            width: 100%;
            border-collapse: collapse;
        }

        .registration-table th,
        .registration-table td {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            font-size: 14px;
        }

        .registration-table thead {
            background: var(--surface-alt);
        }

        .registration-meta {
            font-size: 12px;
            color: var(--text-muted);
            display: block;
        }

        .course-form-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .blog-card {
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            margin-bottom: 28px;
            overflow: hidden;
        }

        .blog-card .card-body {
            padding: 26px 28px 10px;
        }

        .blog-card .card-footer {
            background: var(--surface-alt);
            padding: 18px 28px;
        }

        .blog-card h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .blog-meta {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 18px;
        }

        details summary {
            font-weight: 600;
            cursor: pointer;
            color: var(--primary);
        }

        details summary::-webkit-details-marker {
            display: none;
        }

        .blog-content-preview {
            background: var(--surface-alt);
            border-radius: 14px;
            padding: 18px;
            color: var(--text-muted);
        }

        .edit-panel {
            background: var(--surface-alt);
            padding: 24px 28px;
        }

        .collapse:not(.show) {
            display: none;
        }

        @media (max-width: 992px) {
            .dashboard-topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .topbar-actions {
                width: 100%;
                justify-content: space-between;
            }

            .dashboard-nav {
                flex-wrap: wrap;
                row-gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .dashboard-main {
                padding: 28px 18px 48px;
            }

            .dashboard-topbar {
                padding: 22px 18px 16px;
            }

            .topbar-actions {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }

            .dashboard-nav {
                padding: 0 18px 16px;
                gap: 8px;
            }

            .module-card {
                padding: 22px;
            }

            .blog-card .card-body,
            .blog-card .card-footer {
                padding-left: 22px;
                padding-right: 22px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-shell">
    <header class="dashboard-header-shell">
        <div class="dashboard-topbar">
            <div class="topbar-brand">
                <span class="brand-logo"><i class="bi bi-lightning-charge"></i></span>
                <div>
                    <strong>Jompson Admin</strong><br>
                    <small>Painel de controlo</small>
                </div>
            </div>
            <div class="topbar-actions">
                <a class="topbar-link" href="../index.html" target="_blank" rel="noopener">
                    <i class="bi bi-globe2"></i>
                    Ver site
                </a>
                <div class="user-chip">
                    <i class="bi bi-person-circle"></i>
                    <span>Administrador</span>
                </div>
                <a class="btn-logout" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Terminar sessão
                </a>
            </div>
        </div>
        <nav class="dashboard-nav" id="dashboard-nav">
            <a href="#overview" class="active"><i class="bi bi-speedometer2"></i>Visão Geral</a>
            <a href="#indicadores"><i class="bi bi-bar-chart"></i>Indicadores</a>
            <a href="#categorias"><i class="bi bi-diagram-3"></i>Categorias</a>
            <a href="#subcategorias"><i class="bi bi-diagram-2"></i>Subcategorias</a>
            <a href="#courses"><i class="bi bi-mortarboard"></i>Cursos</a>
            <a href="#inscricoes"><i class="bi bi-people"></i>Inscrições</a>
            <a href="#comunicacoes"><i class="bi bi-envelope-gear"></i>Comunicações</a>
            <a href="#blogs"><i class="bi bi-journal-text"></i>Conteúdo</a>
        </nav>
    </header>
    <main class="dashboard-main">
        <section id="overview" class="content-section content-section--single">
            <div class="welcome-banner">
                <div>
                    <h1>Bem-vindo de volta 👋</h1>
                    <p class="text-muted mb-0">Acompanhe métricas, publique artigos e mantenha o site atualizado.</p>
                </div>
            </div>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-briefcase"></i></div>
                    <h3>Serviços</h3>
                    <strong><?php echo number_format((int) $stats['services'], 0, ',', '.'); ?></strong>
                    <span>Projetos concluídos</span>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-emoji-smile"></i></div>
                    <h3>Clientes</h3>
                    <strong><?php echo number_format((int) $stats['clients'], 0, ',', '.'); ?></strong>
                    <span>Clientes satisfeitos</span>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-award"></i></div>
                    <h3>Experiência</h3>
                    <strong><?php echo number_format((int) $stats['experience'], 0, ',', '.'); ?>+</strong>
                    <span>Anos dedicados</span>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-journal-richtext"></i></div>
                    <h3>Artigos</h3>
                    <strong><?php echo number_format(count($blogs), 0, ',', '.'); ?></strong>
                    <span>Total publicados</span>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-mortarboard"></i></div>
                    <h3>Cursos</h3>
                    <strong><?php echo number_format(count($courses), 0, ',', '.'); ?></strong>
                    <span>Disponíveis no site</span>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-person-lines-fill"></i></div>
                    <h3>Pré-inscrições</h3>
                    <strong><?php echo number_format(count($courseRegistrations), 0, ',', '.'); ?></strong>
                    <span>Contactos recebidos</span>
                </div>
            </div>
        </section>

        <?php if ($successMessage || $errorMessage): ?>
            <div class="alert-stack">
                <?php if ($successMessage): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Sucesso!</strong> <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Ups!</strong> <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <section id="indicadores" class="content-section">
            <section class="module-card">
                <header>
                    <h2>Indicadores da página inicial</h2>
                    <span>Actualize as estatísticas principais</span>
                </header>
                <form method="post" action="save_stats.php">
                    <div class="form-group">
                        <label for="services">Serviços Concluídos</label>
                        <input type="number" min="0" class="form-control" id="services" name="services" value="<?php echo (int) $stats['services']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="clients">Clientes Satisfeitos</label>
                        <input type="number" min="0" class="form-control" id="clients" name="clients" value="<?php echo (int) $stats['clients']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="experience">Anos de Experiência</label>
                        <input type="number" min="0" class="form-control" id="experience" name="experience" value="<?php echo (int) $stats['experience']; ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar indicadores</button>
                </form>
            </section>
            <section class="module-card">
                <header>
                    <h2>Novo artigo do blog</h2>
                    <span>Partilhe uma novidade com o público</span>
                </header>
                <form method="post" action="save_blog.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label for="title">Título</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="date">Data</label>
                        <input type="date" class="form-control" id="date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="author">Autor</label>
                        <input type="text" class="form-control" id="author" name="author" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Imagem (URL) <span class="text-muted small">(opcional)</span></label>
                        <input type="text" class="form-control" id="image" name="image" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label for="image_file">Carregar imagem</label>
                        <input type="file" class="form-control-file" id="image_file" name="image_file" accept="image/png,image/jpeg,image/webp">
                        <small class="form-text text-muted">Formatos permitidos: JPG, PNG ou WEBP (máximo de 2&nbsp;MB).</small>
                    </div>
                    <div class="form-group">
                        <label for="excerpt">Resumo</label>
                        <textarea class="form-control" id="excerpt" name="excerpt" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="content">Conteúdo completo</label>
                        <textarea class="form-control" id="content" name="content" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Publicar artigo</button>
                </form>
            </section>
        </section>

        <section id="categorias" class="content-section content-section--single">
            <section class="module-card">
                <header>
                    <h2>Gerir categorias</h2>
                    <span>Cria categorias para organizar os programas disponibilizados.</span>
                </header>
                <form class="mb-4" method="post" action="save_course_category.php">
                    <input type="hidden" name="mode" value="create">
                    <div class="form-group">
                        <label for="new-course-category">Nova categoria</label>
                        <input type="text" class="form-control" id="new-course-category" name="name" placeholder="Formação Executiva" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Adicionar categoria</button>
                </form>
                <?php if (empty($courseCategories)): ?>
                    <div class="course-taxonomy-empty">Ainda não existem categorias registadas.</div>
                <?php else: ?>
                    <ul class="course-taxonomy-list">
                        <?php foreach ($courseCategories as $category): ?>
                            <?php
                                $categoryId = $category['id'] ?? '';
                                $categoryName = $category['name'] ?? '—';
                                $subCount = isset($subcategoriesByCategory[$categoryId]) ? count($subcategoriesByCategory[$categoryId]) : 0;
                                $courseCount = $categoryCourseCounts[$categoryId] ?? 0;
                                $canDeleteCategory = $categoryId !== '' && $subCount === 0 && $courseCount === 0;
                            ?>
                            <li>
                                <div class="course-taxonomy-meta">
                                    <span class="course-badge"><?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="course-taxonomy-count"><?php echo htmlspecialchars($subCount . ' subcat. •' . $courseCount . ' cursos', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="course-taxonomy-actions">
                                    <form method="post" action="delete_course_category.php" onsubmit="return <?php echo $canDeleteCategory ? 'confirm(\'Eliminar esta categoria?\')' : 'false'; ?>;">
                                        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($categoryId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" <?php echo $canDeleteCategory ? '' : 'disabled'; ?>>Eliminar</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="small text-muted mt-3">Elimina primeiro as subcategorias e cursos associados antes de remover uma categoria.</p>
            </section>
        </section>

        <section id="subcategorias" class="content-section content-section--single">
            <section class="module-card">
                <header>
                    <h2>Gerir subcategorias</h2>
                    <span>Organiza os cursos dentro das respectivas categorias.</span>
                </header>
                <?php if (empty($courseCategories)): ?>
                    <div class="course-taxonomy-empty">Cria uma categoria antes de adicionar subcategorias.</div>
                <?php else: ?>
                    <form class="mb-4" method="post" action="save_course_subcategory.php">
                        <input type="hidden" name="mode" value="create">
                        <div class="form-group">
                            <label for="new-subcategory-category">Categoria</label>
                            <select class="form-control" id="new-subcategory-category" name="category_id" required>
                                <option value="" disabled selected>Selecciona uma categoria</option>
                                <?php foreach ($courseCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($category['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="new-course-subcategory">Nova subcategoria</label>
                            <input type="text" class="form-control" id="new-course-subcategory" name="name" placeholder="Finanças &amp; Contabilidade" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Adicionar subcategoria</button>
                    </form>
                <?php endif; ?>
                <?php if (empty($courseSubcategories)): ?>
                    <div class="course-taxonomy-empty">Ainda não existem subcategorias registadas.</div>
                <?php else: ?>
                    <ul class="course-taxonomy-list">
                        <?php foreach ($courseSubcategories as $subcategory): ?>
                            <?php
                                $subcategoryId = $subcategory['id'] ?? '';
                                $subcategoryName = $subcategory['name'] ?? '—';
                                $parentCategoryId = $subcategory['category_id'] ?? '';
                                $parentCategoryName = $categoryMap[$parentCategoryId] ?? '—';
                                $subcategoryCourseCount = $subcategoryCourseCounts[$subcategoryId] ?? 0;
                                $canDeleteSubcategory = $subcategoryId !== '' && $subcategoryCourseCount === 0;
                            ?>
                            <li>
                                <div class="course-taxonomy-meta">
                                    <span class="course-badge"><?php echo htmlspecialchars($parentCategoryName, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="course-badge"><?php echo htmlspecialchars($subcategoryName, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="course-taxonomy-count"><?php echo htmlspecialchars($subcategoryCourseCount . ' cursos', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="course-taxonomy-actions">
                                    <form method="post" action="delete_course_subcategory.php" onsubmit="return <?php echo $canDeleteSubcategory ? 'confirm(\'Eliminar esta subcategoria?\')' : 'false'; ?>;">
                                        <input type="hidden" name="subcategory_id" value="<?php echo htmlspecialchars($subcategoryId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" <?php echo $canDeleteSubcategory ? '' : 'disabled'; ?>>Eliminar</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="small text-muted mt-3">Remove os cursos associados antes de eliminar uma subcategoria.</p>
            </section>
        </section>

        <section id="courses" class="content-section content-section--single">
            <div class="course-admin-grid">
                <section class="module-card">
                    <header>
                        <h2 id="course-form-title">Adicionar curso</h2>
                        <span>Actualize categorias, subcategorias e conteúdos apresentados no site.</span>
                    </header>
                    <form id="course-form" method="post" action="save_course.php">
                        <input type="hidden" name="mode" id="course-mode" value="create">
                        <input type="hidden" name="course_id" id="course-id" value="">
                        <p class="text-muted small" id="course-form-helper">Preenche os campos para adicionar um novo curso ao catálogo.</p>
                        <div class="form-group">
                            <label for="course-category">Categoria</label>
                            <select class="form-control" id="course-category" name="category_id" required>
                                <option value="">Selecciona uma categoria</option>
                                <?php foreach ($courseCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($category['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="course-subcategory">Subcategoria</label>
                            <select class="form-control" id="course-subcategory" name="subcategory_id" required>
                                <option value="">Selecciona uma subcategoria</option>
                                <?php foreach ($courseSubcategories as $subcategory): ?>
                                    <option value="<?php echo htmlspecialchars($subcategory['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-category="<?php echo htmlspecialchars($subcategory['category_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($subcategory['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="course-title">Título do curso</label>
                            <input type="text" class="form-control" id="course-title" name="title" placeholder="Elaboração e Técnicas de Negociação de Contratos" required>
                        </div>
                        <div class="form-group">
                            <label for="course-headline">Chamada curta</label>
                            <input type="text" class="form-control" id="course-headline" name="headline" placeholder="Domine a narrativa do programa em poucas palavras">
                        </div>
                        <div class="form-group">
                            <label for="course-price">Preço do curso</label>
                            <input type="text" class="form-control" id="course-price" name="price" placeholder="Ex.: 150.000 AOA" required>
                        </div>
                        <div class="form-group">
                            <label for="course-overview">Descrição geral</label>
                            <textarea class="form-control" id="course-overview" name="overview" rows="4" placeholder="Apresenta o propósito e o diferencial do programa."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="course-general-objectives">Objectivos gerais</label>
                            <textarea class="form-control" id="course-general-objectives" name="general_objectives" rows="3" placeholder="Lista cada objectivo numa linha."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="course-specific-objectives">Objectivos específicos</label>
                            <textarea class="form-control" id="course-specific-objectives" name="specific_objectives" rows="3" placeholder="Lista cada objectivo numa linha."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="course-contents">Conteúdos e módulos</label>
                            <textarea class="form-control" id="course-contents" name="contents" rows="4" placeholder="Detalha os módulos ou tópicos do programa, um por linha."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="course-details">Informações rápidas</label>
                            <textarea class="form-control" id="course-details" name="details" rows="3" placeholder="Inclui carga horária, modalidade, certificação, investimento, etc."></textarea>
                            <small class="form-text text-muted">Cada linha é apresentada como um item de destaque ao lado da descrição.</small>
                        </div>
                        <div class="form-group">
                            <label for="course-pdf">Ficha ou brochura (URL)</label>
                            <input type="url" class="form-control" id="course-pdf" name="pdf_url" placeholder="https://...">
                            <small class="form-text text-muted">Opcional: liga a uma ficha técnica ou PDF do curso.</small>
                        </div>
                        <div class="course-form-actions">
                            <button type="submit" class="btn btn-success" id="course-submit">Guardar curso</button>
                            <button type="button" class="btn btn-outline-primary" id="course-reset">Novo curso</button>
                        </div>
                    </form>
                </section>
                <section class="module-card">
                    <header>
                        <h2>Cursos publicados</h2>
                        <span>Organiza o menu vertical conforme a imagem de referência.</span>
                    </header>
                    <?php if (empty($courses)): ?>
                        <div class="course-empty">
                            Ainda não existem cursos registados. Adiciona o primeiro curso para activar a página pública.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="course-list">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Subcategoria</th>
                                        <th>Curso</th>
                                        <th>Preço</th>
                                        <th>Actualização</th>
                                        <th class="text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <?php
                                            $updatedAtRaw = $course['updated_at'] ?? '';
                                            $updatedDisplay = '—';
                                            if ($updatedAtRaw !== '') {
                                                $timestamp = strtotime($updatedAtRaw);
                                                if ($timestamp !== false) {
                                                    $updatedDisplay = date('d/m/Y H:i', $timestamp);
                                                } else {
                                                    $updatedDisplay = $updatedAtRaw;
                                                }
                                            }
                                            $encodedCourse = htmlspecialchars(json_encode($course, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                            $coursePrice = $course['price'] ?? '—';
                                        ?>
                                        <tr>
                                            <td><span class="course-badge"><?php echo htmlspecialchars($course['category'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><span class="course-badge"><?php echo htmlspecialchars($course['subcategory'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if (!empty($course['headline'])): ?>
                                                    <div class="text-muted small mt-1"><?php echo htmlspecialchars($course['headline'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($coursePrice, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($updatedDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-right">
                                                <div class="course-actions">
                                                    <button type="button" class="btn btn-outline-primary btn-sm course-edit-btn" data-course="<?php echo $encodedCourse; ?>">Editar</button>
                                                    <form method="post" action="delete_course.php" onsubmit="return confirm('Eliminar este curso?');">
                                                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <div id="course-taxonomy-data" data-categories="<?php echo htmlspecialchars(json_encode($courseCategories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" data-subcategories="<?php echo htmlspecialchars(json_encode($courseSubcategories, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>" hidden></div>
        </section>

        <section id="inscricoes" class="content-section content-section--single">
            <div class="module-card">
                <header>
                    <h2>Pré-inscrições recebidas</h2>
                    <span>Acompanhe as candidaturas enviadas pela página de cursos.</span>
                </header>
                <?php if (empty($courseRegistrations)): ?>
                    <div class="course-empty">Ainda não recebemos inscrições. Assim que o formulário for submetido, elas aparecerão aqui.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="registration-table">
                            <thead>
                                <tr>
                                    <th>Participante</th>
                                    <th>Empresa / País</th>
                                    <th>Curso e Pagamento</th>
                                    <th>Mensagem</th>
                                    <th>Recebido</th>
                                    <th class="text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courseRegistrations as $registration): ?>
                                    <?php
                                        $submittedAtRaw = $registration['submitted_at'] ?? '';
                                        $submittedDisplay = '—';
                                        if ($submittedAtRaw !== '') {
                                            $timestamp = strtotime($submittedAtRaw);
                                            if ($timestamp !== false) {
                                                $submittedDisplay = date('d/m/Y H:i', $timestamp);
                                            } else {
                                            $submittedDisplay = $submittedAtRaw;
                                            }
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($registration['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="registration-meta"><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="registration-meta"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($registration['telefone'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($registration['empresa'])): ?>
                                                <div><?php echo htmlspecialchars($registration['empresa'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($registration['pais'])): ?>
                                                <span class="registration-meta">País: <?php echo htmlspecialchars($registration['pais'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <span class="registration-meta">BI/NIF: <?php echo htmlspecialchars($registration['documento'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($registration['profissao'])): ?>
                                                <span class="registration-meta">Profissão: <?php echo htmlspecialchars($registration['profissao'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><strong><?php echo htmlspecialchars($registration['curso'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <?php if (!empty($registration['course_price'])): ?>
                                                <span class="registration-meta">Preço: <?php echo htmlspecialchars($registration['course_price'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($registration['course_id'])): ?>
                                                <span class="registration-meta">ID: <?php echo htmlspecialchars($registration['course_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <span class="registration-meta">Forma de pagamento: <?php echo htmlspecialchars($registration['forma_pagamento'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($registration['comprovativo'])): ?>
                                                <div class="mt-2">
                                                    <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(admin_asset($registration['comprovativo']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Ver comprovativo</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 220px;">
                                            <?php echo nl2br(htmlspecialchars($registration['mensagem'], ENT_QUOTES, 'UTF-8')); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($submittedDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-right">
                                            <form method="post" action="delete_registration.php" onsubmit="return confirm('Eliminar esta inscrição?');">
                                                <input type="hidden" name="registration_id" value="<?php echo htmlspecialchars($registration['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="comunicacoes" class="content-section content-section--single">
            <section class="module-card">
                <header>
                    <h2>Configurações de email</h2>
                    <span>Utiliza estes dados para integrar o domínio nos clientes de correio.</span>
                </header>
                <div class="config-grid">
                    <div class="config-group">
                        <h3>Servidor SMTP (saída)</h3>
                        <dl class="config-list">
                            <div class="config-item">
                                <dt>Protocolo</dt>
                                <dd>SMTP</dd>
                            </div>
                            <div class="config-item">
                                <dt>Host</dt>
                                <dd><?php echo htmlspecialchars($smtpConfig['host'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Porta</dt>
                                <dd><?php echo htmlspecialchars((string) ($smtpConfig['port'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Encriptação</dt>
                                <dd><?php echo htmlspecialchars(strtoupper($smtpConfig['encryption'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Utilizador</dt>
                                <dd><?php echo htmlspecialchars($smtpConfig['username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Palavra-passe</dt>
                                <dd><?php echo htmlspecialchars($smtpConfig['password'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="config-group">
                        <h3>Servidor IMAP (entrada)</h3>
                        <dl class="config-list">
                            <div class="config-item">
                                <dt>Protocolo</dt>
                                <dd>IMAP</dd>
                            </div>
                            <div class="config-item">
                                <dt>Host</dt>
                                <dd><?php echo htmlspecialchars($imapConfig['host'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Porta</dt>
                                <dd><?php echo htmlspecialchars((string) ($imapConfig['port'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Encriptação</dt>
                                <dd><?php echo htmlspecialchars(strtoupper($imapConfig['encryption'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Utilizador</dt>
                                <dd><?php echo htmlspecialchars($imapConfig['username'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Palavra-passe</dt>
                                <dd><?php echo htmlspecialchars($imapConfig['password'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                        </dl>
                    </div>
                    <div class="config-group">
                        <h3>Endereços utilizados</h3>
                        <dl class="config-list">
                            <div class="config-item">
                                <dt>Remetente</dt>
                                <dd><?php echo htmlspecialchars(($emailConfig['from_name'] ?? 'JOMPSON Cursos') . ' <' . ($emailConfig['from_address'] ?? 'info@jompson.com') . '>', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Resposta</dt>
                                <dd><?php echo htmlspecialchars($emailConfig['reply_to_fallback'] ?? $emailConfig['from_address'] ?? 'info@jompson.com', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                            <div class="config-item">
                                <dt>Destino</dt>
                                <dd><?php echo htmlspecialchars($emailConfig['to_address'] ?? 'geral@jompson.com', ENT_QUOTES, 'UTF-8'); ?></dd>
                            </div>
                        </dl>
                        <p class="config-note">Os alertas de pré-inscrição são enviados via autenticação SSL directa na porta <?php echo htmlspecialchars((string) ($smtpConfig['port'] ?? '465'), ENT_QUOTES, 'UTF-8'); ?>.</p>
                    </div>
                </div>
            </section>
        </section>

        <section id="blogs" class="content-section content-section--single">
            <h2 class="section-title">Artigos publicados</h2>
            <?php if (empty($blogs)): ?>
                <div class="alert alert-info">Ainda não existem artigos publicados.</div>
            <?php endif; ?>
            <?php foreach ($blogs as $blog): ?>
                <article class="card blog-card">
                <div class="card-body">
                    <h3><?php echo htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="blog-meta">
                        <span class="mr-3"><i class="bi bi-calendar-week mr-1"></i><?php echo htmlspecialchars($blog['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="mr-3"><i class="bi bi-person mr-1"></i><?php echo htmlspecialchars($blog['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="text-monospace"><i class="bi bi-link-45deg mr-1"></i><?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php if (!empty($blog['image'])): ?>
                        <figure class="mb-3">
                            <img src="<?php echo htmlspecialchars(admin_asset($blog['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pré-visualização do artigo" class="img-fluid rounded" style="max-height: 220px; object-fit: cover;">
                        </figure>
                    <?php endif; ?>
                    <p class="mb-3"><strong>Resumo:</strong> <?php echo nl2br(htmlspecialchars($blog['excerpt'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <details>
                        <summary>Ver conteúdo completo</summary>
                        <div class="mt-3 blog-content-preview">
                            <?php echo nl2br(htmlspecialchars($blog['content'], ENT_QUOTES, 'UTF-8')); ?>
                        </div>
                    </details>
                    <div class="mt-3">
                        <form class="d-inline-block" method="post" action="save_blog.php">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="slug" value="<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Eliminar este artigo?');">Eliminar artigo</button>
                        </form>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-outline-primary btn-sm" type="button" data-toggle="collapse" data-target="#edit-<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">Editar conteúdo</button>
                </div>
                <div class="collapse" id="edit-<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="edit-panel">
                        <form method="post" action="save_blog.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="slug" value="<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($blog['image'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="form-group">
                                <label>Título</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Data</label>
                                <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($blog['date'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Autor</label>
                                <input type="text" class="form-control" name="author" value="<?php echo htmlspecialchars($blog['author'], ENT_QUOTES, 'UTF-8'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Imagem (URL)</label>
                                <input type="text" class="form-control" name="image" value="<?php echo htmlspecialchars($blog['image'], ENT_QUOTES, 'UTF-8'); ?>">
                                <small class="form-text text-muted">Mantém a imagem actual ou indica um novo endereço.</small>
                            </div>
                            <div class="form-group">
                                <label>Substituir imagem</label>
                                <input type="file" class="form-control-file" name="image_file" accept="image/png,image/jpeg,image/webp">
                                <small class="form-text text-muted">Se carregar um novo ficheiro, ele substituirá a imagem actual.</small>
                            </div>
                            <div class="form-group">
                                <label>Resumo</label>
                                <textarea class="form-control" name="excerpt" required><?php echo htmlspecialchars($blog['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Conteúdo</label>
                                <textarea class="form-control" name="content" required><?php echo htmlspecialchars($blog['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar alterações</button>
                        </form>
                    </div>
                </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var navLinks = document.querySelectorAll('#dashboard-nav a');
        function setActiveNav(hash) {
            if (!hash) {
                return;
            }
            navLinks.forEach(function (link) {
                if (link.getAttribute('href') === hash) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }

        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                setActiveNav(link.getAttribute('href'));
            });
        });

        setActiveNav(window.location.hash || '#overview');

        var courseForm = document.getElementById('course-form');
        if (!courseForm) {
            return;
        }

        var modeInput = document.getElementById('course-mode');
        var idInput = document.getElementById('course-id');
        var helper = document.getElementById('course-form-helper');
        var title = document.getElementById('course-form-title');
        var submitButton = document.getElementById('course-submit');
        var resetButton = document.getElementById('course-reset');
        var categorySelect = document.getElementById('course-category');
        var subcategorySelect = document.getElementById('course-subcategory');
        var taxonomyData = document.getElementById('course-taxonomy-data');

        var categoriesData = [];
        var subcategoriesData = [];

        if (taxonomyData) {
            try {
                categoriesData = JSON.parse(taxonomyData.getAttribute('data-categories') || '[]') || [];
            } catch (error) {
                categoriesData = [];
            }

            try {
                subcategoriesData = JSON.parse(taxonomyData.getAttribute('data-subcategories') || '[]') || [];
            } catch (error) {
                subcategoriesData = [];
            }
        }

        function sortByName(a, b) {
            return (a.name || '').localeCompare(b.name || '', 'pt', { sensitivity: 'base' });
        }

        categoriesData.sort(sortByName);
        subcategoriesData.sort(function (a, b) {
            var categoryCompare = (a.category_id || '').localeCompare(b.category_id || '', 'pt', { sensitivity: 'base' });
            if (categoryCompare !== 0) {
                return categoryCompare;
            }
            return sortByName(a, b);
        });

        function renderCategoryOptions(selectedId) {
            if (!categorySelect) {
                return;
            }

            categorySelect.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = categoriesData.length ? 'Selecciona uma categoria' : 'Nenhuma categoria disponível';
            placeholder.disabled = categoriesData.length === 0;
            placeholder.selected = true;
            categorySelect.appendChild(placeholder);

            categoriesData.forEach(function (category) {
                var option = document.createElement('option');
                option.value = category.id || '';
                option.textContent = category.name || '—';
                if (category.id === selectedId) {
                    option.selected = true;
                    placeholder.selected = false;
                }
                categorySelect.appendChild(option);
            });

            categorySelect.disabled = categoriesData.length === 0;
            if (selectedId && categorySelect.value !== selectedId) {
                categorySelect.value = selectedId;
            }
        }

        function renderSubcategoryOptions(categoryId, selectedId) {
            if (!subcategorySelect) {
                return;
            }

            subcategorySelect.innerHTML = '';
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = categoryId ? 'Selecciona uma subcategoria' : 'Selecciona uma categoria primeiro';
            placeholder.disabled = !!categoryId;
            placeholder.selected = true;
            subcategorySelect.appendChild(placeholder);

            if (!categoryId) {
                subcategorySelect.disabled = true;
                return;
            }

            var filtered = subcategoriesData.filter(function (item) {
                return (item.category_id || '') === categoryId;
            });

            filtered.forEach(function (subcategory) {
                var option = document.createElement('option');
                option.value = subcategory.id || '';
                option.textContent = subcategory.name || '—';
                if (subcategory.id === selectedId) {
                    option.selected = true;
                    placeholder.selected = false;
                }
                subcategorySelect.appendChild(option);
            });

            subcategorySelect.disabled = filtered.length === 0;
            if (selectedId && subcategorySelect.value !== selectedId) {
                subcategorySelect.value = selectedId;
            }
        }

        renderCategoryOptions('');
        renderSubcategoryOptions('', '');

        if (categorySelect) {
            categorySelect.addEventListener('change', function () {
                renderSubcategoryOptions(categorySelect.value, '');
            });
        }

        var textFields = {
            title: document.getElementById('course-title'),
            headline: document.getElementById('course-headline'),
            price: document.getElementById('course-price'),
            overview: document.getElementById('course-overview'),
            general_objectives: document.getElementById('course-general-objectives'),
            specific_objectives: document.getElementById('course-specific-objectives'),
            contents: document.getElementById('course-contents'),
            details: document.getElementById('course-details'),
            pdf_url: document.getElementById('course-pdf')
        };

        function setMode(mode) {
            if (mode === 'update') {
                modeInput.value = 'update';
                if (submitButton) {
                    submitButton.textContent = 'Actualizar curso';
                }
                if (helper) {
                    helper.textContent = 'Edita a informação do curso seleccionado e guarda para actualizar no site.';
                }
                if (title) {
                    title.textContent = 'Editar curso';
                }
            } else {
                modeInput.value = 'create';
                if (submitButton) {
                    submitButton.textContent = 'Guardar curso';
                }
                if (helper) {
                    helper.textContent = 'Preenche os campos para adicionar um novo curso ao catálogo.';
                }
                if (title) {
                    title.textContent = 'Adicionar curso';
                }
                idInput.value = '';
            }
        }

        setMode(modeInput.value || 'create');

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                courseForm.reset();
                setMode('create');
                renderCategoryOptions('');
                renderSubcategoryOptions('', '');
                if (categorySelect && !categorySelect.disabled) {
                    categorySelect.focus();
                }
            });
        }

        document.querySelectorAll('.course-edit-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                var payload = button.getAttribute('data-course');
                if (!payload) {
                    return;
                }

                try {
                    var course = JSON.parse(payload);
                    setMode('update');
                    idInput.value = course.id || '';

                    var categoryId = course.category_id || '';
                    renderCategoryOptions(categoryId);
                    renderSubcategoryOptions(categoryId, course.subcategory_id || '');

                    Object.keys(textFields).forEach(function (key) {
                        if (Object.prototype.hasOwnProperty.call(textFields, key) && textFields[key]) {
                            textFields[key].value = course[key] ? course[key] : '';
                        }
                    });

                    if (categorySelect && !categorySelect.disabled) {
                        categorySelect.focus();
                    }
                } catch (error) {
                    console.error('Não foi possível carregar os dados do curso selecionado.', error);
                }
            });
        });
    });
</script>
</body>
</html>
