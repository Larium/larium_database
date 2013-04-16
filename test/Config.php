<?php

class Config
{
    protected $database = array();

    public function __construct()
    {
        include __DIR__ . '/database.conf.php';
        $this->database = $config;
    }

    public function getDatabase()
    {
        return $this->database;
    }
}
