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
    $p           = getPagination();
    $busca       = clean($_GET['busca'] ?? '');
    $filtroOrig  = clean($_GET['origem'] ?? '');
    $filtroIni   = clean($_GET['data_ini'] ?? '');
    $filtroFim   = clean($_GET['data_fim'] ?? '');

    $where  = ['c.empresa_id = :emp'];
    $params = [':emp' => $empresaId];

    if ($busca) {
        $where[] = '(c.nome LIKE :b OR c.email LIKE :b2 OR c.telefone LIKE :b3)';
        $params[':b']  = "%$busca%";
        $params[':b2'] = "%$busca%";
        $params[':b3'] = "%$busca%";
    }
    if ($filtroOrig) {
        $where[] = 'c.origem = :orig';
        $params[':orig'] = $filtroOrig;
    }
    if ($filtroIni) {
        $where[] = 'DATE(c.data_entrada) >= :dini';
        $params[':dini'] = $filtroIni;
    }
    if ($filtroFim) {
        $where[] = 'DATE(c.data_entrada) <= :dfim';
        $params[':dfim'] = $filtroFim;
    }
    $whereStr = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM contatos c WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Origens disponíveis para filtro
    $origStmt = $pdo->prepare("SELECT DISTINCT origem FROM contatos WHERE empresa_id = :emp AND origem IS NOT NULL ORDER BY origem ASC");
    $origStmt->execute([':emp' => $empresaId]);
    $origens = $origStmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare(
        "SELECT c.id, c.nome, c.telefone, c.email, c.origem, c.data_criacao,
                c.data_entrada, c.data_nascimento, c.data_ultima_compra,
                u.nome AS criado_por_nome,
                (SELECT COUNT(*) FROM negociacoes n WHERE n.contato_id = c.id) AS total_negociacoes
         FROM contatos c
         JOIN usuarios u ON u.id = c.criado_por
         WHERE $whereStr
         ORDER BY c.nome ASC
         LIMIT :limit OFFSET :offset"
    );
    $params[':limit']  = $p['limit'];
    $params[':offset'] = $p['offset'];
    $stmt->execute($params);

    jsonResponse(['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $p['page'], 'limit' => $p['limit'], 'origens' => $origens]);
}

// ---------------------------------------------------------------
// GET ?action=detalhe&id=X
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'detalhe') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $stmt = $pdo->prepare(
        "SELECT c.*, u.nome AS criado_por_nome
         FROM contatos c
         JOIN usuarios u ON u.id = c.criado_por
         WHERE c.id = :id AND c.empresa_id = :emp"
    );
    $stmt->execute([':id' => $id, ':emp' => $empresaId]);
    $contato = $stmt->fetch();
    if (!$contato) jsonResponse(['error' => 'Contato não encontrado.'], 404);

    // Histórico de interações
    $si = $pdo->prepare(
        "SELECT i.*, u.nome AS autor
         FROM interacoes i
         JOIN usuarios u ON u.id = i.usuario_id
         WHERE i.contato_id = :cid
         ORDER BY i.data_criacao DESC"
    );
    $si->execute([':cid' => $id]);
    $contato['interacoes'] = $si->fetchAll();

    // Negociações vinculadas
    $sn = $pdo->prepare(
        "SELECT n.id, n.titulo, n.valor_estimado, n.status, e.nome AS etapa_nome, e.cor
         FROM negociacoes n
         JOIN etapas_funil e ON e.id = n.etapa_id
         WHERE n.contato_id = :cid AND n.empresa_id = :emp
         ORDER BY n.data_atualizacao DESC"
    );
    $sn->execute([':cid' => $id, ':emp' => $empresaId]);
    $contato['negociacoes'] = $sn->fetchAll();

    jsonResponse(['contato' => $contato]);
}

