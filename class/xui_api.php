<?php /** @noinspection MethodShouldBeFinalInspection */

/*\
 * | - Version : xui_api v3
 * |
 * | - Author : github.com/mobinjavari
 * | - Project : github.com/mobinjavari/v2ray-api-php
 * | - Source : github.com/mobinjavari/v2ray-api-php/class/xui_api.php
 * | - Document : github.com/mobinjavari/v2ray-api-php/docs/xui_api.md
 * | - License : github.com/mobinjavari/v2ray-api-php/LICENSE.md
\*/

class xui_api
{
    /**
     * @var mixed
     */
    private mixed $connect;

    /**
     * @var mixed
     */
    private mixed $settings;

    /**
     * @var mixed
     */
    private mixed $cookies;

    /**
     * @var array
     */
    private array $login;

    /**
     * @var array
     */
    public array $status;

    /**
     * @var mixed
     */
    public mixed $empty_object;

    /**
     * @param string $address
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string $default_protocol
     * @param string $default_transmission
     * @param bool $is_3xui
     */
    public function __construct(
        string $address = 'example.com',
        int    $port = 54321,
        string $username = 'admin',
        string $password = 'admin',
        string $default_protocol = 'vless',
        string $default_transmission = 'ws',
        bool   $is_3xui = false,
    )
    {
        # Connect
        $this->connect = new  stdClass();
        $this->connect->address = $address;
        $this->connect->port = $port;
        $this->connect->username = $username;
        $this->connect->password = $password;
        $this->connect->url = "$address:$port/";

        # Settings
        $this->settings = new stdClass();
        $this->settings->is_3xui = $is_3xui;
        $this->settings->protocol = $default_protocol;
        $this->settings->transmission = $default_transmission;

        # Cookies
        $this->cookies = new stdClass();
        $this->cookies->directory = './.cookies/';
        $this->cookies->file = $this->cookies->directory . "$address.$port.txt";

        if (!is_dir($this->cookies->directory)) mkdir($this->cookies->directory);

        # Other
        $this->login = $this->login();
        $this->status = $this->status();
        $this->empty_object = new stdClass();
    }

    /**
     * @param array $replaces
     * @return array
     */
    public function config(array $replaces = []): array
    {
        if (file_exists('config.json')) {
            $guid = $this->random_guid();

            if ($guid['success']) {
                $json = file_get_contents('config.json');

                if ($replaces) {
                    foreach ($replaces as $replace) {
                        $key = $replace['key'] ?? false;
                        $value = $replace['value'] ?? false;

                        if ($key && $value) $json = str_replace($key, $value, $json);
                    }
                }

                $contents = json_decode($json);
                $contents = $this->settings->is_3xui ? $contents->xxxui : $contents->xui;

                return [
                    'success' => true,
                    'msg' => 'Account creation method',
                    'obj' => $contents
                ];
            }

            return $guid;
        }

        return [
            'success' => false,
            'msg' => 'Config file not exists',
            'obj' => ''
        ];
    }

    /**
     * @param string $method
     * @param array $param
     * @return array
     */
    private function curl_custom(string $method, array $param = []): array
    {
        $options = [
            CURLOPT_URL => $this->connect->url . $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEFILE => $this->cookies->file,
            CURLOPT_COOKIEJAR => $this->cookies->file,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($param)
        ];
        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($http_code !== 200) unlink($this->cookies->file);

        return match ($http_code) {
            200 => json_decode($response, true),
            0 => [
                'success' => false,
                'msg' => 'The Client cannot connect to the server',
                'obj' => ''
            ],
            default => [
                'success' => false,
                'msg' => "Status Code $http_code",
                'obj' => ''
            ]
        };
    }

    /**
     * @param bool $reset
     * @return array
     */
    public function login(bool $reset = false): array
    {
        $check = fsockopen(
            $this->connect->address,
            $this->connect->port,
            $error_number,
            $error_message,
            5
        );

        if ($check) {
            if ($reset && file_exists($this->cookies->file)) {
                unlink($this->cookies->file);
            }

            if (file_exists($this->cookies->file)) {
                return [
                    'success' => true,
                    'msg' => 'Cookies are already set',
                    'obj' => ''
                ];
            }

            $login = $this->curl_custom('login', [
                'username' => $this->connect->username,
                'password' => $this->connect->password,
            ]);

            if (!$login['success']) unlink($this->cookies->file);

            return $login;
        }

        return [
            'success' => false,
            'msg' => "Error $error_number $error_message",
            'obj' => ''
        ];
    }

