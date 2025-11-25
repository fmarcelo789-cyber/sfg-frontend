<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);

// Campos permitidos para atualização
$campos_permitidos = [
    'nome', 'whatsapp', 'pais', 'estado', 
    'carteira1', 'carteira1_comentario',
    'carteira2', 'carteira2_comentario', 
    'carteira3', 'carteira3_comentario'
];

try {
    $pdo->beginTransaction();

    // Gerar código de validação
    $codigo_validacao = rand(100000, 999999);
    
    // Buscar email do usuário
    $stmt = $pdo->prepare("SELECT email FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonResponse(['error' => 'Usuário não encontrado'], 404);
    }

    // Preparar campos para atualização
    $campos_update = [];
    $valores = [];
    
    foreach ($campos_permitidos as $campo) {
        if (isset($input[$campo])) {
            $campos_update[] = "$campo = ?";
            $valores[] = $input[$campo];
        }
    }

    if (empty($campos_update)) {
        jsonResponse(['error' => 'Nenhum campo para atualizar'], 400);
    }

    // Adicionar código de validação
    $campos_update[] = "codigo_validacao = ?";
    $campos_update[] = "validacao_pendente = 1";
    $valores[] = $codigo_validacao;
    $valores[] = $user_id;

    // Atualizar dados
    $sql = "UPDATE usuarios SET " . implode(', ', $campos_update) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);

    // Enviar email de validação (simulado)
    $assunto = "SFGlobal - Validação de Alteração de Perfil";
    $mensagem = "Seu código de validação é: $codigo_validacao";
    
    // Aqui você implementaria o envio real do email
    // mail($user['email'], $assunto, $mensagem);

    $pdo->commit();
    
    jsonResponse([
        'success' => true, 
        'message' => 'Dados atualizados. Verifique seu email para o código de validação.',
        'codigo_validacao' => $codigo_validacao // Remover em produção
    ]);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao atualizar perfil'], 500);
}
?>
