<?php
/**
 * esqueci_senha.php — API endpoint para recuperação de senha
 * Integração com Next.js via JSON
 */
session_start();

// Headers de segurança e CORS
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS para desenvolvimento local
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed_origins = ['http://localhost:3000', 'https://secretfriendglobal.com'];
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    }
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Apenas POST permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido']);
    exit();
}

require_once __DIR__ . '/../../conexao.php';

// PHPMailer (sem Composer)
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    require __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
    require __DIR__ . '/../../PHPMailer/src/SMTP.php';
    require __DIR__ . '/../../PHPMailer/src/Exception.php';
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* =========================
   CONFIG SMTP (Titan)
   ========================= */
$SMTP_HOST = 'smtp.titan.email';
$SMTP_USER = 'support@secretfriendglobal.com';
$SMTP_PASS = 'Nova@29160321';               // <<< TROCAR
$SMTP_CAFILE = '/etc/ssl/certs/ca-bundle.crt';
$FROM_MAIL  = $SMTP_USER;
$FROM_NAME  = 'Secret Friend Global';
$REPLY_MAIL = $SMTP_USER;
$REPLY_NAME = 'Suporte SFG';
$RETURN_PATH= $SMTP_USER;

/* =========================
   CSRF helpers
   ========================= */
function gerarTokenCSRF() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* =========================
   Rate limiting helpers
   ========================= */
function verificarRateLimit($conn, $ip, $email = null) {
    // IP: máx 15/h
    $stmt = $conn->prepare("SELECT COUNT(*) AS tentativas
                              FROM tokens_recuperacao
                             WHERE ip_address = ?
                               AND data_criacao > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $tentativas_ip = (int)($stmt->get_result()->fetch_assoc()['tentativas'] ?? 0);
    if ($tentativas_ip >= 15) {
        return ['bloqueado' => true, 'motivo' => 'Muitas tentativas do seu IP. Tente novamente em 1 hora.'];
    }

    // E-mail: máx 15/h
    if ($email) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS tentativas
                                  FROM tokens_recuperacao
                                 WHERE email = ?
                                   AND data_criacao > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $tentativas_email = (int)($stmt->get_result()->fetch_assoc()['tentativas'] ?? 0);
        if ($tentativas_email >= 15) {
            return ['bloqueado' => true, 'motivo' => 'Muitas tentativas para este email. Tente novamente em 1 hora.'];
        }
    }
    return ['bloqueado' => false];
}

/* =========================
   Envio de e-mail (PHPMailer + Titan)
   ========================= */
function enviarEmailRecuperacao($email, $nome, $token, array $cfg) {
    $assunto = "Recuperação de Senha - Secret Friend Global";
    $link = "https://secretfriendglobal.com/nova_senha.php?token=" . urlencode($token);

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;color:#e5e7eb;background:#0a0f1c;padding:20px'>
      <div style='max-width:600px;margin:0 auto;background:#0c1424;border:1px solid #1f2937;border-radius:12px;overflow:hidden'>
        <div style='background:#0b1526;color:#ffe9c2;padding:20px'>
          <h1 style='margin:0'>🔐 Recuperação de Senha</h1>
          <p style='margin:6px 0 0'>Secret Friend Global</p>
        </div>
        <div style='padding:22px'>
          <p>Olá, ".htmlspecialchars($nome)."</p>
          <p>Se você fez esta solicitação, clique abaixo (expira em 1 hora):</p>
          <p style='text-align:center;margin:18px 0'>
            <a href='{$link}' style='display:inline-block;background:#f59e0b;color:#111;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:700'>Redefinir Senha</a>
          </p>
          <p style='color:#9aa4b2;font-size:12px'>Se não foi você, ignore este e-mail.</p>
        </div>
      </div>
    </div>";
    $txt = "Olá, {$nome}\n\nUse o link para redefinir sua senha (expira em 1 hora):\n{$link}\n\n";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['user'];
        $mail->Password   = $cfg['pass'];
        $mail->Port       = 587;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->SMTPOptions = [
          'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
            'cafile'            => $cfg['cafile'],
            'peer_name'         => 'smtp.titan.email',
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
          ]
        ];

        // DMARC alinhado
        $mail->setFrom($cfg['from_mail'], $cfg['from_name']);
        $mail->addReplyTo($cfg['reply_mail'], $cfg['reply_name']);
        $mail->Sender = $cfg['return_path'];

        $mail->addAddress($email, $nome);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body    = $html;
        $mail->AltBody = $txt;

        try {
            return $mail->send();
        } catch (Exception $e587) {
            // fallback de porta (587→465)
            $mail->smtpClose();
            $mail->Port       = 465;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->SMTPOptions['ssl']['crypto_method'] = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            return $mail->send();
        }
    } catch (Exception $e) {
        error_log('[SMTP reset] '.$mail->ErrorInfo);
        return false;
    }
}

/* =========================
   Fluxo principal
   ========================= */
try {
    // Ler dados JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['email'])) {
        throw new Exception('Dados inválidos');
    }

    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (empty($email)) {
        throw new Exception('Por favor, informe seu email.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido.');
    }

    // Rate limit
    $rate_limit = verificarRateLimit($conn, $ip_address, $email);
    if ($rate_limit['bloqueado']) {
        throw new Exception($rate_limit['motivo']);
    }

    // Busca usuário
    $stmt = $conn->prepare("SELECT id, nome, email, ativo FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Resposta genérica por segurança
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Se o email estiver cadastrado, você receberá as instruções de recuperação em alguns minutos.'
        ]);
        exit();
    }

    $usuario = $result->fetch_assoc();

    if (empty($usuario['ativo'])) {
        // Resposta genérica por segurança
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Se o email estiver cadastrado, você receberá as instruções de recuperação em alguns minutos.'
        ]);
        exit();
    }

    // Invalida tokens anteriores não usados
    $stmt = $conn->prepare("UPDATE tokens_recuperacao SET usado = 1 WHERE email = ? AND usado = 0");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    // Novo token (1h)
    $token = bin2hex(random_bytes(32));
    $expira_em = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Salva token
    $stmt = $conn->prepare("INSERT INTO tokens_recuperacao (email, token, expira_em, ip_address, data_criacao, usado)
                            VALUES (?, ?, ?, ?, NOW(), 0)");
    $stmt->bind_param("ssss", $email, $token, $expira_em, $ip_address);
    $stmt->execute();

    // Envia e-mail via SMTP Titan
    $ok = enviarEmailRecuperacao($email, $usuario['nome'], $token, [
        'host'        => $SMTP_HOST,
        'user'        => $SMTP_USER,
        'pass'        => $SMTP_PASS,
        'cafile'      => $SMTP_CAFILE,
        'from_mail'   => $FROM_MAIL,
        'from_name'   => $FROM_NAME,
        'reply_mail'  => $REPLY_MAIL,
        'reply_name'  => $REPLY_NAME,
        'return_path' => $RETURN_PATH,
    ]);

    if ($ok) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Instruções de recuperação enviadas para seu email. Verifique sua caixa de entrada e spam.'
        ]);
    } else {
        throw new Exception('Erro ao enviar email. Tente novamente em alguns minutos.');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
}
?>
