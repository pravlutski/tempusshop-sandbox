<?php

require('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/trigger_error.php');

$p = new ClassName('аргутмент');

$p->check( 4 );

print_r( "рабтоатет, я выполняюсь дальше\n" );

$p->check( 5 );

print_r( "мне пох, я выполняюсь дальше\n" );

 ?>
