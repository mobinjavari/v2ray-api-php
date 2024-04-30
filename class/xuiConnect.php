<?php /** @noinspection MethodShouldBeFinalInspection */

/*\
 * | - Version : xuiConnect v3
 * | - Author : github.com/mobinjavari
\*/

class xuiConnect
{
    /**
     * @var string
     */
    private string $address;

    /**
     * @var string
     */
    private string $serverAddress;
    
    /**
     * @var array
     */
    private array $parseAddress;

    /**
     * @var string
     */
    private string $username;

    /**
     * @var string
     */
    private string $password;

    /**
     * @var array
     */
    private array $settings;

    /**
     * @var array
     */
    private array $cookies;

    /**
     * @var array
     */
    private array $login;

    /**
     * @var array
     */
    public array $status;

    /**
     * @param string $serverAddress
     * @param string|null $tunnelServerAddress
     * @param string $username
     * @param string $password
     * @param int $panel
     */
    public function __construct(
        string $serverAddress = 'api://example.org:54321/',
        string $tunnelServerAddress = null, # api://10.10.10.10:54321/
        string $username = 'admin', # Panel Username
        string $password = 'admin', # Panel Password
        int   $panel = 0, # xui(0) - 3xui(1)
    )
    {
        /* Server Address */
        $this->serverAddress = $serverAddress;
        $this->address = xuiTools::formatServerUrl($tunnelServerAddress ?: $serverAddress ?: '');
        /* Parse Server Address */
        $this->parseAddress = parse_url($this->address) ?? [];
        /* Panel Username */
        $this->username = $username;
        /* Panel Password */
        $this->password = $password;
        /* Panel Settings */
        $this->settings = [
            'TYPE' => $panel,
            'ROOT' => match ($panel) {
                1 => 'panel',
                default => 'xui'
            },
            'DEFAULTS' => [
                'PROTOCOL' => 'vless',
                'TRANSMISSION' => 'ws',
                'HEADER' => 'speedtest.net',
            ],
            'SNIFFING' => [
                'enabled' => true,
                'destOverride' => match ($panel) {
                    1 => ['http', 'tls', 'quic'],
                    default => ['http', 'tls']
                }
            ]
        ];
        /* Cookies */
        $cookieFileName = md5($this->address . $username . $password . $_SERVER['HTTP_HOST']);
        $cookiesDirPath = __DIR__ . '/.xuiCookies';
        $this->cookies = [
            'DIR' => $cookiesDirPath,
            'FILE' => "$cookiesDirPath/$cookieFileName.TXT",
        ];
        /* Login */
        $this->login = $this->login();
        /* Server Status */
        $this->status = $this->status();
    }

    /**
     * @param string $protocol
     * @return void
     */
    public function setDefaultProtocol(string $protocol): void
    {
        $this->settings['DEFAULTS']['PROTOCOL'] = match (strtolower($protocol)) {
            'vmess' => 'vmess',
            'trojan' => 'trojan',
            default => 'vless'
        };
    }

    /**
     * @param string $transmission
     * @return void
     */
    public function setDefaultTransmission(string $transmission): void
    {
        $this->settings['DEFAULTS']['TRANSMISSION'] = match (strtolower($transmission)) {
            'ws' => 'ws',
            default => 'tcp'
        };
    }

    /**
     * @param string $header
     * @return void
     */
    public function setDefaultHeader(string $header): void
    {
        $this->settings['DEFAULTS']['HEADER'] = $header;
    }

    /**
     * @param bool $enable
     * @param array $destOverride
     * @return void
     */
    public function setSniffing(bool $enable, array $destOverride): void
    {
        $this->settings['SNIFFING'] = [
            'enabled' => $enable,
            'destOverride' => $destOverride,
        ];
    }

    /**
     * @return void
     */
    public function deleteCookie(): void
    {
        if (file_exists($this->cookies['FILE']))
            unlink($this->cookies['FILE']);
    }

    /**
     * @param string $method
     * @param array $param
     * @return array
     */
    private function sendRequest(string $method, array $param = []): array
    {
        if (!is_dir($this->cookies['DIR'])) mkdir($this->cookies['DIR']);

        if (filter_var($this->address, FILTER_VALIDATE_URL)) {
            $options = [
                CURLOPT_URL =>  $this->address . $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_COOKIEFILE => $this->cookies['FILE'],
                CURLOPT_COOKIEJAR => $this->cookies['FILE'],
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT  => 10,
                CURLOPT_SSL_VERIFYPEER   => false,
                CURLOPT_SSL_VERIFYHOST   => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($param)
            ];
            $curl = curl_init();
            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode != 200) $this->deleteCookie();

            return match ($httpCode) {
                200 => json_decode($response, true),
                default => xuiTools::httpStatus($httpCode)
            };
        }

