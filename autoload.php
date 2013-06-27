<?php

require_once 'ClassMap.php';

$classes = array(
    'Larium\\Database\\Mysql\\Adapter'          => "Larium/Database/Mysql/Adapter.php",
    'Larium\\Database\\Mysql\\Query'            => "Larium/Database/Mysql/Query.php",
    'Larium\\Database\\Mysql\\ResultIterator'   => "Larium/Database/Mysql/ResultIterator.php",
    'Larium\\Database\\AdapterFactory'          => "Larium/Database/AdapterFactory.php",
    'Larium\\Database\\AdapterInterface'        => "Larium/Database/AdapterInterface.php",
    'Larium\\Database\\QueryInterface'          => "Larium/Database/QueryInterface.php",
    'Larium\\Database\\ResultIteratorInterface' => "Larium/Database/ResultIteratorInterface.php",
    'Larium\\Database\\Logger'                  => "Larium/Database/Logger.php",
);

ClassMap::load(__DIR__ . "/src/", $classes)->register();
