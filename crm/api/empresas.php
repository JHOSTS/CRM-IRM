<?php
// Endpoint exclusivo do usuário master para gerenciar empresas-clientes
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();
if ($user['cargo'] !== 'master') {
    jsonResponse(['error' => 'Acesso restrito ao administrador master.'], 403);
}

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = clean($_GET['action'] ?? '');

// ---------------------------------------------------------------
// GET ?action=lista
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'lista') {
    $stmt = $pdo->prepare(
        "SELECT e.id, e.nome, e.logo, e.cor_primaria, e.cor_secundaria, e.status, e.data_criacao,
                COUNT(u.id) AS total_usuarios
         FROM empresas e
         LEFT JOIN usuarios u ON u.empresa_id = e.id
         GROUP BY e.id ORDER BY e.nome ASC"
    );
    $stmt->execute();
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ---------------------------------------------------------------
// POST ?action=criar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'criar') {
    $body    = getJsonBody();
    $nome    = clean($body['nome'] ?? '');
    $corPrim = clean($body['cor_primaria']   ?? '#4361ee');
    $corSec  = clean($body['cor_secundaria'] ?? '#1a1d27');
    if (!$nome) jsonResponse(['error' => 'Nome é obrigatório.'], 400);

    $stmt = $pdo->prepare("INSERT INTO empresas (nome, cor_primaria, cor_secundaria) VALUES (:nome, :cprim, :csec)");
    $stmt->execute([':nome' => $nome, ':cprim' => $corPrim, ':csec' => $corSec]);
    $empresaId = (int)$pdo->lastInsertId();

    // Criar etapas padrão para a nova empresa
    $etapasPadrao = [
        ['Novo Lead',         1, '#4361ee'],
        ['Contato Feito',     2, '#7209b7'],
        ['Proposta Enviada',  3, '#f48c06'],
        ['Em Negociação',    4, '#3a86ff'],
        ['Ganho',             5, '#2dc653'],
        ['Perdido',           6, '#ef233c'],
    ];
    $se = $pdo->prepare("INSERT INTO etapas_funil (empresa_id, nome, ordem, cor) VALUES (:emp, :nome, :ord, :cor)");
    foreach ($etapasPadrao as [$n, $o, $c]) {
        $se->execute([':emp' => $empresaId, ':nome' => $n, ':ord' => $o, ':cor' => $c]);
    }

    registrarLog(1, (int)$user['id'], 'criou_empresa', $empresaId, ['nome' => $nome]);
    jsonResponse(['success' => true, 'id' => $empresaId], 201);
}

// ---------------------------------------------------------------
// POST ?action=editar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $campos = [];
    $params = [':id' => $id];
    if (isset($body['nome']))          { $campos[] = 'nome = :nome';           $params[':nome']    = clean($body['nome']); }
    if (isset($body['cor_primaria']))   { $campos[] = 'cor_primaria = :cprim';  $params[':cprim']   = clean($body['cor_primaria']); }
    if (isset($body['cor_secundaria'])) { $campos[] = 'cor_secundaria = :csec'; $params[':csec']    = clean($body['cor_secundaria']); }
    if (isset($body['status'])) {
        if (!in_array($body['status'], ['ativo','inativo'], true)) jsonResponse(['error' => 'Status inválido.'], 400);
        $campos[] = 'status = :status'; $params[':status'] = $body['status'];
    }

    if (empty($campos)) jsonResponse(['error' => 'Nenhum campo para atualizar.'], 400);
    $pdo->prepare('UPDATE empresas SET ' . implode(', ', $campos) . ' WHERE id = :id')->execute($params);

    registrarLog(1, (int)$user['id'], 'editou_empresa', $id);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
