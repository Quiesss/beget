<?php

namespace DomainsThings;

use PDO;
use Exception;
use Domens\DomainException;
use PDOStatement;

class DomainThings
{
    private PDO $connection;

    
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function getUserInfo(string $login): array
    {
        $query = $this->connection->prepare('SELECT * FROM users WHERE `user_login` = :login LIMIT 1');
        $query->execute(['login' => $login]);
        return $query->fetch();
    }

    public function howMuchLeft(): string
    {
        $count = $this->getCountDomains();
        $strCount = substr($count, -1);
        if($strCount == 1) $word = "домен";
        elseif ($strCount == 2 || $strCount == 3 || $strCount == 4) $word = "домена";
        else $word = "доменов";
        return "В базе осталось $count $word";
    }

    public function getCountDomains(): int
    {
        return $this->connection->query('SELECT COUNT(*) FROM db_domens_fb')->fetchColumn();
    }

    public function getCountDomainsLinode(): int
    {
        $count = $this->connection->prepare('SELECT COUNT(*) FROM db_domens_fb WHERE `host` = ?');
        $count->execute(['Linode']);
        return $count->fetchColumn();
    }

    public function getCountDomainsDo(): int
    {
        $count = $this->connection->prepare('SELECT COUNT(*) FROM db_domens_fb WHERE `host` = ?');
        $count->execute(['DO']);
        return $count->fetchColumn();
    }

    public function getCountUserDomains($login): int
    {
        $count = $this->connection->prepare('SELECT COUNT(*) FROM custom_domains WHERE `owner` = ?');
        $count->execute([$login]);
        return $count->fetchColumn();
    }

    /**
     * @param int $count
     * @param string $login
     * @return int
     * @throws DomainException
     */
    public function getDomains(int $count, string $login, string $host): int
    {
        $successDomains = 0;
        $totalDomains = $this->getCountDomains();
        $userData = $this->getUserInfo($login)[0];

        $timeLastGet = (strtotime(date('Y-m-d H:i:s')) - strtotime($userData['date_last_d']))/3600;
        if($timeLastGet >= 24) {
            $query = $this->connection->prepare('UPDATE users SET d_today = ?, date_last_d = NOW() WHERE user_login = ?');
            $query->execute([0, $login]);
            $query->closeCursor();
            $userData = $this->getUserInfo($login)[0];
        }
        $muchLeftDomains = $userData['domen_limit'] - $userData['d_today'];
            if ($totalDomains >= $count) {
                if ($muchLeftDomains >= $count) {

                    $query = $this->connection->prepare("INSERT INTO custom_domains (`domen`, `valid`, `owner`, `datedomen`, `host`) SELECT `domen`, `valid`, ?, NOW(), `host` FROM db_domens_fb WHERE `host` = ? LIMIT $count");
                    $statement = $query->execute([$login, "{$host}"]);
                    if ($query->rowCount() != 0) {
                        $successDomains = $count;
                        $query = $this->connection->prepare('DELETE FROM db_domens_fb WHERE `host` = :host LIMIT 1');
                        $query->bindParam('host', $host);
                        $query->execute();
                        $query = $this->connection->prepare('UPDATE users SET date_last_d = NOW(), d_today = ? WHERE user_login = ?');
                        $query->execute([$userData['d_today'] + $count, $login]);
                    }
                } else {
                    throw new DomainException('Вы загрузили достаточно доменов на сегодня');
                }
            } else {
                throw new DomainException('В базе недостаточно доменов');
            }
            return $successDomains;
    }
}