<?php
declare(strict_types=1);

/**
 * API de Cadastro - SFGlobal Platform
 * Integração com EmailManager para envio de boas-vindas
 * Sistema de carteiras e validação completa
 */

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Garantir que não há sessão aberta antes dos includes
if (session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}
@ini_set('session.auto_start', '0');

// Log local + Request ID
$LOG_DIR = __DIR__ . '/../_logs';
if (!is_dir($LOG_DIR)) { @mkdir($LOG_DIR, 0755, true); }
ini_set('error_log', $LOG_DIR . '/php-error.log');

$REQ_ID = bin2hex(random_bytes(6));
header('X-Request-Id: '.$REQ_ID);

if (!function_exists('logd')) {
    function logd($msg, $ctx = []) {
        global $REQ_ID, $LOG_DIR;
        $line = '[cadastro]['.$REQ_ID.'] '.$msg;
        if ($ctx) $line .= ' | '.json_encode($ctx, JSON_UNESCAPED_UNICODE);
        error_log($line);
        @file_put_contents($LOG_DIR.'/app.log', date('c').' '.$line.PHP_EOL, FILE_APPEND);
    }
}

// Helper de resposta JSON
function sendJsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    @ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// POST-only
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST', true, 405);
    sendJsonResponse(['sucesso'=>false,'erro'=>'Método não permitido (use POST)','reqId'=>$REQ_ID], 405);
}

logd('Método OK');

try {
    // Includes necessários
    $pathConfig = __DIR__ . '/../config.php';
    $pathEmail = __DIR__ . '/../classes/EmailManager.php';
    
    if (!file_exists($pathConfig)) {
        logd('config.php não encontrado');
        sendJsonResponse(['sucesso'=>false,'erro'=>'Configuração não encontrada','reqId'=>$REQ_ID], 500);
    }
    
    if (!file_exists($pathEmail)) {
        logd('EmailManager.php não encontrado');
        sendJsonResponse(['sucesso'=>false,'erro'=>'Sistema de email não encontrado','reqId'=>$REQ_ID], 500);
    }
    
    require_once $pathConfig;
    require_once $pathEmail;
    
    logd('Includes OK');
    
} catch (Throwable $e) {
    logd('EXCEÇÃO nos includes', ['msg'=>$e->getMessage()]);
    sendJsonResponse(['sucesso'=>false,'erro'=>'Erro de configuração do servidor','reqId'=>$REQ_ID], 500);
}

// Reabre sessão
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