    private function request(string $method, array $param = []): array
    {
        if ($this->login['success'])
            return $this->curl_custom($method, $param);

        return $this->login;
    }

    /**
     * @param array $filters
     * @return array
     * @noinspection MethodShouldBeFinalInspection
     */
    public function list(array $filters = []): array
    {
        $list = $this->request('xui/inbound/list');

        if ($list['success']) {
            $result = [];
            $list_andis = 0;
            $users = $list['obj'];

            foreach ($users as $user) {
                $filter_status = 1;
                $guid = json_decode($user['settings'], true)['clients'][0]['id'] ?? '';

                if (isset($filters['port']) && (int)$filters['port'] != $user['port']) $filter_status = 0;
                if (isset($filters['guid']) && $filters['guid'] != $guid) $filter_status = 0;
                if (isset($filters['protocol']) && $filters['protocol'] != $user['protocol']) $filter_status = 0;

                if ($filter_status) {
                    $usage = $user['up'] + $user['down'];
                    $expiry_time = $user['expiryTime'] ? intval($user['expiryTime'] / 1000) : 0;
                    $expiry_date = $expiry_time ? date('Y-m-d', $expiry_time) : 0;
                    $expiry_days = $expiry_time ? round(($expiry_time - time()) / (60 * 60 * 24)) : 0;
                    $result[$list_andis] = [
                        'id' => $user['id'],
                        'up' => $user['up'],
                        'down' => $user['down'],
                        'usage' => $usage,
                        'total' => $user['total'],
                        'remark' => $user['remark'],
                        'enable' => (bool)$user['enable'],
                        'expiryTime' => $expiry_time,
                        'expiryDate' => $expiry_date,
                        'expiryDays' => $expiry_days,
                        'listen' => $user['listen'],
                        'port' => $user['port'],
                        'protocol' => $user['protocol'],
                        'settings' => json_decode($user['settings']),
                        'streamSettings' => json_decode($user['streamSettings']),
                        'tag' => $user['tag'],
                        'sniffing' => json_decode($user['sniffing'])
                    ];
                    ++$list_andis;
                }
            }

            if (count($result) == 1) return [
                'success' => true,
                'msg' => 'One result found',
                'obj' => $result[0]
            ];
            elseif (count($result) == 0) return [
                'success' => false,
                'msg' => 'Not results found',
                'obj' => ''
            ];
            else return [
                'success' => true,
                'msg' => 'All results',
                'obj' => $result
            ];
        }

        return $list;
    }

    /**
     * @param string|null $protocol
     * @param int $total
     * @param string|null $transmission
     * @param string $remark
     * @param int $port
     * @param int $expiry_time
     * @return array
     */
    public function add(
        string $protocol = null,
        int    $total = 0,
        string $transmission = null,
        string $remark = 'Created By API',
        int    $port = 0,
        int    $expiry_time = 0,
    ): array
    {
        $guid = $this->random_guid();

        if ($guid['success']) {
            $guid = $guid['obj']['guid'];
            $replaces = [
                ['key' => '%GUID%', 'value' => $guid],
                ['key' => '%PASSWORD%', 'value' => $this->random_string()],
            ];
            $config = $this->config($replaces);

            if ($config['success']) {
                $config = $config['obj'];
                $protocol = $protocol ?? $this->settings->protocol;
                $port = $port ?: $this->random_port();
                $expiry_time = $expiry_time ? $expiry_time * 1000 : 0;
                $total *= (1024 * 1024 * 1024);
                $settings = match ($protocol) {
                    'vless' => $config->vless->settings,
                    'trojan' => $config->trojan->settings,
                    default /* vmess */ => $config->vmess->settings
                };

                if ($protocol == 'trojan') $transmission = 'tcp';
                else $transmission = $transmission ?? $this->settings->transmission;

                $stream_settings = match ($transmission) {
                    'ws' => match ($protocol) {
                        'vless' => $config->vless->ws,
                        'vmess' => $config->vmess->ws,
                    },
                    default /* tcp */ => match ($protocol) {
                        'vless' => $config->vless->tcp,
                        'vmess' => $config->vmess->tcp,
                    }
                };
                $sniffing = $config->sniffing;

                $new = [
                    'up' => 0,
                    'down' => 0,
                    'total' => $total,
                    'remark' => $remark,
                    'enable' => true,
                    'expiryTime' => $expiry_time,
                    'listen' => "",
                    'port' => $port,
                    'protocol' => $protocol,
                    'settings' => json_encode($settings),
                    'streamSettings' => json_encode($stream_settings),
                    'sniffing' => json_encode($sniffing)
                ];
                $result = $this->request("xui/inbound/add", $new);
                $result['obj'] = [
                    'port' => $port,
                ];

                return $result;
            }

            return $config;
        }

        return $guid;
    }

