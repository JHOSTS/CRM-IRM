<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();
requireCargo($user, ['gerente']);
$empresaId = getEmpresaId($user);
$pdo = getDB();

requireMethod('GET');

$inicio = clean($_GET['inicio'] ?? date('Y-m-01'));
$fim    = clean($_GET['fim']    ?? date('Y-m-t'));

// Negociações por etapa
$stmtEtapas = $pdo->prepare(
    "SELECT e.nome, e.cor, COUNT(n.id) AS total, COALESCE(SUM(n.valor_estimado),0) AS valor_total
     FROM etapas_funil e
     LEFT JOIN negociacoes n ON n.etapa_id = e.id AND n.empresa_id = :emp AND n.status = 'em_andamento'
     WHERE e.empresa_id = :emp2
     GROUP BY e.id ORDER BY e.ordem"
);
$stmtEtapas->execute([':emp' => $empresaId, ':emp2' => $empresaId]);
$porEtapa = $stmtEtapas->fetchAll();

// Totais gerais no período
$stmtTotais = $pdo->prepare(
    "SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN n.status = 'ganho'   THEN 1 ELSE 0 END) AS ganhas,
       SUM(CASE WHEN n.status = 'perdido' THEN 1 ELSE 0 END) AS perdidas,
       COALESCE(SUM(CASE WHEN n.status = 'ganho' THEN n.valor_estimado END), 0) AS valor_ganho,
       -- Em andamento = apenas negociações na etapa 'Em Negociação'
       SUM(CASE WHEN n.status = 'em_andamento'
                 AND LOWER(e.nome) LIKE '%negoci%' THEN 1 ELSE 0 END) AS em_negociacao,
       COALESCE(SUM(CASE WHEN n.status = 'em_andamento'
                          AND LOWER(e.nome) LIKE '%negoci%' THEN n.valor_estimado END), 0) AS valor_em_negociacao,
       -- Pipeline = todas em andamento (exceto ganho/perdido)
       SUM(CASE WHEN n.status = 'em_andamento' THEN 1 ELSE 0 END) AS pipeline,
       COALESCE(SUM(CASE WHEN n.status = 'em_andamento' THEN n.valor_estimado END), 0) AS valor_pipeline
     FROM negociacoes n
     JOIN etapas_funil e ON e.id = n.etapa_id
     WHERE n.empresa_id = :emp AND DATE(n.data_criacao) BETWEEN :ini AND :fim"
);
$stmtTotais->execute([':emp' => $empresaId, ':ini' => $inicio, ':fim' => $fim]);
$totais = $stmtTotais->fetch();

// Taxa de conversão
$totais['taxa_conversao'] = $totais['total'] > 0
    ? round(($totais['ganhas'] / $totais['total']) * 100, 1)
    : 0;

// Ranking atendentes
$stmtRanking = $pdo->prepare(
    "SELECT u.nome,
            COUNT(n.id) AS total,
            SUM(CASE WHEN n.status = 'ganho' THEN 1 ELSE 0 END) AS ganhas,
            COALESCE(SUM(CASE WHEN n.status = 'ganho' THEN n.valor_estimado END), 0) AS valor_ganho
     FROM usuarios u
     LEFT JOIN negociacoes n ON n.responsavel_id = u.id
       AND n.empresa_id = :emp AND DATE(n.data_criacao) BETWEEN :ini AND :fim
     WHERE u.empresa_id = :emp2 AND u.cargo = 'atendente' AND u.status = 'ativo'
     GROUP BY u.id ORDER BY ganhas DESC, valor_ganho DESC"
);
$stmtRanking->execute([':emp' => $empresaId, ':emp2' => $empresaId, ':ini' => $inicio, ':fim' => $fim]);
$ranking = $stmtRanking->fetchAll();

// Tarefas atrasadas
$stmtTarefas = $pdo->prepare(
    "SELECT COUNT(*) FROM atividades WHERE empresa_id = :emp AND concluida = 0 AND data_vencimento < NOW()"
);
$stmtTarefas->execute([':emp' => $empresaId]);
$tarefasAtrasadas = (int)$stmtTarefas->fetchColumn();

// Novos contatos no período
$stmtContatos = $pdo->prepare(
    "SELECT COUNT(*) FROM contatos WHERE empresa_id = :emp AND DATE(data_criacao) BETWEEN :ini AND :fim"
);
$stmtContatos->execute([':emp' => $empresaId, ':ini' => $inicio, ':fim' => $fim]);
$novosContatos = (int)$stmtContatos->fetchColumn();

jsonResponse([
    'periodo'          => ['inicio' => $inicio, 'fim' => $fim],
    'totais'           => $totais,
    'por_etapa'        => $porEtapa,
    'ranking'          => $ranking,
    'tarefas_atrasadas'=> $tarefasAtrasadas,
    'novos_contatos'   => $novosContatos,
]);
