<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$perHour = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon2/stat/perHour.json');
$perHour = json_decode($perHour, 1);
unset($perHour['date']);
echo json_encode($perHour);

 ?>
