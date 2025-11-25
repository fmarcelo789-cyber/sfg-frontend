<?php
// Sidebar para páginas autenticadas
$pagina_atual = $_GET['page'] ?? 'dashboard';
?>
<aside class="w-64 bg-gray-800 border-r border-gray-700 fixed h-screen overflow-y-auto">
    <div class="p-6">
        <div class="text-center mb-8">
            <div class="inline-block p-3 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg">
                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
            </div>
            <h2 class="text-white font-bold mt-2">SFGlobal</h2>
        </div>

        <nav class="space-y-2">
            <?php
            $menus = [
                ['url' => 'dashboard', 'nome' => 'Dashboard', 'icon' => '📊'],
                ['url' => 'boards', 'nome' => 'Boards', 'icon' => '🏆'],
                ['url' => 'team', 'nome' => 'Meu Time', 'icon' => '👥'],
                ['url' => 'profile', 'nome' => 'Perfil', 'icon' => '👤'],
                ['url' => 'finances', 'nome' => 'Finanças', 'icon' => '💰'],
                ['url' => 'withdraw', 'nome' => 'Saques', 'icon' => '💳'],
                ['url' => 'support', 'nome' => 'Suporte', 'icon' => '💬'],
            ];

            foreach ($menus as $menu):
                $ativo = ($pagina_atual === $menu['url']) ? 'bg-blue-500 text-white' : 'text-gray-400 hover:text-white';
            ?>
                <a href="?page=<?php echo $menu['url']; ?>" 
                    class="block px-4 py-2 rounded-lg transition <?php echo $ativo; ?>">
                    <span class="mr-2"><?php echo $menu['icon']; ?></span><?php echo $menu['nome']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>
