<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();
requireCargo($user, ['gerente']);
$empresaId = getEmpresaId($user);
$pdo = getDB();

requireMethod('GET');

$p       = getPagination(50);
$inicio  = clean($_GET['inicio'] ?? date('Y-m-d'));
$fim     = clean($_GET['fim']    ?? date('Y-m-d'));
$usuId   = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : null;

$where  = ['l.empresa_id = :emp', "DATE(l.data_criacao) BETWEEN :ini AND :fim"];
$params = [':emp' => $empresaId, ':ini' => $inicio, ':fim' => $fim];

if ($usuId) { $where[] = 'l.usuario_id = :uid'; $params[':uid'] = $usuId; }

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM log_atividades l WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT l.id, l.acao, l.referencia_id, l.detalhes, l.data_criacao,
            u.nome AS usuario_nome, u.cargo AS usuario_cargo
     FROM log_atividades l
     JOIN usuarios u ON u.id = l.usuario_id
     WHERE $whereStr
     ORDER BY l.data_criacao DESC
     LIMIT :limit OFFSET :offset"
);
$params[':limit']  = $p['limit'];
$params[':offset'] = $p['offset'];
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Decodificar JSON de detalhes
foreach ($rows as &$r) {
    $r['detalhes'] = $r['detalhes'] ? json_decode($r['detalhes'], true) : null;
}
unset($r);

jsonResponse(['data' => $rows, 'total' => $total, 'page' => $p['page']]);
