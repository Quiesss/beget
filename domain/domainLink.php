<?php

namespace DomainsThings;

use Domens\DomainException;
use Domens\Session;
use PDO;

class domainLink
{
    private PDO $connection;
    private Session $session;
    private mixed $LinodeToken;
    private mixed $DOtoken;
    private mixed $ip;
    private string $login;

    public function __construct($connection,Session $session)
    {
        $this->connection = $connection;
        $this->session = $session;

        $query = $this->connection->prepare("SELECT * FROM users WHERE user_id = :id");
        $query->execute([
            'id' => $this->session->getData('user')['user_id']
        ]);
        $data = $query->fetch();
        $this->LinodeToken = $data['token_linode'];
        $this->DOtoken = $data['token_do'];
        $this->login = $data['user_login'];
        $this->ip = $data['anote_ip'];
    }

    public function addAnote($ch1, $domainId, $domain, $ip, $token)
    {
        curl_setopt($ch1, CURLOPT_URL, 'https://api.linode.com/v4/domains/'.$domainId.'/records');
        curl_setopt($ch1, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch1, CURLOPT_POSTFIELDS, "{\"type\":\"A\",\"name\":\"".$domain."\",\"target\":\"".$ip."\"}");
        curl_setopt($ch1, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$token,
            'Content-Type: application/json',
        ));
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        $unres1 = curl_exec($ch1);
         return json_decode($unres1, true);
    }

    /**
     * @param $domain
     * @param $token
     * @return array
     * @throws DomainException
     */
    public function linkLinode($domain): array
    {
        $email = 'simplemail@mail.com';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.linode.com/v4/domains');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"domain\":\"".$domain."\",\"type\":\"master\",\"soa_email\":\"".$email."\"}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$this->LinodeToken,
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $unres = curl_exec($ch);
        $success = 0;
        curl_reset($ch);
        $result_d = json_decode($unres, true);
        if(isset($result_d['domain']) && $result_d['domain'] == $domain) {

            $addAnote = $this->addAnote($ch, $result_d['id'], $domain, $this->ip, $this->LinodeToken);
            $addPF = $this->linkPF($ch, $domain);
            $this->session->setData('hostMsg', "Домен $domain успешно добавлен");
            $answer = "Домен $domain добавлен";
            $this->DelDomain($domain, "Linode");
            $success = 1;

            if(!isset($addAnote['errors'][0]['reason'])) {
                $this->session->setData('anoteMsg', "А запись добавлена");
            } else $this->session->setData('anoteMsg', "Ошибка: {$addAnote['errors'][0]['reason']}");
            $this->session->setData('pfMsg', "PF: $domain - {$addPF}");
        } elseif ($result_d['errors'][0]['reason'] == 'DNS zone already exists.') {
            $this->trashDomain($domain, 'Linode');
            $this->DelDomain($domain, 'Linode');
            $answer = " Невалидный домен";
            $success = 1;
        }
        else {
            $this->session->setData('hostMsg', "Ошибка: {$result_d['errors'][0]['reason']}");
            $answer = "Ошибка: {$result_d['errors'][0]['reason']}";

        }
            curl_close($ch);
        return array(
            'q' => $answer,
            'w' => $success
        );
    }

    public function linkPF($ch, $domain): string
    {
        $listToPF = json_encode(array(
            "enabled" => true,
            "domain" => $domain,
            "mask" => $domain,
            "ssl" => true,
            "backend" => "188.40.140.223",
            "fhttps" => true,
            "cachelevel" => 1,
            "tags" => [$this->login ?? "default"]
        ));

        curl_setopt_array($ch, array(
            CURLOPT_URL => 'https://api.privateflare.com/domains/',
            CURLOPT_POSTFIELDS => $listToPF,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'X-Auth-Key: uKWsaLmkhuZ1zhY0t6KMeVqeUiE1vu7j'
            ),
        ));
        $response = curl_exec($ch);
        curl_reset($ch);
        return $response;
    }

    public function linkDO($domain):array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.digitalocean.com/v2/domains');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{\"name\":\"" . $domain . "\",\"ip_address\":\"" . $this->ip . "\"}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->DOtoken,
            'Content-Type: application/json',
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $unres = curl_exec($ch);
        $result = json_decode($unres, true);
        $success = 0;
        $pf = "";
        if(isset($result['message']))
        {
            if($result['message'] == "domain '$domain': name already exists") {
                $trash = $this->trashDomain($domain, 'DO');
                $this->DelDomain($domain, 'DO');
                $answer = "Невалидный домен, {$result['message']}" . " " . $trash;
                $success = 1;
            } else  $answer = $result['message'];
        } else {
//        if ($result['message'] == "Unable to authenticate you.") $answer = "Что-то не так с токеном DigitalOcean'a";
//        elseif ($result['message'] == "Data can't be blank") $answer = "Не верный IP";
//        elseif ($result['message'] == "Name can't be blank") $answer = "Нет имени домена";
//        elseif ($result['message'] == "Name can not be just a TLD.") $answer = "Не корректное имя домена";
//        elseif ($result['message'] == "domain '$domain': name already exists") $answer = "Такой домен уже существует";
            $answer = "Домен ".$domain." успешно создан";
            $success = 1;
            $pf = $this->linkPF($ch, $domain);
            $this->DelDomain($domain, "DO");
        }
        $this->session->setData('hostMsg', $answer);
        return array(
            'q' => $answer . "<br>" . $pf,
            'w' => $success
        );
    }

    public function DelDomain($domain, $host)
    {
        $query = $this->connection->prepare("DELETE FROM custom_domains WHERE `domen` = :domain AND `host` = :host AND `owner` = :login LIMIT 1");
        $query->execute([
            'domain' => $domain,
            'host' => "{$host}",
            'login' => $this->login
        ]);
    }

    public function trashDomain($domain, $host)
    {
        $query = $this->connection->prepare("INSERT INTO trash_domains (`domen`, `valid`, `owner`, `datedomen`, `host`) SELECT `domen`, `valid`, `owner`, NOW(), `host` FROM custom_domains WHERE `domen` = ? AND `host` = ? AND `owner` = ? LIMIT 1");
        $statement = $query->execute([$domain, "{$host}", $this->login]);
        if($statement) {
            return "yeap";
        } else return "oops";
        //$query->fetch()
    }

}