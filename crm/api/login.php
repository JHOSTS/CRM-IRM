<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireMethod('POST');

$body = getJsonBody();
$email = clean($body['email'] ?? '');
$senha = $body['senha'] ?? '';

if (!$email || !$senha) {
    jsonResponse(['error' => 'E-mail e senha são obrigatórios.'], 400);
}

$pdo = getDB();

$stmt = $pdo->prepare(
    "SELECT u.id, u.empresa_id, u.nome, u.email, u.senha_hash, u.cargo, u.status,
            e.status AS empresa_status
     FROM usuarios u
     JOIN empresas e ON e.id = u.empresa_id
     WHERE u.email = :email
     LIMIT 1"
);
$stmt->execute([':email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
    jsonResponse(['error' => 'Credenciais inválidas.'], 401);
}

if ($usuario['status'] !== 'ativo') {
    jsonResponse(['error' => 'Usuário inativo. Entre em contato com seu gerente.'], 403);
}

if ($usuario['empresa_status'] !== 'ativo') {
    jsonResponse(['error' => 'Empresa inativa. Entre em contato com a agência.'], 403);
}

// Atualizar último login
$upd = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id");
$upd->execute([':id' => $usuario['id']]);

// Salvar sessão (nunca salvar senha_hash)
$_SESSION['user'] = [
    'id'         => (int) $usuario['id'],
    'empresa_id' => (int) $usuario['empresa_id'],
    'nome'       => $usuario['nome'],
    'email'      => $usuario['email'],
    'cargo'      => $usuario['cargo'],
];

// Fuso horário do navegador do usuário
$tz = $body['timezone'] ?? 'America/Sao_Paulo';
if (!in_array($tz, timezone_identifiers_list(), true)) {
    $tz = 'America/Sao_Paulo';
}
$_SESSION['user_timezone'] = $tz;

registrarLog((int)$usuario['empresa_id'], (int)$usuario['id'], 'login');

jsonResponse(['success' => true, 'cargo' => $usuario['cargo'], 'nome' => $usuario['nome']]);
