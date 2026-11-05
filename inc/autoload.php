<?php
spl_autoload_register(function ($class) {
    $prefix = 'Illuminate\\';
    $baseDir = ABSPATH . 'inc/Databases/database-master/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
echo '12234'.$file;
    if (file_exists($file)) {
        require $file;
    }
});

?> 