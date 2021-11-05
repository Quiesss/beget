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
    public function getDomens(int $count, string $tag)
    {

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

        $response = json_decode(curl_exec($curl),true);
        $domainslist = array();
        $i=1;
        foreach($response['domains'] as $key => $val)
        {
            if (isset($response['domains'][$key]['tags'][0]) && $response['domains'][$key]['tags'][0] == $tag) {
                $domainslist[$i]['domain'] = ($response['domains'][$key]['domain'] == '') ? 'Пустая строка' : $response['domains'][$key]['domain'];
                $domainslist[$i]['ssl'] = ($response['domains'][$key]['ssl'] == '1') ? 1 : 0;
                $domainslist[$i]['online'] = ($response['domains'][$key]['online'] == '1') ? 1 : 0;
                $domainslist[$i]['datedomen'] = $response['domains'][$key]['created'];
                $domainslist[$i]['backend'] = $response['domains'][$key]['backend'];
                $i++;
            }
        }
//        foreach ($response['domains'] as $key => $val) {
//            if ($val['tags'][0] == $tag) {
//                $domainslist[] = $val[0]['domain'];
//            }
//        }
        $array = [];
        foreach ($domainslist as $key => $value) {
            $array[$key] = $value['online'];
        }
        array_multisort($array, SORT_DESC, $domainslist);
        return $domainslist;
    }
}