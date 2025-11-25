<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    // Buscar indicações diretas (1ª geração)
    $stmt = $pdo->prepare("
        SELECT u.id, u.nome, u.email, u.board_atual, u.saldo_sfcoin, u.data_cadastro,
               u.ultimo_login, u.status_deposito,
               CASE 
                   WHEN u.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'ativo'
                   ELSE 'inativo'
               END as status_atividade
        FROM usuarios u
        JOIN indicacoes i ON i.indicado_id = u.id
        WHERE i.indicador_id = ?
        ORDER BY u.data_cadastro DESC
    ");
    $stmt->execute([$user_id]);
    $indicacoes_diretas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para cada indicação direta, buscar suas indicações (2ª geração)
    $arvore_completa = [];
    foreach ($indicacoes_diretas as $indicado) {
        $stmt = $pdo->prepare("
            SELECT u.id, u.nome, u.email, u.board_atual, u.saldo_sfcoin, u.data_cadastro,
                   u.ultimo_login, u.status_deposito,
                   CASE 
                       WHEN u.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'ativo'
                       ELSE 'inativo'
                   END as status_atividade
            FROM usuarios u
            JOIN indicacoes i ON i.indicado_id = u.id
            WHERE i.indicador_id = ?
            ORDER BY u.data_cadastro DESC
        ");
        $stmt->execute([$indicado['id']]);
        $segunda_geracao = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Para cada 2ª geração, buscar 3ª geração
        foreach ($segunda_geracao as &$seg_gen) {
            $stmt = $pdo->prepare("
                SELECT u.id, u.nome, u.email, u.board_atual, u.saldo_sfcoin, u.data_cadastro,
                       u.ultimo_login, u.status_deposito,
                       CASE 
                           WHEN u.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'ativo'
                           ELSE 'inativo'
                       END as status_atividade
                FROM usuarios u
                JOIN indicacoes i ON i.indicado_id = u.id
                WHERE i.indicador_id = ?
                ORDER BY u.data_cadastro DESC
            ");
            $stmt->execute([$seg_gen['id']]);
            $seg_gen['terceira_geracao'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $indicado['segunda_geracao'] = $segunda_geracao;
        $arvore_completa[] = $indicado;
    }

    // Calcular estatísticas do time
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u1.id) as primeira_geracao,
            COUNT(DISTINCT u2.id) as segunda_geracao,
            COUNT(DISTINCT u3.id) as terceira_geracao,
            COUNT(DISTINCT CASE WHEN u1.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u1.id END) as ativos_1gen,
            COUNT(DISTINCT CASE WHEN u2.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u2.id END) as ativos_2gen,
            COUNT(DISTINCT CASE WHEN u3.ultimo_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u3.id END) as ativos_3gen
        FROM indicacoes i1
        LEFT JOIN usuarios u1 ON u1.id = i1.indicado_id
        LEFT JOIN indicacoes i2 ON i2.indicador_id = u1.id
        LEFT JOIN usuarios u2 ON u2.id = i2.indicado_id
        LEFT JOIN indicacoes i3 ON i3.indicador_id = u2.id
        LEFT JOIN usuarios u3 ON u3.id = i3.indicado_id
        WHERE i1.indicador_id = ?
    ");
    $stmt->execute([$user_id]);
    $estatisticas = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'arvore_completa' => $arvore_completa,
        'estatisticas' => [
            'primeira_geracao' => (int)$estatisticas['primeira_geracao'],
            'segunda_geracao' => (int)$estatisticas['segunda_geracao'],
            'terceira_geracao' => (int)$estatisticas['terceira_geracao'],
            'total_time' => (int)$estatisticas['primeira_geracao'] + (int)$estatisticas['segunda_geracao'] + (int)$estatisticas['terceira_geracao'],
            'ativos_1gen' => (int)$estatisticas['ativos_1gen'],
            'ativos_2gen' => (int)$estatisticas['ativos_2gen'],
            'ativos_3gen' => (int)$estatisticas['ativos_3gen']
        ]
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao buscar dados do time'], 500);
}
?>
