<?php
// Verificar autenticação se necessário
$requireAuth = $_GET['auth'] ?? false;
if ($requireAuth && !isAuthenticated()) {
    header('Location: /pages/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'SFGlobal Platform'; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <style>
        :root {
            --color-primary: #3b82f6;
            --color-secondary: #8b5cf6;
            --color-accent: #10b981;
        }
        
        .htmx-request.htmx-settling .htmx-swapping {
            opacity: 0;
            transition: opacity 200ms ease-out;
        }
        
        .htmx-request.htmx-settled .htmx-settling {
            opacity: 1;
            transition: opacity 200ms ease-in;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-950 text-gray-100">
    <div class="flex min-h-screen">
        <?php if (isAuthenticated()): ?>
            <?php include __DIR__ . '/sidebar.php'; ?>
        <?php endif; ?>
        
        <div class="flex-1">
            <?php if (isAuthenticated()): ?>
                <?php include __DIR__ . '/navbar.php'; ?>
            <?php endif; ?>
            
            <main class="<?php echo isAuthenticated() ? 'ml-64 p-8' : 'p-8'; ?>">
                <?php echo $content ?? ''; ?>
            </main>
        </div>
    </div>
    
    <script>
        // Configuração HTMX
        htmx.config.defaultIndicatorStyle = "spinner";
        htmx.config.defaultSwapStyle = "innerHTML";
        
        // Evento customizado para HTMX
        document.body.addEventListener('htmx:afterSwap', function(evt) {
            console.log('HTMX swap realizado');
        });
    </script>
</body>
</html>