    /**
     * @param int $port
     * @param array $changes
     * @return array
     */
    public function update(int $port, array $changes): array
    {
        $guid = $this->random_guid();

        if ($guid['success']) {
            $replaces = [
                ['key' => '%GUID%', 'value' => $guid['obj']['guid']],
                ['key' => '%PASSWORD%', 'value' => $this->random_string()],
            ];
            $config = $this->config($replaces);
            $user = $this->list(['port' => $port]);

            if ($user['success']) {
                if ($config['success']) {
                    $user = $user['obj'];
                    $config = $config['obj'];
                    #-#-#-#-#-#-#-#-#-#-#-#-#
                    $total = $changes['total'] ?? false;
                    $remark = $changes['remark'] ?? false;
                    $expiry_time = $changes['expiryTime'] ?? false;
                    $port = $changes['port'] ?? false;
                    $protocol = $changes['protocol'] ?? false;
                    $transmission = $changes['transmission'] ?? false;

                    if (is_string($remark))
                        $user['remark'] = $remark;

                    if (is_numeric($total))
                        $user['total'] = $total * (1024 * 1024 * 1024);

                    if (is_numeric($expiry_time))
                        $user['expiryTime'] = $expiry_time * 1000;

                    if (is_numeric($port) && !$this->list(['port' => $port])['success'])
                        $user['port'] = $port;

                    if (isset($changes['reset'])) {
                        $user['up'] = 0;
                        $user['down'] = 0;
                    }

                    if (isset($changes['enable']) && is_bool($changes['enable'])) {
                        $user['enable'] = $changes['enable'];
                    }

                    if (is_string($protocol)) {
                        $user['protocol'] = $protocol;
                        $settings = match ($protocol) {
                            'vmess' => $config->vmess->settings,
                            'vless' => $config->vless->settings,
                            'trojan' => $config->trojan->settings,
                            default => $user['settings']
                        };
                        $user['settings'] = $settings;
                    }

                    if (is_string($transmission)) {
                        $stream_settings = match ($transmission) {
                            'ws' => match ($protocol) {
                                'vless' => $config->vless->ws,
                                'vmess' => $config->vmess->ws,
                            },
                            'tcp' => match ($protocol) {
                                'vless' => $config->vless->tcp,
                                'vmess' => $config->vmess->tcp,
                            },
                            default => $user['streamSettings']
                        };
                        $user['streamSettings'] = $stream_settings;
                    }

                    $user['settings'] = json_encode($user['settings']);
                    $user['streamSettings'] = json_encode($user['streamSettings']);
                    $user["sniffing"] = json_encode($user["sniffing"]);
                    $result = $this->request("xui/inbound/update/{$user['id']}", $user);
                    $result['obj'] = ['port' => $user['port']];

                    return $result;
                }

                return $config;
            }

            return $user;
        }

        return $guid;
    }

    /**
     * @param string $key
     * @param string $type
     * @return array
     */
    public function fetch(string $key, string $type = 'guid'): array
    {
        $user = $this->list([$type => $key]);

        if ($user['success']) {
            $user = $user['obj'];
            $port = $user['port'];
            $url = $this->create_url($port);
            $qrcode = $this->create_qrcode($port);

            if ($url['success']) {
                if ($qrcode['success']) {
                    $user['url'] = $url['obj']['url'];
                    $user['qrcode'] = $qrcode['obj']['qrcode'];

                    return [
                        'success' => true,
                        'msg' => 'User information found successfully',
                        'obj' => $user
                    ];
                }

                return $qrcode;
            }

            return $url;
        }

        return $user;
    }

