<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(DISTINCT i.id) as total_indicacoes_diretas,
               (SELECT COUNT(*) FROM usuarios WHERE indicador_id = u.id OR indicador_id IN 
                (SELECT id FROM usuarios WHERE indicador_id = u.id)) as total_indicacoes_arvore,
               (SELECT SUM(valor) FROM historico_pontos WHERE usuario_id = u.id AND DATE(data_criacao) = CURDATE()) as ganhos_hoje,
               (SELECT SUM(valor) FROM historico_pontos WHERE usuario_id = u.id) as total_acumulado
        FROM usuarios u 
        LEFT JOIN indicacoes i ON i.indicador_id = u.id 
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar histórico de pontos para gráfico
    $stmt = $pdo->prepare("
        SELECT DATE(data_criacao) as data, SUM(valor) as valor 
        FROM historico_pontos 
        WHERE usuario_id = ? AND data_criacao >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(data_criacao)
        ORDER BY data_criacao ASC
    ");
    $stmt->execute([$user_id]);
    $historico_pontos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar atividades recentes
    $stmt = $pdo->prepare("
        SELECT 'indicacao' as tipo, u.nome, hp.valor, hp.data_criacao
        FROM historico_pontos hp
        JOIN usuarios u ON u.id = hp.referencia_id
        WHERE hp.usuario_id = ? AND hp.tipo = 'indicacao'
        UNION ALL
        SELECT 'board' as tipo, CONCAT('Nível ', hp.referencia_id) as nome, hp.valor, hp.data_criacao
        FROM historico_pontos hp
        WHERE hp.usuario_id = ? AND hp.tipo = 'board'
        ORDER BY data_criacao DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $user_id]);
    $atividades_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular crescimento
    $crescimento = 0;
    if (count($historico_pontos) >= 2) {
        $ultimo = end($historico_pontos)['valor'];
        $penultimo = prev($historico_pontos)['valor'];
        if ($penultimo > 0) {
            $crescimento = (($ultimo - $penultimo) / $penultimo) * 100;
        }
    }

    jsonResponse([
        'sfcoin_balance' => (float)$user['saldo_sfcoin'],
        'saldo_disponivel' => (float)$user['saldo_usdt'],
        'total_indicacoes' => (int)$user['total_indicacoes_arvore'],
        'indicacoes_diretas' => (int)$user['total_indicacoes_diretas'],
        'total_sacado' => (float)$user['total_sacado'],
        'board_atual' => (int)$user['board_atual'],
        'sfg_prime_nivel' => (int)$user['sfg_prime_nivel'],
        'ganhos_hoje' => (float)($user['ganhos_hoje'] ?? 0),
        'total_acumulado' => (float)($user['total_acumulado'] ?? 0),
        'crescimento' => round($crescimento, 2),
        'historico_pontos' => $historico_pontos,
        'atividades_recentes' => $atividades_recentes
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}
?>
