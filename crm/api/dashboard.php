<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireLogin();
requireCargo($user, ['gerente']);
$empresaId = getEmpresaId($user);
$pdo = getDB();

requireMethod('GET');

$inicio      = clean($_GET['inicio']        ?? date('Y-m-01'));
$fim         = clean($_GET['fim']          ?? date('Y-m-t'));
$respFiltro  = isset($_GET['responsavel_id']) ? (int)$_GET['responsavel_id'] : null;

// Condição e parâmetro opcionais de responsável
$respCond  = $respFiltro ? ' AND n.responsavel_id = :resp' : '';
$respParam = $respFiltro ? [':resp' => $respFiltro] : [];

// Negociações por etapa
$stmtEtapas = $pdo->prepare(
    "SELECT e.nome, e.cor, COUNT(n.id) AS total, COALESCE(SUM(n.valor_estimado),0) AS valor_total
     FROM etapas_funil e
     LEFT JOIN negociacoes n ON n.etapa_id = e.id AND n.empresa_id = :emp AND n.status = 'em_andamento'{$respCond}
     WHERE e.empresa_id = :emp2
     GROUP BY e.id ORDER BY e.ordem"
);
$stmtEtapas->execute(array_merge([':emp' => $empresaId, ':emp2' => $empresaId], $respParam));
$porEtapa = $stmtEtapas->fetchAll();

// Totais gerais no período
$stmtTotais = $pdo->prepare(
    "SELECT
       COUNT(*) AS total,
       SUM(CASE WHEN n.status = 'ganho'   THEN 1 ELSE 0 END) AS ganhas,
       SUM(CASE WHEN n.status = 'perdido' THEN 1 ELSE 0 END) AS perdidas,
       COALESCE(SUM(CASE WHEN n.status = 'ganho'   THEN n.valor_estimado END), 0) AS valor_ganho,
       COALESCE(SUM(CASE WHEN n.status = 'perdido' THEN n.valor_estimado END), 0) AS valor_perdido,
       SUM(CASE WHEN n.status = 'em_andamento'
                 AND LOWER(e.nome) LIKE '%negoci%' THEN 1 ELSE 0 END) AS em_negociacao,
       COALESCE(SUM(CASE WHEN n.status = 'em_andamento'
                          AND LOWER(e.nome) LIKE '%negoci%' THEN n.valor_estimado END), 0) AS valor_em_negociacao,
       SUM(CASE WHEN n.status = 'em_andamento' THEN 1 ELSE 0 END) AS pipeline,
       COALESCE(SUM(CASE WHEN n.status = 'em_andamento' THEN n.valor_estimado END), 0) AS valor_pipeline
     FROM negociacoes n
     JOIN etapas_funil e ON e.id = n.etapa_id
     WHERE n.empresa_id = :emp AND DATE(n.data_criacao) BETWEEN :ini AND :fim{$respCond}"
);
$stmtTotais->execute(array_merge([':emp' => $empresaId, ':ini' => $inicio, ':fim' => $fim], $respParam));
$totais = $stmtTotais->fetch();

// Taxa de conversão
$totais['taxa_conversao'] = $totais['total'] > 0
    ? round(($totais['ganhas'] / $totais['total']) * 100, 1)
    : 0;

// Ranking atendentes
$stmtRanking = $pdo->prepare(
    "SELECT u.nome,
            COUNT(n.id)                                                              AS total_leads,
            SUM(CASE WHEN n.status = 'ganho'   THEN 1 ELSE 0 END)                  AS ganhos,
            SUM(CASE WHEN n.status = 'perdido' THEN 1 ELSE 0 END)                  AS perdidos,
            COALESCE(SUM(CASE WHEN n.status = 'ganho' THEN n.valor_estimado END),0) AS valor_ganho
     FROM usuarios u
     LEFT JOIN negociacoes n
       ON n.responsavel_id = u.id
      AND n.empresa_id = :emp
      AND DATE(n.data_criacao) BETWEEN :ini AND :fim
     WHERE u.empresa_id = :emp2
       AND u.cargo != 'master'
       AND u.status  = 'ativo'
     GROUP BY u.id
     ORDER BY ganhos DESC, valor_ganho DESC"
);
$stmtRanking->execute([':emp' => $empresaId, ':emp2' => $empresaId, ':ini' => $inicio, ':fim' => $fim]);
$ranking = array_map(function($r) {
    $r['conversao'] = $r['total_leads'] > 0
        ? round(($r['ganhos'] / $r['total_leads']) * 100, 1)
        : 0;
    return $r;
}, $stmtRanking->fetchAll());

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

// Leads por dia
$stmtDia = $pdo->prepare(
    "SELECT DATE(data_criacao) AS dia, COUNT(*) AS total
     FROM negociacoes
     WHERE empresa_id = :emp AND DATE(data_criacao) BETWEEN :ini AND :fim{$respCond}
     GROUP BY DATE(data_criacao) ORDER BY dia ASC"
);
$stmtDia->execute(array_merge([':emp' => $empresaId, ':ini' => $inicio, ':fim' => $fim], $respParam));
$leadsDia = $stmtDia->fetchAll();

// Leads por usuário (atendentes)
$stmtPorUser = $pdo->prepare(
    "SELECT u.nome,
            COUNT(n.id) AS total_leads,
            SUM(CASE WHEN n.status = 'ganho'   THEN 1 ELSE 0 END) AS ganhos,
            SUM(CASE WHEN n.status = 'perdido' THEN 1 ELSE 0 END) AS perdidos,
            COALESCE(SUM(CASE WHEN n.status = 'ganho' THEN n.valor_estimado END), 0) AS valor_ganho
     FROM usuarios u
     LEFT JOIN negociacoes n ON n.responsavel_id = u.id
       AND n.empresa_id = :emp AND DATE(n.data_criacao) BETWEEN :ini AND :fim
     WHERE u.empresa_id = :emp2 AND u.cargo != 'master' AND u.status = 'ativo'
     GROUP BY u.id ORDER BY total_leads DESC"
);
$stmtPorUser->execute([':emp' => $empresaId, ':emp2' => $empresaId, ':ini' => $inicio, ':fim' => $fim]);
$leadsPorUser = $stmtPorUser->fetchAll();

jsonResponse([
    'periodo'          => ['inicio' => $inicio, 'fim' => $fim],
    'totais'           => $totais,
    'por_etapa'        => $porEtapa,
    'ranking'          => $ranking,
    'tarefas_atrasadas'=> $tarefasAtrasadas,
    'novos_contatos'   => $novosContatos,
    'leads_dia'        => $leadsDia,
    'leads_por_user'   => $leadsPorUser,
]);
