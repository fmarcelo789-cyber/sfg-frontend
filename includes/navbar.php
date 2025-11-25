<?php
// Navbar para páginas autenticadas
$usuario_id = $_SESSION['user_id'] ?? null;
$usuario_nome = $_SESSION['user_nome'] ?? 'Usuário';
?>
<nav class="bg-gray-800 border-b border-gray-700 sticky top-0 z-50">
    <div class="px-6 py-4 flex items-center justify-between">
        <div class="flex items-center space-x-8">
            <h1 class="text-xl font-bold text-white">SFGlobal</h1>
            <div class="hidden md:flex space-x-6">
                <a href="?page=dashboard" class="text-gray-300 hover:text-white transition">Dashboard</a>
                <a href="?page=boards" class="text-gray-300 hover:text-white transition">Boards</a>
                <a href="?page=team" class="text-gray-300 hover:text-white transition">Team</a>
                <a href="?page=finances" class="text-gray-300 hover:text-white transition">Finanças</a>
            </div>
        </div>
        
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <p class="text-white font-semibold"><?php echo htmlspecialchars($usuario_nome); ?></p>
                <p class="text-sm text-gray-400">ID: <?php echo htmlspecialchars($usuario_id); ?></p>
            </div>
            <button hx-post="/api/auth/logout.php" hx-confirm="Deseja sair?"
                class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                Sair
            </button>
        </div>
    </div>
</nav>
