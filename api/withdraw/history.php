<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    $stmt = $pdo->prepare("
        SELECT s.*, 
               CASE 
                   WHEN s.status = 'pendente_validacao' THEN 'Aguardando Validação'
                   WHEN s.status = 'processado' THEN 'Processado'
                   WHEN s.status = 'cancelado' THEN 'Cancelado'
                   ELSE s.status
               END as status_descricao
        FROM saques s
        WHERE s.usuario_id = ?
        ORDER BY s.data_solicitacao DESC
    ");
    $stmt->execute([$user_id]);
    $saques = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular estatísticas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_saques,
            SUM(CASE WHEN status = 'processado' THEN valor ELSE 0 END) as total_sacado,
            SUM(CASE WHEN status = 'pendente_validacao' THEN valor ELSE 0 END) as pendente_validacao
        FROM saques 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'saques' => $saques,
        'estatisticas' => [
            'total_saques' => (int)$stats['total_saques'],
            'total_sacado' => (float)$stats['total_sacado'],
            'pendente_validacao' => (float)$stats['pendente_validacao']
        ]
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao buscar histórico de saques'], 500);
}
?>
