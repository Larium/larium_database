<?php

namespace Larium\Database;

interface QueryInterface
{
    /**
     * @return string
     */
    public function toSql();

    /**
     * @return string
     */
    public function toRealSql();

    /**
     * @return array
     */
    public function getBindParams();

    /**
     * @return string
     */
    public function getObject();
}
