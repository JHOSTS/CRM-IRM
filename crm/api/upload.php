<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();
requireCargo($user, ['gerente']);

requireMethod('POST');

$tipo = clean($_GET['tipo'] ?? '');

if ($tipo === 'logo') {
    if (empty($_FILES['logo'])) {
        jsonResponse(['error' => 'Nenhum arquivo enviado.'], 400);
    }

    $file    = $_FILES['logo'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Erro no upload do arquivo.'], 400);
    }

    if ($file['size'] > $maxSize) {
        jsonResponse(['error' => 'Arquivo muito grande. Máximo 2MB.'], 400);
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        jsonResponse(['error' => 'Formato inválido. Use JPG, PNG, WebP, GIF ou SVG.'], 400);
    }

    $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
    $dir     = __DIR__ . '/../assets/img/logos/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Master pode fazer upload para qualquer empresa
    $empresaId = ($user['cargo'] === 'master' && !empty($_GET['empresa_id']))
        ? (int)$_GET['empresa_id']
        : getEmpresaId($user);
    $pdo = getDB();
    $old = $pdo->prepare("SELECT logo FROM empresas WHERE id = :id");
    $old->execute([':id' => $empresaId]);
    $oldLogo = $old->fetchColumn();
    if ($oldLogo && file_exists(__DIR__ . '/../' . $oldLogo)) {
        unlink(__DIR__ . '/../' . $oldLogo);
    }

    $filename = 'empresa_' . $empresaId . '_' . time() . '.' . $ext;
    $dest     = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(['error' => 'Falha ao salvar o arquivo.'], 500);
    }

    $logoPath = 'assets/img/logos/' . $filename;
    $stmt = $pdo->prepare("UPDATE empresas SET logo = :logo WHERE id = :id");
    $stmt->execute([':logo' => $logoPath, ':id' => $empresaId]);

    registrarLog($empresaId, (int)$user['id'], 'atualizou_logo', $empresaId);
    jsonResponse(['success' => true, 'logo' => $logoPath]);
}

jsonResponse(['error' => 'Tipo de upload não reconhecido.'], 400);
