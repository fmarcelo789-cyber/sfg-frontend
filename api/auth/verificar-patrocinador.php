<?php
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit;
}

$usuario = $_GET['usuario'] ?? '';

if (empty(trim($usuario))) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário é obrigatório']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome, usuario FROM usuarios WHERE usuario = ? OR link_indicacao = ? LIMIT 1");
    $stmt->execute([$usuario, $usuario]);
    $patrocinador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patrocinador) {
        echo json_encode([
            'sucesso' => true,
            'patrocinador' => [
                'id' => (int)$patrocinador['id'],
                'nome' => $patrocinador['nome'],
                'usuario' => $patrocinador['usuario']
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'erro' => 'Patrocinador não encontrado']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno do servidor']);
}
?>
