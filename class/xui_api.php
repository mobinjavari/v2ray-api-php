<?php /** @noinspection MethodShouldBeFinalInspection */

/*\
 * | - Version : xui_api v2.3
 * |
 * | - Author : github.com/mobinjavari
 * | - Source : github.com/mobinjavari/v2ray-api-php
 * | - License : github.com/mobinjavari/v2ray-api-php/LICENSE.md
\*/

class xui_api
{
    private string $address;

    private string $port;

    private string $username;

    private string $password;

    private string $default_protocol;

    private string $default_transmission;

    private string $cookies_directory;

    private string $cookie_txt_path;

    public mixed $empty_object;

    public function __construct(
        string $address,
        string $port,
        string $username,
        string $password,
        string $default_protocol = "",
        string $default_transmission = ""
    )
    {
        $this->address = $address;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->default_protocol =
            empty($default_protocol) ? "vless" : $default_protocol;
        $this->default_transmission =
            empty($default_transmission) ? "ws" : $default_transmission;
        $this->empty_object = new stdClass();
        $this->cookies_directory = "./.cookies/";
        $this->cookie_txt_path = "$this->cookies_directory$this->address.$this->port.txt";

        if(!is_dir($this->cookies_directory)) mkdir($this->cookies_directory);

        if(!file_exists($this->cookie_txt_path))
        {
            $login = $this->login();

            if(!$login["success"])
            {
                unlink($this->cookie_txt_path);
                exit($login["msg"]);
            }
        }
    }

