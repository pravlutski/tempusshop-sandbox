-- ci_worker_busy: TIMEOUT по реальному crontab (дамп 2026-08-30)
--
-- Правило TIMEOUT (минуты после TIME_CHECK до тревоги):
--   max_gap = максимальная пауза между соседними запусками cron
--   max_gap <=  60 мин  → 180  (3 часа; частые кроны часто идут дольше интервала)
--   max_gap <= 360 мин  → max_gap * 2
--   max_gap <=1440 мин  → max_gap + 360
--   иначе               → max_gap + 1440
--
-- checkFboNew WR: cron 2,17,32,47 * * * * = каждые 15 мин, НЕ каждые 45.
-- Старый TIMEOUT=45 давал ложные «обработчик 3» на длинном FBO-прогоне.
-- Новый TIMEOUT=180.
-- TIME_CHECK=NOW() сбрасывает текущую отложенную тревогу.

SET @now = NOW();

-- 1) Активные кроны, которые сами пишут TIME_CHECK через WorkersChecker
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_common_adverts_AdvertManager_php_ozon' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/common/adverts/AdvertManager.php ozon';  -- AdvertManager ozon | 7 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_common_adverts_AdvertManager_php_wb' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/common/adverts/AdvertManager.php wb';  -- AdvertManager wb | 4 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_parser_AvitoOrder_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/parser/AvitoOrder.php';  -- AvitoOrder | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'common_DynamicPrice_DPCorrector_php_OZON_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPCorrector.php OZON IP';  -- DPCorrector OZON IP | 15,30,45 * * * * gap=30m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'common_DynamicPrice_DPCorrector_php_WB_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPCorrector.php WB WR';  -- DPCorrector WB WR | 15,30,45 * * * * gap=30m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'common_DynamicPrice_DPMain_php_OZON_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPMain.php OZON IP';  -- DPMain OZON IP | 3 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'wb_classes_control_ItemsControl_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/classes/control/ItemsControl.php WR';  -- ItemsControl WR | 32 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_orders_OzonOrderFbo_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/orders/OzonOrderFbo.php IP';  -- OzonOrderFbo IP | */12 * * * * gap=12m was=36
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_orders_OzonOrderMain_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/orders/OzonOrderMain.php IP';  -- OzonOrderMain IP | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_orders_OzonOrderStatus_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/orders/OzonOrderStatus.php IP';  -- OzonOrderStatus IP | */15 * * * * gap=15m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'wb_classes_control_PriceControl_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/classes/control/PriceControl.php WR';  -- PriceControl WR | 37 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'SyncHistory' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/ms/SyncHistory.php';  -- SyncHistory | */45 * * * * gap=45m was=135
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'UpdateCollection' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/ozon/UpdateCollection.php';  -- UpdateCollection | 10 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'UpdateOrderFBO' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/ozon/UpdateOrderFBO.php';  -- UpdateOrderFBO | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'UpdateOrderFBO_wb' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/wb/UpdateOrderFBO.php';  -- UpdateOrderFBO | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderFBO_php_TL' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderFBO.php TL';  -- WBOrderFBO TL | 52 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderFBO_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderFBO.php WR';  -- WBOrderFBO WR | 45 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderMain_php_TL' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php TL';  -- WBOrderMain TL | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderMain_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php WR';  -- WBOrderMain WR | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderMain_php_WT' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php WT';  -- WBOrderMain WT | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderStatus_php_TL' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php TL';  -- WBOrderStatus TL | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderStatus_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php WR';  -- WBOrderStatus WR | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderStatus_php_WT' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php WT';  -- WBOrderStatus WT | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_orders_cancelOrder_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/orders/cancelOrder.php';  -- cancelOrder | */5 * * * * gap=5m was=20
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_checkFboNew_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/checkFboNew.php WR';  -- checkFboNew WR | 2,17,32,47 * * * * gap=15m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_system_checkFreeSpace_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/system/checkFreeSpace.php';  -- checkFreeSpace | */30 * * * * gap=30m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_catalog_check_price_analysis_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/check_price_analysis.php';  -- check_price_analysis | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_parser_competitor_php_12' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/parser/competitor.php 12';  -- competitor 12 | 20 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'parser_wb_alltime_cron_wb2_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/parser/wb_alltime/cron_wb2.php';  -- cron_wb2 | 10 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_analytics_exportAnalyticsData_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/exportAnalyticsData.php';  -- exportAnalyticsData | 18 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_analytics_exportAnalyticsData_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/analytics/exportAnalyticsData.php';  -- exportAnalyticsData | 18 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_analytics_getAnalyticsCsv_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/getAnalyticsCsv.php';  -- getAnalyticsCsv | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_analytics_getAnalyticsCsv_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/analytics/getAnalyticsCsv.php';  -- getAnalyticsCsv | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_ms_getOrders_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/ms/getOrders.php';  -- getOrders | */15 * * * * gap=15m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'onlinerGetOrder' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/onliner/getOrders.php';  -- getOrders | */5 * * * * gap=5m was=20
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ozon_getPostingsStatPH_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/getPostingsStatPH.php';  -- getPostingsStatPH | 7 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_importFBOGroup_php_IP_AVTO' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/importFBOGroup.php IP AVTO';  -- importFBOGroup IP AVTO | */20 * * * * gap=20m was=35
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_yandex_importOrders_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/importOrders.php';  -- importOrders | */12 * * * * gap=12m was=36
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_yandex_importPrices_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/importPrices.php';  -- importPrices | 0,30 * * * * gap=30m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_importPrices_php_CABINET_TL' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=TL';  -- importPrices CABINET=TL | 3-59/10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_importPrices_php_CABINET_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=WR';  -- importPrices CABINET=WR | 2-59/10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_importPrices_php_CABINET_WT' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=WT';  -- importPrices CABINET=WT | 3-59/10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_importPrices_php_IP_AVTO' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/importPrices.php IP AVTO';  -- importPrices IP AVTO | 5,30 * * * * gap=35m was=50
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_yandex_importPromos_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/importPromos.php';  -- importPromos | 07 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_importSalesGroup_php_IP_AVTO' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/importSalesGroup.php IP AVTO';  -- importSalesGroup IP AVTO | 7,27,49 0-3,4-23 * * * gap=22m was=37
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_yandex_importStock_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/importStock.php';  -- importStock | 15,45 * * * * gap=30m was=45
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_importStock_php_CABINET_TL' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=TL';  -- importStock CABINET=TL | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_importStock_php_CABINET_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=WR';  -- importStock CABINET=WR | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_importStock_php_CABINET_WT' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=WT';  -- importStock CABINET=WT | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'ozon_importStock_v2_php_IP_AVTO' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/importStock_v2.php IP AVTO';  -- importStock_v2 IP AVTO | */12 * * * * gap=12m was=36
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_analytics_sppAnalytics_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/analytics/sppAnalytics.php';  -- sppAnalytics | 0 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_analytics_sppAnalytics2_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/sppAnalytics2.php IP';  -- sppAnalytics2 IP | 6 * * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'SyncOrder' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/ms/syncOrder.php';  -- syncOrder | */5 * * * * gap=5m was=20
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'syncRetailCrm' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/ms/syncRetailCrm.php';  -- syncRetailCrm | */10 * * * * gap=10m was=30
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'onlinerUpdateItems' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/onliner/updateItems.php';  -- updateItems | 10 */1 * * * gap=60m was=75
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'updateStock' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/updateStock.php';  -- updateStock | */5 * * * * gap=5m was=20
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_catalog_update_catalog_diff_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/update_catalog_diff.php';  -- update_catalog_diff | */5 * * * * gap=5m was=20
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'price_analys_php_PRICE_ID_ALL' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/update_price_analys.php PRICE_ID=ALL';  -- update_price_analys PRICE_ID=ALL | 0 * * * * gap=60m was=150
UPDATE ci_worker_busy SET TIMEOUT = 180, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_rabbitmq_updater_props_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/rabbitmq/updater_props.php';  -- updater_props | */10 * * * * gap=10m was=70
UPDATE ci_worker_busy SET TIMEOUT = 240, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_reportStock_php_IP_AVTO' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/reportStock.php IP AVTO';  -- reportStock IP AVTO | 0 */2 * * * gap=120m was=135
UPDATE ci_worker_busy SET TIMEOUT = 360, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_wb_orders_WBOrderLost_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderLost.php WR';  -- WBOrderLost WR | 0 */3 * * * gap=180m was=195
UPDATE ci_worker_busy SET TIMEOUT = 720, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'SyncDemand' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/ms/SyncDemand.php';  -- SyncDemand | 0 */6 * * * gap=360m was=375
UPDATE ci_worker_busy SET TIMEOUT = 720, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ozon_updateProps_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/updateProps.php';  -- updateProps | 0 */6 * * * gap=360m was=375
UPDATE ci_worker_busy SET TIMEOUT = 1080, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_other_check_orders_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/other/check_orders.php';  -- check_orders | 0 7-20/2 * * * gap=720m was=735
UPDATE ci_worker_busy SET TIMEOUT = 1500, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_control_checkCheckFBo_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/control/checkCheckFBo.php';  -- checkCheckFBo | 0 7-12 * * * gap=1140m was=1155
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_infograph_libozon_OZONInfograhGenerator_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/infograph/libozon/OZONInfograhGenerator.php';  -- OZONInfograhGenerator | 19 15 * * * gap=1440m was=1680
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'UpdateTopList' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/UpdateTopList.php';  -- UpdateTopList | 0 0 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_infograph_libwb_WBInfograhGenerator_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/infograph/libwb/WBInfograhGenerator.php';  -- WBInfograhGenerator | 35 15 * * * gap=1440m was=1680
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_analytics_checkSppChange_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/analytics/checkSppChange.php';  -- checkSppChange | 00 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_nakladnie_clear_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/nakladnie/clear.php';  -- clear | 10 1 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ozon_clearSales_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/clearSales.php';  -- clearSales | 45 3 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_other_clear_log_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/other/clear_log.php';  -- clear_log | 10 2 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_parser_competitor_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/parser/competitor.php';  -- competitor | 0 11 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_wb_correctDelete_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/correctDelete.php';  -- correctDelete | 10 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_analytics_countOrders_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/countOrders.php';  -- countOrders | 15 0 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_modules_descGen_cron_descGenerator_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/modules/descGen/cron/descGenerator.php';  -- descGenerator | 00 21 * * * gap=1440m was=1620
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_other_export_kassa_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/other/export_kassa.php';  -- export_kassa | 0 1 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_marketplace_onliner_getArticles_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/onliner/getArticles.php';  -- getArticles | 10 2 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_getFboStat_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/getFboStat.php IP';  -- getFboStat IP | 45 8 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_getFboStat_php_TL' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/getFboStat.php TL';  -- getFboStat TL | 35 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_getFboStat_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/getFboStat.php WR';  -- getFboStat WR | 45 8 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_yandex_analytics_getOrdersReport_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/analytics/getOrdersReport.php';  -- getOrdersReport | 0 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ozon_getPostingsStatPD_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/getPostingsStatPD.php';  -- getPostingsStatPD | 15 0 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_yandex_analytics_getPriceReport_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/analytics/getPriceReport.php';  -- getPriceReport | 30 9 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_wb_getReviews_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/getReviews.php';  -- getReviews | 10 11 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_analytics_stocks_getStockReport_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/getStockReport.php';  -- getStockReport | 0 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'ozon_analytics_stocks_getStockReturns_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/getStockReturns.php IP';  -- getStockReturns IP | 0 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_analytics_stocks_getToClientStockMovement_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/getToClientStockMovement.php';  -- getToClientStockMovement | 0 10 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_modules_promcom_cron_getTurnover_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/modules/promcom/cron/getTurnover.php';  -- getTurnover | 20 4 * * * gap=1440m was=1530
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ms_getTurnover_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ms/getTurnover.php';  -- getTurnover | 30 10 * * * gap=1440m was=1530
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ms_getTurnoverWB_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ms/getTurnoverWB.php';  -- getTurnoverWB | 10 10 * * * gap=1440m was=1530
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_analytics_importCompetitorsData_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/importCompetitorsData.php';  -- importCompetitorsData | 35 11 * * * gap=1440m was=1530
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_importProducts_php_WR' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importProducts.php WR';  -- importProducts WR | 30 4 * * * gap=1440m was=1620
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_importProducts_php_WT' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/importProducts.php WT';  -- importProducts WT | 00 19 * * * gap=1440m was=1620
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'engine_ozon_importProducts_nosync_php_IP' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/importProducts_nosync.php IP';  -- importProducts_nosync IP | 0 5 * * * gap=1440m was=1620
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'ozon_importProducts_nosync_php_IP_1' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/importProducts_nosync.php IP 1';  -- importProducts_nosync IP 1 | 0 3 * * * gap=1440m was=1620
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_offline_pricelist_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/offline/pricelist.php';  -- pricelist | 10 12 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_offline_pricelist_ru_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/offline/pricelist_ru.php';  -- pricelist_ru | 10 12 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'set_flag_update_catalog_all_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/other/set_flag_update_catalog_all.php';  -- set_flag_update_catalog_all | 0 23 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'marketplace_wb_set_item_props_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/wb/set_item_props.php';  -- set_item_props | 30 03 * * * gap=1440m was=1560
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_common_sheetsExport_sheetsExport_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/common/sheetsExport/sheetsExport.php';  -- sheetsExport | 19 2 * * * gap=1440m was=1560
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_wb_analytics_topAnalytics_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/analytics/topAnalytics.php';  -- topAnalytics | 0 9 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_analytics_topAnalytics2_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/analytics/topAnalytics2.php';  -- topAnalytics2 | 0 9 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_catalog_update_activity_items_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/update_activity_items.php';  -- update_activity_items | 0 1 * * * gap=1440m was=1530
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_other_update_all_props_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/other/update_all_props.php';  -- update_all_props | 10 1 * * * gap=1440m was=1560
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_catalog_update_barcode_alt_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/update_barcode_alt.php';  -- update_barcode_alt | 10 0 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_marketplace_update_collection_ozon_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/update_collection_ozon.php';  -- update_collection_ozon | 10 00 * * * gap=1440m was=1455
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'cron_catalog_update_price_table_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/update_price_table.php';  -- update_price_table | 0 2 * * * gap=1440m was=1530
UPDATE ci_worker_busy SET TIMEOUT = 1800, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'local_cron_catalog_updaterProps_php' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/catalog/updaterProps.php';  -- updaterProps | 0 4 * * * gap=1440m was=1560
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'TopItemsWB' OR PATH_SCRIPT = 'tempusshop.ru/local/cron/marketplace/wb/TopItemsWB.php';  -- TopItemsWB | 0 21 * * 4 gap=10080m was=1500
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_sites_calculateSort_php_RU' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/sites/calculateSort.php RU';  -- calculateSort RU | 50 11 * * 1 gap=10080m was=10170
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_ozon_cleaning_clearSalesLog_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/cleaning/clearSalesLog.php';  -- clearSalesLog | 0 0 * * 1 gap=10080m was=10095
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_sites_getProfitMS_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/sites/getProfitMS.php';  -- getProfitMS | 10 0 * * 1 gap=10080m was=10170
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_ozon_getTopMS_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/ozon/getTopMS.php';  -- getTopMS | 5 19 * * 2 gap=10080m was=10170
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'admin_panel_engine_wb_getTopMS_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/wb/getTopMS.php';  -- getTopMS | 10 19 * * 2 gap=10080m was=10170
UPDATE ci_worker_busy SET TIMEOUT = 11520, TIME_CHECK = @now, SEND_MESSAGE = 'Y' WHERE WORKER_ID = 'panel_engine_yandex_analytics_getTopMS_php' OR PATH_SCRIPT = 'tempusshop.ru/admin/panel/engine/yandex/analytics/getTopMS.php';  -- getTopMS | 0 9 * * 2 gap=10080m was=10170

