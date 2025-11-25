<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    // Buscar dados financeiros do usuário
    $stmt = $pdo->prepare("
        SELECT u.saldo_sfcoin, u.saldo_usdt, u.total_sacado,
               (SELECT SUM(valor) FROM historico_pontos WHERE usuario_id = u.id AND DATE(data_criacao) = CURDATE() AND valor > 0) as ganhos_hoje,
               (SELECT SUM(valor) FROM historico_pontos WHERE usuario_id = u.id AND valor > 0) as total_acumulado
        FROM usuarios u 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar transações recentes
    $stmt = $pdo->prepare("
        SELECT hp.tipo, hp.valor, hp.data_criacao,
               CASE 
                   WHEN hp.tipo = 'indicacao' THEN CONCAT('Indicação de ', u.nome)
                   WHEN hp.tipo = 'board' THEN CONCAT('Conquista Board Nível ', hp.referencia_id)
                   WHEN hp.tipo = 'sfg_prime' THEN CONCAT('SFG Prime Nível ', hp.referencia_id)
                   WHEN hp.tipo = 'swap' AND hp.valor < 0 THEN 'Conversão SFCoin → USDT'
                   WHEN hp.tipo = 'swap' AND hp.valor > 0 THEN 'Recebimento USDT'
                   WHEN hp.tipo = 'saque' THEN 'Saque realizado'
                   ELSE hp.tipo
               END as descricao
        FROM historico_pontos hp
        LEFT JOIN usuarios u ON u.id = hp.referencia_id AND hp.tipo = 'indicacao'
        WHERE hp.usuario_id = ?
        ORDER BY hp.data_criacao DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $transacoes_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular crescimento
    $stmt = $pdo->prepare("
        SELECT 
            (SELECT SUM(valor) FROM historico_pontos WHERE usuario_id = ? AND DATE(data_criacao) = CURDATE() AND valor > 0) as hoje,
            (SELECT SUM(valor) FROM historico_pontos WHERE usuario_id = ? AND DATE(data_criacao) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND valor > 0) as ontem
    ");
    $stmt->execute([$user_id, $user_id]);
    $crescimento_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $crescimento = 0;
    if ($crescimento_data['ontem'] > 0) {
        $crescimento = (($crescimento_data['hoje'] - $crescimento_data['ontem']) / $crescimento_data['ontem']) * 100;
    }

    jsonResponse([
        'saldo_sfcoin' => (float)$stats['saldo_sfcoin'],
        'saldo_usdt' => (float)$stats['saldo_usdt'],
        'total_sacado' => (float)$stats['total_sacado'],
        'ganhos_hoje' => (float)($stats['ganhos_hoje'] ?? 0),
        'total_acumulado' => (float)($stats['total_acumulado'] ?? 0),
        'crescimento' => round($crescimento, 2),
        'transacoes_recentes' => $transacoes_recentes
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao buscar dados financeiros'], 500);
}
?>
