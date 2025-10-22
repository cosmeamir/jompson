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
            min-height: 100vh;
        }

        .dashboard-sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0f172a 0%, #1d4ed8 100%);
            color: #f8fbff;
            padding: 32px 26px 40px;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            align-self: flex-start;
            min-height: 100vh;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            margin-bottom: 48px;
        }

        .sidebar-brand .brand-logo {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.15);
            font-size: 20px;
            margin-right: 12px;
        }

        .sidebar-brand strong {
            font-size: 18px;
            letter-spacing: 0.02em;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
            flex: 1;
        }

        .sidebar-nav li {
            margin-bottom: 8px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            text-decoration: none;
            color: inherit;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            font-size: 18px;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.16);
            transform: translateX(4px);
        }

        .sidebar-footer {
            margin-top: 40px;
            padding: 16px;
            background: rgba(15, 23, 42, 0.35);
            border-radius: 14px;
            font-size: 13px;
            line-height: 1.5;
        }

        .dashboard-main {
            flex: 1;
            padding: 42px clamp(20px, 5vw, 56px);
        }

        .dashboard-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            gap: 16px;
        }

        .dashboard-header h1 {
            font-size: clamp(24px, 3vw, 32px);
            font-weight: 600;
            margin: 0;
        }

        .user-chip {
            display: inline-flex;
            align-items: center;
            background: var(--surface);
            padding: 10px 16px;
            border-radius: 999px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            font-weight: 500;
            gap: 12px;
        }

        .user-chip i {
            color: var(--primary);
            font-size: 18px;
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
            .dashboard-shell {
                flex-direction: column;
            }

            .dashboard-sidebar {
                width: 100%;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
                padding: 20px clamp(18px, 5vw, 28px);
                border-bottom-left-radius: 24px;
                border-bottom-right-radius: 24px;
                min-height: auto;
                position: sticky;
                top: 0;
                z-index: 1020;
            }

            .sidebar-brand {
                margin-bottom: 0;
            }

            .sidebar-nav {
                display: flex;
                gap: 8px;
                margin-left: 20px;
            }

            .sidebar-nav li {
                margin-bottom: 0;
            }

            .sidebar-footer {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .dashboard-main {
                padding: 28px 18px 48px;
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
    <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
            <span class="brand-logo"><i class="bi bi-lightning-charge"></i></span>
            <div>
                <strong>Jompson Admin</strong><br>
                <small>Painel de controlo</small>
            </div>
        </div>
        <ul class="sidebar-nav">
            <li><a class="active" href="dashboard.php"><i class="bi bi-speedometer2"></i>Dashboard</a></li>
            <li><a href="../index.html" target="_blank"><i class="bi bi-globe2"></i>Ver site</a></li>
            <li><a href="#indicadores"><i class="bi bi-bar-chart"></i>Indicadores</a></li>
            <li><a href="#courses"><i class="bi bi-mortarboard"></i>Cursos</a></li>
            <li><a href="#inscricoes"><i class="bi bi-people"></i>Inscrições</a></li>
            <li><a href="#blogs"><i class="bi bi-journal-text"></i>Blog</a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="d-flex align-items-center mb-2"><i class="bi bi-shield-lock mr-2"></i> Sessão segura</div>
            <a class="btn btn-outline-light btn-sm" href="logout.php">Terminar sessão</a>
        </div>
    </aside>
    <main class="dashboard-main">
        <div class="dashboard-header">
            <div>
                <h1>Bem-vindo de volta 👋</h1>
                <div class="text-muted">Acompanhe métricas, publique artigos e mantenha o site atualizado.</div>
            </div>
            <div class="user-chip">
                <i class="bi bi-person-circle"></i>
                <span>Administrador</span>
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

        <div id="indicadores" class="content-section">
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
        </div>

        <section id="courses" class="content-section">
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
                            <input type="text" class="form-control" id="course-category" name="category" placeholder="Formação Executiva" required>
                        </div>
                        <div class="form-group">
                            <label for="course-subcategory">Subcategoria</label>
                            <input type="text" class="form-control" id="course-subcategory" name="subcategory" placeholder="Finanças &amp; Contabilidade" required>
                        </div>
                        <div class="form-group">
                            <label for="course-title">Título do curso</label>
                            <input type="text" class="form-control" id="course-title" name="title" placeholder="Elaboração e Técnicas de Negociação de Contratos" required>
                        </div>
                        <div class="form-group">
                            <label for="course-headline">Chamada curta</label>
                            <input type="text" class="form-control" id="course-headline" name="headline" placeholder="Domine a negociação de contratos empresariais.">
                            <small class="form-text text-muted">Um resumo breve que aparece junto ao título do curso.</small>
                        </div>
                        <div class="form-group">
                            <label for="course-overview">Apresentação</label>
                            <textarea class="form-control" id="course-overview" name="overview" rows="3" placeholder="Descreve o contexto, público-alvo e resultados esperados."></textarea>
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
        </section>

        <section id="inscricoes" class="content-section">
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
                                            <span class="registration-meta">Forma de pagamento: <?php echo htmlspecialchars($registration['forma_pagamento'], ENT_QUOTES, 'UTF-8'); ?></span>
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

        <h2 id="blogs" class="section-title">Artigos publicados</h2>
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
    </main>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
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

        var fieldMap = {
            category: document.getElementById('course-category'),
            subcategory: document.getElementById('course-subcategory'),
            title: document.getElementById('course-title'),
            headline: document.getElementById('course-headline'),
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
                submitButton.textContent = 'Actualizar curso';
                helper.textContent = 'Edita a informação do curso seleccionado e guarda para actualizar no site.';
                title.textContent = 'Editar curso';
            } else {
                modeInput.value = 'create';
                submitButton.textContent = 'Guardar curso';
                helper.textContent = 'Preenche os campos para adicionar um novo curso ao catálogo.';
                title.textContent = 'Adicionar curso';
                idInput.value = '';
            }
        }

        setMode(modeInput.value || 'create');

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                courseForm.reset();
                setMode('create');
                if (fieldMap.category) {
                    fieldMap.category.focus();
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
                    Object.keys(fieldMap).forEach(function (key) {
                        if (Object.prototype.hasOwnProperty.call(fieldMap, key) && fieldMap[key]) {
                            fieldMap[key].value = course[key] ? course[key] : '';
                        }
                    });
                    if (fieldMap.category) {
                        fieldMap.category.focus();
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
