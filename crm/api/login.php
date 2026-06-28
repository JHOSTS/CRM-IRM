<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
requireMethod('POST');

$body  = getJsonBody();
$email = clean($body['email'] ?? '');
$senha = $body['senha'] ?? '';

if (!$email || !$senha) {
    jsonResponse(['error' => 'E-mail e senha são obrigatórios.'], 400);
}

$pdo = getDB();

// Rate limiting: máx 10 tentativas por IP em 15 minutos
$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: 'unknown';
$stmtCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND tentativa_em > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
$stmtCheck->execute([':ip' => $ip]);
if ((int)$stmtCheck->fetchColumn() >= 10) {
    jsonResponse(['error' => 'Muitas tentativas de login. Aguarde 15 minutos.'], 429);
}

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
    // Registrar tentativa falha e limpar antigas (1% de chance para não pesar sempre)
    $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)")->execute([':ip' => $ip]);
    if (rand(1, 100) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE tentativa_em < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
    }
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

// Regenerar ID de sessão ao autenticar (previne session fixation)
session_regenerate_id(true);

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
