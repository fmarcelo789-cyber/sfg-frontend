<?php
/**
 * Componente de alerta reutilizável
 * @param string $tipo 'success', 'error', 'warning', 'info'
 * @param string $mensagem Mensagem do alerta
 */
function alert(string $tipo, string $mensagem): void {
    $cores = [
        'success' => 'bg-green-500/20 border-green-500 text-green-200',
        'error' => 'bg-red-500/20 border-red-500 text-red-200',
        'warning' => 'bg-yellow-500/20 border-yellow-500 text-yellow-200',
        'info' => 'bg-blue-500/20 border-blue-500 text-blue-200'
    ];
    
    $cor = $cores[$tipo] ?? $cores['info'];
    ?>
    <div class="p-4 rounded-lg border <?php echo $cor; ?>">
        <?php echo htmlspecialchars($mensagem); ?>
    </div>
    <?php
}
?>
