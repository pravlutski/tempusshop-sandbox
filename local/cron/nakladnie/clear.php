<?
if (file_exists('/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/')) {
    foreach (glob('/var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/*') as $file) {
        unlink($file);
    }
}
