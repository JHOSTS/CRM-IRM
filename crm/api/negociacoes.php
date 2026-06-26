<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user      = requireLogin();
$empresaId = getEmpresaId($user);
$pdo       = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = clean($_GET['action'] ?? '');

// ---------------------------------------------------------------
// GET ?action=kanban — lista negociações agrupadas por etapa
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'kanban') {
    $stmt = $pdo->prepare(
        "SELECT e.id AS etapa_id, e.nome AS etapa_nome, e.cor, e.ordem,
                n.id, n.titulo, n.valor_estimado, n.status,
                c.nome AS contato_nome,
                u.nome AS responsavel_nome,
                (SELECT COUNT(*) FROM atividades a
                 WHERE a.negociacao_id = n.id AND a.concluida = 0
                   AND a.data_vencimento < NOW()) AS tarefas_atrasadas
         FROM etapas_funil e
         LEFT JOIN negociacoes n ON n.etapa_id = e.id
           AND n.empresa_id = :emp AND n.status = 'em_andamento'
         LEFT JOIN contatos c ON c.id = n.contato_id
         LEFT JOIN usuarios u ON u.id = n.responsavel_id
         WHERE e.empresa_id = :emp2
         ORDER BY e.ordem ASC, n.data_atualizacao DESC"
    );
    $stmt->execute([':emp' => $empresaId, ':emp2' => $empresaId]);
    $rows = $stmt->fetchAll();

    $etapas = [];
    foreach ($rows as $row) {
        $eid = $row['etapa_id'];
        if (!isset($etapas[$eid])) {
            $etapas[$eid] = [
                'id'    => $eid,
                'nome'  => $row['etapa_nome'],
                'cor'   => $row['cor'],
                'ordem' => (int) $row['ordem'],
                'cards' => [],
            ];
        }
        if ($row['id']) {
            $etapas[$eid]['cards'][] = [
                'id'              => (int)$row['id'],
                'titulo'          => $row['titulo'],
                'contato_nome'    => $row['contato_nome'],
                'responsavel'     => $row['responsavel_nome'],
                'valor_estimado'  => $row['valor_estimado'] ? (float)$row['valor_estimado'] : null,
                'tarefas_atrasadas' => (int)$row['tarefas_atrasadas'],
            ];
        }
    }
    jsonResponse(['etapas' => array_values($etapas)]);
}

// ---------------------------------------------------------------
// GET ?action=lista — listagem paginada para relatórios
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'lista') {
    $p      = getPagination();
    $status = clean($_GET['status'] ?? '');
    $busca  = clean($_GET['busca'] ?? '');

    $where = ['n.empresa_id = :emp'];
    $params = [':emp' => $empresaId];

    if ($status && in_array($status, ['em_andamento','ganho','perdido'], true)) {
        $where[] = 'n.status = :status';
        $params[':status'] = $status;
    }
    if ($busca) {
        $where[] = '(n.titulo LIKE :busca OR c.nome LIKE :busca2)';
        $params[':busca']  = "%$busca%";
        $params[':busca2'] = "%$busca%";
    }

    $whereStr = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM negociacoes n JOIN contatos c ON c.id = n.contato_id WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT n.id, n.titulo, n.valor_estimado, n.status, n.data_criacao, n.data_atualizacao,
                c.nome AS contato_nome, u.nome AS responsavel_nome, e.nome AS etapa_nome
         FROM negociacoes n
         JOIN contatos c ON c.id = n.contato_id
         JOIN usuarios u ON u.id = n.responsavel_id
         JOIN etapas_funil e ON e.id = n.etapa_id
         WHERE $whereStr
         ORDER BY n.data_atualizacao DESC
         LIMIT :limit OFFSET :offset"
    );
    $params[':limit']  = $p['limit'];
    $params[':offset'] = $p['offset'];
    $stmt->execute($params);

    jsonResponse(['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $p['page'], 'limit' => $p['limit']]);
}

// ---------------------------------------------------------------
// GET ?action=detalhe&id=X
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'detalhe') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $stmt = $pdo->prepare(
        "SELECT n.*, c.nome AS contato_nome, c.telefone, c.email AS contato_email,
                u.nome AS responsavel_nome, e.nome AS etapa_nome, e.cor AS etapa_cor
         FROM negociacoes n
         JOIN contatos c ON c.id = n.contato_id
         JOIN usuarios u ON u.id = n.responsavel_id
         JOIN etapas_funil e ON e.id = n.etapa_id
         WHERE n.id = :id AND n.empresa_id = :emp"
    );
    $stmt->execute([':id' => $id, ':emp' => $empresaId]);
    $neg = $stmt->fetch();
    if (!$neg) jsonResponse(['error' => 'Negociação não encontrada.'], 404);

    // Interações
    $si = $pdo->prepare(
        "SELECT i.*, u.nome AS autor FROM interacoes i
         JOIN usuarios u ON u.id = i.usuario_id
         WHERE i.negociacao_id = :nid ORDER BY i.data_criacao DESC"
    );
    $si->execute([':nid' => $id]);
    $neg['interacoes'] = $si->fetchAll();

    // Atividades
    $sa = $pdo->prepare(
        "SELECT a.*, u.nome AS responsavel_nome FROM atividades a
         JOIN usuarios u ON u.id = a.responsavel_id
         WHERE a.negociacao_id = :nid ORDER BY a.data_vencimento ASC"
    );
    $sa->execute([':nid' => $id]);
    $neg['atividades'] = $sa->fetchAll();

    jsonResponse(['negociacao' => $neg]);
}

