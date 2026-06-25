<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user      = requireLogin();
$empresaId = getEmpresaId($user);
$pdo       = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = clean($_GET['action'] ?? '');

// ---------------------------------------------------------------
// GET ?action=lista
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'lista') {
    $stmt = $pdo->prepare(
        "SELECT * FROM etapas_funil WHERE empresa_id = :emp ORDER BY ordem ASC"
    );
    $stmt->execute([':emp' => $empresaId]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ---------------------------------------------------------------
// POST ?action=criar  (somente gerente)
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'criar') {
    requireCargo($user, ['gerente']);
    $body  = getJsonBody();
    $nome  = clean($body['nome'] ?? '');
    $cor   = clean($body['cor'] ?? '#6c757d');
    $ordem = (int)($body['ordem'] ?? 99);

    if (!$nome) jsonResponse(['error' => 'Nome é obrigatório.'], 400);

    $stmt = $pdo->prepare(
        "INSERT INTO etapas_funil (empresa_id, nome, ordem, cor) VALUES (:emp, :nome, :ord, :cor)"
    );
    $stmt->execute([':emp' => $empresaId, ':nome' => $nome, ':ord' => $ordem, ':cor' => $cor]);
    jsonResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

// ---------------------------------------------------------------
// POST ?action=editar  (somente gerente)
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    requireCargo($user, ['gerente']);
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $ve = $pdo->prepare("SELECT id FROM etapas_funil WHERE id = :id AND empresa_id = :emp");
    $ve->execute([':id' => $id, ':emp' => $empresaId]);
    if (!$ve->fetch()) jsonResponse(['error' => 'Etapa não encontrada.'], 404);

    $campos = [];
    $params = [':id' => $id, ':emp' => $empresaId];
    if (isset($body['nome']))  { $campos[] = 'nome = :nome';   $params[':nome']  = clean($body['nome']); }
    if (isset($body['cor']))   { $campos[] = 'cor = :cor';     $params[':cor']   = clean($body['cor']); }
    if (isset($body['ordem'])) { $campos[] = 'ordem = :ord';   $params[':ord']   = (int)$body['ordem']; }

    if (empty($campos)) jsonResponse(['error' => 'Nenhum campo para atualizar.'], 400);
    $pdo->prepare('UPDATE etapas_funil SET ' . implode(', ', $campos) . ' WHERE id = :id AND empresa_id = :emp')
        ->execute($params);
    jsonResponse(['success' => true]);
}

// ---------------------------------------------------------------
// POST ?action=excluir  (somente gerente)
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'excluir') {
    requireCargo($user, ['gerente']);
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    // Impedir exclusão se houver negociações vinculadas
    $cn = $pdo->prepare("SELECT COUNT(*) FROM negociacoes WHERE etapa_id = :id");
    $cn->execute([':id' => $id]);
    if ((int)$cn->fetchColumn() > 0) {
        jsonResponse(['error' => 'Não é possível excluir uma etapa com negociações vinculadas.'], 409);
    }

    $pdo->prepare("DELETE FROM etapas_funil WHERE id = :id AND empresa_id = :emp")
        ->execute([':id' => $id, ':emp' => $empresaId]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
