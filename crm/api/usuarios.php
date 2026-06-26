<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user      = requireLogin();
$isMaster  = $user['cargo'] === 'master';
$empresaId = getEmpresaId($user);
$pdo       = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = clean($_GET['action'] ?? '');

// ---------------------------------------------------------------
// GET ?action=responsaveis — acessível a todos os cargos
// Atendente: retorna apenas ele mesmo
// Gerente/Master: retorna todos da empresa ativa
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'responsaveis') {
    if ($user['cargo'] === 'atendente') {
        jsonResponse(['data' => [['id' => (int)$user['id'], 'nome' => $user['nome']]]]);
    }
    $stmt = $pdo->prepare(
        "SELECT id, nome FROM usuarios
         WHERE empresa_id = :emp AND status = 'ativo' AND cargo != 'master'
         ORDER BY nome ASC"
    );
    $stmt->execute([':emp' => $empresaId]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// A partir daqui apenas gerente+
requireCargo($user, ['gerente']);

// ---------------------------------------------------------------
// GET ?action=lista
// Master sem empresa ativa: lista todos; com empresa ativa: filtra
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'lista') {
    if ($isMaster) {
        $stmt = $pdo->prepare(
            "SELECT u.id, u.empresa_id, u.nome, u.email, u.cargo, u.status,
                    u.data_criacao, u.ultimo_login,
                    e.nome AS empresa_nome
             FROM usuarios u
             JOIN empresas e ON e.id = u.empresa_id
             WHERE u.empresa_id = :emp
             ORDER BY u.nome ASC"
        );
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, empresa_id, nome, email, cargo, status, data_criacao, ultimo_login,
                    NULL AS empresa_nome
             FROM usuarios
             WHERE empresa_id = :emp
             ORDER BY nome ASC"
        );
    }
    $stmt->execute([':emp' => $empresaId]);
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ---------------------------------------------------------------
// GET ?action=todas — master: todos os usuários de todas as empresas
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'todas' && $isMaster) {
    $stmt = $pdo->query(
        "SELECT u.id, u.empresa_id, u.nome, u.email, u.cargo, u.status,
                u.data_criacao, u.ultimo_login,
                e.nome AS empresa_nome
         FROM usuarios u
         JOIN empresas e ON e.id = u.empresa_id
         WHERE u.cargo != 'master'
         ORDER BY e.nome ASC, u.nome ASC"
    );
    jsonResponse(['data' => $stmt->fetchAll()]);
}

// ---------------------------------------------------------------
// POST ?action=criar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'criar') {
    $body  = getJsonBody();
    $nome  = clean($body['nome'] ?? '');
    $email = strtolower(clean($body['email'] ?? ''));
    $cargo = clean($body['cargo'] ?? 'atendente');
    $senha = $body['senha'] ?? '';

    // Master pode definir empresa_id ao criar
    $empDestino = $isMaster && !empty($body['empresa_id'])
        ? (int)$body['empresa_id']
        : $empresaId;

    if (!$nome || !$email || !$senha) jsonResponse(['error' => 'Nome, e-mail e senha são obrigatórios.'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'E-mail inválido.'], 400);
    if (!in_array($cargo, ['atendente','gerente'], true)) $cargo = 'atendente';
    if (strlen($senha) < 8) jsonResponse(['error' => 'A senha deve ter ao menos 8 caracteres.'], 400);

    $ve = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $ve->execute([':email' => $email]);
    if ($ve->fetch()) jsonResponse(['error' => 'Este e-mail já está em uso.'], 409);

    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (empresa_id, nome, email, senha_hash, cargo) VALUES (:emp, :nome, :email, :hash, :cargo)"
    );
    $stmt->execute([
        ':emp'   => $empDestino,
        ':nome'  => $nome,
        ':email' => $email,
        ':hash'  => $hash,
        ':cargo' => $cargo,
    ]);
    $newId = (int)$pdo->lastInsertId();

    registrarLog($empDestino, (int)$user['id'], 'criou_usuario', $newId, ['nome' => $nome, 'cargo' => $cargo]);
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ---------------------------------------------------------------
// POST ?action=editar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    // Master pode editar qualquer usuário; gerente só da própria empresa
    if ($isMaster) {
        $vu = $pdo->prepare("SELECT id, empresa_id FROM usuarios WHERE id = :id AND cargo != 'master'");
        $vu->execute([':id' => $id]);
    } else {
        $vu = $pdo->prepare("SELECT id, empresa_id FROM usuarios WHERE id = :id AND empresa_id = :emp");
        $vu->execute([':id' => $id, ':emp' => $empresaId]);
    }
    $alvo = $vu->fetch();
    if (!$alvo) jsonResponse(['error' => 'Usuário não encontrado.'], 404);

    $empAlvo = (int)$alvo['empresa_id'];
    $campos  = [];
    $params  = [':id' => $id, ':emp' => $empAlvo];

    if (isset($body['nome']))   { $campos[] = 'nome = :nome';   $params[':nome']   = clean($body['nome']); }
    if (isset($body['status'])) {
        if (!in_array($body['status'], ['ativo','inativo'], true)) jsonResponse(['error' => 'Status inválido.'], 400);
        $campos[] = 'status = :status'; $params[':status'] = $body['status'];
    }
    if (isset($body['cargo'])) {
        if (!in_array($body['cargo'], ['atendente','gerente'], true)) jsonResponse(['error' => 'Cargo inválido.'], 400);
        $campos[] = 'cargo = :cargo'; $params[':cargo'] = $body['cargo'];
    }
    if (!empty($body['senha'])) {
        if (strlen($body['senha']) < 8) jsonResponse(['error' => 'A senha deve ter ao menos 8 caracteres.'], 400);
        $campos[] = 'senha_hash = :hash';
        $params[':hash'] = password_hash($body['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
    }

    if (empty($campos)) jsonResponse(['error' => 'Nenhum campo para atualizar.'], 400);

    $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $campos) . ' WHERE id = :id AND empresa_id = :emp')
        ->execute($params);

    registrarLog($empAlvo, (int)$user['id'], 'editou_usuario', $id);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
