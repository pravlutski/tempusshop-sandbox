<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
	<code>#0 3 * * * /userscripts/dump.sh</code>
	<p>Отключен. создание бэкапов кастомное</p>
	<hr>
	
	<h4>#python price monitoring</h4>
	<code>20 10 * * * /usr/local/bin/python3.8 /userscripts/python/alltime.ru.py > /userscripts/python/cron_log/alltime.ru.log 2>&1</code>
	<p>Парсер страниц www.alltime.ru</p>
	<hr>

	<code>20 10 * * * /usr/local/bin/python3.8 /userscripts/python/bw.by.py > /userscripts/python/cron_log/bw.by.log 2>&1</code>
	<p>Парсер страниц www.bw.by/catalog/?SIZEN_1=500
	<hr>

	<code>#20 10 * * * /usr/local/bin/python3.8 /userscripts/python/minutashop.ru.py > /userscripts/python/cron_log/minutashop.ru.log 2>&1</code>
	<p>Отключен. Парсер страниц </p>
	<hr>

	<code>#0 10 * * * /usr/local/bin/python3.8 /userscripts/python/topgshop.ru.py > /userscripts/python/cron_log/topgshop.ru.log 2>&1</code>
	<p>Отключен. Парсер страниц topgshop.ru</p>
	<hr>

	<code>0 10 * * * /usr/local/bin/python3.8 /userscripts/python/naru4ka.ru.py > /userscripts/python/cron_log/naru4ka.ru.log 2>&1</code>
	<p>Парсер страниц naru4ka.ru</p>
	<hr>

		  

	<code>9 */3 * * * *  /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/cron_events.php > /userscripts/cronlog.log</code>
	<p>странно. это запуск агентов, рассылок. обычно его ставят +- каждую минуты, чтобы дергались нужные события.
	<hr>

		  
	<h4>#feed</h4>
	<code>*/5 * * * *  /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 22 >>/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/logs/22.txt</code>
	<p>Формирование 22 фида</p>
	<hr>

	<code>0 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 26</code>
	<p>Формирование 26 фида</p>
	<hr>

	<code>55 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/KaspiFeedModule/cron/buildFeed.php</code>
	<p>Формирование фида</p>
	<hr>

		
	<h4>#marketplace</h4>
	<code>10 2 * * * /usr/bin/php81  -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/onliner/getArticles.php</code>
	<p>Парсер структуры онлайнера https://content.onliner.by/catalog/structure.xml и сохраниение нужных нам позиций в таблицу ci_onliner_articles. Используется для линковки</p>
	<hr>

	<code>10 */1 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/onliner/updateItems.php</code>
	<p>Обновление позиций на онлайнере</p>
	<hr>

	<code>*/3 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/onliner/getOrders.php</code>
	<p>Забираем заказы с онлайнера</p>
	<hr>

	<code>#0 2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/divide_into_groups.php</code>
	<p></p>
	<p>Отключен. </p>
	<hr>

	<code>#42 16 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/all_cycle.php cabinet=WR</code>
	<p>Отключен. </p>
	<hr>

	<code>30 03 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/set_item_props.php</code>
	<p>Обновление у товаров свойств PROP_MAXYSS_WB, WBARTICLE, WBARTICLE_KZ, WBARTICLE2, WBARTICLE3, array("WIDTH" => 200, "LENGTH" => 200, "HEIGHT" => 200, "WEIGHT" => 200)</p>
	<hr>

	<code>#35 07 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/upload_items.php cabinet=DEFAULT</code>
	<p>Отключен. </p>
	<hr>

	<code>#0 */1 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/TopItemsWB.php</code>
	<p>Отключен. </p>
	<hr>

	<code>0 21 * * 4 /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/TopItemsWB.php</code>
	<p>Обновление списка ТОПа WB. таблица ci_wb_top</p>
	<hr>

	<code>10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/UpdateCollection.php</code>
	<p>Обновление свойств COLLECTION, COLORTERM</p>
	<hr>

	<code>0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/configurator/cron/index.php</code>
	<p>Заполнение тегов. Свойство MARKETPLACE_WB_TAGS</p>
	<hr>

	<code>#30 7 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/statStockWB/cron/StatisticsStockWB.php</code>
	<p></p>
	<p>Отключен. </p>
	<hr>

	<code>00 21 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/descGen/cron/descGenerator.php</code>
	<p>Формирование описания. Пишет в свойство DESC_RICH_OZON</p>
	<hr>


	<h4>#parse</h4>
	<code>#0 23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/yandexPricesReport.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#0 9 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/yandex_mparser.php</code>
	<p>Отключен. </p>
	<hr>

	<code>40 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/catalog_onliner.php</code>
	<p>Парсер файла цен конкурентов из админки онлайнера. Сохранение в ci_catalog_onliner</p>
	<hr>


	<h4>#autoAdvWB</h4>
	<code>#0 16 * * 0,3 /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/autoAdvWB/autoAdvMain.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#11 11 * * 1,4 /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/autoAdvWB/autoAdvDelete.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#20 */4 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/autoAdvWB/autoAdvDeposit.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#15 4,16 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/autoAdvWB/autoAdvAnalisys.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#10 16 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/autoAdvWB/autoAdvCpm.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#5 */6 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/autoAdvWB/autoAdvLimits.php</code>
	<p>Отключен. </p>
	<hr>

	<code>20 4 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/cron/getTurnover.php</code>
	<p>Сохранение в current_cost_ms себестоимости товаров полученной из MS</p>
	<hr>

	<code>#30 13 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/cron/autoAdvStats.php</code>
	<p>Отключен. </p>
	<hr>

	<code>0 9 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/cron/advStats.php</code>
	<p>advert-api.wb.ru</p>
	<hr>


	<h4>#positionsWBparser</h4>
	<code>0 0,4,8,12,16,20 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/parserWB/SearchParserWB.php</code>
	<p>advert-api.wb.ru</p>
	<hr>

	<code>0 22 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/parserWB/ParserDataExport.php</code>
	<p></p>
	<hr>


	<h4>#moysklad</h4>
	<code>*/10 * * * *  /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/syncRetailCrm.php</code>
	<p>Проверка синхронизации заказов в МС и retailcrm</p>
	<hr>

	<code>#*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/createDocuments.php</code>
	<p>Отключен. </p>
	<hr>

	<code>0 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/getOrders.php</code>
	<p>Забираем из МС заказы. сохранет в ci_ms_order</p>
	<hr>

	<code>*/45 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/SyncHistory.php</code>
	<p>Синхронизация возвратов, приёмок, товаров из МС</p>
	<hr>

	<code>0 7 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/stockParser/cron/reportMS.php</code>
	<p></p>
	<hr>


	<h4>#catalog</h4>
	<code>0 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_price_analys.php PRICE_ID=ALL</code>
	<p>Обновление цен на основании анализа цен</p>
	<hr>

	<code>*/5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/updateStock.php</code>
	<p>Обновление складов из МС</p>
	<hr>


	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/UpdateSaleItems.php</code>
	<p>Обновление Суперцен</p>
	<hr>

	<code>0 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/UpdateTopList.php</code>
	<p>Обновление списка ТОПа. таблица ci_top_models</p>
	<hr>

	<code>#0 15 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/changeStatusOrder.php</code>
	<p>Отключен. Изменение статусов заказов на "Выполнен, без смс", "Выполнен" старше 3 дней</p>
	<hr>

	<code>10 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_barcode_alt.php</code>
	<p></p>
	<hr>

	<code>*/5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_diff.php</code>
	<p>Обновление цен у измененных товаров</p>
	<hr>

	<code>0 2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_price_table.php</code>
	<p>Обновление временной таблицы с ценами. ci_price_catalog</p>
	<hr>

	<code>0 1 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_activity_items.php</code>
	<p>Обновляем активность у товаров. если цена на товар не изменялась > 365 дней, то деактивируем, иначе активируем.</p>
	<hr>


	<h4>#system</h4>
	<code>* * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/checkWorkers.php</code>
	<p>Проверка работы запущенных скриптов. перезапуск если упал</p>
	<hr>

	<code>0 2 * * * /var/www/bitrix/data/www/tempusshop.ru/local/cron/system/clearCache.sh</code>
	<p>Удаление старых временных файлов, кэша</p>
	<hr>

	<code>*/30 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/system/checkFreeSpace.php</code>
	<p>Чекер свободного места</p>
	<hr>


	<h4>#other</h4>
	<code>0 7-20/2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/check_orders.php</code>
	<p>Проверка заказов в reatailcrm, MS, bitrix на отличия в системах</p>
	<hr>

	<code>10 2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/clear_log.php</code>
	<p>Удаление логов старше 90 дней</p>
	<hr>

	<code>10 1 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/update_prop_hit.php</code>
	<p>Обновление свойства "Новинка"</p>
	<hr>

	<code>20 1 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/update_prop_favorit.php</code>
	<p>Обновление свойства "Наши предложения" для товаров которые в ТОПе</p>
	<hr>

	<code>#0 4 * * * cd /home/bitrix/tempus_gbq && /home/bitrix/tempus_gbq/venv/bin/python3.9 run.py</code>
	<p>Отключен. </p>
	<hr>

	<code>10 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/BqParse.php ACTION=parseAll</code>
	<p></p>
	<hr>

	<code>10 14 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/subscribe_product_send_email.php</code>
	<p>Отправка письма о поступлении товара на склад. Наверно не актуально</p>
	<hr>

	<code>0 23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/set_flag_update_catalog_all.php</code>
	<p>Обновление каталога</p>
	<hr>

	<code>#0 9 * * 1-5 /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/expiredCardNotifier.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#0 8 * * * /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/other/parserWBsearch.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#35 12 * * * /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/tunoverTest.php</code>
	<p>Отключен. </p>
	<hr>


	<h4>#BQ Directory</h4>
	<code>#15 5 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/customers.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#30 5 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/ms/directory/products.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#55 5 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/BqParseDirectory.php ACTION=parseAll</code>
	<p>Отключен. </p>
	<hr>


	<h4>#ozon</h4>
	<code>#0 * * * * /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importPrices.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#18 20 * * * /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importStock.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importAll.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#28 00 * * * /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importAll.php</code>
	<p>Отключен. </p>
	<hr>

	<code>30 00 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importProducts.php</code>
	<p></p>
	<hr>

	<code>#30 22 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/clearSales.php</code>
	<p>Отключен. </p>
	<hr>

	<code>0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importAllKZ.php</code>
	<hr>

	<code>#0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/importSalesGroup.php</code>
	<p>Отключен. </p>
	<hr>

	<code>30 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/getFboStat.php</code>
	<hr>


	<h4>#ozon analytics</h4>
	<code>5 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/topAnalytics.php</code>
	<p></p>
	<hr>

	<code>15 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/countOrders.php</code>
	<p></p>
	<hr>

	<code>35 11 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/analytics/importCompetitorsData.php</code>
	<p></p>
	<hr>


	<h4>#ozon posting shares</h4>
	<code>7 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon/cron/getPostingsStatPH.php</code>
	<p></p>
	<hr>

	<code>15 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon/cron/getPostingsStatPD.php</code>
	<p></p>
	<hr>

	<code>7 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/getPostingsStatPH.php</code>
	<p></p>
	<hr>

	<code>15 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/getPostingsStatPD.php</code>
	<p></p>
	<hr>


	<h4>#ozon orders</h4>
	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/orders/OzonOrderMain.php</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/orders/OzonOrderStatus.php</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/UpdateOrderFBO.php</code>
	<p>Загрузка заказов ozon FBO</p>
	<hr>


	<h4>#ozon orders TI</h4>
	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/orders/OzonOrderMain.php</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/orders/OzonOrderStatus.php</code>
	<p></p>
	<hr>

	<h4>#ozon orders KZ</h4>
	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/ordersKZ/OzonOrderMain.php</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport/ordersKZ/OzonOrderStatus.php</code>
	<p></p>
	<hr>

	<h4>#ozon clear logs</h4>
	<code>0 0 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/clearLogs.php</code>
	<p>Удаление из /admin/panel/engine/ozon/logs/TI/sales/detail файлов старше 5 дней</p>
	<hr>


	<h4>#wb</h4>
	<code>#*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/wb/loadOrderWR.php</code>
	<p>Отключен. </p>
	<hr>

	<code>#*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/wb/loadOrderDEFAULT.php</code>
	<p>Отключен. </p>
	<hr>


	<h4>#wb stock-price</h4>
	<code>*/5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=WR</code>
	<p></p>
	<hr>

	<code>1-59/5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=TL</code>
	<p></p>
	<hr>

	<code>2-59/5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=WR</code>
	<p></p>
	<hr>

	<code>3-59/5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=TL</code>
	<p></p>
	<hr>


	<h4>#wb orders</h4>
	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php WR</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php WR</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php TL</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php TL</code>
	<p></p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/UpdateOrderFBO.php</code>
	<p>Загрузка заказов WB FBO</p>
	<hr>


	<h4>#wb products</h4>
	<code>30 18 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importProducts.php WR</code>
	<p></p>
	<hr>

	<code>30 22 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/importProducts.php TL</code>
	<p></p>
	<hr>


	<h4>#wb charts</h4>
	<code>30 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/getFboStat.php WR</code>
	<p></p>
	<hr>

	<code>35 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/getFboStat.php TL</code>
	<p></p>
	<hr>


	<h4>#wb analytics</h4>
	<code>5 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/analytics/topAnalytics.php</code>
	<p></p>
	<hr>


	<h4>#wbNew</h4>
	<code>10 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/correctDelete.php</code>
	<p>Очистка таблицы wdhs_wb_fbo_correct</p>
	<hr>

	<code>*/20 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/wb_parser.php</code>
	<p>Парсер страниц https://catalog.wb.ru/sellers/v2/catalog*</p>
	<hr>

	<code>0,15,30,45 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/checkFboMorning.php</code>
	<p>Парсер и запись в wdhs_wb_fbo_correct</p>
	<hr>

	<code>2,17,32,47 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/checkFbo.php</code>
	<p></p>
	<hr>


	<h4>#ozon_ti</h4>
	<code>#0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/importAll.php</code>
	<p>Отключен. </p>
	<hr>

	<code>00 08 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/importProducts_nosync.php</code>
	<p></p>
	<hr>

	<code>#30 00 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/clearSales.php</code>
	<p></p>
	<hr>

	<code>#0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/importSalesGroup.php</code>
	<p></p>
	<p>Отключен. </p>
	<hr>

	<code>30 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/getFboStat.php</code>
	<p></p>
	<hr>


	<h4>#ozonNewAll</h4>
	<code>0 * * * * /usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importPrices.php TI AVTO</code>
	<p></p>
	<hr>

	<code>10 * * * * /usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importStock.php TI AVTO</code>
	<p></p>
	<hr>

	<code>0 */2 * * * /usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/reportStock.php TI AVTO</code>
	<p></p>
	<hr>

	<code>45 3 * * * /usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/deactSales.php</code>
	<p></p>
	<hr>

	<code>50 3 * * * /usr/bin/php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importPricesSalesx05.php</code>
	<p></p>
	<hr>

	<code>#15 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importSalesGroup.php IP</code>
	<p>Отключен. </p>
	<hr>

	<code>#15 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importSalesGroup.php TI</code>
	<p>Отключен. </p>
	<hr>

	<code>15 0-3,4-23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importSalesGroup.php IP AVTO</code>
	<p></p>
	<hr>

	<code>15 0-3,4-23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importSalesGroup.php TI AVTO</code>
	<p></p>
	<hr>

	<code>#*/15 0-3,4-23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/importFBOGroup.php</code>
	<p></p>
	<hr>

	<code>*/10 0-3,4-23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importFBOGroup.php TI AVTO</code>
	<p></p>
	<hr>

	<code>*/10 0-3,4-23 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ozon/importFBOGroup.php IP AVTO</code>
	<p></p>
	<hr>

	<code>50 18 * * * /usr/bin/php81 /var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/img_ozon_v2.php</code>
	<p></p>
	<hr>

	<code>30 10 * * * /usr/bin/php81 /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/ms/getTurnover.php</code>
	<p></p>
	<hr>


	<h4>#nakladnie</h4>
	<code>10 1 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/nakladnie/clear.php</code>
	<p>Удаление всех файлов в /var/www/bitrix/data/www/tempusshop.ru/upload/nakladnie_cache/</p>
	<hr>

	<h4>#offline</h4>
	<code>10 12 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/offline/pricelist.php</code>
	<p>Запись в таблицу offline_price цен BY</p>
	<hr>

	<h4>#infograph</h4>
	<code>#0 */8 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/updater.php</code>
	<p>Отключен. </p>
	<hr>

	<code>0 */12 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/updater_photo.php</code>
	<p>Наложение на картинки вотермарка и сохранение в IMAGE_MARKETPLACE</p>
	<hr>

	<code>0 */8 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/updater_ya.php</code>
	<p>Обновление активности у товаров яндекса</p>
	<hr>

	<code>#00 16 * * * /usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/updater_ya.php</code>
	<p>Отключен. </p>
	<hr>

	<code>28 10 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/img.php</code>
	<p>Наложение на картинки вотермарка и сохранение в INFO_WB_IMAGE</p>
	<hr>

	<code>#11 13 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/img_ozon.php</code>
	<p>Отключен. </p>
	<hr>

	<code>10 00 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/update_collection_ozon.php</code>
	<p>Обновление свойства COLLECTION_OZON</p>
	<hr>

	<code>#32 22 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/upload_items_top.php</code>
	<p>Отключен. Обновление карточек на WB</p>
	<hr>

	<code>59 21 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/updater.php</code>
	<p>Обновление свойств OZON_ID, OZON_ID_TI, TNVD_CODE, TNVD_DESC, NAME_MARKETPLACE, NAME_WB_MP, NAME_YA_MP, DATE_LAST_STOCK, rich_ozon</p>
	<hr>

	<code>#18 19 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/wb.php</code>
	<p>Отключен. </p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/wb_parser.php</code>
	<p>Парсер страниц WB</p>
	<hr>

	<code>#0 */2 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/configurator/cron/ozon.php</code>
	<p>Отключен. </p>
	<hr>


	<h4>#sber</h4>
	<code>30 * * * * /usr/bin/curl -s https://tempusshop.ru/bitrix/catalog_export/clean_sber.php</code>
	<p>Что то странное. удаляет из сформированного ранее export_TUs.xml товары, которых нет в наличии у поставщиков с активностью сбера. </p>
	<hr>

	<code>5 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempuswatch.kz/local/cron/catalog/update_catalog_kz.php</code>
	<p>Проставляет в свойство AVAILABILITY_KZ - "В наличии" если товар есть в прайслисте. если в прайсе нет, то "Нет в наличии" не ставит.</p>
	<hr>

	<code>#0 */4 * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/dev/export_test.php</code>
	<p>Отключен. </p>
	<hr>

	<code>*/60 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/dev/update_new_site_price_active.php</code>
	<p>Отправка на https://tempus.ru/local/rest/exchange_price_activity.php цен, наличия</p>
	<hr>

	<code>*/10 * * * * /usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/dev/sender_sale.php</code>
	<p>Отправка товаров из раздела "Суперцена RU" на https://tempus.ru/local/rest/exchange-sale.php</p>
	<hr>


</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