    public function request(string $method, array | string $param = "") : array
    {
        $URL = "$this->address:$this->port/$method";
        $POST = is_array($param) ? json_encode($param) : $param;
        $options = [
            CURLOPT_URL => $URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_COOKIEFILE => $this->cookie_txt_path,
            CURLOPT_COOKIEJAR => $this->cookie_txt_path,
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
        curl_close($ch);

        return match ($http_code) {
            200 => json_decode($response,true),
            0 => [
                "msg" => "The Client cannot connect to the server",
                "success" => false
            ],
            default => [
                "msg" => "Status Code : $http_code",
                "success" => false
            ]
        };
    }

    public function login() : array
    {
        return $this->request("login",[
            "username" => $this->username,
            "password" => $this->password
        ]);
    }

    public function list(array $filter = []) : array
    {
        $list = (array)$this->request(
            "xui/inbound/list"
        )["obj"];

        if(!empty($filter))
        {
            $result = [];

            for ($list_andis = 0, $num = 0; $num < count($list); $num++)
            {
                $filter_status = 1;
                $list_settings = json_decode($list[$num]["settings"],true);

                if(!empty($filter["port"]) && $filter["port"] !== (int)$list[$num]["port"]) $filter_status = 0;
                if(!empty($filter["uid"]) && $filter["uid"] !== $list_settings["clients"][0]["id"]) $filter_status = 0;
                if(!empty($filter["protocol"]) && $filter["protocol"] !== $list[$num]["protocol"]) $filter_status = 0;

                if($filter_status)
                {
                    $result[$list_andis]["id"] = (int)$list[$num]["id"];
                    $result[$list_andis]["up"] = (int)$list[$num]["up"];
                    $result[$list_andis]["down"] = (int)$list[$num]["down"];
                    $result[$list_andis]["usage"] = (int)$list[$num]["up"] + (int)$list[$num]["down"];
                    $result[$list_andis]["total"] = (int)$list[$num]["total"];
                    $result[$list_andis]["remark"] = $list[$num]["remark"];
                    $result[$list_andis]["enable"] = (bool)$list[$num]["enable"];
                    $result[$list_andis]["expiryTime"] = (int)$list[$num]["expiryTime"];
                    $result[$list_andis]["expiryDate"] =
                        $list[$num]["expiryTime"] == 0 ? 0 :
                            date("Y-m-d",($list[$num]["expiryTime"] / 1000));
                    $result[$list_andis]["expiryDays"] =
                        $list[$num]["expiryTime"] == 0 ? 0 :
                            round(($list[$num]["expiryTime"] / 1000 - time()) / (60 * 60 * 24));
                    $result[$list_andis]["listen"] = $list[$num]["listen"];
                    $result[$list_andis]["port"] = (int)$list[$num]["port"];
                    $result[$list_andis]["protocol"] = $list[$num]["protocol"];
                    $result[$list_andis]["settings"] =
                        json_decode($list[$num]["settings"],true);
                    $result[$list_andis]["streamSettings"] =
                        json_decode($list[$num]["streamSettings"],true);
                    $result[$list_andis]["streamSettings"]["wsSettings"]["headers"] = $this->empty_object;
                    $result[$list_andis]["tag"] = $list[$num]["tag"];
                    $result[$list_andis]["sniffing"] =
                        json_decode($list[$num]["sniffing"],true);
                    $result[$list_andis]["url"] =
                        $this->url(
                            $result[$list_andis]["port"],
                            $result[$list_andis]["protocol"],
                            $result[$list_andis]["settings"]["clients"][0]["id"],
                            $result[$list_andis]["remark"],
                            $result[$list_andis]["streamSettings"]["network"],
                        );
                    $list_andis++;
                }
            }

            if(count($result) == 1) return $result[0];
            elseif(count($result) == 0) return [];
            else return $result;
        }

        return $list;
    }

    public function url(
        int $port,
        string $protocol = "",
        string $uid = "",
        string $remark = "",
        string $transmission = "",
    ) : string
    {
        $protocol = empty($protocol) ? $this->list(["port" => $port])["protocol"] : $protocol;
        $uid = empty($uid) ? $this->list(["port" => $port])["settings"]["clients"][0]["id"] : $uid;
        $remark = empty($remark) ? $this->list(["port" => $port])["remark"] : $remark;
        $transmission = empty($transmission) ?
            $this->list(["port" => $port])["streamSettings"]["network"] : $transmission;
        $path = $transmission == "ws" ? "/" : "";

        switch ($protocol)
        {
            case "vmess":
                $vmess_url = "vmess://";
                $vmess_settings = [
                    "v" => "2",
                    "ps" => $remark,
                    "add" => $this->address,
                    "port" => $port,
                    "id" => $uid,
                    "aid" => 0,
                    "net" => $transmission,
                    "type" => "none",
                    "host" => "",
                    "path" => $path,
                    "tls" => "none"
                ];
                $vmess_base = base64_encode(json_encode($vmess_settings));
                return $vmess_url.$vmess_base;

            case "vless":
                $vless_url = "vless://$uid";
                $vless_url .= "@$this->address:$port";
                $vless_url .= "?type=$transmission&security=none&path=$path";
                $vless_url .= "#$remark";
                return $vless_url;

            default:return "Error, url could not be created";
        }
    }

    /**
     * @throws Exception
     */
    private function genUserId() : string
    {
        $data = random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @throws Exception
     */
    public function add(
        string $protocol = "",
        int $total = 0,
        string $transmission = "",
        string $remark = "",
        int $port = 0,
        int $ex_time = 0
    ) : bool
    {
        $uid = $this->genUserId();
        $protocol = empty($protocol) ? $this->default_protocol : $protocol;
        $transmission = empty($transmission) ? $this->default_transmission : $transmission;
        $remark = empty($remark) ? "Created by API" : $remark;
        $total = $total * 1024 * 1024 * 1024;
        $ex_time *= 1000;
        $port = empty($port) ? rand(11111,65330) : $port;
        $settings = match ($protocol) {
            "vmess" => [
                "clients" => [
                    ["id" => "$uid","alterId" => 0]
                ],
                "disableInsecureEncryption" => false
            ],
            "vless" => [
                "clients" => [
                    ["id" => "$uid","flow" => "xtls-rprx-direct"]
                ],
                "decryption" => "none","fallbacks" => []
            ]
        };
        $stream_settings = match ($transmission) {
            "tcp" => [
                "network" => "tcp",
                "security" => "none",
                "tcpSettings" => [
                    "header" => [
                        "type" => "none"
                    ]
                ]
            ],
            "ws" => [
                "network" => "ws",
                "security" => "none",
                "wsSettings" => [
                    "path" => "/",
                    "headers" => $this->empty_object
                ]
            ]
        };
        $post = [
            "up" => 0,
            "down" => 0,
            "total" => $total,
            "remark" => $remark,
            "enable" => true,
            "expiryTime" => $ex_time,
            "listen" => "",
            "port" => $port,
            "protocol" => $protocol,
            "settings" => json_encode($settings),
            "streamSettings" => json_encode($stream_settings),
            "sniffing" => json_encode([
                "enabled" => true,
                "destOverride" => ["http","tls"]
            ])
        ];

        return (bool)$this->request("xui/inbound/add",$post)["success"];
    }

    /**
     * @throws Exception
     */
    public function update(int $port, array $changes) : bool
    {
        $user = $this->list(["port" => $port]);
        $id = $user["id"];

        if(isset($changes["reset"]))
        {
            $user["up"] = 0;
            $user["down"] = 0;
        }

        if(isset($changes["total"]))
            $user["total"] = (int)$changes["total"] * 1024 * 1024 * 1024;

        if(isset($changes["enable"]))
            $user["enable"] = $changes["enable"];

        if(isset($changes["remark"]))
            $user["remark"] = $changes["remark"];

        if(isset($changes["expiryTime"]))
            $user["expiryTime"] = $changes["expiryTime"] * 1000;

        if(isset($changes["port"]))
            if($this->list(["port" => $changes["port"]]) == [])
                $user["port"] = $changes["port"];

        if(isset($changes["protocol"])) {
            $user["protocol"] = $changes["protocol"];
            $settings = match ($user["protocol"]) {
                "vmess" => [
                    "clients" => [
                        ["id" => $this->genUserId(),"alterId" => 0]
                    ],
                    "disableInsecureEncryption" => false
                ],
                "vless" => [
                    "clients" => [
                        ["id" => $this->genUserId(),"flow" => "xtls-rprx-direct"]
                    ],
                    "decryption" => "none","fallbacks" => []
                ]
            };
            $user["settings"] = json_encode($settings);
        } else {
            $user["settings"] = json_encode($user["settings"]);
        }

        if(isset($changes["transmission"])) {
            $stream_settings = match ($changes["transmission"]) {
                "tcp" => [
                    "network" => "tcp",
                    "security" => "none",
                    "tcpSettings" => [
                        "header" => [
                            "type" => "none"
                        ]
                    ]
                ],
                "ws" => [
                    "network" => "ws",
                    "security" => "none",
                    "wsSettings" => [
                        "path" => "/",
                        "headers" => $this->empty_object
                    ]
                ]
            };
            $user["streamSettings"] = json_encode($stream_settings);
        } else {
            $user["streamSettings"] = json_encode($user["streamSettings"]);
        }

        $user["sniffing"] = json_encode($user["sniffing"]);

        return (bool)$this->request("xui/inbound/update/$id",$user)["success"];
    }

    public function del(int $id) : bool
    {
        return (bool)$this->request(
            "xui/inbound/del/$id"
        )["success"];
    }

    public function status() : array
    {
        $status = $this->request(
            "server/status"
        )["obj"];

        $status["cpu"] = round($status["cpu"]) ."%";
        $status["mem"]["current"] = $this->formatBytes($status["mem"]["current"]);
        $status["mem"]["total"] = $this->formatBytes($status["mem"]["total"]);
        $status["swap"]["current"] = $this->formatBytes($status["swap"]["current"]);
        $status["swap"]["total"] = $this->formatBytes($status["swap"]["total"]);
        $status["disk"]["current"] = $this->formatBytes($status["disk"]["current"]);
        $status["disk"]["total"] = $this->formatBytes($status["disk"]["total"]);
        $status["netIO"]["up"] = $this->formatBytes($status["netIO"]["up"]);
        $status["netIO"]["down"] = $this->formatBytes($status["netIO"]["down"]);
        $status["netTraffic"]["sent"] = $this->formatBytes($status["netTraffic"]["sent"]);
        $status["netTraffic"]["recv"] = $this->formatBytes($status["netTraffic"]["recv"]);
        $status["uptime"] = $this->formatTime($status["uptime"]);

        return $status;
    }

    public function formatBytes(int $size,int $format = 2, int $precision = 2) : string
    {
        $base = log($size, 1024);

        if($format == 1) {
            $suffixes = ['بایت', 'کلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت']; # Persian
        } elseif ($format == 2) {
            $suffixes = ["B", "KB", "MB", "GB", "TB"];
        } else {
            $suffixes = ['B', 'K', 'M', 'G', 'T'];
        }

        if($size <= 0) return "0 ".$suffixes[1];

        $result = pow(1024, $base - floor($base));
        $result = round($result, $precision);
        $suffixes = $suffixes[floor($base)];

        return $result ." ". $suffixes;
    }

    public function formatTime(int $time, int $format = 2) : string
    {
        if($format == 1) {
            $lang = ["ثانیه","دقیقه","ساعت","روز","هفته","ماه","سال"]; # Persian
        } else {
            $lang = ["Second(s)","Minute(s)","Hour(s)","Day(s)","Week(s)","Month(s)","Year(s)"];
        }

        if($time >= 1 && $time < 60) {
            return round($time) . " " . $lang[0];
        } elseif ($time >= 60 && $time < 3600) {
            return round($time / 60) . " " . $lang[1];
        } elseif ($time >= 3600 && $time < 86400) {
            return round($time / 3600) . " " . $lang[2];
        } elseif ($time >= 86400 && $time < 604800) {
            return round($time / 86400) . " " . $lang[3];
        } elseif ($time >= 604800 && $time < 2600640) {
            return round($time / 604800) . " " . $lang[4];
        } elseif ($time >= 2600640 && $time < 31207680) {
            return round($time / 2600640) . " " . $lang[5];
        } elseif ($time >= 31207680) {
            return round($time / 31207680) . " " . $lang[6];
        } else {
            return "Not supported";
        }
    }
}