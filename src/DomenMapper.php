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
    public function GetByDomens($param): array
    {
        $statement = $this->connection->prepare("SELECT * FROM :nameTable");
        $statement->execute([
            'nameTable' => 'domen_' . $param,
        ]);
        $result = $statement->fetchAll();
        return $result;
    }
}