        $this->deleteCookie();
        return xuiTools::httpStatus(400);
    }

    /**
     * @return array
     */
    private function login(): array
    {
        $hostname = $this->parseAddress['host'] ?? '';
        $port = $this->parseAddress['port'] ?? 443;
        $check = fsockopen($hostname, $port, $errCode, $errMessage, 5);

        if ($check) {
            if (file_exists($this->cookies['FILE']))
                return xuiTools::httpStatus(200, 'Cookies are already set');

            $login = $this->sendRequest('login', [
                'username' => $this->username,
                'password' => $this->password,
                'LoginSecret' => '',
            ]);

            if (!$login['success']) $this->deleteCookie();

            return $login;
        }

        $this->deleteCookie();
        return xuiTools::httpStatus($errCode, $errMessage);
    }

    /**
     * @param string $method
     * @param array $param
     * @return array
     */
    private function request(string $method, array $param = []): array
    {
        if ($this->login['success'])
            return $this->sendRequest($method, $param);

        return $this->login;
    }

    /**
     * @param array $filters
     * @return array
     * @noinspection MethodShouldBeFinalInspection
     */
    private function list(array $filters = []): array
    {
        $list = $this->request("{$this->settings['ROOT']}/inbound/list");

        if ($list['success']) {
            $result = [];
            $listIndex = 0;

            if ($data = $list['obj'] ?? [])
                switch ($this->settings['TYPE']) {
                    case 1: # 3XUI
                        /* Panel Inbounds */
                        $inbounds = $data;

                        foreach ($inbounds as $inbound) {
                            /* Inbound Clients */
                            $clients = json_decode($inbound['settings'], true)['clients'] ?? [];
                            /* Inbound Stream Settings */
                            $streamSettings = json_decode($inbound['streamSettings'], true) ?? [];
                            /* Inbound Client Stats */
                            $clientStats = $inbound['clientStats'] ?? [];
                            /* $inbound Filter Status */
                            $inboundFilterStatus = true;
                            /* Inbound Result */
                            $inboundUpload = $inbound['up'];
                            $inboundDownload = $inbound['down'];
                            $inboundTotal = $inbound['total'];
                            $inboundUsage = $inboundUpload + $inboundDownload;
                            $inboundRemaining = $inboundTotal ? $inboundTotal - $inboundUsage : 0;
                            $inboundExpiryTime = intval($inbound['expiryTime'] / 1000);
                            $inboundExpiryDays =
                                $inboundExpiryTime ? round(($inboundExpiryTime - time()) / (60 * 60 * 24)) : 0;
                            $inboundUsagePercent = $inboundTotal ? round($inboundUsage * 100 / $inboundTotal) : 0;
                            $inboundUploadPercent = $inboundUsage ? round($inboundUpload * $inboundUsagePercent / $inboundUsage) : 0;
                            $inboundPercents = [
                                'up' => $inboundUploadPercent,
                                'down' => $inboundUsagePercent - $inboundUploadPercent,
                                'usage' => $inboundUsagePercent,
                                'remaining' => 100 - $inboundUsagePercent,
                            ];
                            $inboundResult = [
                                'id' => $inbound['id'],
                                'up' => $inboundUpload,
                                'down' => $inboundDownload,
                                'usage' => $inboundUsage,
                                'remaining' => $inboundRemaining,
                                'total' => $inboundTotal,
                                'percents' => $inboundPercents,
                                'expiryTime' => $inboundExpiryTime,
                                'expiryDays' => $inboundExpiryDays,
                                'panelType' => $this->settings['ROOT'],
                                'enable' => boolval($inbound['enable']),
                                'port' => $inbound['port'],
                                'protocol' => $inbound['protocol'],
                                'transmission' => $streamSettings['network'],
                                'remark' => $inbound['remark'],
                            ];
                            /* Inbound Filters */
                            if (isset($filters['enable']) && (bool)$filters['enable'] != boolval($inbound['enable'] ?? '')) $inboundFilterStatus = 0;
                            if (isset($filters['remark']) && $filters['remark'] != ($inbound['remark'] ?? '')) $inboundFilterStatus = 0;
                            if (isset($filters['port']) && $filters['port'] != ($inbound['port'] ?? '')) $inboundFilterStatus = 0;
                            if (isset($filters['protocol']) && $filters['protocol'] != ($inbound['protocol'] ?? '')) $inboundFilterStatus = 0;
                            if (isset($filters['transmission']) && $filters['transmission'] != ($streamSettings['network'] ?? '')) $inboundFilterStatus = 0;

                            if ($inboundFilterStatus)
                                foreach ($clients as $client) {
                                    /* Client Filter Status */
                                    $clientFilterStatus = true;
                                    /* Client Filters */
                                    if (isset($filters['enable']) && (bool)$filters['enable'] != boolval($client['enable'] ?? '')) $clientFilterStatus = 0;
                                    if (isset($filters['uuid']) && $filters['uuid'] != ($client['id'] ?? '')) $clientFilterStatus = 0;
                                    if (isset($filters['password']) && $filters['password'] != ($client['password'] ?? '')) $clientFilterStatus = 0;
                                    if (isset($filters['email']) && $filters['email'] != ($client['email'] ?? '')) $clientFilterStatus = 0;

                                    if ($clientFilterStatus) {
                                        foreach ($clientStats as $state) {
                                            if ($state['email'] == $client['email']) {
                                                /* Client Result */
                                                $upload = $state['up'];
                                                $download = $state['down'];
                                                $total = $state['total'];
                                                $usage = $upload + $download;
                                                $remaining = $total ? $total - $usage : 0;
                                                $expiryTime = $state['expiryTime'] ? intval($state['expiryTime'] / 1000) : 0;
                                                $expiryDays = $expiryTime ? round(($expiryTime - time()) / (60 * 60 * 24)) : 0;
                                                $usagePercent = $total ? round($usage * 100 / $total) : 0;
                                                $uploadPercent = $usage ? round($upload * $usagePercent / $usage) : 0;
                                                $percents = [
                                                    'up' => $uploadPercent,
                                                    'down' => $usagePercent - $uploadPercent,
                                                    'usage' => $usagePercent,
                                                    'remaining' => 100 - $usagePercent,
                                                ];
                                                $clientResult = [
                                                    'id' => $state['id'],
                                                    'up' => $upload,
                                                    'down' => $download,
                                                    'usage' => $usage,
                                                    'remaining' => $remaining,
                                                    'total' => $total,
                                                    'percents' => $percents,
                                                    'expiryTime' => $expiryTime,
                                                    'expiryDays' => $expiryDays,
                                                    'enable' => boolval($state['enable']),
                                                    'email' => $client['email'],
                                                    'limitIp' => $client['limitIp'] ?? 0,
                                                    'subId' => $client['subId'] ?? '',
                                                ];
                                                $clientResult = array_merge($clientResult, match ($inboundResult['protocol']) {
                                                    'trojan' => ['password' => $client['password']],
                                                    default => ['uuid' => $client['id']]
                                                });
                                                /* Main Result */
                                                $result[$listIndex++] = [
                                                    'inbound' => $inboundResult,
                                                    'user' => $clientResult,
                                                ];
                                            }
                                        }
                                    }
                                }
                        }
                        break;

                    default: # XUI
                        /* Panel Users */
                        $users = $data;

                        foreach ($users as $user) {
                            /* User Settings */
                            $settings = json_decode($user['settings'], true)['clients'][0] ?? [];
                            /* Stream Settings */
                            $streamSettings = json_decode($user['streamSettings'], true) ?? [];
                            /* Filter Status */
                            $filterStatus = true;
                            /* Filter Users */
                            if (isset($filters['enable']) && (bool)$filters['enable'] != boolval($user['enable'] ?? '')) $filterStatus = 0;
                            if (isset($filters['remark']) && $filters['remark'] != ($user['remark'] ?? '')) $filterStatus = 0;
                            if (isset($filters['port']) && (int)$filters['port'] != intval($user['port'] ?? '')) $filterStatus = 0;
                            if (isset($filters['protocol']) && $filters['protocol'] != ($user['protocol'] ?? '')) $filterStatus = 0;
                            if (isset($filters['transmission']) && $filters['transmission'] != ($streamSettings['network'] ?? '')) $filterStatus = 0;
                            if (isset($filters['uuid']) && $filters['uuid'] != ($settings['id'] ?? '')) $filterStatus = 0;
                            if (isset($filters['password']) && $filters['password'] != ($settings['password'] ?? '')) $filterStatus = 0;

                            if ($filterStatus) {
                                /* Inbound & User Result */
                                $upload = $user['up'];
                                $download = $user['down'];
                                $total = $user['total'];
                                $usage = $user['up'] + $user['down'];
                                $remaining = $total ? $total - $usage : 0;
                                $expiryTime = intval($user['expiryTime'] / 1000);
                                $expiryDays = $expiryTime ? round(($expiryTime - time()) / (60 * 60 * 24)) : 0;
                                $usagePercent = $total ? round($usage * 100 / $total) : 0;
                                $uploadPercent = $usage ? round($upload * $usagePercent / $usage) : 0;
                                $percents = [
                                    'up' => $uploadPercent,
                                    'down' => $usagePercent - $uploadPercent,
                                    'usage' => $usagePercent,
                                    'remaining' => 100 - $usagePercent,
                                ];
                                $inboundResult = [
                                    'id' => $user['id'],
                                    'up' => $upload,
                                    'down' => $download,
                                    'usage' => $usage,
                                    'remaining' => $remaining,
                                    'total' => $total,
                                    'percents' => $percents,
                                    'expiryTime' => $expiryTime,
                                    'expiryDays' => $expiryDays,
                                    'panelType' => $this->settings['ROOT'],
                                    'enable' => boolval($user['enable']),
                                    'port' => $user['port'],
                                    'protocol' => $user['protocol'],
                                    'transmission' => $streamSettings['network'],
                                    'remark' => $user['remark'],
                                ];
                                $userResult = [
                                    'id' => $user['id'],
                                    'up' => $upload,
                                    'down' => $download,
                                    'usage' => $usage,
                                    'remaining' => $remaining,
                                    'total' => $total,
                                    'percents' => $percents,
                                    'expiryTime' => $expiryTime,
                                    'expiryDays' => $expiryDays,
                                    'enable' => boolval($user['enable']),
                                    'email' => '',
                                    'limitIp' => 0,
                                    'subId' => '',
                                ];
                                $userResult = array_merge($userResult, match ($inboundResult['protocol']) {
                                    'trojan' => ['password' => $settings['password']],
                                    default => ['uuid' => $settings['id']]
                                });
                                /* Main Result */
                                $result[$listIndex++] = [
                                    'inbound' => $inboundResult,
                                    'user' => $userResult,
                                ];
                            }
                        }
                        break;
                }

            if (count($result))
                return xuiTools::httpStatus(200, 'List Successfully', $result);
            return xuiTools::httpStatus(404, 'Not results found');
        }

        return $list;
    }

    /**
     * @param string|null $protocol
     * @param string|null $transmission
     * @param array $replaces
     * @return array
     */
    private function xuiConfig(string $protocol = null, string $transmission = null, array $replaces = []): array
    {
        $protocol = $protocol ?: $this->settings['DEFAULTS']['PROTOCOL'];
        $transmission = $transmission ?: $this->settings['DEFAULTS']['TRANSMISSION'];
        $configPath = __DIR__ . '/.xuiConfig.json';

        if (file_exists($configPath)) {
            $configJson = file_get_contents($configPath);
            $replaces['%HEADER%'] = $this->settings['DEFAULTS']['HEADER'];

            foreach ($replaces as $replaceKey => $replaceValue) {
                $configJson = str_replace($replaceKey, $replaceValue, $configJson);
            }

            $configData = json_decode($configJson);
            $config = match ($this->settings['TYPE']) {
                1 => $configData[1],
                default => $configData[0]
            };
            $configProtocol = $config->$protocol ?? false;
            $configSettings = $configProtocol->settings ?? false;
            $configStreamSettings = $configProtocol->$transmission ?? false;
            $configUrl = $configProtocol->url ?? false;
            $result = ($configSettings && $configStreamSettings && $configUrl) ? [
                'settings' => $configSettings,
                'streamSettings' => [
                    'network' => $transmission,
                    'security' => 'none',
                    "{$transmission}Settings" => $configStreamSettings
                ],
                'sniffing' => $this->settings['SNIFFING'],
                'url' => $configUrl,
            ] : [];

            return $result ? xuiTools::httpStatus(200, 'Account creation method', $result) : xuiTools::httpStatus(400);
        }

        return xuiTools::httpStatus(500, 'API Config file not exists');
    }

    /**
     * @return int
     */
    private function randPort(): int
    {
        while (true) {
            $randPort = rand(1000, 65000);
            $checkPort = $this->list(['port' => $randPort]);

            if (!$checkPort['success']) break;
        }

        return $randPort;
    }

    /**
     * @param string|null $protocol
     * @param string|null $transmission
     * @param int|null $port
     * @return array
     * @throws Exception
     */
    private function getInbound(string $protocol = null, string $transmission = null, int $port = null): array
    {
        $protocol = $protocol ?: $this->settings['DEFAULTS']['PROTOCOL'];
        $transmission = $transmission ?: $this->settings['DEFAULTS']['TRANSMISSION'];

        switch ($this->settings['TYPE']) {
            case 1:
                $inboundFilters = ['protocol' => $protocol, 'transmission' => $transmission];
                if ($port) $inboundFilters['port'] = $port;
                $checkInbound = $this->list($inboundFilters);

                if ($checkInbound['success']) {
                    $data = $checkInbound['obj'][0];

                    return [
                        'success' => true,
                        'msg' => 'Inbound exists',
                        'obj' => [
                            'inboundId' => $data['inbound']['id'],
                            'inboundPort' => $data['inbound']['port'],
                        ]
                    ];
                }

                $uuid = xuiTools::randUUID();
                $port = $this->randPort();
                $password = xuiTools::randStr();
                $email = xuiTools::randStr(8);
                $remark = 'API-' . strtoupper($protocol) . '-' . strtoupper($transmission);
                $replaces = [
                    '%UUID%' => $uuid,
                    '%EMAIL%' => $email,
                    '%LIMIT_IP%' => 0,
                    '%TOTAL%' => 0,
                    '%EXPIRY_TIME%' => 0,
                    '%PASSWORD%' => $password,
                    '%ENABLE%' => true,
                ];
                $config = $this->xuiConfig($protocol, $transmission, $replaces);

                if ($config['success']) {
                    $config = $config['obj'];
                    $new = [
                        'up' => 0,
                        'down' => 0,
                        'total' => 0,
                        'remark' => $remark,
                        'enable' => true,
                        'expiryTime' => 0,
                        'listen' => '',
                        'port' => $port,
                        'protocol' => $protocol,
                        'settings' => json_encode($config['settings'] ?? []),
                        'streamSettings' => json_encode($config['streamSettings'] ?? []),
                        'sniffing' => json_encode($this->settings['SNIFFING'])
                    ];
                    $createInbound = $this->request("{$this->settings['ROOT']}/inbound/add", $new);

                    if ($createInbound['success']) {
                        $inboundData = $createInbound['obj'];

                        return [
                            'success' => true,
                            'msg' => 'Create Inbound Successfully',
                            'obj' => [
                                'inboundId' => $inboundData['id'],
                                'inboundPort' => $inboundData['port']
                            ]
                        ];
                    }

                    return $createInbound;
                }

                return $config;

            default:
                return [
                    'success' => false,
                    'msg' => 'The panel type is not 3xui',
                    'obj' => null
                ];
        }
    }

    /**
     * @param float $total
     * @param int $expiryDays
     * @param string|null $protocol
     * @param string|null $transmission
     * @param string|null $xuiRemark
     * @return array
     * @throws Exception
     */
    public function add(
        float  $total = 0,
        int    $expiryDays = 0,
        string $protocol = null,
        string $transmission = null,
        string $xuiRemark = null,
    ): array
    {
        $uuid = xuiTools::randUUID();
        $email = xuiTools::randStr(8);
        $password = xuiTools::randStr();
        $xuiPort = $this->randPort();
        $xuiRemark = $xuiRemark ?: xuiTools::randStr(5);
        $total *= (1024 * 1024 * 1024);
        $expiryDays = ($expiryDays * 60 * 60 * 24);
        $expiryDays = match ($this->settings['TYPE']) {
            1 => $expiryDays * -1000,
            default => (time() + $expiryDays) * 1000
        };
        $protocol = $protocol ?: $this->settings['DEFAULTS']['PROTOCOL'];
        $transmission =
            ($protocol == 'trojan') ? 'tcp' : ($transmission ?? $this->settings['DEFAULTS']['TRANSMISSION']);
        $replaces = [
            '%UUID%' => $uuid,
            '%PASSWORD%' => $password,
            '%EMAIL%' => $email,
            '%LIMIT_IP%' => 0,
            '%TOTAL%' => $total,
            '"%EXPIRY_TIME%"' => $expiryDays,
            '%ENABLE%' => true,
        ];
        $config = $this->xuiConfig($protocol, $transmission, $replaces);

        if ($config['success']) {
            $config = $config['obj'];

            switch ($this->settings['TYPE']) {
                case 1:
                    $inbound = $this->getInbound($protocol, $transmission);

                    if ($inbound['success']) {
                        $inbound = $inbound['obj'];
                        $newUser = [
                            'id' => $inbound['inboundId'],
                            'settings' => json_encode($config['settings'])
                        ];
                        $result = $this->request("{$this->settings['ROOT']}/inbound/addClient", $newUser);
                        $result['obj'] = match ($protocol) {
                            'trojan' => [
                                'password' => $password,
                                'email' => $email,
                            ],
                            default => [
                                'uuid' => $uuid,
                                'email' => $email,
                            ]
                        };

                        return $result;
                    }

                    return $inbound;

                default:
                    $newUser = [
                        'up' => 0,
                        'down' => 0,
                        'total' => $total,
                        'remark' => $xuiRemark,
                        'enable' => true,
                        'expiryTime' => $expiryDays,
                        'listen' => '',
                        'port' => $xuiPort,
                        'protocol' => $protocol,
                        'settings' => json_encode($config['settings']),
                        'streamSettings' => json_encode($config['streamSettings']),
                        'sniffing' => json_encode($this->settings['SNIFFING'])
                    ];
                    $result = $this->request("{$this->settings['ROOT']}/inbound/add", $newUser);
                    $result['obj'] = match ($protocol) {
                        'trojan' => [
                            'password' => $password,
                            'port' => $xuiPort,
                        ],
                        default => [
                            'uuid' => $uuid,
                            'port' => $xuiPort,
                        ]
                    };

                    return $result;
            }
        }

        return $config;
    }

    /**
     * @param array $update
     * @param array $where
     * @return array
     * @throws Exception
     */
    public function update(array $update, array $where = []): array
    {
        $usersList = $this->list($where);

        if ($usersList['success']) {
            $updateError = 0;
            $result = [
                'success' => true,
                'msg' => 'Update Successfully',
                'obj' => []
            ];

            foreach ($usersList['obj'] as $data) {
                $inboundId = $data['inbound']['id'] ?? null;
                $userId = $data['user']['id'] ?? null;
                $resetUsage = $update['resetUsage'] ?? false;
                $upload = $resetUsage ? 0 : ($data['user']['up'] ?? 0);
                $download = $resetUsage ? 0 : ($data['user']['down'] ?? 0);
                $protocol = $data['inbound']['protocol'] ?? null;
                $transmission = $data['inbound']['transmission'] ?? null;
                $uuid = $data['user']['uuid'] ?? false ?: xuiTools::randUUID();
                $password = $data['user']['password'] ?? false ?: xuiTools::randStr();
                $email = $data['user']['email'] ?? false ?: xuiTools::randStr(8);
                $expiryTime = ($update['expiryTime'] ?? $data['user']['expiryTime'] ?? 0) * 1000;
                $total = (isset($update['total']) ? $update['total'] * (1024 * 1024 * 1024) : $data['user']['total']) ?? 0;
                $limitIp = $update['limitIp'] ?? $data['user']['limitIp'] ?? 0;
                $enable = $update['enable'] ?? $data['user']['enable'] ?? false;
                $remark = $update['remark'] ?? $data['inbound']['remark'] ?? xuiTools::randStr(4);
                $port = $update['port'] ?? $data['inbound']['port'] ?? $this->randPort();

                if (!is_numeric($expiryTime))
                    return xuiTools::httpStatus(400, 'Bad Request - The expiryTime value must be of type int');
                elseif (!is_numeric($total))
                    return xuiTools::httpStatus(400, 'Bad Request - The total value must be of type int');
                elseif (!is_numeric($limitIp))
                    return xuiTools::httpStatus(400, 'Bad Request - The limitIp value must be of type int');
                elseif (!is_bool($enable))
                    return xuiTools::httpStatus(400, 'Bad Request - The enable value must be of type bool');
                elseif (!is_numeric($port))
                    return xuiTools::httpStatus(400, 'Bad Request - The port value must be of type int');

                $replaces = [
                    '%UUID%' => $uuid,
                    '%EMAIL%' => $email,
                    '%LIMIT_IP%' => $limitIp,
                    '%TOTAL%' => $total,
                    '"%EXPIRY_TIME%"' => $expiryTime,
                    '%ENABLE%' => $enable,
                ];
                $config = $this->xuiConfig($protocol, $transmission, $replaces);

                if ($config['success']) {
                    $config = $config['obj'];

                    switch ($this->settings['TYPE']) {
                        case 1:
                            if ($resetUsage) {
                                $this->request("{$this->settings['ROOT']}/inbound/$inboundId/resetClientTraffic/$email", []);
                            }
                        
                            $updateParam = [
                                'id' => $inboundId,
                                'settings' => json_encode($config['settings'])
                            ];
                            $updateResult =
                                $this->request("{$this->settings['ROOT']}/inbound/updateClient/$uuid", $updateParam);
                            $updateResult['obj'] = match ($protocol) {
                                'trojan' => [
                                    'password' => $password,
                                    'email' => $email,
                                ],
                                default => [
                                    'uuid' => $uuid,
                                    'email' => $email,
                                ]
                            };
                            break;

                        default:
                            $updateParam = [
                                'up' => $upload,
                                'down' => $download,
                                'total' => $total,
                                'remark' => $remark,
                                'enable' => $enable,
                                'expiryTime' => $expiryTime,
                                'listen' => '',
                                'port' => $port,
                                'protocol' => $protocol,
                                'settings' => json_encode($config['settings']),
                                'streamSettings' => json_encode($config['streamSettings']),
                                'sniffing' => json_encode($this->settings['SNIFFING'])
                            ];
                            $updateResult =
                                $this->request("{$this->settings['ROOT']}/inbound/update/$userId", $updateParam);
                            $updateResult['obj'] = match ($protocol) {
                                'trojan' => [
                                    'password' => $password,
                                    'port' => $port,
                                ],
                                default => [
                                    'uuid' => $uuid,
                                    'port' => $port,
                                ]
                            };
                            break;
                    }

                    if (!$updateResult['success']) {
                        $result['success'] = false;
                        $result['msg'] = ++$updateError . " Error during update";
                    }

                    $result['obj'][] = $updateResult;
                } else {
                    return $config;
                }
            }

            return xuiTools::httpStatus($result['success'] ? 200 : 400, $result['msg'], $result['obj']);
        }

        return $usersList;
    }

    /**
     * @param array $where
     * @param string|null $customRemark
     * @return array
     */
    public function createUrl(array $where, string $customRemark = null): array
    {
        $address = parse_url($this->serverAddress)['host'] ?? $this->serverAddress;
        $user = $this->list($where);

        if ($user['success']) {
            $user = $user['obj'][0];
            $email = $user['user']['email'] ?? '';
            $protocol = $user['inbound']['protocol'] ?? '';
            $port = $user['inbound']['port'] ?? '';
            $remark = $customRemark ?? $user['inbound']['remark'] ?? '';
            $transmission = $user['inbound']['transmission'] ?? '';
            $uuid = $user['user']['uuid'] ?? '';
            $password = $user['user']['password'] ?? '';
            $replaces = [
                '%USER%' => $uuid,
                '%PASS%' => $password,
                '%REMARK%' => $remark,
                '%EMAIL%' => $email,
                '%ADDRESS%' => $address,
                '%PORT%' => $port,
                '%TRANSMISSION%' => $transmission,
            ];
            $config = $this->xuiConfig($protocol, $transmission, $replaces);

            if ($config['success']) {
                $config = $config['obj'];

                switch ($protocol) {
                    case 'vmess':
                        $vmess = $config['url'];
                        $vmess->host = base64_encode(json_encode($vmess->host));

                        return [
                            'success' => true,
                            'msg' => '',
                            'obj' => [
                                'url' => xuiTools::buildUrl((array)$vmess)
                            ]
                        ];

                    case 'vless':
                    case 'trojan':
                        return [
                            'success' => true,
                            'msg' => '',
                            'obj' => [
                                'url' => xuiTools::buildUrl((array)$config['url'])
                            ]
                        ];

                    default:
                        return [
                            'success' => false,
                            'msg' => 'Error, url could not be created',
                            'obj' => null
                        ];
                }
            }

            return $config;
        }

        return $user;
    }

    /**
     * @param array $where
     * @return array
     */
    public function fetch(array $where): array
    {
        $createUrl = $this->createURL($where);

        if ($createUrl['success']) {
            $url = $createUrl['obj']['url'];
            $qrcode = xuiTools::genQRCode($url);

            if ($qrcode['success']) {
                $qrcode = $qrcode['obj'];
                $user = $this->list($where);

                if ($user['success']) {
                    $user = $user['obj'][0];
                    $user['user']['url'] = $url;
                    $user['user']['qrcode'] = $qrcode;

                    return [
                        'success' => true,
                        'msg' => 'User found successfully',
                        'obj' => $user
                    ];
                }

                return $user;
            }

            return $qrcode;
        }

        return $createUrl;
    }

    /**
     * @param array $where
     * @param int|null $toDate
     * @return array
     */
    public function delete(array $where, int $toDate = null): array
    {
        $usersList = $this->list($where);

        if ($usersList['success']) {
            $deleteError = 0;
            $result = [
                'success' => true,
                'msg' => 'Delete Successfully',
                'obj' => []
            ];

            foreach ($usersList['obj'] as $user) {
                $checkToDate = is_null($toDate);
                $checkLimited = $user['user']['expiryTime'];
                $checkDate = $user['user']['expiryTime'] <= $toDate;

                if ($checkToDate || ($checkLimited && $checkDate)) {
                    $protocol = $user['inbound']['protocol'] ?? '';
                    $port = $user['inbound']['port'] ?? '';
                    $uuid = $user['user']['uuid'] ?? '';
                    $password = $user['user']['password'] ?? '';
                    $email = $user['user']['email'] ?? '';

                    switch ($this->settings['TYPE']) {
                        case 1:
                            $userKey = match ($protocol) {
                                'trojan' => $user['user']['password'],
                                default => $user['user']['uuid']
                            };
                            $inboundId = $user['inbound']['id'];
                            $deleteResult =
                                $this->request("{$this->settings['ROOT']}/inbound/$inboundId/delClient/$userKey");
                            $deleteResult['obj'] = match ($protocol) {
                                'trojan' => [
                                    'password' => $password,
                                    'email' => $email,
                                ],
                                default => [
                                    'uuid' => $uuid,
                                    'email' => $email,
                                ]
                            };
                            break;

                        default:
                            $userKey = $user['user']['id'];
                            $deleteResult = $this->request("{$this->settings['ROOT']}/inbound/del/$userKey");
                            $deleteResult['obj'] = match ($protocol) {
                                'trojan' => [
                                    'password' => $password,
                                    'port' => $port,
                                ],
                                default => [
                                    'uuid' => $uuid,
                                    'port' => $port,
                                ]
                            };
                    }

                    if (!$deleteResult['success']) {
                        $result['success'] = false;
                        $result['msg'] = ++$deleteError . " Error during update";
                    }

                    $result['obj'][] = $deleteResult;
                }
            }

            return $result;
        }

        return $usersList;
    }

    /**
     * @return array
     */
    private function status(): array
    {
        $status = $this->request('server/status');

        if ($status['success']) {
            $status = $status['obj'];

            return xuiTools::httpStatus(200, 'Server status', $status);
        }

        return $status;
    }
}

