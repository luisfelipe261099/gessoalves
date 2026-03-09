<?php
// ============================================================
//  GESSO ALVES — Painel Admin do Portfólio
//  Acesse: http://localhost/d/admin.php
//  SENHA PADRÃO: gessoalves2026  ← ALTERE ABAIXO
// ============================================================
define('ADMIN_PASSWORD', 'gessoalves2026');
define('UPLOAD_DIR',     __DIR__ . '/uploads/portfolio/');
define('UPLOAD_URL',     'uploads/portfolio/');
define('MAX_SIZE',       8 * 1024 * 1024); // 8 MB
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

session_start();

// ── CSRF helpers ──────────────────────────────────────────────
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}
function csrf_verify(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

// ── Actions ───────────────────────────────────────────────────
$error   = '';
$success = '';

// LOGIN
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (hash_equals(ADMIN_PASSWORD, $_POST['password'] ?? '')) {
        $_SESSION['admin'] = true;
        session_regenerate_id(true);
    } else {
        $error = 'Senha incorreta.';
    }
}

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

$logged = !empty($_SESSION['admin']);

if ($logged) {
    // Create upload dir if needed
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
        // Prevent PHP execution inside uploads folder
        file_put_contents(UPLOAD_DIR . '.htaccess',
            "php_flag engine off\nOptions -ExecCGI\nAddHandler default-handler .php\n");
    }

    // ── Titles helpers ─────────────────────────────────────────
    $titles_file = UPLOAD_DIR . 'titles.json';
    function load_titles(string $f): array {
        if (!is_file($f)) return [];
        $data = json_decode(file_get_contents($f), true);
        return is_array($data) ? $data : [];
    }
    function save_titles(string $f, array $t): void {
        file_put_contents($f, json_encode($t, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    // UPLOAD
    if (isset($_POST['action']) && $_POST['action'] === 'upload' && csrf_verify()) {
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $file  = $_FILES['foto'];
            $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);

            if (!in_array($mime, $ALLOWED_MIME, true)) {
                $error = 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WEBP.';
            } elseif (!in_array($ext, $ALLOWED_EXT, true)) {
                $error = 'Extensão não permitida.';
            } elseif ($file['size'] > MAX_SIZE) {
                $error = 'Arquivo muito grande. Máximo 8 MB.';
            } elseif (!@getimagesize($file['tmp_name'])) {
                $error = 'O arquivo não é uma imagem válida.';
            } else {
                $safeName = preg_replace('/[^a-zA-Z0-9\-_]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
                $safeName = substr($safeName, 0, 60);
                $fname    = uniqid('p_') . '_' . $safeName . '.' . $ext;
                $dest     = UPLOAD_DIR . $fname;
                move_uploaded_file($file['tmp_name'], $dest);
                // Save title
                $title  = trim(strip_tags($_POST['titulo'] ?? ''));
                $titles = load_titles($titles_file);
                $titles[$fname] = $title !== '' ? $title : $safeName;
                save_titles($titles_file, $titles);
                $success = 'Foto adicionada com sucesso!';
            }
        } else {
            $error = 'Erro ao receber o arquivo. Tente novamente.';
        }
    }

    // DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && csrf_verify()) {
        $fname    = basename($_POST['file'] ?? '');
        $fullpath = UPLOAD_DIR . $fname;
        if ($fname && is_file($fullpath) && strpos(realpath($fullpath), realpath(UPLOAD_DIR)) === 0) {
            unlink($fullpath);
            $titles = load_titles($titles_file);
            unset($titles[$fname]);
            save_titles($titles_file, $titles);
            $success = 'Foto removida.';
        } else {
            $error = 'Arquivo não encontrado.';
        }
    }

    // Load images
    $titles = load_titles($titles_file);
    $images = [];
    if (is_dir(UPLOAD_DIR)) {
        $files = @scandir(UPLOAD_DIR);
        if ($files) {
            sort($files);
            foreach ($files as $f) {
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, $ALLOWED_EXT, true)) continue;
                $fp = UPLOAD_DIR . $f;
                if (!@getimagesize($fp)) continue;
                $images[] = ['file' => $f, 'title' => $titles[$f] ?? $f];
            }
        }
    }
}

