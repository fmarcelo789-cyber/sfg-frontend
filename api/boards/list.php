<?php
require_once '../config.php';

$user_id = verificarSessao();

try {
    // Buscar dados do usuário
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar posição atual nos boards
    $boards_data = [];
    foreach ($boards_config as $nivel => $config) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as posicao 
            FROM board_filas 
            WHERE nivel = ? AND usuario_id <= ? AND status = 'ativo'
            ORDER BY data_entrada ASC
        ");
        $stmt->execute([$nivel, $user_id]);
        $posicao = $stmt->fetch(PDO::FETCH_ASSOC)['posicao'];

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_fila 
            FROM board_filas 
            WHERE nivel = ? AND status = 'ativo'
        ");
        $stmt->execute([$nivel]);
        $total_fila = $stmt->fetch(PDO::FETCH_ASSOC)['total_fila'];

        $boards_data[] = [
            'nivel' => $nivel,
            'nome' => $config['nome'],
            'premio' => $config['premio'],
            'icone' => $config['icone'],
            'posicao' => $posicao,
            'total_fila' => $total_fila,
            'ativo' => $user['board_atual'] >= $nivel,
            'conquistado' => $user['board_atual'] > $nivel,
            'indicacoes_necessarias' => $config['indicacoes_necessarias'] ?? 0
        ];
    }

    // Buscar dados do SFG Prime se desbloqueado
    $sfg_prime_data = [];
    if ($user['board_atual'] >= 7) { // Desbloqueado após nível Família
        foreach ($sfg_prime_config as $nivel => $config) {
            $sfg_prime_data[] = [
                'nivel' => $nivel,
                'nome' => $config['nome'],
                'premio' => $config['premio'],
                'ativo' => $user['sfg_prime_nivel'] >= $nivel,
                'conquistado' => $user['sfg_prime_nivel'] > $nivel
            ];
        }
    }

    jsonResponse([
        'boards' => $boards_data,
        'sfg_prime' => $sfg_prime_data,
        'user_board_atual' => $user['board_atual'],
        'user_sfg_prime_nivel' => $user['sfg_prime_nivel'],
        'total_indicacoes' => $user['total_indicacoes']
    ]);
} catch (Exception $e) {
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}
?>