class xuiTools
{
    /**
     * @param string $text
     * @param string $htmlClassName
     * @return array
     */
    public static function genQRCode(string $text, string $htmlClassName = ''): array
    {
        $text = urlencode($text);
        $parseUrl = [
            'scheme' => 'https',
            'host' => 'quickchart.io',
            'path' => '/qr',
            'query' => "text=$text&margin=3&size=1080&format=svg&dark=523489&ecLevel=L",
        ];
        $url = self::buildUrl($parseUrl);

        return self::httpStatus(200, 'Create QR Code Successfully', [
            'url' => $url,
            'html' => "<img src='$url' alt='$text' class='$htmlClassName' title='QR CODE'>",
            'svg' => file_get_contents($url) ?? 'Invalid'
        ]);
    }

    /**
     * @param array $data
     * @return string
     */
    public static function buildUrl(array $data = [
        'scheme' => 'vless',
        'user' => 'user',
        'host' => 'example.org',
        'port' => 1111,
        'query' => 'query',
        'fragment' => 'remark'
    ]): string
    {
        $build = (isset($data['scheme']) ? "{$data['scheme']}://" : '');
        $build .= (isset($data['user']) ? "{$data['user']}@" : '');
        $build .= (isset($data['host']) ? "{$data['host']}" : '');
        $build .= (isset($data['port']) ? ":{$data['port']}" : '');
        $build .= (isset($data['path']) ? "{$data['path']}" : '');
        $build .= (isset($data['query']) ? "?{$data['query']}" : '');
        $build .= (isset($data['fragment']) ? "#{$data['fragment']}" : '');

        return $build;
    }

