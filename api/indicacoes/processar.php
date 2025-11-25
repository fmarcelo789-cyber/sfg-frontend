<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$indicado_id = $input['indicado_id'] ?? 0;
$valor_deposito = $input['valor_deposito'] ?? 10; // $10 padrão

try {
    $pdo->beginTransaction();

    // Buscar dados do indicado
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$indicado_id]);
    $indicado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$indicado) {
        jsonResponse(['error' => 'Usuário não encontrado'], 404);
    }

    // Buscar indicador
    $stmt = $pdo->prepare("SELECT indicador_id FROM indicacoes WHERE indicado_id = ?");
    $stmt->execute([$indicado_id]);
    $indicacao = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($indicacao) {
        $indicador_id = $indicacao['indicador_id'];

        // Processar swap automático: $10 = 100 SFCoin
        $sfcoin_total = $valor_deposito * 10; // $10 = 100 SFCoin
        $sfcoin_indicado = $sfcoin_total / 2; // 50 SFCoin para o indicado
        $sfcoin_board = $sfcoin_total / 2; // 50 SFCoin para entrada no board

        // Atualizar saldo do indicado
        $stmt = $pdo->prepare("UPDATE usuarios SET saldo_sfcoin = saldo_sfcoin + ?, status_deposito = 'ativo' WHERE id = ?");
        $stmt->execute([$sfcoin_indicado, $indicado_id]);

        // Dar comissão ao indicador (50 SFCoin)
        $stmt = $pdo->prepare("UPDATE usuarios SET saldo_sfcoin = saldo_sfcoin + ? WHERE id = ?");
        $stmt->execute([$sfcoin_indicado, $indicador_id]);

        // Registrar no histórico do indicado
        $stmt = $pdo->prepare("
            INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
            VALUES (?, 'swap', ?, 0, NOW())
        ");
        $stmt->execute([$indicado_id, $sfcoin_indicado]);

        // Registrar no histórico do indicador
        $stmt = $pdo->prepare("
            INSERT INTO historico_pontos (usuario_id, tipo, valor, referencia_id, data_criacao) 
            VALUES (?, 'indicacao', ?, ?, NOW())
        ");
        $stmt->execute([$indicador_id, $sfcoin_indicado, $indicado_id]);

        // Colocar o indicado no board nível 0 (Presente)
        $stmt = $pdo->prepare("
            INSERT INTO board_filas (usuario_id, nivel, data_entrada, status) 
            VALUES (?, 0, NOW(), 'ativo')
        ");
        $stmt->execute([$indicado_id]);

        // Atualizar contador de indicações do indicador
        $stmt = $pdo->prepare("
            UPDATE usuarios SET total_indicacoes = (
                SELECT COUNT(*) FROM indicacoes WHERE indicador_id = usuarios.id
            ) WHERE id = ?
        ");
        $stmt->execute([$indicador_id]);
    }

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Indicação processada com sucesso']);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao processar indicação'], 500);
}
?>
