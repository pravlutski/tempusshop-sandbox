<?php

$path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/common/IP/";
$filename = date('Y_m_d') . ".log";

if ( !file_exists($path . $filename) ) die("<span>Нет данных</span>");

$contents = file_get_contents( $path . $filename );

 ?>
 
 <div class="response">
   <pre>
     <?=$contents?>
   </pre>
 </div>