// ---------------------------------------------------------------
// POST ?action=criar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'criar') {
    $body = getJsonBody();
    $titulo       = clean($body['titulo'] ?? '');
    $contatoId    = (int)($body['contato_id'] ?? 0);
    $etapaId      = (int)($body['etapa_id'] ?? 0);
    $responsavelId= (int)($body['responsavel_id'] ?? $user['id']);
    $valor        = isset($body['valor_estimado']) && $body['valor_estimado'] !== '' ? (float)$body['valor_estimado'] : null;

    if (!$titulo || !$contatoId || !$etapaId) {
        jsonResponse(['error' => 'Título, contato e etapa são obrigatórios.'], 400);
    }

    // Validar que contato e etapa pertencem à empresa
    $vc = $pdo->prepare("SELECT id FROM contatos WHERE id = :id AND empresa_id = :emp");
    $vc->execute([':id' => $contatoId, ':emp' => $empresaId]);
    if (!$vc->fetch()) jsonResponse(['error' => 'Contato inválido.'], 400);

    $ve = $pdo->prepare("SELECT id FROM etapas_funil WHERE id = :id AND empresa_id = :emp");
    $ve->execute([':id' => $etapaId, ':emp' => $empresaId]);
    if (!$ve->fetch()) jsonResponse(['error' => 'Etapa inválida.'], 400);

    $stmt = $pdo->prepare(
        "INSERT INTO negociacoes (empresa_id, contato_id, etapa_id, responsavel_id, titulo, valor_estimado)
         VALUES (:emp, :con, :eta, :resp, :tit, :val)"
    );
    $stmt->execute([
        ':emp'  => $empresaId,
        ':con'  => $contatoId,
        ':eta'  => $etapaId,
        ':resp' => $responsavelId,
        ':tit'  => $titulo,
        ':val'  => $valor,
    ]);
    $newId = (int)$pdo->lastInsertId();

    registrarLog($empresaId, (int)$user['id'], 'criou_negociacao', $newId, ['titulo' => $titulo]);
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ---------------------------------------------------------------
// POST ?action=mover — drag-and-drop entre colunas
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'mover') {
    $body    = getJsonBody();
    $id      = (int)($body['id'] ?? 0);
    $etapaId = (int)($body['etapa_id'] ?? 0);

    if (!$id || !$etapaId) jsonResponse(['error' => 'Parâmetros inválidos.'], 400);

    // Verificar posse da negociação e da etapa
    $vn = $pdo->prepare("SELECT id, titulo FROM negociacoes WHERE id = :id AND empresa_id = :emp");
    $vn->execute([':id' => $id, ':emp' => $empresaId]);
    $neg = $vn->fetch();
    if (!$neg) jsonResponse(['error' => 'Negociação não encontrada.'], 404);

    $ve = $pdo->prepare("SELECT id, nome FROM etapas_funil WHERE id = :id AND empresa_id = :emp");
    $ve->execute([':id' => $etapaId, ':emp' => $empresaId]);
    $etapa = $ve->fetch();
    if (!$etapa) jsonResponse(['error' => 'Etapa inválida.'], 400);

    // Auto-definir status baseado no nome da etapa
    $nomeEtapa = mb_strtolower(trim($etapa['nome']));
    if (str_contains($nomeEtapa, 'ganho') || $nomeEtapa === 'won') {
        $novoStatus = 'ganho';
    } elseif (str_contains($nomeEtapa, 'perdido') || str_contains($nomeEtapa, 'lost')) {
        $novoStatus = 'perdido';
    } else {
        $novoStatus = 'em_andamento';
    }

    $stmt = $pdo->prepare(
        "UPDATE negociacoes SET etapa_id = :eta, status = :status WHERE id = :id AND empresa_id = :emp"
    );
    $stmt->execute([':eta' => $etapaId, ':status' => $novoStatus, ':id' => $id, ':emp' => $empresaId]);

    // Atualiza data_ultima_compra do contato quando negociação é ganha
    if ($novoStatus === 'ganho') {
        $nc = $pdo->prepare("SELECT contato_id FROM negociacoes WHERE id = :id");
        $nc->execute([':id' => $id]);
        $cid = (int)$nc->fetchColumn();
        if ($cid) {
            $pdo->prepare("UPDATE contatos SET data_ultima_compra = CURDATE() WHERE id = :id AND empresa_id = :emp")
                ->execute([':id' => $cid, ':emp' => $empresaId]);
        }
    }

    registrarLog($empresaId, (int)$user['id'], 'moveu_negociacao', $id, [
        'negociacao' => $neg['titulo'],
        'etapa'      => $etapa['nome'],
        'status'     => $novoStatus,
    ]);
    jsonResponse(['success' => true, 'status' => $novoStatus]);
}

