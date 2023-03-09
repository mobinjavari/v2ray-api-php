<?php

/*\
 * | - Author : github.com/mobinjavari
 * | - Source : github.com/mobinjavari/v2ray-api-php
 * | - License : github.com/mobinjavari/v2ray-api-php/LICENSE.md
\*/

class xui_fronting_tls_api
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
        $URL = "$this->address:$this->port/$method";
        $POST = is_array($param) ? json_encode($param) : $param;
        $options = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_COOKIEFILE => "./.cookie.txt",
            CURLOPT_COOKIEJAR => "./.cookie.txt",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $POST
        ];

        if(is_array($param)) $options[CURLOPT_HTTPHEADER] = ["Content-Type: application/json"];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return match ($http_code) {
            200 => json_decode($response,true),
            default => "Error $http_code"
        };
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

            for ($list_andis = 0 ,$num = 0, $lim = 0; $num < count($list); $num++)
            {
                $email = empty($filter["email"]) ? "" : $filter["email"];
                $client_stats = $list[$lim]["clientStats"];
                $settings = json_decode($list[$lim]["settings"],true);
                $remark = $list[$lim]["remark"];
                $port = (int)$list[$lim]["port"];
                $protocol = $list[$lim]["protocol"];
                $stream_settings = json_decode($list[$lim]["streamSettings"],true);

                if(!empty($id)) return $list[$id-1];
                if($lim >= count($settings["clients"])) $lim++;

                if(!empty($email))
                {
                    for ($num_2 = 0; $num_2 < count($settings["clients"]); $num_2++)
                    {
                        if($settings["clients"][$num_2]["email"] == $filter["email"])
                        {
                            $result[$list_andis]["guid"] = $settings["clients"][$num_2]["id"];
                            $result[$list_andis]["flow"] = $settings["clients"][$num_2]["flow"];
                            $result[$list_andis]["email"] = $settings["clients"][$num_2]["email"];
                            $result[$list_andis]["limitIp"] = (int)$settings["clients"][$num_2]["limitIp"];
                            $result[$list_andis]["totalGB"] = (int)$settings["clients"][$num_2]["totalGB"];
                            $result[$list_andis]["fingerprint"] = $settings["clients"][$num_2]["fingerprint"];
                            $result[$list_andis]["expiryTime"] = (int)$settings["clients"][$num_2]["expiryTime"];
                            $result[$list_andis]["clientAndis"] = $num_2;
                        }
                    }
                    for ($num_2 = 0; $num_2 < count($client_stats); $num_2++)
                    {
                        if($client_stats[$num_2]["email"] == $filter["email"])
                        {
                            $result[$list_andis]["id"] = (int)$client_stats[$num_2]["id"];
                            $result[$list_andis]["inboundId"] = (int)$client_stats[$num_2]["inboundId"];
                            $result[$list_andis]["enable"] = (int)$client_stats[$num_2]["enable"];
                            $result[$list_andis]["up"] = (int)$client_stats[$num_2]["up"];
                            $result[$list_andis]["down"] = (int)$client_stats[$num_2]["down"];
                            $result[$list_andis]["expiryTime"] = (int)$client_stats[$num_2]["expiryTime"];
                            $result[$list_andis]["total"] = (int)$client_stats[$num_2]["total"];
                            $result[$list_andis]["statsAndis"] = $num_2;
                            $result[$list_andis]["url"] = $this->url(
                                $protocol,
                                $result[$list_andis]["guid"],
                                "$remark-$email",
                                $stream_settings["network"],
                                $port,
                                $result[$list_andis]["fingerprint"],
                                $stream_settings["wsSettings"]["path"]
                            );
                            $list_andis++;
                        }
                    }
                }
            }

            if(count($result) == 1) return $result[0];
            elseif(count($result) == 0) return [];
            else return $result;
        }

        return $list;
    }

    public function url(
        string $porotocol,
        string $guid,
        string $remark,
        string $network,
        int $port,
        string $fp,
        string $path
    ) : string
    {
        switch ($porotocol)
        {
            case "vmess":
                $vmess_url = "vmess://";
                $path = $network == "ws" ? "/" : "";
                $vmess_settings = [
                    "v" => "2",
                    "ps" => $remark,
                    "add" => $this->address,
                    "port" => $port,
                    "id" => $guid,
                    "aid" => 0,
                    "net" => $network,
                    "type" => "none",
                    "host" => "",
                    "path" => $path,
                    "tls" => "tls"
                ];
                $vmess_base = base64_encode(json_encode($vmess_settings));
                return $vmess_url.$vmess_base;

            case "vless":
                $vless_url = "vless://$guid";
                $vless_url .= "@$this->address:$port";
                $vless_url .= "?type=$network&security=tls&path=$path&fp=$fp&encryption=none&host=$this->address&sni=$this->address";
                $vless_url .= "#$remark";
                return $vless_url;

            default:return "Error, url could not be created";
        }
    }

    /**
     * @throws Exception
     */
    private function guidv4() {
        $data = random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    function update(int $andis, int $client_andis, array $changes)
    {
        if(!empty($changes))
        {
            $list = $this->list();

            if(!empty($changes["settings"]))
            {
                $settings = json_decode($list[$andis]["settings"],true);

                if(!empty($changes["settings"]["clients"]["limitIp"]))
                    $settings["clients"][$client_andis]["limitIp"] =
                        $changes["settings"]["clients"]["limitIp"];

                if(!empty($changes["settings"]["clients"]["totalGB"]))
                    $settings["clients"][$client_andis]["totalGB"] =
                        $changes["settings"]["clients"]["totalGB"];

                if(!empty($changes["settings"]["clients"]["expiryTime"]))
                    $settings["clients"][$client_andis]["expiryTime"] =
                        $changes["settings"]["clients"]["expiryTime"];

                $settings = json_encode($settings);
            } else $settings = "";

            $result["up"] = empty($changes["up"]) ? $list[$andis]["up"] : $changes["up"];
            $result["down"] = empty($changes["down"]) ? $list[$andis]["down"] : $changes["down"];
            $result["total"] = empty($changes["total"]) ? $list[$andis]["total"] : $changes["total"];
            $result["remark"] = empty($changes["remark"]) ? $list[$andis]["remark"] : $changes["remark"];
            $result["enable"] = empty($changes["enable"]) ? $list[$andis]["enable"] : $changes["enable"];
            $result["expiryTime"] = empty($changes["expiryTime"]) ? $list[$andis]["expiryTime"] : $changes["expiryTime"];
            $result["clientStats"] = null;
            $result["listen"] = $list[$andis]["listen"];
            $result["port"] = $list[$andis]["port"];
            $result["protocol"] = $list[$andis]["protocol"];
            $result["settings"] = empty($settings) ? $list[$andis]["settings"] : addslashes($settings);
            $result["streamSettings"] = $list[$andis]["streamSettings"];
            $result["tag"] = $list[$andis]["tag"];
            $result["sniffing"] = $list[$andis]["sniffing"];

            $send = $this->request("xui/inbound/update/".++$andis,$result);

            return (bool)$send["success"];
        }

        return true;
    }

    public function del($id) : bool
    {
        return (bool)$this->request(
            "xui/inbound/del/$id"
        )["success"];
    }
}