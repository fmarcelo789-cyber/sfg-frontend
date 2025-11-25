<?php
require_once __DIR__ . '/../includes/config.php';

// Roteamento simples
$page = $_GET['page'] ?? 'home';

switch($page) {
    case 'login':
        $pageTitle = 'Login - SFGlobal';
        ob_start();
        include __DIR__ . '/../pages/login.php';
        $content = ob_get_clean();
        break;
    
    case 'cadastro':
        $pageTitle = 'Cadastro - SFGlobal';
        ob_start();
        include __DIR__ . '/../pages/cadastro.php';
        $content = ob_get_clean();
        break;
    
    case 'dashboard':
        requireAuth();
        $pageTitle = 'Dashboard - SFGlobal';
        ob_start();
        include __DIR__ . '/../pages/dashboard.php';
        $content = ob_get_clean();
        break;
    
    case 'deposito':
        requireAuth();
        $pageTitle = 'Primeiro Depósito - SFGlobal';
        ob_start();
        include __DIR__ . '/../pages/primeiro-deposito.php';
        $content = ob_get_clean();
        break;
    
    default:
        if (isAuthenticated()) {
            header('Location: ?page=dashboard');
        } else {
            header('Location: ?page=login');
        }
        exit;
}

include __DIR__ . '/../includes/layout.php';
?>
