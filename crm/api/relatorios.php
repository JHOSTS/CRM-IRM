<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();
requireCargo($user, ['gerente']);
$empresaId = getEmpresaId($user);
$pdo = getDB();

requireMethod('GET');

$action  = clean($_GET['action'] ?? 'negociacoes');
$inicio  = clean($_GET['inicio'] ?? date('Y-m-01'));
$fim     = clean($_GET['fim']    ?? date('Y-m-t'));
$export  = clean($_GET['export'] ?? '');
$p       = getPagination(50);

if ($action === 'negociacoes') {
    $where  = ['n.empresa_id = :emp', "DATE(n.data_criacao) BETWEEN :ini AND :fim"];
    $params = [':emp' => $empresaId, ':ini' => $inicio, ':fim' => $fim];

    if (!empty($_GET['status'])) {
        $s = clean($_GET['status']);
        if (in_array($s, ['em_andamento','ganho','perdido'], true)) {
            $where[] = 'n.status = :status'; $params[':status'] = $s;
        }
    }
    if (!empty($_GET['responsavel_id'])) {
        $where[] = 'n.responsavel_id = :resp'; $params[':resp'] = (int)$_GET['responsavel_id'];
    }

    $whereStr = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM negociacoes n WHERE $whereStr");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    if ($export === 'csv') {
        // Exportação CSV — sem paginação
        $stmt = $pdo->prepare(
            "SELECT n.titulo, c.nome AS contato, u.nome AS responsavel,
                    e.nome AS etapa, n.status, n.valor_estimado,
                    n.data_criacao, n.data_atualizacao
             FROM negociacoes n
             JOIN contatos c ON c.id = n.contato_id
             JOIN usuarios u ON u.id = n.responsavel_id
             JOIN etapas_funil e ON e.id = n.etapa_id
             WHERE $whereStr ORDER BY n.data_criacao DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="negociacoes_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['Título','Contato','Responsável','Etapa','Status','Valor Estimado','Criado em','Atualizado em'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['titulo'], $r['contato'], $r['responsavel'],
                $r['etapa'], $r['status'],
                $r['valor_estimado'] ? number_format((float)$r['valor_estimado'], 2, ',', '.') : '',
                $r['data_criacao'], $r['data_atualizacao'],
            ], ';');
        }
        fclose($out);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT n.id, n.titulo, c.nome AS contato_nome, u.nome AS responsavel_nome,
                e.nome AS etapa_nome, n.status, n.valor_estimado,
                n.data_criacao, n.data_atualizacao
         FROM negociacoes n
         JOIN contatos c ON c.id = n.contato_id
         JOIN usuarios u ON u.id = n.responsavel_id
         JOIN etapas_funil e ON e.id = n.etapa_id
         WHERE $whereStr
         ORDER BY n.data_criacao DESC
         LIMIT :limit OFFSET :offset"
    );
    $params[':limit']  = $p['limit'];
    $params[':offset'] = $p['offset'];
    $stmt->execute($params);

    jsonResponse(['data' => $stmt->fetchAll(), 'total' => $total, 'page' => $p['page']]);
}

jsonResponse(['error' => 'Ação não reconhecida.'], 400);
