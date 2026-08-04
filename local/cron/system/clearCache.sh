#!/bin/sh

find /var/www/bitrix/data/www/tempusshop.ru/bitrix/cache/  -type f -mtime 5 -exec rm -f {} \;
find /var/www/bitrix/data/www/tempusshop.ru/upload/pricelist_tmp/  -type f -mtime 10 -exec rm -f {} \;
find /var/www/bitrix/data/www/tempusshop.ru/upload/tmp_ceneo/  -type f -mtime 2 -exec rm -f {} \;
find /var/www/bitrix_logs/details_log/ -type f -mtime +90 -delete;