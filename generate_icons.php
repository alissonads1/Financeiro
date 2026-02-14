<?php
// Script para gerar ícones do PWA
function createIcon($size)
{
    $im = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($im, 79, 70, 229); // #4f46e5
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefill($im, 0, 0, $bg);

    // Draw a simple currency symbol style (Circle with $)
    imagefilledellipse($im, $size / 2, $size / 2, $size * 0.6, $size * 0.6, $fg);

    // Save
    imagepng($im, "icon-$size.png");
    imagedestroy($im);
}

if (function_exists('imagecreatetruecolor')) {
    createIcon(192);
    createIcon(512);
    echo "Icons created successfully.";
} else {
    echo "GD Library not enabled.";
}
?>