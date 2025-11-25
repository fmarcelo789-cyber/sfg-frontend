<?php
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método não permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$senha = $input['senha'] ?? '';

if (empty($email) || empty($senha)) {
    jsonResponse(['error' => 'Email e senha são obrigatórios'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha'])) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_nome'] = $user['nome'];

        // Atualizar último login
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        jsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                'email' => $user['email'],
                'saldo_sfcoin' => (float)$user['saldo_sfcoin'],
                'board_atual' => (int)$user['board_atual'],
                'total_indicacoes' => (int)$user['total_indicacoes']
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Credenciais inválidas'], 401);
    }
} catch (Exception $e) {
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}
?>
