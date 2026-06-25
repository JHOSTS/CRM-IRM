<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user      = requireLogin();
requireCargo($user, ['gerente']); // master também passa pela regra interna do requireCargo
$empresaId = getEmpresaId($user);
$pdo       = getDB();
$method    = $_SERVER['REQUEST_METHOD'];
$action    = clean($_GET['action'] ?? '');

// ---------------------------------------------------------------
// GET ?action=lista
// ---------------------------------------------------------------
if ($method === 'GET' && $action === 'lista') {
    $stmt = $pdo->prepare(
        "SELECT id, nome, email, cargo, status, data_criacao, ultimo_login
         FROM usuarios
         WHERE empresa_id = :emp
         ORDER BY nome ASC"
    );
    $stmt->execute([':emp' => $empresaId]);
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

    if (!$nome || !$email || !$senha) jsonResponse(['error' => 'Nome, e-mail e senha são obrigatórios.'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error' => 'E-mail inválido.'], 400);
    if (!in_array($cargo, ['atendente','gerente'], true)) $cargo = 'atendente';
    if (strlen($senha) < 8) jsonResponse(['error' => 'A senha deve ter ao menos 8 caracteres.'], 400);

    // Verificar se e-mail já existe
    $ve = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email");
    $ve->execute([':email' => $email]);
    if ($ve->fetch()) jsonResponse(['error' => 'Este e-mail já está em uso.'], 409);

    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (empresa_id, nome, email, senha_hash, cargo) VALUES (:emp, :nome, :email, :hash, :cargo)"
    );
    $stmt->execute([
        ':emp'   => $empresaId,
        ':nome'  => $nome,
        ':email' => $email,
        ':hash'  => $hash,
        ':cargo' => $cargo,
    ]);
    $newId = (int)$pdo->lastInsertId();

    registrarLog($empresaId, (int)$user['id'], 'criou_usuario', $newId, ['nome' => $nome, 'cargo' => $cargo]);
    jsonResponse(['success' => true, 'id' => $newId], 201);
}

// ---------------------------------------------------------------
// POST ?action=editar
// ---------------------------------------------------------------
if ($method === 'POST' && $action === 'editar') {
    $body = getJsonBody();
    $id   = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido.'], 400);

    // Confirmar que o usuário alvo pertence à mesma empresa
    $vu = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND empresa_id = :emp");
    $vu->execute([':id' => $id, ':emp' => $empresaId]);
    if (!$vu->fetch()) jsonResponse(['error' => 'Usuário não encontrado.'], 404);

    $campos = [];
    $params = [':id' => $id, ':emp' => $empresaId];

    if (isset($body['nome']))   { $campos[] = 'nome = :nome';   $params[':nome']  = clean($body['nome']); }
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

    registrarLog($empresaId, (int)$user['id'], 'editou_usuario', $id);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
