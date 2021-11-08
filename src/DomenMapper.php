<?php

namespace Domens;
use PDO;
class DomenMapper
{
    public int $count;
    private PDO $connection;

    public function __construct(PDO $connection) {
        $this->connection = $connection;
    }
    public function GetByDomens($login): array
    {
        $statement = $this->connection->prepare("SELECT * FROM custom_domains WHERE `owner` = ? ORDER BY `datedomen` DESC");
        $statement->execute([$login]);
        $result = $statement->fetchAll();
        return $result;
    }
}