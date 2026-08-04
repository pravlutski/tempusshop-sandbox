<?php
if ( empty($_POST['cabinet']) ){
  die('Не получен кабинет из формы');
}

$settings = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsIll_'. $_POST['cabinet'] . '.json');

echo $settings;

 ?>
