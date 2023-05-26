<?php

include '../class/xui_api.php';

// $connect = new xui_api(
//     '172.104.128.168',
//     54321,
//     'admin',
//     'admin',
//     'vless',
//     'tcp'
// );

$connect = new xui_api(
    '139.162.185.41',
    23118,
    'admin',
    '8024019',
    'vless',
    'tcp',
    true
);

header('Content-Type: application/json');
print_r($connect->status['obj']['xray']['state'] ?? '');
echo "\n\n\n";

//print_r($connect->status);
//print_r($connect->config());
// print_r($connect->list(['protocol' => 'vless','transmission' => 'tcp']));
//print_r($connect->add('vless', 10, 'tcp', '982934829'));
// print_r($connect->create_url(22809));
// print_r($connect->read_url($connect->create_url(22809)['obj']['url']));

$update = [
    'enable' => true,
//    'total' => 1000,
//    'remark' => '1212298984',
    'protocol' => 'vless',
];
//print_r($connect->update(22271,$update));
//print_r($connect->fetch('94dfe06b-97f5-4a33-80b1-7ac3d142b49e'));

//print_r($connect->delete(27384));
