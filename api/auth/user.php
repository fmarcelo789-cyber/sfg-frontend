<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(i.id) as total_indicacoes_diretas,
               (SELECT COUNT(*) FROM indicacoes WHERE indicador_id = u.id OR indicador_id IN 
                (SELECT id FROM usuarios WHERE indicador_id = u.id)) as total_indicacoes_arvore
        FROM usuarios u 
        LEFT JOIN indicacoes i ON i.indicador_id = u.id 
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse(['error' => 'Usuário não encontrado'], 404);
    }

    jsonResponse([
        'id' => $user['id'],
        'nome' => $user['nome'],
        'email' => $user['email'],
        'whatsapp' => $user['whatsapp'],
        'pais' => $user['pais'],
        'estado' => $user['estado'],
        'saldo_sfcoin' => (float)$user['saldo_sfcoin'],
        'saldo_usdt' => (float)$user['saldo_usdt'],
        'board_atual' => (int)$user['board_atual'],
        'sfg_prime_nivel' => (int)$user['sfg_prime_nivel'],
        'total_indicacoes' => (int)$user['total_indicacoes_arvore'],
        'indicacoes_diretas' => (int)$user['total_indicacoes_diretas'],
        'total_sacado' => (float)$user['total_sacado'],
        'carteira1' => $user['carteira1'],
        'carteira1_comentario' => $user['carteira1_comentario'],
        'carteira2' => $user['carteira2'],
        'carteira2_comentario' => $user['carteira2_comentario'],
        'carteira3' => $user['carteira3'],
        'carteira3_comentario' => $user['carteira3_comentario']
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}
?>
