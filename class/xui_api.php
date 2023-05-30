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
        string $address = 'https://example.com',
        int    $port = 54321,
        string $username = 'admin',
        string $password = 'admin',
        string $default_protocol = 'vless',
        string $default_transmission = 'ws',
        bool   $is_3xui = false,
    )
    {
        # Connect
        $url = parse_url($address);
        $this->connect = new  stdClass();
        $this->connect->protocol = $url['scheme'];
        $this->connect->address = $url['host'];
        $this->connect->port = $port;
        $this->connect->username = $username;
        $this->connect->password = $password;
        $this->connect->url = "$address:$port/";

        # Settings
        $this->settings = new stdClass();
        $this->settings->is_3xui = $is_3xui;
        $this->settings->path = $is_3xui ? 'panel' : 'xui';
        $this->settings->protocol = $default_protocol;
        $this->settings->transmission = $default_transmission;

        # Cookies
        $this->cookies = new stdClass();
        $this->cookies->directory = './.cookies/';
        $this->cookies->file = $this->cookies->directory . "{$this->connect->address}.$port.txt";

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
    private function config(array $replaces = []): array
    {
        if (file_exists('config.json')) {
            $guid = $this->random_guid();

            if ($guid['success']) {
                $json = file_get_contents('config.json');

                if ($replaces) {
                    foreach ($replaces as $replace) {
                        $key = $replace['key'] ?? false;
                        $value = $replace['value'] ?? false;
                        $json = str_replace($key, $value, $json);
                    }
                }

                $contents = json_decode($json);
                $contents = $this->settings->is_3xui ? $contents->p3xui : $contents->xui;

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
        $list = $this->request("{$this->settings->path}/inbound/list");

        if ($list['success']) {
            $result = [];
            $list_andis = 0;
            $users = $list['obj'];

            if ($this->settings->is_3xui) {
                $inbounds = $users;

                foreach ($inbounds as $users) {
                    $clients = json_decode($users['settings'], true)['clients'];
                    $stream_settings = json_decode($users['streamSettings'], true);
                    $client_stats = $users['clientStats'];

                    foreach ($clients as $user) {
                        $filter_status = 1;

                        if (isset($filters['port']) && $filters['port'] != $users['port']) $filter_status = 0;
                        if (isset($filters['protocol']) && $filters['protocol'] != $users['protocol']) $filter_status = 0;
                        if (isset($filters['guid']) && $filters['guid'] != ($user['id'] ?? '')) $filter_status = 0;
                        if (isset($filters['password']) && $filters['password'] != ($user['password'] ?? '')) $filter_status = 0;
                        if (isset($filters['email']) && $filters['email'] != $user['email']) $filter_status = 0;
                        if (isset($filters['transmission']) && $filters['transmission'] != $stream_settings['network']) $filter_status = 0;

                        if ($filter_status) {
                            $result[$list_andis]['settings'] = $user;
                        }

                        if (isset($result[$list_andis])) {
                            foreach ($client_stats as $state) {
                                if ($state['email'] == $result[$list_andis]['settings']['email']) {
                                    $usage = $state['up'] + $state['down'];
                                    $expiry_time = $state['expiryTime'] ? intval($state['expiryTime'] / 1000) : 0;
                                    $expiry_date = $expiry_time ? date('Y-m-d', $expiry_time) : 0;
                                    $expiry_days = $expiry_time ? round(($expiry_time - time()) / (60 * 60 * 24)) : 0;

                                    $result[$list_andis]['id'] = $state['id'];
                                    $result[$list_andis]['port'] = $users['port'];
                                    $result[$list_andis]['inboundId'] = $state['inboundId'];
                                    $result[$list_andis]['enable'] = $state['enable'];
                                    $result[$list_andis]['email'] = $state['email'];
                                    $result[$list_andis]['up'] = $state['up'];
                                    $result[$list_andis]['down'] = $state['down'];
                                    $result[$list_andis]['usage'] = $usage;
                                    $result[$list_andis]['expiryTime'] = $expiry_time;
                                    $result[$list_andis]['expiryDate'] = $expiry_date;
                                    $result[$list_andis]['expiryDays'] = $expiry_days;
                                    $result[$list_andis]['total'] = $state['total'];
                                    $result[$list_andis]['remark'] = $users['remark'];
                                    $result[$list_andis]['protocol'] = $users['protocol'];
                                    $result[$list_andis]['transmission'] = $stream_settings['network'];
                                }
                            }

                            if (isset($result[$list_andis]['id'])) ++$list_andis;
                            else unset($result[$list_andis]);
                        }
                    }
                }
            } else {
                foreach ($users as $user) {
                    $filter_status = 1;
                    $guid = json_decode($user['settings'], true)['clients'][0]['id'] ?? '';
                    $password = json_decode($user['settings'], true)['clients'][0]['password'] ?? '';

                    if (isset($filters['port']) && (int)$filters['port'] != $user['port']) $filter_status = 0;
                    if (isset($filters['guid']) && $filters['guid'] != $guid) $filter_status = 0;
                    if (isset($filters['password']) && $filters['password'] != $password) $filter_status = 0;
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
     * @info for 3x-ui panel
     * @param string $protocol
     * @param string $transmission
     * @return array
     */
    private function get_inbound(string $protocol, string $transmission): array
    {
        if ($this->settings->is_3xui) {
            $inbound = $this->list(['protocol' => $protocol, 'transmission' => $transmission]);

            if ($inbound['success']) {
                $data = $inbound['obj'][0] ?? $inbound['obj'];

                return [
                    'success' => true,
                    'msg' => '',
                    'obj' => [
                        'inbound' => $data['inboundId']
                    ]
                ];
            }

            $guid = $this->random_guid();

            if ($guid['success']) {
                $guid = $guid['obj']['guid'];
                $port = $this->random_port();
                $replaces = [
                    ['key' => '%GUID%', 'value' => $guid],
                    ['key' => '%EMAIL%', 'value' => "API-DEFAULT-$port"],
                    ['key' => '%LIMIT_IP%', 'value' => 0],
                    ['key' => '%TOTAL%', 'value' => 0],
                    ['key' => '%EXPIRY_TIME%', 'value' => 0],
                    ['key' => '%PASSWORD%', 'value' => $this->random_string()],
                    ['key' => '%ENABLE%', 'value' => true],
                ];
                $config = $this->config($replaces);

                if ($config['success']) {
                    $config = $config['obj'];
                    $new = [
                        'up' => 0,
                        'down' => 0,
                        'total' => 0,
                        'remark' => "$protocol-$transmission",
                        'enable' => true,
                        'expiryTime' => 0,
                        'listen' => "",
                        'port' => $port,
                        'protocol' => $protocol,
                        'settings' => json_encode(match ($protocol) {
                            'vmess' => $config->vmess->settings,
                            'trojan' => $config->trojan->settings,
                            default /* vless */ => $config->vless->settings
                        }),
                        'streamSettings' => json_encode(match ($transmission) {
                            'ws' => match ($protocol) {
                                'vmess' => $config->vmess->ws,
                                default /* vless */ => $config->vless->ws,
                            },
                            default /* tcp */ => match ($protocol) {
                                'vmess' => $config->vmess->tcp,
                                default /* vless */ => $config->vless->tcp,
                            }
                        }),
                        'sniffing' => json_encode($config->sniffing)
                    ];
                    $result = $this->request("panel/inbound/add", $new);

                    if ($result['success']) {
                        $result = $result['obj'];
                        $result = [
                            'success' => true,
                            'msg' => '',
                            'obj' => [
                                'inbound' => $result['id'],
                                'port' => $result['port']
                            ]
                        ];
                    }

                    return $result;
                }

                return $config;
            }

            return $guid;
        }

        return [
            'success' => true,
            'msg' => 'The panel type is not 3x-ui',
            'obj' => ''
        ];
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
            $total *= (1024 * 1024 * 1024);
            $expiry_time = $expiry_time ? $expiry_time * 1000 : 0;
            $email = $this->random_string();
            $replaces = [
                ['key' => '%GUID%', 'value' => $guid],
                ['key' => '%PASSWORD%', 'value' => $this->random_string()],
                ['key' => '%EMAIL%', 'value' => $email],
                ['key' => '%LIMIT_IP%', 'value' => 0],
                ['key' => '%TOTAL%', 'value' => $total],
                ['key' => '%EXPIRY_TIME%', 'value' => $expiry_time],
                ['key' => '%ENABLE%', 'value' => true],
            ];
            $config = $this->config($replaces);

            if ($config['success']) {
                $config = $config['obj'];
                $transmission = $protocol == 'trojan' ? 'tcp' : $transmission ?? $this->settings->transmission;

                if ($this->settings->is_3xui) {
                    $inbound = $this->get_inbound($protocol, $transmission);

                    if ($inbound['success']) {
                        $inbound = $inbound['obj'];
                        $new = [
                            'id' => $inbound['inbound'],
                            'settings' => json_encode(match ($protocol) {
                                'vmess' => $config->vmess->settings,
                                'trojan' => $config->trojan->settings,
                                default /* vless */ => $config->vless->settings,
                            })
                        ];
                        $result = $this->request("panel/inbound/addClient", $new);
                        $result['obj'] = [
                            'email' => $email,
                        ];

                        return $result;
                    }

                    return $inbound;
                } else {
                    $protocol = $protocol ?? $this->settings->protocol;
                    $port = $port ?: $this->random_port();
                    $settings = match ($protocol) {
                        'vless' => $config->vless->settings,
                        'trojan' => $config->trojan->settings,
                        default /* vmess */ => $config->vmess->settings
                    };

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
                }

                return $result;
            }

            return $config;
        }

        return $guid;
    }

    /**
     * @param string $guid
     * @param array $changes
     * @return array
     */
    public function update(string $guid, array $changes): array
    {
        $user = $this->list(['guid' => $guid]);

        if ($user['success']) {
            if ($this->settings->is_3xui) {
                $user = $user['obj'][0] ?? $user['obj'];
                $expiry_time = isset($changes['expiryTime']) ? ($changes['expiryTime'] * 1000) : $user['expiryTime'] ?? '';
                $total = isset($changes['total']) ? ($changes['total'] * (1024 * 1024 * 1024)) : $user['total'] ?? '';
                $limit_ip = $changes['limitIp'] ?? $user['settings']['limitIp'] ?? '';
                $enable = (isset($changes['enable']) && is_bool($changes['enable'])) ? $changes['enable'] : $user['enable'] ?? '';
                $replaces = [
                    ['key' => '%GUID%', 'value' => $user['settings']['id'] ?? ''],
                    ['key' => '%EMAIL%', 'value' => $user['email'] ?? ''],
                    ['key' => '%LIMIT_IP%', 'value' => $limit_ip],
                    ['key' => '%TOTAL%', 'value' => $total],
                    ['key' => '%EXPIRY_TIME%', 'value' => $expiry_time],
                    ['key' => '"%ENABLE%"', 'value' => $enable ? 'true' : 'false'],
                ];
                $config = $this->config($replaces);

                if ($config['success']) {
                    $config = $config['obj'];
                    $update['id'] = $user['inboundId'];
                    $update['settings'] = json_encode(match ($user['protocol']) {
                        'vmess' => $config->vmess->settings,
                        default /* vless */ => $config->vless->settings
                    });
                    $result = $this->request("panel/inbound/updateClient/{$user['settings']['id']}", $update);
                    $result['obj'] = ['email' => $user['email']];

                    return $result;
                }

                return $config;
            } else {
                $guid = $this->random_guid();

                if ($guid['success']) {
                    $replaces = [
                        ['key' => '%GUID%', 'value' => $guid['obj']['guid']],
                        ['key' => '%PASSWORD%', 'value' => $this->random_string()],
                    ];
                    $config = $this->config($replaces);

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

                return $guid;
            }
        }

        return $user;
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
            $url = $this->create_url($key, $type);
            $qrcode = $this->create_qrcode($key, $type);

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
     * @param string $key
     * @param string $type
     * @return array
     */
    public function delete(string $key, string $type = 'guid'): array
    {
        $user = $this->list([$type => $key]);

        if ($user['success']) {
            $user = $user['obj'][0] ?? $user['obj'];
            $is_3xui = $this->settings->is_3xui;
            $delete = $is_3xui ? "panel/inbound/{$user['inboundId']}/delClient/$key" : "xui/inbound/del/{$user['id']}";

            return $this->request($delete);
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
     * @param string $key
     * @param string $type
     * @return array
     */
    public function create_url(string $key, string $type = 'guid'): array
    {
        $user = $this->list([$type => $key]);

        if ($user['success']) {
            $user = $user['obj'][0] ?? $user['obj'];
            $address = $this->connect->address;

            if ($this->settings->is_3xui) {
                $email = $user['email'] ?? '';
                $protocol = $user['protocol'] ?? '';
                $port = $user['port'] ?? '';
                $remark = '';
                $transmission = $user['transmission'] ?? '';
            } else {
                $email = '';
                $protocol = $user['protocol'] ?? '';
                $port = $user['port'] ?? '';
                $remark = $user['remark'] ?? $this->random_string(4);
                $transmission = $user['streamSettings']->network;
            }

            $replaces = [
                ['key' => '%REMARK%', 'value' => $remark],
                ['key' => '%EMAIL%', 'value' => $email],
                ['key' => '%ADDRESS%', 'value' => $address],
                ['key' => '%PORT%', 'value' => $port],
                ['key' => '%TRANSMISSION%', 'value' => $transmission],
            ];
            $input = match ($type) {
                'password' => [['key' => '%PASS%', 'value' => $key]],
                default /* guid */ => [['key' => '%USER%', 'value' => $key]]
            };

            $replaces = array_merge($replaces, $input);

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
     * @param string $key
     * @param string $type
     * @return array
     */
    public function create_qrcode(string $key, string $type = 'guid'): array
    {
        $url = $this->create_url($key, $type);

        if ($url['success']) {
            $text = urlencode($url['obj']['url']);
            $url = [
                'scheme' => 'https',
                'host' => 'quickchart.io',
                'path' => '/qr',
                'query' => "text=$text&margin=3&size=1080&format=svg&dark=523489&ecLevel=L",
            ];
            $qrcode = $this->build_url($url);

            return [
                'success' => true,
                'msg' => '',
                'obj' => [
                    'qrcode' => $qrcode
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