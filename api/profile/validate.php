<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$codigo = $input['codigo'] ?? '';

if (empty($codigo)) {
    jsonResponse(['error' => 'Código de validação é obrigatório'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT codigo_validacao, validacao_pendente 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['validacao_pendente']) {
        jsonResponse(['error' => 'Nenhuma validação pendente'], 400);
    }

    if ($user['codigo_validacao'] != $codigo) {
        jsonResponse(['error' => 'Código de validação inválido'], 400);
    }

    // Confirmar validação
    $stmt = $pdo->prepare("
        UPDATE usuarios 
        SET validacao_pendente = 0, codigo_validacao = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);

    jsonResponse(['success' => true, 'message' => 'Perfil validado com sucesso']);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao validar código'], 500);
}
?>
