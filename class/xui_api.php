<?php

/*\
 * | - Author : github.com/mobinjavari
 * | - Source : github.com/mobinjavari/v2ray-api-php
 * | - License : github.com/mobinjavari/v2ray-api-php/LICENSE.md
\*/

class xui_api
{
    private string $address;

    private int $port;

    private string $username;

    private string $password;

    public function __construct(string $address, int $port, string $username, string $password)
    {
        $this->address = $address;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;

        if(!file_exists('./.cookie.txt')) $this->login();
    }

    public function request(string $method, array | string $param = "") : array
    {
        $value = is_array($param) ? json_encode($param) : $param;
        $handle = curl_init("$this->address:$this->port/$method");
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_ENCODING, "");
        curl_setopt($handle, CURLOPT_COOKIEFILE, "./.cookie.txt");
        curl_setopt($handle, CURLOPT_COOKIEJAR, "./.cookie.txt");
        curl_setopt($handle, CURLOPT_MAXREDIRS, 10);
        curl_setopt($handle, CURLOPT_TIMEOUT, 0);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($handle, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $value);
        $response = json_decode(curl_exec($handle),true);
        curl_close($handle);
        return $response;
    }

    public function login() : bool
    {
        return (bool)$this->request("login",[
            "username" => $this->username,
            "password" => $this->password
        ]);
    }

    public function list(array $filter = []) : array
    {
        $list = $this->request(
            "xui/inbound/list"
        )["obj"];

        if(!empty($filter))
        {
            $result = [];

            for ($list_andis = 0, $num = 0; $num <= count($list); $num++)
            {
                $filter_status = 1;
                $port = $filter["port"];
                $uuid = $filter["uuid"];
                $protocol = $filter["protocol"];

                if(!empty($port) && $port !== $list[$num]["port"]) $filter_status = 0;
                if(!empty($uuid) && $uuid !== $list[$num]["uuid"]) $filter_status = 0;
                if(!empty($protocol) && $protocol !== $list[$num]["protocol"]) $filter_status = 0;

                if($filter_status)
                {
                    $result[$list_andis]["id"] = (int)$list[$num]["id"];
                    $result[$list_andis]["up"] = (int)$list[$num]["up"];
                    $result[$list_andis]["down"] = (int)$list[$num]["down"];
                    $result[$list_andis]["total"] = (int)$list[$num]["total"];
                    $result[$list_andis]["remark"] = $list[$num]["remark"];
                    $result[$list_andis]["enable"] = (bool)$list[$num]["enable"];
                    $result[$list_andis]["expiryTime"] = (int)$list[$num]["expiryTime"];
                    $result[$list_andis]["listen"] = $list[$num]["listen"];
                    $result[$list_andis]["port"] = (int)$list[$num]["port"];
                    $result[$list_andis]["protocol"] = $list[$num]["protocol"];
                    $result[$list_andis]["settings"] = json_decode($list[$num]["settings"],true);
                    $result[$list_andis]["streamSettings"] = json_decode($list[$num]["streamSettings"],true);
                    $result[$list_andis]["tag"] = $list[$num]["tag"];
                    $result[$list_andis]["sniffing"] = json_decode($list[$num]["sniffing"],true);
                    $list_andis++;
                }
            }

            if(count($result) == 1) return $result[0];
            elseif($result == 0) return [];
            else return $result;
        }

        return $list;
    }
}