    /**
     * @param string $url
     * @return array
     */
    public static function readUrl(string $url = 'vless://user@example.org:1111?query#remark'): array
    {
        $url = parse_url($url) ?? [];
        $protocol = $url['scheme'] ?? '';
        $url = match ($protocol) {
            'vmess' => json_decode(base64_decode($url['host']), true),
            default => $url
        };
        $host = $url['add'] ?? $url['host'] ?? false;
        $port = $url['port'] ?? false;
        $user = $url['id'] ?? $url['user'] ?? false;

        return ($host && $port && $user) ? [
            'host' => $host,
            'port' => $port,
            'user' => $user,
        ] : [];
    }

    /**
     * @param int $size
     * @param int $format
     * @param int $precision
     * @param bool $arrayReturn
     * @return array|string
     */
    public static function formatBytes(
        int  $size,
        int  $format = 0,
        int  $precision = 0,
        bool $arrayReturn = false
    ): array|string
    {
        $base = log($size, 1024);
        $units = match ($format) {
            1 => ['بایت', 'کلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'], # Persian
            2 => ['B', 'K', 'M', 'G', 'T'],
            default => ['B', 'KB', 'MB', 'GB', 'TB']
        };

        if (!$size) return $arrayReturn ? [0, $units[1]] : "0 {$units[1]}";

        $result = pow(1024, $base - floor($base));
        $result = round($result, $precision);
        $unit = $units[floor($base)];

        return $arrayReturn ? [$result, $unit] : "$result $unit";
    }

