<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user      = requireLogin();
$empresaId = getEmpresaId($user);
$pdo       = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = clean($_GET['action'] ?? '');

// ---------------------------------------------------------------
// GET ?action=lista — tarefas do usuário (atendente) ou todas (gerente)
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'lista') {
    $p          = getPagination();
    $filtro     = clean($_GET['filtro'] ?? ''); // hoje, atrasadas, abertas, concluidas
    $negId      = isset($_GET['negociacao_id']) ? (int)$_GET['negociacao_id'] : null;

    $where  = ['a.empresa_id = :emp'];
    $params = [':emp' => $empresaId];

    // Atendente só vê as próprias tarefas
    if ($user['cargo'] === 'atendente') {
        $where[] = 'a.responsavel_id = :uid';
        $params[':uid'] = $user['id'];
    }

    if ($negId) { $where[] = 'a.negociacao_id = :nid'; $params[':nid'] = $negId; }

    match ($filtro) {
        'hoje'      => ($where[] = 'DATE(a.data_vencimento) = CURDATE() AND a.concluida = 0'),
        'atrasadas' => ($where[] = 'a.data_vencimento < NOW() AND a.concluida = 0'),
        'abertas'   => ($where[] = 'a.concluida = 0'),
        'concluidas'=> ($where[] = 'a.concluida = 1'),
        default     => null,
    };

    $whereStr = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM atividades a WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT a.*, u.nome AS responsavel_nome,
                n.titulo AS negociacao_titulo
         FROM atividades a
         JOIN usuarios u ON u.id = a.responsavel_id
         LEFT JOIN negociacoes n ON n.id = a.negociacao_id
         WHERE $whereStr
         ORDER BY a.concluida ASC, a.data_vencimento ASC
         LIMIT :limit OFFSET :offset"
    );
    $params[':limit']  = $p['limit'];
    $params[':offset'] = $p['offset'];
    $stmt->execute($params);

    jsonResponse(['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $p['page']]);
}

// ---------------------------------------------------------------
// GET ?action=contagem — badge de tarefas atrasadas/hoje
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'contagem') {
    $extra = $user['cargo'] === 'atendente' ? ' AND a.responsavel_id = ' . (int)$user['id'] : '';

    $stmt = $pdo->prepare(
        "SELECT
           SUM(CASE WHEN a.data_vencimento < NOW() AND a.concluida = 0 THEN 1 ELSE 0 END) AS atrasadas,
           SUM(CASE WHEN DATE(a.data_vencimento) = CURDATE() AND a.concluida = 0 THEN 1 ELSE 0 END) AS hoje
         FROM atividades a
         WHERE a.empresa_id = :emp $extra"
    );
    $stmt->execute([':emp' => $empresaId]);
    jsonResponse($stmt->fetch() ?: ['atrasadas' => 0, 'hoje' => 0]);
}

// ---------------------------------------------------------------
// POST ?action=criar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'criar') {
    $body        = getJsonBody();
    $titulo      = clean($body['titulo'] ?? '');
    $desc        = clean($body['descricao'] ?? '');
    $vencimento  = clean($body['data_vencimento'] ?? '');
    $negId       = isset($body['negociacao_id']) ? (int)$body['negociacao_id'] : null;
    // Atendente só pode ser responsável de si mesmo
    $respId = $user['cargo'] === 'atendente'
        ? (int)$user['id']
        : (int)($body['responsavel_id'] ?? $user['id']);

    if (!$titulo) jsonResponse(['error' => 'Título é obrigatório.'], 400);

    // Validar negociação se informada
    if ($negId) {
        $vn = $pdo->prepare("SELECT id FROM negociacoes WHERE id = :id AND empresa_id = :emp");
        $vn->execute([':id' => $negId, ':emp' => $empresaId]);
        if (!$vn->fetch()) jsonResponse(['error' => 'Negociação inválida.'], 400);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO atividades (empresa_id, negociacao_id, responsavel_id, titulo, descricao, data_vencimento)
         VALUES (:emp, :nid, :resp, :tit, :desc, :venc)"
    );
    $stmt->execute([
        ':emp'  => $empresaId,
        ':nid'  => $negId,
        ':resp' => $respId,
        ':tit'  => $titulo,
        ':desc' => $desc ?: null,
        ':venc' => $vencimento ?: null,
    ]);
    $newId = (int)$pdo->lastInsertId();

    registrarLog($empresaId, (int)$user['id'], 'criou_tarefa', $newId, ['titulo' => $titulo]);
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ---------------------------------------------------------------
// POST ?action=concluir
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'concluir') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $va = $pdo->prepare("SELECT id FROM atividades WHERE id = :id AND empresa_id = :emp");
    $va->execute([':id' => $id, ':emp' => $empresaId]);
    if (!$va->fetch()) jsonResponse(['error' => 'Tarefa não encontrada.'], 404);

    $stmt = $pdo->prepare(
        "UPDATE atividades SET concluida = 1, data_conclusao = NOW() WHERE id = :id AND empresa_id = :emp"
    );
    $stmt->execute([':id' => $id, ':emp' => $empresaId]);

    registrarLog($empresaId, (int)$user['id'], 'concluiu_tarefa', $id);
    jsonResponse(['success' => true]);
}

// ---------------------------------------------------------------
// POST ?action=editar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $va = $pdo->prepare("SELECT id FROM atividades WHERE id = :id AND empresa_id = :emp");
    $va->execute([':id' => $id, ':emp' => $empresaId]);
    if (!$va->fetch()) jsonResponse(['error' => 'Tarefa não encontrada.'], 404);

    $campos = [];
    $params = [':id' => $id, ':emp' => $empresaId];

    if (isset($body['titulo']))          { $campos[] = 'titulo = :tit';          $params[':tit']  = clean($body['titulo']); }
    if (isset($body['descricao']))       { $campos[] = 'descricao = :desc';      $params[':desc'] = clean($body['descricao']) ?: null; }
    if (isset($body['data_vencimento'])) { $campos[] = 'data_vencimento = :venc';$params[':venc'] = clean($body['data_vencimento']) ?: null; }
    if (isset($body['responsavel_id']))  { $campos[] = 'responsavel_id = :resp'; $params[':resp'] = (int)$body['responsavel_id']; }

    if (empty($campos)) jsonResponse(['error' => 'Nenhum campo para atualizar.'], 400);

    $pdo->prepare('UPDATE atividades SET ' . implode(', ', $campos) . ' WHERE id = :id AND empresa_id = :emp')
        ->execute($params);

    registrarLog($empresaId, (int)$user['id'], 'editou_tarefa', $id);
    jsonResponse(['success' => true]);
}

// ---------------------------------------------------------------
// POST ?action=excluir
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'excluir') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $stmt = $pdo->prepare("DELETE FROM atividades WHERE id = :id AND empresa_id = :emp");
    $stmt->execute([':id' => $id, ':emp' => $empresaId]);

    registrarLog($empresaId, (int)$user['id'], 'excluiu_tarefa', $id);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