try {
    // Receber dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    // Validação de campos obrigatórios
    $required = ['nome', 'usuario', 'email', 'whatsapp', 'senha', 'confirmar_senha', 'id_indicador', 'carteiras'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
            throw new Exception("Campo {$field} é obrigatório");
        }
    }
    
    // Validações específicas
    if (!isset($input['maior_idade']) || !$input['maior_idade']) {
        throw new Exception('Você deve confirmar que é maior de 18 anos');
    }
    
    if (!isset($input['termos']) || !$input['termos']) {
        throw new Exception('Você deve aceitar os termos de uso');
    }
    
    $nome = trim($input['nome']);
    $usuario = trim($input['usuario']);
    $email = trim($input['email']);
    $whatsapp = trim($input['whatsapp']);
    $pais = trim($input['pais'] ?? 'Brasil');
    $estado = trim($input['estado'] ?? '');
    $senha = $input['senha'];
    $confirmar_senha = $input['confirmar_senha'];
    $id_indicador = (int)$input['id_indicador'];
    $carteiras = $input['carteiras'] ?? [];
    
    // Validações
    if (strlen($nome) < 2) throw new Exception('Nome deve ter pelo menos 2 caracteres');
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $usuario)) throw new Exception('Usuário deve ter entre 3 e 20 caracteres (letras, números e _)');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email inválido');
    if (strlen($senha) < 6) throw new Exception('Senha deve ter pelo menos 6 caracteres');
    if ($senha !== $confirmar_senha) throw new Exception('Senhas não coincidem');
    if ($id_indicador <= 0) throw new Exception('Patrocinador é obrigatório');
    
    // Validar carteiras
    if (empty($carteiras) || !is_array($carteiras)) {
        throw new Exception('Pelo menos uma carteira é obrigatória');
    }
    
    $carteira_principal = $carteiras[0] ?? null;
    if (!$carteira_principal || empty($carteira_principal['tipo']) || empty(trim($carteira_principal['endereco']))) {
        throw new Exception('A primeira carteira é obrigatória');
    }
    
    logd('Validações OK', ['usuario'=>$usuario, 'email'=>$email, 'carteiras'=>count($carteiras)]);
    
    // Verificar patrocinador
    $stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id = ?");
    $stmt->execute([$id_indicador]);
    $patrocinador = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patrocinador) {
        throw new Exception('Patrocinador não encontrado');
    }
    
    logd('Patrocinador OK', ['id'=>$id_indicador, 'nome'=>$patrocinador['nome']]);
    
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Verificar duplicidade
    $stmt = $pdo->prepare("SELECT usuario, email FROM usuarios WHERE usuario = ? OR email = ? LIMIT 1");
    $stmt->execute([$usuario, $email]);
    $duplicado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($duplicado) {
        if ($duplicado['usuario'] === $usuario) {
            throw new Exception('Nome de usuário já está em uso');
        }
        if ($duplicado['email'] === $email) {
            throw new Exception('Email já está cadastrado');
        }
    }
    
    // Gerar link de indicação único
    $tentativas = 0;
    do {
        $link_indicacao = substr(md5($usuario.'_'.uniqid('', true).'_'.microtime(true).'_'.$tentativas), 0, 12);
        $stmt = $pdo->prepare("SELECT 1 FROM usuarios WHERE link_indicacao = ? LIMIT 1");
        $stmt->execute([$link_indicacao]);
        $existe = $stmt->fetch();
        $tentativas++;
    } while ($existe && $tentativas < 5);
    
    if ($existe) {
        throw new Exception('Erro ao gerar link de indicação único');
    }
    
    // Hash da senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Inserir usuário
    $sql = "INSERT INTO usuarios 
            (nome, usuario, email, whatsapp, senha, id_indicador, link_indicacao, pais, estado, data_cadastro, status, email_confirmado) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'ativo', 1)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nome, $usuario, $email, $whatsapp, $senha_hash, $id_indicador, $link_indicacao, $pais, $estado]);
    
    $novo_id = $pdo->lastInsertId();
    
    // Inserir carteiras
    $sql_carteira = "INSERT INTO usuario_carteiras (usuario_id, tipo, endereco, comentario, principal, data_criacao) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt_carteira = $pdo->prepare($sql_carteira);
    
    foreach ($carteiras as $index => $carteira) {
        if (!empty($carteira['tipo']) && !empty(trim($carteira['endereco']))) {
            $principal = ($index === 0) ? 1 : 0; // Primeira carteira é principal
            $comentario = trim($carteira['comentario'] ?? '');
            
            $stmt_carteira->execute([
                $novo_id,
                $carteira['tipo'],
                trim($carteira['endereco']),
                $comentario,
                $principal
            ]);
        }
    }
    
    $pdo->commit();
    logd('Usuário criado com sucesso', ['id'=>$novo_id]);
    
    // Enviar email de boas-vindas
    try {
        $emailManager = new EmailManager();
        $dados_usuario = [
            'nome' => $nome,
            'usuario' => $usuario,
            'email' => $email,
            'link_indicacao' => $link_indicacao,
            'id' => $novo_id,
            'whatsapp' => $whatsapp,
            'pais' => $pais,
            'estado' => $estado
        ];
        
        $resultado_email = $emailManager->enviarBoasVindas($dados_usuario);
        logd('Email enviado', ['sucesso'=>$resultado_email['sucesso']]);
        
    } catch (Throwable $e) {
        logd('Erro no email', ['msg'=>$e->getMessage()]);
        // Não falha o cadastro se o email der erro
    }
    
    // Resposta de sucesso
    sendJsonResponse([
        'sucesso' => true,
        'mensagem' => 'Conta criada com sucesso! Verifique seu email.',
        'usuario' => [
            'id' => $novo_id,
            'nome' => $nome,
            'usuario' => $usuario,
            'link_indicacao' => $link_indicacao
        ],
        'reqId' => $REQ_ID
    ], 201);
    
} catch (Throwable $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    
    logd('ERRO no cadastro', ['msg'=>$e->getMessage()]);
    sendJsonResponse([
        'sucesso' => false,
        'erro' => $e->getMessage(),
        'reqId' => $REQ_ID
    ], 400);
}
?>
