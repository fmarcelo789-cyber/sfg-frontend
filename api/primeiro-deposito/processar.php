<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

include '../conexao.php';

$user_id = $_SESSION['id'];
$metodo = $_POST['metodo'] ?? '';
$valor_enviado = floatval($_POST['valor_enviado'] ?? 0);

try {
    if ($valor_enviado !== 6.00) {
        throw new Exception('Valor deve ser exatamente $6.00 para ativar sua conta');
    }

    $conn->begin_transaction();

    if ($metodo === 'usdt') {
        // Processar depósito USDT
        $hash_transacao = trim($_POST['hash_transacao'] ?? '');
        
        if (empty($hash_transacao) || !preg_match('/^0x[a-fA-F0-9]{64}$/', $hash_transacao)) {
            throw new Exception('Hash da transação inválido');
        }

        // Verificar se hash já existe
        $stmt = $conn->prepare("SELECT id FROM depositos WHERE hash_transacao = ?");
        $stmt->bind_param("s", $hash_transacao);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception('Esta transação já foi registrada');
        }

        // Inserir depósito USDT
        $stmt = $conn->prepare("INSERT INTO depositos (usuario_id, valor, metodo, hash_transacao, status, observacoes, data_criacao, primeiro_deposito) VALUES (?, ?, 'usdt_bep20', ?, 'pendente', 'Primeiro depósito - Ativação de conta', NOW(), 1)");
        $stmt->bind_param("ids", $user_id, $valor_enviado, $hash_transacao);
        $stmt->execute();

        $message = 'Depósito USDT registrado! Aguarde a validação automática.';
        
    } elseif ($metodo === 'pix') {
        // Processar depósito PIX
        if (!isset($_FILES['comprovante']) || $_FILES['comprovante']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Comprovante de pagamento é obrigatório');
        }

        $comprovante = $_FILES['comprovante'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        
        if (!in_array($comprovante['type'], $allowed_types)) {
            throw new Exception('Formato de arquivo não permitido. Use JPG, PNG ou PDF');
        }

        if ($comprovante['size'] > 5 * 1024 * 1024) { // 5MB
            throw new Exception('Arquivo muito grande. Máximo 5MB');
        }

        // Salvar comprovante
        $upload_dir = '../uploads/comprovantes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($comprovante['name'], PATHINFO_EXTENSION);
        $filename = 'pix_' . $user_id . '_' . time() . '.' . $file_extension;
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($comprovante['tmp_name'], $filepath)) {
            throw new Exception('Erro ao salvar comprovante');
        }

        // Inserir depósito PIX
        $stmt = $conn->prepare("INSERT INTO depositos (usuario_id, valor, metodo, comprovante_path, status, observacoes, data_criacao, primeiro_deposito) VALUES (?, ?, 'pix', ?, 'pendente', 'Primeiro depósito PIX - Aguardando análise', NOW(), 1)");
        $stmt->bind_param("ids", $user_id, $valor_enviado, $filename);
        $stmt->execute();

        $message = 'Comprovante PIX enviado! Sua conta será ativada após análise.';
        
    } else {
        throw new Exception('Método de pagamento inválido');
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'redirect' => '/primeiro-deposito'
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