    /**
     * @return array
     */
    private function status(): array
    {
        $status = $this->request("server/status");

        if ($status['success']) {
            $status = $status['obj'];
            $status['cpu'] = round($status['cpu']) . '%';
            $status['mem']['current'] = $this->format_bytes($status['mem']['current']);
            $status['mem']['total'] = $this->format_bytes($status['mem']['total']);
            $status['swap']['current'] = $this->format_bytes($status['swap']['current']);
            $status['swap']['total'] = $this->format_bytes($status['swap']['total']);
            $status['disk']['current'] = $this->format_bytes($status['disk']['current']);
            $status['disk']['total'] = $this->format_bytes($status['disk']['total']);
            $status['netIO']['up'] = $this->format_bytes($status['netIO']['up']);
            $status['netIO']['down'] = $this->format_bytes($status['netIO']['down']);
            $status['netTraffic']['sent'] = $this->format_bytes($status['netTraffic']['sent']);
            $status['netTraffic']['recv'] = $this->format_bytes($status['netTraffic']['recv']);
            $status['uptime'] = $this->format_bytes($status['uptime']);

            return [
                'success' => true,
                'msg' => 'Server status',
                'obj' => $status
            ];
        }

        return $status;
    }

    /**
     * @param int $port
     * @return array
     */
    public function delete(int $port): array
    {
        $user = $this->list(['port' => $port]);

        if ($user['success']) {
            $user = $user['obj'];
            $id = $user['id'];
            return $this->request("xui/inbound/del/$id");
        }

        return $user;
    }

    /**
     * @param array $parse_url
     * @return string
     */
    function build_url(array $parse_url): string
    {
        $build = (isset($parse_url['scheme']) ? "{$parse_url['scheme']}:" : '');
        $build .= ((isset($parse_url['user']) || isset($parse_url['host'])) ? '//' : '');
        $build .= (isset($parse_url['user']) ? "{$parse_url['user']}" : '');
        $build .= (isset($parse_url['pass']) ? ":{$parse_url['pass']}" : '');
        $build .= (isset($parse_url['pass']) ? ":{$parse_url['pass']}" : '');
        $build .= (isset($parse_url['user']) ? '@' : '');
        $build .= (isset($parse_url['host']) ? "{$parse_url['host']}" : '');
        $build .= (isset($parse_url['port']) ? ":{$parse_url['port']}" : '');
        $build .= (isset($parse_url['path']) ? "{$parse_url['path']}" : '');
        $build .= (isset($parse_url['query']) ? "?{$parse_url['query']}" : '');
        $build .= (isset($parse_url['fragment']) ? "#{$parse_url['fragment']}" : '');

        return $build;
    }

    /**
     * @param int $port
     * @return array
     */
    public function create_url(int $port): array
    {
        $user = $this->list(['port' => $port]);

        if ($user['success']) {
            $user = $user['obj'];
            $address = $this->connect->address;
            $protocol = $user['protocol'] ?? '';
            $guid = $user['settings']->clients[0]->id ?? '';
            $password = $user['settings']->clients[0]->password ?? '';
            $remark = $user['remark'] ?? $this->random_string(4);
            $transmission = $user['streamSettings']->network;
            $replaces = [
                ['key' => '%REMARK%', 'value' => $remark],
                ['key' => '%ADDRESS%', 'value' => $address],
                ['key' => '%PORT%', 'value' => $port],
                ['key' => '%USER%', 'value' => $guid],
                ['key' => '%PASS%', 'value' => $password],
                ['key' => '%TRANSMISSION%', 'value' => $transmission],
            ];
            $config = $this->config($replaces);

            if ($config['success']) {
                $config = $config['obj'];

                switch ($protocol) {
                    case 'vmess':
                        $vmess = $config->vmess->url;
                        $vmess->host = base64_encode(json_encode($vmess->host));

                        return [
                            'success' => true,
                            'msg' => '',
                            'obj' => [
                                'url' => $this->build_url((array)$vmess)
                            ]
                        ];

                    case 'vless':
                        return [
                            'success' => true,
                            'msg' => '',
                            'obj' => [
                                'url' => $this->build_url((array)$config->vless->url)
                            ]
                        ];

                    case 'trojan':
                        return [
                            'success' => true,
                            'msg' => '',
                            'obj' => [
                                'url' => $this->build_url((array)$config->trojan->url)
                            ]
                        ];

                    default :
                        return [
                            'success' => false,
                            'msg' => 'Error, url could not be created',
                            'obj' => ''
                        ];
                }
            }

            return $config;
        }

        return $user;
    }

