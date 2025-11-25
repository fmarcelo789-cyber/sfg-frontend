<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$senha_atual = $input['senha_atual'] ?? '';
$nova_senha = $input['nova_senha'] ?? '';
$confirmar_senha = $input['confirmar_senha'] ?? '';

if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_senha)) {
    jsonResponse(['error' => 'Todos os campos são obrigatórios'], 400);
}

if ($nova_senha !== $confirmar_senha) {
    jsonResponse(['error' => 'Nova senha e confirmação não coincidem'], 400);
}

if (strlen($nova_senha) < 6) {
    jsonResponse(['error' => 'Nova senha deve ter pelo menos 6 caracteres'], 400);
}

try {
    // Verificar senha atual
    $stmt = $pdo->prepare("SELECT senha, email FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($senha_atual, $user['senha'])) {
        jsonResponse(['error' => 'Senha atual incorreta'], 400);
    }

    // Gerar código de validação
    $codigo_validacao = rand(100000, 999999);
    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

    // Armazenar temporariamente
    $stmt = $pdo->prepare("
        UPDATE usuarios 
        SET nova_senha_temp = ?, codigo_validacao = ?, validacao_pendente = 1 
        WHERE id = ?
    ");
    $stmt->execute([$nova_senha_hash, $codigo_validacao, $user_id]);

    // Enviar email (simulado)
    $assunto = "SFGlobal - Validação de Alteração de Senha";
    $mensagem = "Seu código de validação para alteração de senha é: $codigo_validacao";
    
    jsonResponse([
        'success' => true, 
        'message' => 'Código de validação enviado para seu email.',
        'codigo_validacao' => $codigo_validacao // Remover em produção
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao alterar senha'], 500);
}
?>
