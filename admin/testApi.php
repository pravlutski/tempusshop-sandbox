<?php
$ch = curl_init('https://api.pixian.ai/api/v2/remove-background');

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER,
    array('Authorization: Basic cHh6YjY5aDI3M3NlOTluOjlvdGo2bmlqNmljM3RoNjl2cDBycXVjYjFvOTdpbml0YjdpYzk1dGFzMG1taHI5bzRmMjE='));
curl_setopt($ch, CURLOPT_POSTFIELDS,
    array(
      'image.url' => 'https://tempusshop.ru/upload/iblock/047/i0czxz40dpa394jewuf139kidkmsug2f.png',
    ));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$data = curl_exec($ch);
if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
  file_put_contents("pixian_result.png", $data);
} else {
  echo "Error: " . $data;
}
curl_close($ch);
