<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();
$input = json_decode(file_get_contents('php://input'), true);
$valor = (float)($input['valor'] ?? 0);
$carteira_id = (int)($input['carteira_id'] ?? 1);
$metodo_validacao = $input['metodo_validacao'] ?? 'email'; // email ou sms

if ($valor <= 0) {
    jsonResponse(['error' => 'Valor deve ser maior que zero'], 400);
}

if ($valor < 10) {
    jsonResponse(['error' => 'Valor mínimo para saque é $10.00'], 400);
}

if (!in_array($carteira_id, [1, 2, 3])) {
    jsonResponse(['error' => 'Carteira inválida'], 400);
}

try {
    $pdo->beginTransaction();

    // Verificar saldo do usuário
    $stmt = $pdo->prepare("
        SELECT saldo_usdt, email, whatsapp, 
               carteira1, carteira1_comentario,
               carteira2, carteira2_comentario, 
               carteira3, carteira3_comentario
        FROM usuarios WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['saldo_usdt'] < $valor) {
        jsonResponse(['error' => 'Saldo insuficiente'], 400);
    }

    // Verificar se a carteira está cadastrada
    $carteira_campo = "carteira{$carteira_id}";
    if (empty($user[$carteira_campo])) {
        jsonResponse(['error' => 'Carteira não cadastrada. Configure no seu perfil.'], 400);
    }

    // Gerar código de validação
    $codigo_validacao = rand(100000, 999999);

    // Criar solicitação de saque
    $stmt = $pdo->prepare("
        INSERT INTO saques (usuario_id, valor, carteira_endereco, carteira_comentario, 
                           codigo_validacao, metodo_validacao, status, data_solicitacao) 
        VALUES (?, ?, ?, ?, ?, ?, 'pendente_validacao', NOW())
    ");
    $stmt->execute([
        $user_id, 
        $valor, 
        $user[$carteira_campo],
        $user["{$carteira_campo}_comentario"],
        $codigo_validacao,
        $metodo_validacao
    ]);

    $saque_id = $pdo->lastInsertId();

    // Enviar código de validação
    if ($metodo_validacao === 'email') {
        // Simular envio de email
        $assunto = "SFGlobal - Código de Validação para Saque";
        $mensagem = "Seu código de validação para saque de \${$valor} é: {$codigo_validacao}";
        // mail($user['email'], $assunto, $mensagem);
    } else {
        // Simular envio de SMS
        $mensagem = "SFGlobal: Seu código para saque de \${$valor} é: {$codigo_validacao}";
        // Aqui implementaria envio de SMS
    }

    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Solicitação de saque criada. Verifique seu ' . ($metodo_validacao === 'email' ? 'email' : 'SMS') . ' para o código de validação.',
        'saque_id' => $saque_id,
        'codigo_validacao' => $codigo_validacao // Remover em produção
    ]);

} catch (Exception $e) {
    $pdo->rollback();
    jsonResponse(['error' => 'Erro ao processar saque'], 500);
}
?>
