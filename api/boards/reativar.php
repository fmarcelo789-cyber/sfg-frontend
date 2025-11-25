<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$nivel = $input['nivel'] ?? 0;

try {
    // Verificar se usuário tem indicações suficientes
    $stmt = $pdo->prepare("SELECT total_indicacoes FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $indicacoes_necessarias = $boards_config[$nivel]['indicacoes_necessarias'] ?? 0;
    
    if ($user['total_indicacoes'] < $indicacoes_necessarias) {
        jsonResponse(['error' => 'Indicações insuficientes'], 400);
    }

    // Reativar usuário no board
    $stmt = $pdo->prepare("UPDATE board_filas SET status = 'ativo' WHERE usuario_id = ? AND nivel = ?");
    $stmt->execute([$user_id, $nivel]);

    jsonResponse(['success' => true, 'message' => 'Board reativado com sucesso']);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao reativar board'], 500);
}
?>
