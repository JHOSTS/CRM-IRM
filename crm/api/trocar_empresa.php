<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();

if ($user['cargo'] !== 'master') {
    jsonResponse(['error' => 'Acesso restrito ao master.'], 403);
}

requireMethod('POST');

$body      = getJsonBody();
$empresaId = (int)($body['empresa_id'] ?? 0);

if (!$empresaId) {
    jsonResponse(['error' => 'empresa_id inválido.'], 400);
}

// Confirmar que a empresa existe
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id, nome, cor_primaria, cor_secundaria, logo FROM empresas WHERE id = :id AND status = 'ativo'");
$stmt->execute([':id' => $empresaId]);
$empresa = $stmt->fetch();

if (!$empresa) {
    jsonResponse(['error' => 'Empresa não encontrada ou inativa.'], 404);
}

// Salvar empresa ativa na sessão do master
$_SESSION['active_empresa_id'] = $empresaId;

jsonResponse([
    'success' => true,
    'empresa' => [
        'id'             => (int)$empresa['id'],
        'nome'           => $empresa['nome'],
        'cor_primaria'   => $empresa['cor_primaria'],
        'cor_secundaria' => $empresa['cor_secundaria'],
        'logo'           => $empresa['logo'],
    ],
]);
