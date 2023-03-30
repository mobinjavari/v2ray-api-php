<div align="center">
    <h1>API usage guide</h1>
</div><br><br>

<div align="left">
    <ul>
        <li>In the first step, download the project file and open it with your desired editor</li><br>
        <li>Use the following code sample to connect to the server</li><br>
        <pre><code>$example = new xui_api("IP","Port","Username","Password"); # Login</code></pre><br><br>
        <li>Use the following code sample to create a user</li><br>
        <pre><code>$example->new("Email"); # Add client </code></pre>
        <p><sub>The first parameter receives the value of the client's email</sub></p>
        <p><sub>The second parameter receives the volume value in gigabytes (0 = unlimited)</sub></p>
        <p><sub>The third parameter takes the value of the expiration date in Unix format</sub></p>
        <pre><code>$example->new("mobinjavari",10,0); # Add custom client </code></pre><br><br>
        <li>Use the following code sample to update the user (<small>Andis value and andis client are required</small>)</li>
        <p>You can get the value of andis and andis client from list method</p><br>
        <pre><code>$example->update(0,1,$changes); # Update user</code></pre>
        <p><sub>In the $changes array, all the following values can be edited, if you don't need them, you can remove them from the presentation</sub></p>
        <pre><code>$changes = [
    "up" => ($up_gb * 1024 * 1024 * 1024),
    "down" => ($down_gb * 1024 * 1024 * 1024),
    "total" => ($total_gb * 1024 * 1024 * 1024),
    "remark" => "Update",
    "enable" => false,
    "expiryTime" => ($ex_timestamp * 1000),
    "settings" => [
        "clients" => [
            "limitIp" => 10,
            "totalGB" => ($total_gb * 1024 * 1024 * 1024),
            "expiryTime" => ($ex_timestamp * 1000)
        ]
    ]
];</code></pre><br><br>
        <li>User deletion code example</li><br>
        <pre><code>$example->del(1); # Delete user</code></pre>
        <p><sub>It takes the value of the user ID and deletes it</sub></p><br><br>
        <li>Sample code for receiving user information</li><br>
        <pre><code>$example->list(); # All users</code></pre>
        <p><sub>It receives the information of all users</sub></p>
        <p><sub>You can filter the received amounts - example :</sub></p>
        <pre><code>$filters = ["email" => "mobinjavari"];
$example->list($filters); # A specific user</code></pre>
        <p><sub>The values that the filter supports email / id</sub></p><br><br>
        <li>Get server status and specifications</li><br>
        <pre><code>$example->status(); # Get server</code></pre>
        <p><sub>Returns an array of data</sub></p>
    </ul>
</div>