// ---------------------------------------------------------------
// POST ?action=editar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    $body   = getJsonBody();
    $id     = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $vn = $pdo->prepare("SELECT id FROM negociacoes WHERE id = :id AND empresa_id = :emp");
    $vn->execute([':id' => $id, ':emp' => $empresaId]);
    if (!$vn->fetch()) jsonResponse(['error' => 'Negociação não encontrada.'], 404);

    $campos = [];
    $params = [':id' => $id, ':emp' => $empresaId];

    if (isset($body['titulo']))          { $campos[] = 'titulo = :tit';          $params[':tit']    = clean($body['titulo']); }
    if (isset($body['valor_estimado']))  { $campos[] = 'valor_estimado = :val';  $params[':val']    = $body['valor_estimado'] !== '' ? (float)$body['valor_estimado'] : null; }
    if (isset($body['responsavel_id']))  { $campos[] = 'responsavel_id = :resp'; $params[':resp']   = (int)$body['responsavel_id']; }
    if (isset($body['status'])) {
        if (!in_array($body['status'], ['em_andamento','ganho','perdido'], true)) jsonResponse(['error' => 'Status inválido.'], 400);
        $campos[] = 'status = :status'; $params[':status'] = $body['status'];
        if ($body['status'] === 'perdido') { $campos[] = 'motivo_perda = :mot'; $params[':mot'] = clean($body['motivo_perda'] ?? ''); }
        if ($body['status'] === 'ganho') {
            // Atualiza data_ultima_compra do contato
            $nc = $pdo->prepare("SELECT contato_id FROM negociacoes WHERE id = :id AND empresa_id = :emp");
            $nc->execute([':id' => $id, ':emp' => $empresaId]);
            $cid = (int)$nc->fetchColumn();
            if ($cid) {
                $pdo->prepare("UPDATE contatos SET data_ultima_compra = CURDATE() WHERE id = :id AND empresa_id = :emp")
                    ->execute([':id' => $cid, ':emp' => $empresaId]);
            }
        }
    }
    if (isset($body['etapa_id'])) {
        $ve = $pdo->prepare("SELECT id FROM etapas_funil WHERE id = :id AND empresa_id = :emp");
        $ve->execute([':id' => (int)$body['etapa_id'], ':emp' => $empresaId]);
        if (!$ve->fetch()) jsonResponse(['error' => 'Etapa inválida.'], 400);
        $campos[] = 'etapa_id = :eta'; $params[':eta'] = (int)$body['etapa_id'];
    }

    if (empty($campos)) jsonResponse(['error' => 'Nenhum campo para atualizar.'], 400);

    $sql = 'UPDATE negociacoes SET ' . implode(', ', $campos) . ' WHERE id = :id AND empresa_id = :emp';
    $pdo->prepare($sql)->execute($params);

    registrarLog($empresaId, (int)$user['id'], 'editou_negociacao', $id);
    jsonResponse(['success' => true]);
}

// ---------------------------------------------------------------
// POST ?action=excluir
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'excluir') {
    requireCargo($user, ['gerente']);
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $stmt = $pdo->prepare("DELETE FROM negociacoes WHERE id = :id AND empresa_id = :emp");
    $stmt->execute([':id' => $id, ':emp' => $empresaId]);

    registrarLog($empresaId, (int)$user['id'], 'excluiu_negociacao', $id);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
