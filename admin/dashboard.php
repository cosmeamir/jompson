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
    <title>Painel | Área Administrativa</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {background:#f5f7fb;}
        header {background:#052c65;color:#fff;padding:20px 0;margin-bottom:30px;}
        .card {box-shadow:0 8px 20px rgba(0,0,0,0.05);}
        textarea {min-height:140px;}
        .blog-card + .blog-card {margin-top:1.5rem;}
    </style>
</head>
<body>
<header>
    <div class="container d-flex justify-content-between align-items-center">
        <h1 class="h4 mb-0">Painel Administrativo</h1>
        <a class="btn btn-outline-light btn-sm" href="logout.php">Sair</a>
    </div>
</header>
<div class="container mb-5">
    <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>Indicadores da página inicial</strong>
                </div>
                <div class="card-body">
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
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>Novo artigo do blog</strong>
                </div>
                <div class="card-body">
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
                        <button type="submit" class="btn btn-success">Publicar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h5 mb-3">Artigos publicados</h2>
    <?php if (empty($blogs)): ?>
        <div class="alert alert-info">Ainda não existem artigos publicados.</div>
    <?php endif; ?>
    <?php foreach ($blogs as $blog): ?>
        <div class="card blog-card">
            <div class="card-body">
                <h3 class="h5"><?php echo htmlspecialchars($blog['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="mb-1 text-muted">Slug: <code><?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?></code></p>
                <p class="mb-1"><strong>Data:</strong> <?php echo htmlspecialchars($blog['date'], ENT_QUOTES, 'UTF-8'); ?> | <strong>Autor:</strong> <?php echo htmlspecialchars($blog['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty($blog['image'])): ?>
                    <div class="mb-3">
                        <strong>Imagem:</strong>
                        <div class="mt-2">
                            <img src="<?php echo htmlspecialchars(admin_asset($blog['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pré-visualização do artigo" class="img-fluid rounded" style="max-height: 220px; object-fit: cover;">
                        </div>
                    </div>
                <?php endif; ?>
                <p class="mb-3"><strong>Resumo:</strong> <?php echo nl2br(htmlspecialchars($blog['excerpt'], ENT_QUOTES, 'UTF-8')); ?></p>
                <details>
                    <summary>Ver conteúdo completo</summary>
                    <div class="mt-2 border rounded p-3 bg-light">
                        <?php echo nl2br(htmlspecialchars($blog['content'], ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </details>
                <div class="mt-3">
                    <form class="d-inline-block" method="post" action="save_blog.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slug" value="<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Eliminar este artigo?');">Eliminar</button>
                    </form>
                </div>
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#edit-<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">Editar artigo</button>
            </div>
            <div class="collapse" id="edit-<?php echo htmlspecialchars($blog['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card-body border-top">
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
        </div>
    <?php endforeach; ?>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
