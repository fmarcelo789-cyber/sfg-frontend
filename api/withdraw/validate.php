<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$saque_id = (int)($input['saque_id'] ?? 0);
$codigo = $input['codigo'] ?? '';

if (empty($codigo)) {
    jsonResponse(['error' => 'Código de validação é obrigatório'], 400);
}

try {
    $pdo->beginTransaction();

    // Verificar saque
    $stmt = $pdo->prepare("
        SELECT * FROM saques 
        WHERE id = ? AND usuario_id = ? AND status = 'pendente_validacao'
    ");
    $stmt->execute([$saque_id, $user_id]);
    $saque = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$saque) {
        jsonResponse(['error' => 'Saque não encontrado ou já processado'], 404);
    }

    if ($saque['codigo_validacao'] != $codigo) {
        jsonResponse(['error' => 'Código de validação inválido'], 400);
    }

    // Verificar se ainda tem saldo
    $stmt = $pdo->prepare("SELECT saldo_usdt FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user['saldo_usdt'] < $saque['valor']) {
        jsonResponse(['error' => 'Saldo insuficiente'], 400);
    }

    // Processar saque
    $stmt = $pdo->prepare("UPDATE usuarios SET saldo_usdt = saldo_usdt - ?, total_sacado = total_sacado + ? WHERE id = ?");
    $stmt->execute([$saque['valor'], $saque['valor'], $user_id]);

    // Atualizar status do saque
    $stmt = $pdo->prepare("UPDATE saques SET status = 'processado', data_processamento = NOW() WHERE id = ?");
    $stmt->execute([$saque_id]);

    // Registrar no histórico
    $stmt = $pdo->prepare("
        INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
        VALUES (?, 'saque', ?, ?, NOW())
    ");
    $stmt->execute([$user_id, -$saque['valor'], $saque_id]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Saque processado com sucesso! O valor será transferido em até 24 horas.',
        'valor_sacado' => $saque['valor'],
        'carteira_destino' => $saque['carteira_endereco']
    ]);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao validar saque'], 500);
}
?>
