<?php

namespace Domens;


use PDO;

class UserData
{
    private PDO $connection;

    public function __construct($connection) {
        $this->connection = $connection;
    }

    /**
     * @param $param
     */
    static function domenTable($param)
    {
        $statement = $this->connection->prepare(
            "
        create table :tablename
(
    domen_id  int auto_increment
        primary key,
    domen     varchar(100) null,
    valid     varchar(30)  null,
    datedomen timestamp    null
);
        ");
        $statement->execute([
            'tablename' => $param
        ]);
    }
}