$csrf = csrf_token();
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin — Portfólio | Gesso Alves</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --green:  #1E4230;
    --gold:   #C9A56B;
    --beige:  #EDE8DC;
    --light:  #FFFFFF;
    --muted:  #6B7A6E;
    --border: #D6CFBF;
    --danger: #c0392b;
  }
  body { font-family: 'Montserrat', sans-serif; background: var(--beige); color: var(--green); min-height: 100vh; }

  /* ── Top bar ── */
  .topbar { background: var(--green); padding: 14px 32px; display: flex; align-items: center; justify-content: space-between; }
  .topbar h1 { color: var(--beige); font-size: 1.1rem; font-weight: 700; letter-spacing: .3px; }
  .topbar h1 span { color: var(--gold); }
  .topbar a { color: var(--beige); font-size: .85rem; text-decoration: none; border: 1px solid rgba(255,255,255,.3); padding: 6px 14px; border-radius: 6px; transition: background .2s; }
  .topbar a:hover { background: rgba(255,255,255,.1); }

  /* ── Login ── */
  .login-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 56px); padding: 32px; }
  .login-card { background: var(--light); border-radius: 16px; padding: 48px 40px; width: 100%; max-width: 380px; box-shadow: 0 8px 40px rgba(30,66,48,.12); text-align: center; }
  .login-card h2 { font-size: 1.4rem; margin-bottom: 6px; }
  .login-card p  { color: var(--muted); font-size: .88rem; margin-bottom: 28px; }
  .field { text-align: left; margin-bottom: 18px; }
  .field label { display: block; font-size: .82rem; font-weight: 700; margin-bottom: 6px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; }
  .field input { width: 100%; border: 1.5px solid var(--border); border-radius: 8px; padding: 11px 14px; font-family: inherit; font-size: .95rem; color: var(--green); background: var(--beige); transition: border-color .2s; }
  .field input:focus { outline: none; border-color: var(--green); }
  .btn { width: 100%; padding: 13px; background: var(--green); color: var(--beige); font-family: inherit; font-size: .95rem; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; letter-spacing: .4px; transition: background .2s; }
  .btn:hover { background: #163320; }
  .btn-gold { background: var(--gold); color: var(--green); }
  .btn-gold:hover { background: #b8935a; }

  /* ── Alerts ── */
  .alert { padding: 12px 16px; border-radius: 8px; font-size: .9rem; font-weight: 600; margin-bottom: 20px; }
  .alert-err  { background: #fdecea; color: var(--danger); border-left: 4px solid var(--danger); }
  .alert-ok   { background: #e6f4ec; color: #1a7a3f; border-left: 4px solid #1a7a3f; }

  /* ── Main ── */
  .main { max-width: 1100px; margin: 0 auto; padding: 36px 24px 60px; }
  .page-title { font-size: 1.6rem; font-weight: 900; margin-bottom: 6px; }
  .page-title span { color: var(--gold); }
  .page-sub { color: var(--muted); font-size: .9rem; margin-bottom: 36px; }

  /* ── Upload card ── */
  .upload-card { background: var(--light); border-radius: 14px; padding: 28px 32px; margin-bottom: 40px; box-shadow: 0 2px 16px rgba(30,66,48,.07); }
  .upload-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
  .upload-card h3::before { content: ''; display: block; width: 4px; height: 18px; background: var(--gold); border-radius: 4px; }
  .upload-row { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; }
  .upload-row .field { flex: 1; min-width: 220px; margin: 0; }
  .file-label { display: flex; align-items: center; gap: 10px; border: 2px dashed var(--border); border-radius: 8px; padding: 14px 18px; cursor: pointer; font-size: .88rem; color: var(--muted); background: var(--beige); transition: border-color .2s; }
  .file-label:hover { border-color: var(--green); color: var(--green); }
  .file-label input { display: none; }
  #file-name { font-size: .82rem; font-style: italic; margin-top: 6px; color: var(--muted); }
  .upload-row .btn { width: auto; padding: 13px 28px; flex-shrink: 0; }

  /* ── Grid ── */
  .grid-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
  .grid-header h3 { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
  .grid-header h3::before { content: ''; display: block; width: 4px; height: 18px; background: var(--gold); border-radius: 4px; }
  .count-badge { background: var(--green); color: var(--beige); font-size: .78rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
  .photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
  .photo-card { background: var(--light); border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(30,66,48,.08); position: relative; }
  .photo-card img { width: 100%; height: 170px; object-fit: cover; display: block; }
  .photo-card .photo-name { font-size: .75rem; color: var(--muted); padding: 8px 12px 4px; word-break: break-all; }
  .photo-card form { padding: 0 10px 10px; }
  .btn-del { width: 100%; padding: 8px; background: #fdecea; color: var(--danger); border: 1.5px solid #f5c6c6; font-family: inherit; font-size: .82rem; font-weight: 700; border-radius: 6px; cursor: pointer; transition: background .2s; }
  .btn-del:hover { background: var(--danger); color: #fff; }

  .empty { text-align: center; padding: 60px 20px; color: var(--muted); font-size: .95rem; background: var(--light); border-radius: 14px; }
  .empty strong { display: block; font-size: 1.1rem; color: var(--green); margin-bottom: 8px; }
</style>
</head>
<body>

<div class="topbar">
  <h1>Gesso <span>Alves</span> — Painel Admin</h1>
  <?php if ($logged): ?>
  <a href="?logout=1">Sair</a>
  <?php endif; ?>
</div>

<?php if (!$logged): ?>
<!-- ── LOGIN ── -->
<div class="login-wrap">
  <div class="login-card">
    <h2>Área Restrita</h2>
    <p>Digite a senha de acesso ao painel.</p>
    <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <div class="field">
        <label for="pwd">Senha</label>
        <input type="password" id="pwd" name="password" autofocus autocomplete="current-password" required>
      </div>
      <button class="btn btn-gold" type="submit">Entrar</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── PAINEL ── -->
<div class="main">
  <div class="page-title">Gerenciar <span>Portfólio</span></div>
  <p class="page-sub">Adicione ou remova fotos que aparecem na seção de portfólio do site.</p>

  <?php if ($error):   ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>

  <!-- Upload -->
  <div class="upload-card">
    <h3>Adicionar Nova Foto</h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="csrf"   value="<?= htmlspecialchars($csrf) ?>">
      <div class="upload-row">
        <div class="field">
          <label class="file-label" for="foto">
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Clique para selecionar imagem (JPG, PNG, WEBP — máx 8MB)
            <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/gif,image/webp" required>
          </label>
          <div id="file-name">Nenhum arquivo selecionado</div>
        </div>
        <div class="field" style="min-width:220px;margin:0;">
          <label style="display:block;font-size:.82rem;font-weight:700;margin-bottom:6px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Título da Foto</label>
          <input type="text" name="titulo" placeholder="Ex: Sanca com LED — Sala de Estar" maxlength="80"
            style="width:100%;border:1.5px solid var(--border);border-radius:8px;padding:11px 14px;font-family:inherit;font-size:.95rem;color:var(--green);background:var(--beige);">
        </div>
        <button type="submit" class="btn btn-gold">Enviar Foto</button>
      </div>
    </form>
  </div>

  <!-- Photos -->
  <div class="grid-header">
    <h3>Fotos no Portfólio</h3>
    <span class="count-badge"><?= count($images) ?> foto<?= count($images) !== 1 ? 's' : '' ?></span>
  </div>

  <?php if (empty($images)): ?>
  <div class="empty">
    <strong>Nenhuma foto ainda</strong>
    Adicione fotos usando o formulário acima. Elas aparecerão automaticamente no portfólio do site.
  </div>
  <?php else: ?>
  <div class="photo-grid">
    <?php foreach ($images as $item): ?>
    <div class="photo-card">
      <img src="<?= htmlspecialchars(UPLOAD_URL . rawurlencode($item['file'])) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy">
      <div class="photo-name"><?= htmlspecialchars($item['title']) ?></div>
      <form method="POST" onsubmit="return confirm('Remover esta foto do portfólio?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="csrf"   value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="file"   value="<?= htmlspecialchars($item['file']) ?>">
        <button type="submit" class="btn-del">🗑 Remover</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
<?php endif; ?>

<script>
  document.getElementById('foto')?.addEventListener('change', function() {
    const label = document.getElementById('file-name');
    label.textContent = this.files[0] ? this.files[0].name : 'Nenhum arquivo selecionado';
  });
</script>
</body>
</html>