-- 2) Выключить наблюдение: Bitrix core, tempus.ru, .sh, отсутствующие файлы,
--    и строки из старого INSERT без живого cron. Чекер пропускает пустой TIME_CHECK.
--   cron_events_tempusshop  cron_events  cron=*/3 * * * *  tempusshop.ru/bitrix/php_interface/cron_events.php
--   cron_events_tempru  cron_events  cron=*/3 * * * *  tempus.ru/bitrix/php_interface/cron_events.php
--   catalog_export_cron_frame_php_6  cron_frame 6  cron=25 */6 * * *  tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 6
--   catalog_export_cron_frame_php_7  cron_frame 7  cron=25 0 * * *  tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 7
--   catalog_export_cron_frame_php_4  cron_frame 4  cron=35 */8 * * *  tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 4
--   catalog_export_cron_frame_php_54  cron_frame 54  cron=10 5 * * *  tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 54
--   tempru_local_control_orders_php  orders  cron=05 * * * *  tempus.ru/local/control/orders.php
--   tempru_local_control_OrdersControl_OrderControl_php  OrderControl  cron=13 9-18/4 * * *  tempus.ru/local/control/OrdersControl/OrderControl.php
--   tempru_local_rest_control_php  control  cron=15 * * * *  tempus.ru/local/rest/control.php
--   catalog_export_cron_frame_php_22  cron_frame 22  cron=*/5 * * * *  tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 22
--   catalog_export_cron_frame_php_26  cron_frame 26  cron=0 * * * *  tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 26
--   cron_catalog_update_count_section_php  update_count_section  cron=0 3 * * *  tempusshop.ru/local/cron/catalog/update_count_section.php
--   cron_catalog_update_collection_property_php  update_collection_property  cron=10 * * * *  tempus.ru/local/cron/catalog/update_collection_property.php
--   local_cron_system_clearCache_sh  clearCache  cron=0 2 * * *  tempusshop.ru/local/cron/system/clearCache.sh
--   other_subscribe_product_send_email_php  subscribe_product_send_email  cron=10 * * * *  tempus.ru/local/cron/other/subscribe_product_send_email.php
--   cron_catalog_update_price_property_php  update_price_property  cron=*/30 * * * *  tempus.ru/local/cron/catalog/update_price_property.php
--   tempru_local_cron_elasticsearch_php  elasticsearch  cron=*/30 * * * *  tempus.ru/local/cron/elasticsearch.php
--   tempru_local_cron_elasticsearch_taps_php  elasticsearch_taps  cron=*/10 * * * *  tempus.ru/local/cron/elasticsearch_taps.php
--   tempru_local_cron_elasticsearch_logs_php  elasticsearch_logs  cron=*/2 * * * *  tempus.ru/local/cron/elasticsearch_logs.php
--   catalog_export_cron_frame_php_12  cron_frame 12  cron=20 */4 * * *  tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 12
--   engine_ozon_unarchiveProducts_php_IP_AVTO  unarchiveProducts IP AVTO  cron=10 4 * * *  tempusshop.ru/admin/panel/engine/ozon/unarchiveProducts.php IP AVTO
UPDATE ci_worker_busy SET TIME_CHECK = NULL, SEND_MESSAGE = 'N'
WHERE WORKER_ID IN (
  'cron_events_tempusshop',
  'cron_events_tempru',
  'catalog_export_cron_frame_php_6',
  'catalog_export_cron_frame_php_7',
  'catalog_export_cron_frame_php_4',
  'catalog_export_cron_frame_php_54',
  'tempru_local_control_orders_php',
  'tempru_local_control_OrdersControl_OrderControl_php',
  'tempru_local_rest_control_php',
  'catalog_export_cron_frame_php_22',
  'catalog_export_cron_frame_php_26',
  'cron_catalog_update_count_section_php',
  'cron_catalog_update_collection_property_php',
  'local_cron_system_clearCache_sh',
  'other_subscribe_product_send_email_php',
  'cron_catalog_update_price_property_php',
  'tempru_local_cron_elasticsearch_php',
  'tempru_local_cron_elasticsearch_taps_php',
  'tempru_local_cron_elasticsearch_logs_php',
  'catalog_export_cron_frame_php_12',
  'engine_ozon_unarchiveProducts_php_IP_AVTO'
)
   OR PATH_SCRIPT IN (
  'tempusshop.ru/bitrix/php_interface/cron_events.php',
  'tempus.ru/bitrix/php_interface/cron_events.php',
  'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 6',
  'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 7',
  'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 4',
  'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 54',
  'tempus.ru/local/control/orders.php',
  'tempus.ru/local/control/OrdersControl/OrderControl.php',
  'tempus.ru/local/rest/control.php',
  'tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 22',
  'tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 26',
  'tempusshop.ru/local/cron/catalog/update_count_section.php',
  'tempus.ru/local/cron/catalog/update_collection_property.php',
  'tempusshop.ru/local/cron/system/clearCache.sh',
  'tempus.ru/local/cron/other/subscribe_product_send_email.php',
  'tempus.ru/local/cron/catalog/update_price_property.php',
  'tempus.ru/local/cron/elasticsearch.php',
  'tempus.ru/local/cron/elasticsearch_taps.php',
  'tempus.ru/local/cron/elasticsearch_logs.php',
  'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 12',
  'tempusshop.ru/admin/panel/engine/ozon/unarchiveProducts.php IP AVTO'
)
   OR PATH_SCRIPT LIKE 'tempus.ru/%'
   OR PATH_SCRIPT LIKE '%/bitrix/%'
   OR PATH_SCRIPT LIKE '%.sh'
   OR PATH_SCRIPT LIKE '%checkFboNew.php TL%'
   OR PATH_SCRIPT LIKE '%checkFboNew.php WT%'
   OR WORKER_ID IN ('panel_engine_wb_checkFboNew_php_TL', 'panel_engine_wb_checkFboNew_php_IP');

