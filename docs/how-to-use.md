# xui Connect   
In the first step, we clone the project file on our local system through git

Run the following code in Terminal or Console ( Note that git must be installed on your system )

```php
git clone https://github.com/mobinjavari/v2ray-api-php.git
```

## Instance 
Create an instance of the xuiConnect class

<table>
    <tr align="center">
        <th>☑️ Variable</th>
        <th>🗂️ Data Type</th>
        <th>✔️ Sample correct input</th>
        <th>❌ Sample wrong input</th>
        <th>🧾 Description</th>
    </tr>
    <tr align="center">
        <td align="left">$serverAddress</td>
        <td>string</td>
        <td>
            <code>api://example.org:54321/</code><br>
            <code>api://10.10.10.10:54321/</code>
        </td>
        <td>
            <code>https://example.org:54321/</code><br>
            <code>http://10.10.10.10:54321/</code>
        </td>
        <td>Server address and port on which the panel is installed</td>
    </tr>
    <tr align="center">
        <td align="left">$tunnelServerAddress</td>
        <td>string</td>
        <td>
            <code>api://example.org:54321/</code><br>
            <code>api://10.10.10.10:54321/</code>
        </td>
        <td>
            <code>https://example.org:54321/</code><br>
            <code>http://10.10.10.10:54321/</code>
        </td>
        <td>Tunnel server address and panel port (if there is no tunnel, leave its value <code>null</code>)</td>
    </tr>
    <tr align="center">
        <td align="left">$username</td>
        <td>string</td>
        <td><code>admin</code></td>
        <td> - </td>
        <td>Username to login to the panel</td>
    </tr>
    <tr align="center">
        <td align="left">$password</td>
        <td>string</td>
        <td><code>admin</code></td>
        <td> - </td>
        <td>Password to enter the panel</td>
    </tr>
    <tr align="center">
        <td align="left">$panel</td>
        <td>int</td>
        <td><code>1</code></td>
        <td> - </td>
        <td>Specifying the panel type, enter the value <code>1</code> for the Sinai panel (3xui) and the value <code>0</code> for the Chinese or English panel (xui).</td>
    </tr>
</table>

## Properties
Guide to using properties
<table>
    <tr align="center">
        <th>📘 Name</th>
        <th>📤 Return</th>
        <th>🧾 Description</th>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?id=status">status</a></td>
        <td>array</td>
        <td>It returns the status of the server, including upload and download information, etc. in the form of an array</td>
    </tr>
</table>


## Functions
Guide to using functions

<table>
    <tr align="center">
        <th>📘 Name</th>
        <th width="25%">📩 Inputs</th>
        <th>📤 Return</th>
        <th>🧾 Description</th>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?id=set-default-protocol">setDefaultProtocol</a></td>
        <td><code>$protocol</code> (string)</td>
        <td>void</td>
        <td>Changing the default protocol value, values <code>vless</code> , <code>vmess</code> , <code>trojan</code> can be used.</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?set-default-transmission">setDefaultTransmission</a></td>
        <td><code>$transmission</code> (string)</td>
        <td>void</td>
        <td>Changing the default transmission value, <code>ws</code>, <code>tcp</code> values can be used.</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?set-default-header">setDefaultHeader</a></td>
        <td><code>$header</code> (string)</td>
        <td>void</td>
        <td>To change the default header, used in the config link</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?id=set-sniffing">setSniffing</a></td>
        <td>
            <code>$enable</code> (bool) <br>
            <code>$destOverride</code> (array)
        </td>
        <td>void</td>
        <td>Setting the sniffing value receives a boolean value in the first input and an array containing <code>http</code>, <code>tls</code>, <code>quic</code>... in the second input.</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?id=create-url">createUrl</a></td>
        <td>
            <code>$where</code> (array)
            <br>
            <code>$customRemark</code> (string)
        </td>
        <td>array</td>
        <td>It is used to create the url (config link) of the user, in the first input, the provided value is used to bet the user's key information (<code>uuid</code>, <code>email</code>, <code>port</code>...) and in the second input, it takes the personalized remark value, which is by default has the <code>null</code> value</td>
    </tr>
    <tr align="center"><td></td><td></td><td></td><td></td></tr>
    <tr align="center"><td></td><td></td><td></td><td></td></tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?id=add">add</a></td>
        <td>
            <code>$total</code> (float)
            <br>
            <code>$expiryDays</code> (int)
            <br>
            <code>$protocol</code> (string|null)
            <br>
            <code>$transmission</code> (string|null)
            <br>
            <code>$xuiRemark</code> (string|null)
        </td>
        <td>array</td>
        <td>It is used to add a user to the panel, the <code>$total</code> input gets the volume in gigabytes, the input <code>$expiryDays</code> receives the expiration date as a number such as 30 days, the default value is 0, which means infinity., <code>$xuiRemark</code> input is <code>0</code> (Chinese,..) for panels.</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="/how-to-use?id=update">update</a></td>
        <td>
            <code>$update</code> (array)
            <br>
            <code>$where</code> (array)
        </td>
        <td>array</td>
        <td>Used to update user information, <code>$update</code> entry contains an array of changes, <code>$where</code> entry contains an array that specifies It allows the update to be applied to which user or users</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="#">fetch</a></td>
        <td><code>$where</code> (array)</td>
        <td>array</td>
        <td>Used to get user information, the <code>$fetch</code> entry takes an array of unique user information and returns the first user found.</td>
    </tr>
    <tr align="center">
        <td align="left"><a href="#">delete</a></td>
        <td>
            <code>$where</code> (array)
            <br>
            <code>$toDate</code> (int)
        </td>
        <td>array</td>
        <td>It is used to delete the user from the panel, the first input is the condition of the users who are deleted and the second input is the users whose expiration date is <code>$toDate</code>, which is null by default and all users which includes <code>$where</code>, deletes it</td>
    </tr>
