<?php
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sponsor = sanitize($_POST['sponsor'] ?? '');
    
    if (empty($sponsor)) {
        echo '<div class="text-red-400 text-sm">Digite um nome de patrocinador</div>';
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('SELECT id, nome FROM usuarios WHERE username = :username AND ativo = 1');
        $stmt->execute([':username' => $sponsor]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo '<div class="text-green-400 text-sm">✓ Patrocinador válido: ' . htmlspecialchars($user['nome']) . '</div>';
            echo '<input type="hidden" name="sponsor_id" value="' . $user['id'] . '">';
        } else {
            echo '<div class="text-red-400 text-sm">✗ Patrocinador não encontrado</div>';
        }
    } catch (Exception $e) {
        echo '<div class="text-red-400 text-sm">Erro ao verificar patrocinador</div>';
    }
}
?>
