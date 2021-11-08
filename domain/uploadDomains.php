<?php

namespace DomainsThings;


use PDO;
use Slim\Psr7\UploadedFile;

class uploadDomains
{
    private PDO $connection;
    public $file;

    public function __construct($connection, $filename)
    {
        $this->connection = $connection;
        $this->file = file('files/' . $filename);
    }

    public function upload(string $host)
    {
        $success = 0;
        foreach($this->file as $key => $val) {
            $correctFile = preg_replace('/^(https?:)?(\/\/)?(www\.)?/', '', $val);
            $query = $this->connection->prepare("INSERT INTO db_domens_fb (domen, host) VALUES (:domain, :host)");
            $query->execute([
                'domain' => $correctFile,
                'host' => $host
            ]);
            $success++;
        }
        return "Успешно загружено " . $success . " доменов";
    }
}