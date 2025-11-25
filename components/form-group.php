<?php
/**
 * Componente de grupo de formulário reutilizável
 * @param string $label Rótulo do campo
 * @param string $name Nome do campo
 * @param string $type Tipo do input
 * @param array $attributes Atributos HTML adicionais
 */
function formGroup(string $label, string $name, string $type = 'text', array $attributes = []): void {
    $required = $attributes['required'] ?? false;
    $placeholder = $attributes['placeholder'] ?? '';
    $value = $attributes['value'] ?? '';
    $class = $attributes['class'] ?? '';
    
    $req_mark = $required ? '<span class="text-red-500">*</span>' : '';
    $req_attr = $required ? 'required' : '';
    ?>
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">
            <?php echo htmlspecialchars($label); ?> <?php echo $req_mark; ?>
        </label>
        <input type="<?php echo htmlspecialchars($type); ?>" 
            name="<?php echo htmlspecialchars($name); ?>"
            placeholder="<?php echo htmlspecialchars($placeholder); ?>"
            value="<?php echo htmlspecialchars($value); ?>"
            <?php echo $req_attr; ?>
            class="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg focus:outline-none focus:border-blue-500 text-white placeholder-gray-400 <?php echo $class; ?>">
    </div>
    <?php
}
?>
