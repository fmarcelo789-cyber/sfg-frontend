<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$user_id = verificarSessao();

// Processar upload de arquivo se houver
$arquivo_evidencia = null;
if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/tickets/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
    $arquivo_evidencia = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $arquivo_evidencia;
    
    if (!move_uploaded_file($_FILES['evidencia']['tmp_name'], $upload_path)) {
        jsonResponse(['error' => 'Erro ao fazer upload do arquivo'], 500);
    }
}

$assunto = $_POST['assunto'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$prioridade = $_POST['prioridade'] ?? 'media';
$descricao = $_POST['descricao'] ?? '';

if (empty($assunto) || empty($categoria) || empty($descricao)) {
    jsonResponse(['error' => 'Assunto, categoria e descrição são obrigatórios'], 400);
}

try {
    // Gerar número do ticket
    $numero_ticket = 'SF' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("
        INSERT INTO tickets (usuario_id, numero_ticket, assunto, categoria, prioridade, 
                           descricao, arquivo_evidencia, status, data_criacao) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'aberto', NOW())
    ");
    $stmt->execute([
        $user_id, 
        $numero_ticket, 
        $assunto, 
        $categoria, 
        $prioridade, 
        $descricao, 
        $arquivo_evidencia
    ]);

    $ticket_id = $pdo->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Ticket criado com sucesso',
        'ticket_id' => $ticket_id,
        'numero_ticket' => $numero_ticket
    ]);

} catch (Exception $e) {
    jsonResponse(['error' => 'Erro ao criar ticket'], 500);
}
?>
