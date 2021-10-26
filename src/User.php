<?php

namespace Domens;


use PDO;
use Exception;
use Domens\UserData;
use Domens\Session;

class User
{
    private PDO $connection;
    private Session $session;

    public function __construct(PDO $connection, $session) {
        $this->session = $session;
        $this->connection = $connection;


    }

    /**
     * @param string $name
     * @return bool
     * @throws AuthorizationException
     */
    public function CheckUserExist(string $name): bool
    {
        $query = $this->connection->prepare('SELECT * FROM users WHERE user_login = :name');
        $query->execute([
            'name' => $name
        ]);
        $res = $query->fetchAll();
        if($res){
            throw new AuthorizationException('User already exist.');
        } else return false;
    }

    /**
     * @param string $name
     * @param $password
     * @return bool
     * @throws AuthorizationException
     */
    public function create(string $name, $password): bool
    {
        if(empty($name) || empty($password)) {
            throw new AuthorizationException('Empty Dates, try again');
        }
        if(!$this->CheckUserExist($name)) {
            $query = $this->connection->prepare('INSERT INTO users (user_login, user_password) VALUES (:login, :pass)');
            $query->execute([
                'login' => $name,
                'pass' => password_hash($password, PASSWORD_BCRYPT)
            ]);
            $this->domenTable($name);
            return true;
        } else return false;
    }

    /**
     * @param string $name
     * @param $password
     * @return bool
     * @throws AuthorizationException
     */
    public function login(string $name, $password): bool
    {
        $query = $this->connection->prepare('SELECT * FROM users WHERE user_login = :login');
        $query->execute([
            'login' => $name,
        ]);
        $q = $query->fetch(PDO::FETCH_ASSOC);
        if(empty($q)) {
            throw new AuthorizationException('That user is not found');
        }
        if(password_verify($password, $q['user_password'])) {
            $this->session->setData('user', [
                'user_login' => $q['user_login'],
                'user_id' => $q['user_id'],
                'onsite' => '1'
            ]);
            return true;
        } else {
            throw new AuthorizationException("Wrong name or pass");
        }

    }
    public function domenTable($param): void
    {
        $query = "CREATE TABLE domen_{$param} (
  `domen_id` int(11) NOT NULL,
  `domen` varchar(100) DEFAULT NULL,
  `valid` varchar(30) DEFAULT NULL,
  `datedomen` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
  ALTER TABLE `domen_qwe`
  ADD PRIMARY KEY (`domen_id`);";

    $statement = $this->connection->exec($query);
    if (empty($statement)) {
        throw new DatabaseExcaption("Table is not created, something wrong");
        }
    }
}