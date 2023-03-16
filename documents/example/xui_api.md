<div align="center">
    <h1>API usage guide</h1>
</div><br><br>

<div align="left">
    <ul>
        <li>In the first step, download the project file and open it with your desired editor</li><br>
        <li>Use the following code sample to connect to the server</li><br>
        <pre><code>$example = new xui_api("IP","Port","Username","Password"); # Login</code></pre><br><br>
        <li>Use the following code sample to create a user</li><br>
        <pre><code>$example->add(); # Add user </code></pre>
        <p><sub>The first parameter receives the protocol value vless/vmess</sub></p>
        <p><sub>The second parameter receives the volume value in gigabytes (0 = unlimited)</sub></p>
        <p><sub>The third parameter takes the network value ws/tcp</sub></p>
        <p><sub>The fourth parameter takes the value of the remark</sub></p>
        <p><sub>The fifth parameter receives the value of the port as a number (if the port is 0, it will automatically create a port)</sub></p>
        <p><sub>The sixth parameter takes the value of the expiration date in unix format</sub></p>
        <pre><code>$example->add("vmess",10,"ws","example",0,0); # Add custom user </code></pre><br><br>
        <li>Use the following code sample to update the user (<small>Port value is required</small>)</li><br>
        <pre><code>$example->update(["port" => 3443]); # Update user</code></pre>
        <p><sub>Supported values: enable, reset, remark, expiryTime, protocol, newPort</sub></p>
        <pre><code>$example->update(["port" => 3443,"enable" => false,"reset" => true,"remark" => "Update","protocol" => "vless"]); # New Data</code></pre><br><br>
        <li>User deletion code example</li><br>
        <pre><code>$example->del(1); # Delete user</code></pre>
        <p><sub>It takes the value of the user ID and deletes it</sub></p><br><br>
        <li>Sample code for receiving user information</li><br>
        <pre><code>$example->list(); # All users</code></pre>
        <p><sub>It receives the information of all users</sub></p>
        <p><sub>You can filter the received amounts - example :</sub></p>
        <pre><code>$filters = ["port" => 20394]; 
$example->list($filters); # A specific user</code></pre>
        <p><sub>The values that the filter supports port / protocol / uuid</sub></p><br><br>
        <li>Get server status and specifications</li><br>
        <pre><code>$example->status(); # Get server</code></pre>
        <p><sub>Returns an array of data</sub></p>
    </ul>
</div>