<?php

namespace Domens;

class privatflare
{

    public int $count;
    public string $tag;

    /**
     * @param int $count
     * @param string $tag
     * @return mixed
     */
    public function getDomens(int $count, string $tag) {

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.privateflare.com/domains/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'X-Auth-Key: uKWsaLmkhuZ1zhY0t6KMeVqeUiE1vu7j'
            )
        ));

        $response = curl_exec($curl);
        return json_decode($response, true);
    }
}