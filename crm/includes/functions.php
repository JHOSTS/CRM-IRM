<?php
require_once __DIR__ . '/auth.php';

// Aplica o fuso do usuário logado (definido no login via navegador)
date_default_timezone_set($_SESSION['user_timezone'] ?? 'America/Sao_Paulo');

/**
 * Retorna resposta JSON e encerra execução.
 */
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Lê o body JSON da requisição.
 */
function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Sanitiza string simples (trim + strip_tags).
 */
function clean(?string $value): string {
    return trim(strip_tags((string)($value ?? '')));
}

/**
 * Paginação: retorna offset e limit validados.
 */
function getPagination(int $defaultLimit = 30): array {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? $defaultLimit)));
    $offset = ($page - 1) * $limit;
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

/**
 * Verifica se o método HTTP é o esperado.
 */
function requireMethod(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        jsonResponse(['error' => 'Método não permitido.'], 405);
    }
}

/**
 * Verifica se o usuário master está acessando com empresa_id específico.
 * Master pode operar em qualquer empresa passando ?empresa_id=X.
 */
function resolveEmpresaId(array $user): int {
    if ($user['cargo'] === 'master' && !empty($_GET['empresa_id'])) {
        return (int) $_GET['empresa_id'];
    }
    if ($user['cargo'] === 'master' && !empty(getJsonBody()['empresa_id'])) {
        return (int) getJsonBody()['empresa_id'];
    }
    return (int) $user['empresa_id'];
}

/**
 * Formata valor monetário para exibição.
 */
function formatMoney(?float $value): string {
    if ($value === null) return '—';
    return 'R$ ' . number_format($value, 2, ',', '.');
}
