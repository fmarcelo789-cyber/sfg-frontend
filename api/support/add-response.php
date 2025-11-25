<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();

// Processar upload de arquivo se houver
$arquivo_anexo = null;
if (isset($_FILES['anexo']) && $_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/tickets/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($_FILES['anexo']['name'], PATHINFO_EXTENSION);
    $arquivo_anexo = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $arquivo_anexo;
    
    if (!move_uploaded_file($_FILES['anexo']['tmp_name'], $upload_path)) {
        jsonResponse(['error' => 'Erro ao fazer upload do arquivo'], 500);
    }
}

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$mensagem = $_POST['mensagem'] ?? '';

if (empty($mensagem)) {
    jsonResponse(['error' => 'Mensagem é obrigatória'], 400);
}

try {
    // Verificar se o ticket pertence ao usuário
    $stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$ticket_id, $user_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Ticket não encontrado'], 404);
    }

    // Adicionar resposta
    $stmt = $pdo->prepare("
        INSERT INTO ticket_respostas (ticket_id, usuario_id, mensagem, arquivo_anexo, data_resposta) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$ticket_id, $user_id, $mensagem, $arquivo_anexo]);

    // Atualizar status do ticket se estava fechado
    $stmt = $pdo->prepare("
        UPDATE tickets 
        SET status = CASE WHEN status = 'fechado' THEN 'aberto' ELSE status END,
            data_ultima_atualizacao = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$ticket_id]);

    jsonResponse([
        'success' => true,
        'message' => 'Resposta adicionada com sucesso'
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao adicionar resposta'], 500);
}
?>
