<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$indicado_id = $input['indicado_id'] ?? 0;
$novo_indicador_id = $input['novo_indicador_id'] ?? 0;

try {
    $pdo->beginTransaction();

    // Verificar se o usuário tem permissão para alterar (deve ser o indicador atual)
    $stmt = $pdo->prepare("SELECT indicador_id FROM indicacoes WHERE indicado_id = ?");
    $stmt->execute([$indicado_id]);
    $indicacao_atual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$indicacao_atual || $indicacao_atual['indicador_id'] != $user_id) {
        jsonResponse(['error' => 'Sem permissão para alterar este indicador'], 403);
    }

    // Verificar se o novo indicador existe
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt->execute([$novo_indicador_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Novo indicador não encontrado'], 404);
    }

    // Alterar o indicador (sem alterar valores já recebidos)
    $stmt = $pdo->prepare("UPDATE indicacoes SET indicador_id = ? WHERE indicado_id = ?");
    $stmt->execute([$novo_indicador_id, $indicado_id]);

    // Atualizar contador de indicações do antigo indicador
    $stmt = $pdo->prepare("
        UPDATE usuarios SET total_indicacoes = (
            SELECT COUNT(*) FROM indicacoes WHERE indicador_id = usuarios.id
        ) WHERE id = ?
    ");
    $stmt->execute([$user_id]);

    // Atualizar contador de indicações do novo indicador
    $stmt = $pdo->prepare("
        UPDATE usuarios SET total_indicacoes = (
            SELECT COUNT(*) FROM indicacoes WHERE indicador_id = usuarios.id
        ) WHERE id = ?
    ");
    $stmt->execute([$novo_indicador_id]);

    $pdo->commit();
    jsonResponse(['success' => true, 'message' => 'Indicador alterado com sucesso']);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao alterar indicador'], 500);
}
?>