    /**
     * @param string $url
     * @return array
     */
    public function read_url(string $url): array
    {
        $url = parse_url($url);
        $error = [
            'success' => false,
            'msg' => 'The url cannot be read',
            'obj' => ''
        ];

        switch ($url['scheme'] ?? '') {
            case 'vmess':
                $data = base64_decode($url['host']);
                $data = json_decode($data, true);
                $host = $data['add'] ?? false;
                $port = $data['port'] ?? false;
                $id = $data['id'] ?? false;

                if ($host && $port && $id) return [
                    'success' => true,
                    'msg' => '',
                    'obj' => [
                        'host' => $host,
                        'port' => $port,
                        'user' => $id,
                    ]
                ];

                return $error;

            case 'vless':
            case 'trojan':
                $host = $url['host'] ?? false;
                $port = $url['port'] ?? false;
                $id = $url['user'] ?? false;

                if ($host && $port && $id) return [
                    'success' => true,
                    'msg' => '',
                    'obj' => [
                        'host' => $host,
                        'port' => $port,
                        'user' => $id,
                    ]
                ];

                return $error;
        }

        return $error;
    }


    /**
     * @param int $port
     * @return array
     */
    public function create_qrcode(int $port): array
    {
        $url = $this->create_url($port);

        if ($url['success']) {
            $text = $url['obj']['url'];

            return [
                'success' => true,
                'msg' => '',
                'obj' => [
                    'qrcode' => "https://api.qrserver.com/v1/create-qr-code?data=$text&size=1000x1000&qzone=4"
                ]
            ];
        }

        return $url;
    }

    /**
     * @return array
     */
    private function random_guid(): array
    {
        try {
            $data = random_bytes(16);
            assert(strlen($data) == 16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $result = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

            return [
                'success' => true,
                'msg' => 'Create guid successfully',
                'obj' => [
                    'guid' => $result
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'msg' => "Error message {$e->getMessage()}",
                'obj' => ''
            ];
        }
    }

    /**
     * @param int $length
     * @return string
     */
    private function random_string(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars_length = strlen($characters) - 1;
        $randstring = '';

        for ($step = 0; $step <= $length; $step++) {
            $randstring .= $characters[rand(0, $chars_length)];
        }

        return $randstring;
    }

    /**
     * @return int
     */
    private function random_port(): int
    {
        while (true) {
            $random_port = rand(1000, 65000);
            $check_port = $this->list(['port' => $random_port]);
            if (!$check_port['success']) break;
        }

        return $random_port;
    }

    /**
     * @param int $size
     * @param int $format
     * @param int $precision
     * @param bool $array_return
     * @return array|string
     */
    public function format_bytes(
        int  $size,
        int  $format = 2,
        int  $precision = 0,
        bool $array_return = false
    ): array|string
    {
        $base = log($size, 1024);

        if ($format == 1) {
            $suffixes = ['بایت', 'کلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت']; # Persian
        } elseif ($format == 2) {
            $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        } else {
            $suffixes = ['B', 'K', 'M', 'G', 'T'];
        }

        if ($size <= 0) return [0, $suffixes[1]];

        $result = pow(1024, $base - floor($base));
        $result = round($result, $precision);
        $suffixes = $suffixes[floor($base)];

        if ($array_return) return [$result, $suffixes];
        else return "$result $suffixes";
    }

    /**
     * @param int $time
     * @param int $format
     * @param bool $array_return
     * @return array|string
     */
    public function format_time(
        int  $time,
        int  $format = 2,
        bool $array_return = false
    ): array|string
    {
        if ($format == 1) {
            $suffixes = ['ثانیه', 'دقیقه', 'ساعت', 'روز', 'هفته', 'ماه', 'سال']; # Persian
        } else {
            $suffixes = ['Second(s)', 'Minute(s)', 'Hour(s)', 'Day(s)', 'Week(s)', 'Month(s)', 'Year(s)'];
        }

        if ($time >= 1 && $time <= 60) {
            $time = round($time);
            $suffixes = $suffixes[0];
        } elseif ($time > 60 && $time <= 3600) {
            $time = round($time / 60);
            $suffixes = $suffixes[1];
        } elseif ($time > 3600 && $time <= 86400) {
            $time = round($time / 3600);
            $suffixes = $suffixes[2];
        } elseif ($time > 86400 && $time <= 604800) {
            $time = round($time / 86400);
            $suffixes = $suffixes[3];
        } elseif ($time > 604800 && $time <= 2600640) {
            $time = round($time / 604800);
            $suffixes = $suffixes[4];
        } elseif ($time > 2600640 && $time <= 31207680) {
            $time = round($time / 2600640);
            $suffixes = $suffixes[5];
        } elseif ($time > 31207680) {
            $time = round($time / 31207680);
            $suffixes = $suffixes[6];
        } else {
            $time = 0;
            $suffixes = $suffixes[0];
        }

        if ($array_return) return [$time, $suffixes];
        else return "$time $suffixes";
    }
}