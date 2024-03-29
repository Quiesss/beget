<?php

namespace UserThings;

use PDO;
use Domens\Session;

class Settings
{
    private PDO $connection;
    public string $login;

    public function __construct($connection,Session $session)
    {
        $this->connection = $connection;
        $this->login = $session->getData('user')['user_login'];
    }

    public function getLinodeToken(): string|null
    {
        $query = $this->connection->prepare("SELECT `token_linode` FROM users WHERE user_login = ?");
        $query->execute([$this->login]);
        return $query->fetch()['token_linode'];
    }

    public function getDoToken(): string|null
    {
        $query = $this->connection->prepare("SELECT `token_do` FROM users WHERE user_login = ?");
        $query->execute([$this->login]);
        return $query->fetch()['token_do'];
    }

    public function getIpNoteDo(): string|null
    {
        $query = $this->connection->prepare("SELECT `anote_ip` FROM users WHERE user_login = ?");
        $query->execute([$this->login]);
        return $query->fetch()['anote_ip'];
    }
    
        public function getIpNoteLinode(): string|null
    {
        $query = $this->connection->prepare("SELECT `anote_ip_linode` FROM users WHERE user_login = ?");
        $query->execute([$this->login]);
        return $query->fetch()['anote_ip_linode'];
    }

    public function ChangeLinodeToken($token)
    {
        $query = $this->connection->prepare("UPDATE users SET `token_linode` = ? WHERE `user_login` = ?");
        $statement = $query->execute([$token, $this->login]);
        if ($statement) {
            return "Токен успешно изменен";
        } else {
            return "Ошибка!";
        }
    }

    public function ChangeDoToken($token)
    {
        $query = $this->connection->prepare("UPDATE users SET `token_do` = ? WHERE `user_login` = ?");
        $statement = $query->execute([$token, $this->login]);
        if ($statement) {
            return "Токен успешно изменен";
        } else {
            return "Ошибка!";
        }
    }

    public function ChangeAnoteDo($anote)
    {
        $query = $this->connection->prepare("UPDATE users SET `anote_ip` = ? WHERE `user_login` = ?");
        $statement = $query->execute([$anote, $this->login]);
        if ($statement) {
            return "ip для А-записи DigitalOcean успешно изменено";
        } else {
            return "Ошибка!";
        }
    }
    
        public function ChangeAnoteLinode($anote)
    {
        $query = $this->connection->prepare("UPDATE users SET `anote_ip_linode` = ? WHERE `user_login` = ?");
        $statement = $query->execute([$anote, $this->login]);
        if ($statement) {
            return "ip для А-записи Linode успешно изменено";
        } else {
            return "Ошибка!";
        }
    }
}