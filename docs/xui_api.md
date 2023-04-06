<div align="center">
    <h1>API usage guide</h1>
</div><br><br>

<div align="left">
    <ul>
        <li>In the first step, download the project file and open it with your desired editor</li><br>
        <li>Use the following code sample to connect to the server</li><br>
        <pre><code>$address = "example.com"; # Domain or Port
$port = 34029; # Panel port
$username = "mobinjavari"; # Username
$password = "Jip98@SDjs8"; # Password
$example = new xui_api($address,$port,$username,$password); # Login to panel</code></pre><br><br>
        <li>Use the following code sample to create a user</li><br>
        <pre><code>$create_user = (int)$example->add(); # Create new user 
echo($create_user); # Display the output value (0 or 1)</code></pre>
        <p><sub>The first parameter receives the protocol value vless/vmess</sub></p>
        <p><sub>The second parameter receives the volume value in gigabytes (0 = unlimited)</sub></p>
        <p><sub>The third parameter takes the transmission value ws/tcp</sub></p>
        <p><sub>The fourth parameter takes the value of the remark</sub></p>
        <p><sub>The fifth parameter receives the value of the port as a number (if the port is 0, it will automatically create a port)</sub></p>
        <p><sub>The sixth parameter takes the value of the expiration date in unix format</sub></p>
        <pre><code>$date = strtotime(time()."+ 31 Days"); # Today's date + 31 days
$create_custom_user = (int)$example->add("vmess",10,"ws","example",0,$date); # Add custom user 
echo($create_custom_user); # Display the output value (0 or 1)</code></pre><br><br>
        <li>Use the following code sample to change user information </li><br>
        <pre><code>$update = (int)$example->update($port,$changes); # Update user
echo($update); # Display the output value (0 or 1)</code></pre>
        <p><sub>Supported values: total, enable, reset, remark, expiryTime, protocol, transmission, port</sub></p>
        <pre><code>$port = 82391;
$changes = [
    "reset" => true, # Reset traffic
    "total" => 10, # Total GB
    "enable" => true, # true or false
    "remark" => "Update", # Change remark
    "expiryTime" => $date, # Unix date format
    "port" => rand(1000,65530), # Change port
    "protocol" => "vmess", # Change protocol (vmess or vless)
    "transmission" => "tcp", # Change transmission (tcp or ws)
];</code></pre><br><br>
        <li>User deletion code example</li><br>
        <pre><code>$user = $example->list(["port" => 43434]); # Get user
$user_delete = (int)$example->del($user["id"]); # Delete user
echo($user_delete); # Display the output value (0 or 1)</code></pre>
        <p><sub>It takes the value of the user ID and deletes it</sub></p><br><br>
        <li>Sample code for receiving user information</li><br>
        <pre><code>$users_data = $example->list(); # Get all users
print_r($users_data); # Display the output value (array)</code></pre>
        <p><sub>It receives the information of all users</sub></p>
        <p><sub>You can filter the received amounts, example :</sub></p>
        <pre><code>$filters = [
    "port" => 20394,
    # "uid" => "xxxxxx",
    # "protocol" => "vless"
]; 
$filtered_user_data = $example->list($filters); # A specific user
print_r($filtered_user_data); # Display the output value (array)</code></pre>
        <p><sub>The values that the filter supports port / protocol / uid</sub></p><br><br>
        <li>Creating a url for the user</li><br>      
        <pre><code>$create_url = (string)$example->url(#port); # A specific user
echo($create_url); # Display the output value (string)</code></pre>
        <li>Get server status and specifications</li><br>
        <pre><code>$server_status = $example->status(); # Get server status
print_r($server_status); # Display the output value (array)</code></pre>
        <p><sub>Returns an array of data</sub></p>
    </ul>
</div>