<?php
function renderButton($label, $type = 'button', $variant = 'primary', $attributes = '') {
    $baseClasses = 'px-4 py-2 rounded-lg font-semibold transition duration-200 focus:outline-none';
    
    $variants = [
        'primary' => 'bg-gradient-to-r from-blue-500 to-purple-500 text-white hover:from-blue-600 hover:to-purple-600',
        'secondary' => 'bg-gray-700 text-white hover:bg-gray-600',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'success' => 'bg-green-600 text-white hover:bg-green-700',
    ];
    
    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']);
    
    return "<button type=\"{$type}\" class=\"{$classes}\" {$attributes}>{$label}</button>";
}
?>