    /**
     * @param int $seconds
     * @param int $format
     * @param bool $arrayReturn
     * @return array|string
     */
    public static function formatTime(
        int  $seconds,
        int  $format = 0,
        bool $arrayReturn = false
    ): array|string
    {
        $units = match ($format) {
            1 => ['سال', 'ماه', 'روز', 'ساعت', 'دقیقه', 'ثانیه'], # Persian
            default => ['Year(s)', 'Month(s)', 'Day(s)', 'Hour(s)', 'Minute(s)', 'Second(s)']
        };
        $time = 0;
        $unit = $units[count($units)-1];
        $secFormat = [31207680, 26006400, 86400, 3600, 60, 1];

        for ($__i__ = 0; $__i__ < count($secFormat); $__i__ ++) {
            if ($seconds > $secFormat[$__i__]) {
                $time = round($seconds / $secFormat[$__i__]);
                $unit = $units[$__i__];
                break;
            }
        }

        return $arrayReturn ? [$time, $unit] : "$time $unit";
    }

    /**
     * @param string $address
     * @param bool $isDomain
     * @return array
     */
    public static function getIPAddressLocation(string $address, bool $isDomain = false): array
    {
        $address = $isDomain ? (gethostbyname($address) ?: 'Invalid') : $address;
        $url = "http://ip-api.com/json/$address";
        $countries = [
            'Afghanistan' => [
                '🇦🇫',
                'افغانستان',
            ],
            'Albania' => [
                '🇦🇱',
                'آلبانی',
            ],
            'Algeria' => [
                '🇩🇿',
                'الجزایر',
            ],
            'Argentina' => [
                '🇦🇷',
                'آرژانتین',
            ],
            'Australia' => [
                '🇦🇺',
                'استرالیا',
            ],
            'Austria' => [
                '🇦🇹',
                'اتریش',
            ],
            'Bangladesh' => [
                '🇧🇩',
                'بنگلادش',
            ],
            'Belgium' => [
                '🇧🇪',
                'بلژیک',
            ],
            'Brazil' => [
                '🇧🇷',
                'برزیل',
            ],
            'Canada' => [
                '🇨🇦',
                'کانادا',
            ],
            'China' => [
                '🇨🇳',
                'چین',
            ],
            'Egypt' => [
                '🇪🇬',
                'مصر',
            ],
            'France' => [
                '🇫🇷',
                'فرانسه',
            ],
            'Germany' => [
                '🇩🇪',
                'آلمان',
            ],
            'India' => [
                '🇮🇳',
                'هند',
            ],
            'Iran' => [
                '🇮🇷',
                'ایران',
            ],
            'Italy' => [
                '🇮🇹',
                'ایتالیا',
            ],
            'Japan' => [
                '🇯🇵',
                'ژاپن',
            ],
            'South Korea' => [
                '🇰🇷',
                'کره جنوبی',
            ],
            'Malaysia' => [
                '🇲🇾',
                'مالزی',
            ],
            'Mexico' => [
                '🇲🇽',
                'مکزیک',
            ],
            'Netherlands' => [
                '🇳🇱',
                'هلند',
            ],
            'Russia' => [
                '🇷🇺',
                'روسیه',
            ],
            'Saudi Arabia' => [
                '🇸🇦',
                'عربستان سعودی',
            ],
            'South Africa' => [
                '🇿🇦',
                'آفریقای جنوبی',
            ],
            'Spain' => [
                '🇪🇸',
                'اسپانیا',
            ],
            'Sweden' => [
                '🇸🇪',
                'سوئد',
            ],
            'Switzerland' => [
                '🇨🇭',
                'سوئیس',
            ],
            'Turkey' => [
                '🇹🇷',
                'ترکیه',
            ],
            'United Kingdom' => [
                '🇬🇧',
                'انگلستان',
            ],
            'United States' => [
                '🇺🇸',
                'ایالات متحده',
            ],
        ];

        if ($response = file_get_contents($url) ?? false) {
            if ($data = json_decode($response, true) ?? false) {
                if ($data['status'] == 'success') {
                    $country = $countries[$data['country'] ?? ''] ?? '-';
                    unset($data['status']);
                    return self::httpStatus(
                        200,
                        'IP Location successfully',
                        array_merge($data,[
                            'flag' => $country[0],
                            'persianName' => $country[1],
                        ])
                    );
                }
            }
        }

        return self::httpStatus(404, 'IP Location Not Found');
    }

