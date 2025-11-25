<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$sfcoin_amount = (float)($input['sfcoin_amount'] ?? 0);

if ($sfcoin_amount <= 0) {
    jsonResponse(['error' => 'Quantidade de SFCoin deve ser maior que zero'], 400);
}

if ($sfcoin_amount < 10) {
    jsonResponse(['error' => 'Quantidade mínima para swap é 10 SFCoin'], 400);
}

try {
    $pdo->beginTransaction();

    // Verificar saldo do usuário
    $stmt = $pdo->prepare("SELECT saldo_sfcoin FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['saldo_sfcoin'] < $sfcoin_amount) {
        jsonResponse(['error' => 'Saldo insuficiente de SFCoin'], 400);
    }

    // Calcular conversão: 10 SFCoin = 1 USDT
    $usdt_amount = $sfcoin_amount / 10;

    // Atualizar saldos
    $stmt = $pdo->prepare("
        UPDATE usuarios 
        SET saldo_sfcoin = saldo_sfcoin - ?, saldo_usdt = saldo_usdt + ? 
        WHERE id = ?
    ");
    $stmt->execute([$sfcoin_amount, $usdt_amount, $user_id]);

    // Registrar no histórico
    $stmt = $pdo->prepare("
        INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
        VALUES (?, 'swap', ?, 0, NOW())
    ");
    $stmt->execute([$user_id, -$sfcoin_amount]);

    $stmt = $pdo->prepare("
        INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
        VALUES (?, 'swap', ?, 0, NOW())
    ");
    $stmt->execute([$user_id, $usdt_amount]);

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Swap realizado com sucesso',
        'sfcoin_convertido' => $sfcoin_amount,
        'usdt_recebido' => $usdt_amount,
        'novo_saldo_sfcoin' => $user['saldo_sfcoin'] - $sfcoin_amount,
        'novo_saldo_usdt' => $user['saldo_usdt'] + $usdt_amount
    ]);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao realizar swap'], 500);
}
?>
