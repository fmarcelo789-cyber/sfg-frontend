<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configurações do banco de dados
$host = 'localhost';
$dbname = 'sfglobal_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro de conexão com banco de dados']);
    exit;
}

// Função para verificar sessão
function verificarSessao() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Usuário não autenticado']);
        exit;
    }
    return $_SESSION['user_id'];
}

// Função para resposta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Configurações dos boards
$boards_config = [
    0 => ['nome' => 'Presente', 'premio' => 0, 'icone' => 'gift', 'entrada' => 50],
    1 => ['nome' => 'Sonho', 'premio' => 100, 'icone' => 'cloud', 'entrada' => 0],
    2 => ['nome' => 'Fé', 'premio' => 200, 'icone' => 'heart', 'entrada' => 0, 'indicacoes_necessarias' => 4],
    3 => ['nome' => 'Visão', 'premio' => 400, 'icone' => 'eye', 'entrada' => 0],
    4 => ['nome' => 'Ação', 'premio' => 800, 'icone' => 'zap', 'entrada' => 0],
    5 => ['nome' => 'Compromisso', 'premio' => 1600, 'icone' => 'handshake', 'entrada' => 0],
    6 => ['nome' => 'Atitude', 'premio' => 3200, 'icone' => 'trending-up', 'entrada' => 0],
    7 => ['nome' => 'Família', 'premio' => 6400, 'icone' => 'users', 'entrada' => 0, 'indicacoes_necessarias' => 20],
    8 => ['nome' => 'Valores', 'premio' => 10000, 'icone' => 'star', 'entrada' => 0],
    9 => ['nome' => 'Generosidade', 'premio' => 20000, 'icone' => 'gift-heart', 'entrada' => 0],
    10 => ['nome' => 'Confiança', 'premio' => 40000, 'icone' => 'shield', 'entrada' => 0],
    11 => ['nome' => 'Determinação', 'premio' => 80000, 'icone' => 'target', 'entrada' => 0],
    12 => ['nome' => 'Prosperidade', 'premio' => 120000, 'icone' => 'crown', 'entrada' => 0, 'indicacoes_necessarias' => 84],
    13 => ['nome' => 'Plenitude', 'premio' => 240000, 'icone' => 'infinity', 'entrada' => 0],
    14 => ['nome' => 'Paz', 'premio' => 480000, 'icone' => 'peace', 'entrada' => 0],
    15 => ['nome' => 'Legado', 'premio' => 1000000, 'icone' => 'trophy', 'entrada' => 0]
];

// Configurações do SFG Prime
$sfg_prime_config = [
    1 => ['nome' => 'Início', 'premio' => 0, 'entrada' => 10000],
    2 => ['nome' => 'Impulso', 'premio' => 2000, 'entrada' => 0],
    3 => ['nome' => 'Domínio', 'premio' => 4000, 'entrada' => 0],
    4 => ['nome' => 'Máximo', 'premio' => 8000, 'entrada' => 0],
    5 => ['nome' => 'Zênite', 'premio' => 12000, 'entrada' => 0],
    6 => ['nome' => 'Sigma', 'premio' => 20000, 'entrada' => 0],
    7 => ['nome' => 'Vértice', 'premio' => 35000, 'entrada' => 0],
    8 => ['nome' => 'Ápice', 'premio' => 50000, 'entrada' => 0]
];
?>