</table>

## Exampels
Examples for using the class

### <small>Instance</small>
```php
$serverAddress = 'api://example.org:54321/'; # Server Address:Port
$tunnelServerAddress = null; # Tunnel Server Address:Port
$username = 'admin';
$password = 'admin';
$panel = 1; # Panel Type x-ui (0) / 3x-ui (1)

$xui = new xuiConnect($serverAddress, $tunnelServerAddress, $username, $password, $panel);
```

### <small>Status</small>
```php
print_r($xui->status);
```

### <small>Set Default Protocol</small>
```php
$xui->setDefaultProtocol('vmess'); # vmess / vless / trojan
```

### <small>Set Default Transmission</small>
```php
$xui->setDefaultTransmission('ws'); # tcp / ws
```

### <small>Set Default Transmission</small>
```php
$xui->getIPAddressLocation('ws'); # tcp / ws
```

### <small>Set Default Header</small>
```php
$xui->setDefaultHeader('wikipedia.org');
```

### <small>Set Sniffing</small>
```php
$xui->setSniffing(true, ['http','tls','quic']);
```

### <small>Create Url</small>
```php
$where = [
    'uuid' => 'userUUID',
    // 'email' => 'userEmail',
    // 'port' => 'userOrInboundPort',
    // 'enable' => true, # true/false
    // 'protocol' => 'vless', # vless / vmess / trojan
    // 'transmission' => 'tcp', # tcp / ws
    // 'remark' => 'userRemark',
    // 'password' => 'userPassword', # Just for trojan
];
print_r($xui->createUrl($where, 'MyRemarkName')); 

```

### <small>Add</small>
```php
$total = 10; # GB / Unlimited (0)
$expiryDays = 30; # Days / Unlimited (0)
$protocol = 'vmess'; # vmess / vless / trojan
$transmission = 'tcp'; # tcp / ws
$xuiRemark = 'userRemark'; # Just for x-ui (0)

print_r($xui->add($total, $expiryDays, $protocol, $transmission, $xuiRemark));
```

### <small>Update</small>
```php
$update = [ # Unlimited (0)
    'remark' => 'MobinJavari', # Just for x-ui (0)
    'expiryTime' => '0', # time() + (60 * 60 * 24) * (10 #days)
    'resetUsage' => true, # true/false,
    'total' => 10, # GB
    'limitIp' => 0, # Just for 3x-ui (1)
    'enable' => true, # true/false
];
$where = [
    'uuid' => 'userUUID',
    // 'email' => 'userEmail',
    // 'port' => 'userOrInboundPort',
    // 'enable' => true, # true/false
    // 'protocol' => 'vless', # vless / vmess / trojan
    // 'transmission' => 'tcp', # tcp / ws
    // 'remark' => 'userRemark',
    // 'password' => 'userPassword', # Just for trojan
];
print_r($xui->update($update, $where));
```



