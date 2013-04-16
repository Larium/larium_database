<?php

spl_autoload_register('autoload_larium_database');

function autoload_larium_database($class) {
    $base = __DIR__ . "/src/"; 
    $classes = array(
        'Larium\\Database\\Mysql\\Adapter'  => $base . "Larium/Database/Mysql/Adapter.php",
        'Larium\\Database\\Mysql\\Query'  => $base . "Larium/Database/Mysql/Query.php",
        'Larium\\Database\\Mysql\\ResultIterator'  => $base . "Larium/Database/Mysql/ResultIterator.php",
        'Larium\\Database\\AdapterFactory'  => $base . "Larium/Database/AdapterFactory.php",
        'Larium\\Database\\AdapterInterface'  => $base . "Larium/Database/AdapterInterface.php",
        'Larium\\Database\\QueryInterface'  => $base . "Larium/Database/QueryInterface.php",
        'Larium\\Database\\ResultIteratorInterface'  => $base . "Larium/Database/ResultIteratorInterface.php",
        'Larium\\Database\\Logger'  => $base . "Larium/Database/Logger.php",
    );
    
    array_key_exists($class, $classes) 
        ? require_once $classes[$class]
        : false;
}