// ---------------------------------------------------------------
// POST ?action=criar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'criar') {
    $body             = getJsonBody();
    $nome             = clean($body['nome'] ?? '');
    $tel              = clean($body['telefone'] ?? '');
    $email            = clean($body['email'] ?? '');
    $origem           = clean($body['origem'] ?? '');
    $dataNasc         = clean($body['data_nascimento'] ?? '');
    $dataEntrada      = clean($body['data_entrada'] ?? '');
    $dataUltCompra    = clean($body['data_ultima_compra'] ?? '');

    if (!$nome) jsonResponse(['error' => 'Nome é obrigatório.'], 400);
    if (!$tel)  jsonResponse(['error' => 'Telefone é obrigatório.'], 400);

    // data_entrada: manual ou NOW()
    $entradaVal = $dataEntrada ?: null;

    $stmt = $pdo->prepare(
        "INSERT INTO contatos
           (empresa_id, nome, telefone, email, origem, criado_por, data_nascimento, data_entrada, data_ultima_compra)
         VALUES
           (:emp, :nome, :tel, :email, :orig, :usr, :nasc, COALESCE(:ent, NOW()), :ulc)"
    );
    $stmt->execute([
        ':emp'   => $empresaId,
        ':nome'  => $nome,
        ':tel'   => $tel ?: null,
        ':email' => $email ?: null,
        ':orig'  => $origem ?: null,
        ':usr'   => $user['id'],
        ':nasc'  => $dataNasc ?: null,
        ':ent'   => $entradaVal,
        ':ulc'   => $dataUltCompra ?: null,
    ]);
    $newId = (int)$pdo->lastInsertId();

    registrarLog($empresaId, (int)$user['id'], 'criou_contato', $newId, ['nome' => $nome]);
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ---------------------------------------------------------------
// POST ?action=editar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    $vc = $pdo->prepare("SELECT id FROM contatos WHERE id = :id AND empresa_id = :emp");
    $vc->execute([':id' => $id, ':emp' => $empresaId]);
    if (!$vc->fetch()) jsonResponse(['error' => 'Contato não encontrado.'], 404);

    $campos = [];
    $params = [':id' => $id, ':emp' => $empresaId];

    if (isset($body['nome']))              { $campos[] = 'nome = :nome';               $params[':nome']  = clean($body['nome']); }
    if (isset($body['telefone']))          { $campos[] = 'telefone = :tel';             $params[':tel']   = clean($body['telefone']) ?: null; }
    if (isset($body['email']))             { $campos[] = 'email = :email';              $params[':email'] = clean($body['email']) ?: null; }
    if (isset($body['origem']))            { $campos[] = 'origem = :orig';              $params[':orig']  = clean($body['origem']) ?: null; }
    if (array_key_exists('data_nascimento', $body))    { $campos[] = 'data_nascimento = :nasc';   $params[':nasc']  = clean($body['data_nascimento']) ?: null; }
    if (array_key_exists('data_entrada', $body))       { $campos[] = 'data_entrada = :ent';       $params[':ent']   = clean($body['data_entrada']) ?: null; }
    if (array_key_exists('data_ultima_compra', $body)) { $campos[] = 'data_ultima_compra = :ulc'; $params[':ulc']   = clean($body['data_ultima_compra']) ?: null; }

    if (empty($campos)) jsonResponse(['error' => 'Nenhum campo para atualizar.'], 400);

    $pdo->prepare('UPDATE contatos SET ' . implode(', ', $campos) . ' WHERE id = :id AND empresa_id = :emp')
        ->execute($params);

    registrarLog($empresaId, (int)$user['id'], 'editou_contato', $id);
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

    $stmt = $pdo->prepare("DELETE FROM contatos WHERE id = :id AND empresa_id = :emp");
    $stmt->execute([':id' => $id, ':emp' => $empresaId]);

    registrarLog($empresaId, (int)$user['id'], 'excluiu_contato', $id);
    jsonResponse(['success' => true]);
}

// ---------------------------------------------------------------
// POST ?action=interacao — adicionar interação/histórico
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'interacao') {
    $body       = getJsonBody();
    $contatoId  = (int)($body['contato_id'] ?? 0);
    $tipo       = clean($body['tipo'] ?? 'observacao');
    $descricao  = clean($body['descricao'] ?? '');
    $negId      = isset($body['negociacao_id']) ? (int)$body['negociacao_id'] : null;

    if (!$contatoId || !$descricao) jsonResponse(['error' => 'Contato e descrição são obrigatórios.'], 400);

    $tiposValidos = ['ligacao','email','reuniao','whatsapp','observacao','outro'];
    if (!in_array($tipo, $tiposValidos, true)) $tipo = 'observacao';

    // Verificar posse do contato
    $vc = $pdo->prepare("SELECT id FROM contatos WHERE id = :id AND empresa_id = :emp");
    $vc->execute([':id' => $contatoId, ':emp' => $empresaId]);
    if (!$vc->fetch()) jsonResponse(['error' => 'Contato não encontrado.'], 404);

    $stmt = $pdo->prepare(
        "INSERT INTO interacoes (contato_id, negociacao_id, usuario_id, tipo, descricao)
         VALUES (:cid, :nid, :uid, :tipo, :desc)"
    );
    $stmt->execute([
        ':cid'  => $contatoId,
        ':nid'  => $negId,
        ':uid'  => $user['id'],
        ':tipo' => $tipo,
        ':desc' => $descricao,
    ]);
    $newId = (int)$pdo->lastInsertId();

    registrarLog($empresaId, (int)$user['id'], 'adicionou_interacao', $newId);
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