    /**
     * @param int $code
     * @param string|null $message
     * @param array|object|null $object
     * @return array|object
     */
    public static function httpStatus(int $code, string $message = null, array|object $object = null): array|object
    {
        $httpCodes = [
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
        ];
        $result = [
            'success' => $code && $code < 300,
            'msg' => $message ?? $httpCodes[$code] ?? 'Unknown Error',
            'obj' => $object
        ];

        if ($code >= 300) $result['err'] = $code;

        return $result;
    }

    /**
     * @param string $errorNote
     * @return bool
     */
    public static function newLog(string $errorNote): bool
    {
        $logDir = __DIR__ . '/.xuiLog';

        if (!is_dir($logDir)) mkdir($logDir);

        $logFileTime = time();
        $logFileName = "$logDir/UUID-ERROR-$logFileTime.TXT";

        return file_put_contents($logFileName, $errorNote);
    }

    /**
     * @return string
     */
    public static function randUUID(): string
    {
        try {
            $data = random_bytes(16);
            assert(strlen($data) == 16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            $result = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        } catch (Exception $exception) {
            self::newLog($exception);
        }

        return $result ?: '';
    }

    /**
     * @param int $length
     * @return string
     */
    public static function randStr(int $length = 10): string
    {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charsLen = strlen($chars) - 1;
        $randStr = '';

        for ($step = 0; $step <= $length; $step++) {
            $randStr .= $chars[rand(0, $charsLen)];
        }

        return $randStr;
    }

    /**
     * @param string $url
     * @return string
     */
    public static function formatServerUrl(string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $addSlashUrl = str_ends_with($url, '/') ? $url : "$url/";

            if (str_starts_with($addSlashUrl, 'api://')) {
                $sslUrl = str_replace('api://', 'ssl://', $addSlashUrl);
                $httpsUrl = str_replace('ssl://', 'https://', $sslUrl);
                $httpUrl = str_replace('https://', 'http://', $httpsUrl);
                $conText = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
                $stream = stream_socket_client($sslUrl, $errNo, $errMg, 2, STREAM_CLIENT_CONNECT, $conText);

                if (!$stream) {
                    return $httpUrl; // SSL connection failed
                }

                $params = stream_context_get_params($stream);
                $cert = $params['options']['ssl']['peer_certificate'];

                if (!$cert) {
                    return $httpUrl; // No SSL certificate found
                }

                return $httpsUrl; // SSL certificate found
            }

            return $addSlashUrl;
        }

        return '';
    }

    /**
     * @return int
     */
    public static function randPort(): int
    {
        return rand(1000, 65000);
    }
}
