<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

/**
 * Retorna o usuário da sessão ou null se não autenticado.
 */
function getSessionUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Verifica se há sessão ativa. Se não houver, redireciona para login (HTML)
 * ou retorna JSON 401 (API).
 */
function requireLogin(bool $isApi = true): array {
    $user = getSessionUser();
    if (!$user) {
        if ($isApi) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado.']);
            exit;
        } else {
            header('Location: /crm/index.php');
            exit;
        }
    }
    return $user;
}

/**
 * Verifica se o usuário tem um dos cargos permitidos.
 * Master sempre passa.
 */
function requireCargo(array $user, array $cargosPermitidos, bool $isApi = true): void {
    if ($user['cargo'] === 'master') return;
    if (!in_array($user['cargo'], $cargosPermitidos, true)) {
        if ($isApi) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado.']);
            exit;
        } else {
            header('Location: /crm/kanban.php?erro=acesso_negado');
            exit;
        }
    }
}

/**
 * Registra uma ação no log de auditoria.
 */
function registrarLog(int $empresaId, int $usuarioId, string $acao, ?int $referenciaId = null, ?array $detalhes = null): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO log_atividades (empresa_id, usuario_id, acao, referencia_id, detalhes)
             VALUES (:emp, :usr, :acao, :ref, :det)"
        );
        $stmt->execute([
            ':emp'  => $empresaId,
            ':usr'  => $usuarioId,
            ':acao' => $acao,
            ':ref'  => $referenciaId,
            ':det'  => $detalhes ? json_encode($detalhes, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (PDOException $e) {
        // Log falhou silenciosamente para não quebrar a ação principal
    }
}

/**
 * Escapa output para evitar XSS.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Retorna o empresa_id ativo.
 * Para master: usa a empresa selecionada na sessão (ou a própria se nenhuma selecionada).
 * Para outros: sempre a empresa do usuário.
 */
function getEmpresaId(array $user): int {
    if ($user['cargo'] === 'master' && isset($_SESSION['active_empresa_id'])) {
        return (int) $_SESSION['active_empresa_id'];
    }
    return (int) $user['empresa_id'];
}

/**
 * Retorna branding (logo, cores) da empresa do usuário logado.
 */
function getEmpresaBranding(int $empresaId): array {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT logo, cor_primaria, cor_secundaria FROM empresas WHERE id = :id");
        $stmt->execute([':id' => $empresaId]);
        $row  = $stmt->fetch();
        return $row ?: ['logo' => null, 'cor_primaria' => '#4361ee', 'cor_secundaria' => '#1a1d27'];
    } catch (PDOException $e) {
        return ['logo' => null, 'cor_primaria' => '#4361ee', 'cor_secundaria' => '#1a1d27'];
    }
}
