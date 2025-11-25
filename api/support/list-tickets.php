<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CASE 
                   WHEN t.status = 'aberto' THEN 'Aberto'
                   WHEN t.status = 'em_andamento' THEN 'Em Andamento'
                   WHEN t.status = 'resolvido' THEN 'Resolvido'
                   WHEN t.status = 'fechado' THEN 'Fechado'
                   ELSE t.status
               END as status_descricao,
               CASE 
                   WHEN t.prioridade = 'baixa' THEN 'Baixa'
                   WHEN t.prioridade = 'media' THEN 'Média'
                   WHEN t.prioridade = 'alta' THEN 'Alta'
                   WHEN t.prioridade = 'urgente' THEN 'Urgente'
                   ELSE t.prioridade
               END as prioridade_descricao
        FROM tickets t
        WHERE t.usuario_id = ?
        ORDER BY t.data_criacao DESC
    ");
    $stmt->execute([$user_id]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar respostas para cada ticket
    foreach ($tickets as &$ticket) {
        $stmt = $pdo->prepare("
            SELECT * FROM ticket_respostas 
            WHERE ticket_id = ? 
            ORDER BY data_resposta ASC
        ");
        $stmt->execute([$ticket['id']]);
        $ticket['respostas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Estatísticas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_tickets,
            SUM(CASE WHEN status = 'aberto' THEN 1 ELSE 0 END) as abertos,
            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
            SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos
        FROM tickets 
        WHERE usuario_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'tickets' => $tickets,
        'estatisticas' => [
            'total_tickets' => (int)$stats['total_tickets'],
            'abertos' => (int)$stats['abertos'],
            'em_andamento' => (int)$stats['em_andamento'],
            'resolvidos' => (int)$stats['resolvidos']
        ]
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao buscar tickets'], 500);
}
?>
