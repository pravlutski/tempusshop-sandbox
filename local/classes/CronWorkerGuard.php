<?php
/**
 * Старт/стоп записи в ci_worker_busy для cron-скриптов.
 * WORKER_ID совпадает с ключами, которые добавлялись в таблицу по crontab.
 */
class CronWorkerGuard
{
    private static $started = false;

    public static function startFromArgv($argv = null)
    {
        if ($argv === null) {
            $argv = isset($_SERVER["argv"]) ? $_SERVER["argv"] : array();
        }
        $id = self::resolveWorkerId($argv);
        if ($id === "" || $id === "checkWorkers") {
            return true;
        }
        return self::start($id);
    }

    public static function start($workerId)
    {
        if ($workerId === "") {
            return true;
        }
        self::ensureClasses();
        if (!class_exists("WorkersChecker")) {
            return true;
        }

        $workers = new WorkersChecker($workerId);
        if (!$workers->checkStatus()) {
            return false;
        }

        $workers->updateStatus("Y");
        if (!self::$started) {
            self::$started = true;
            register_shutdown_function(function () use ($workers) {
                $workers->updateStatus("N");
            });
        }
        return true;
    }

    public static function resolveWorkerId($argv)
    {
        $script = isset($argv[0]) ? $argv[0] : "";
        $args = array();
        if (is_array($argv)) {
            foreach (array_slice($argv, 1) as $arg) {
                if ($arg === "" || $arg === "-f" || $arg === "--") {
                    continue;
                }
                if (isset($arg[0]) && $arg[0] === "-") {
                    continue;
                }
                $args[] = $arg;
            }
        }
        $rel = self::normalizeScript($script);
        $pathScript = $rel;
        if ($args) {
            $pathScript .= " " . implode(" ", $args);
        }

        $map = self::map();
        if (isset($map[$pathScript])) {
            return $map[$pathScript];
        }
        if (isset($map[$rel])) {
            return $map[$rel];
        }
        return "";
    }

    private static function normalizeScript($script)
    {
        $script = str_replace("\\", "/", $script);
        foreach (array("tempusshop.ru/", "tempus.ru/") as $marker) {
            $pos = strpos($script, $marker);
            if ($pos !== false) {
                return substr($script, $pos);
            }
        }
        $script = ltrim($script, "/");
        if (strpos($script, "local/") === 0 || strpos($script, "admin/") === 0) {
            return "tempusshop.ru/" . $script;
        }
        return $script;
    }

    private static function ensureClasses()
    {
        $root = (!empty($_SERVER["DOCUMENT_ROOT"]))
            ? $_SERVER["DOCUMENT_ROOT"]
            : "/var/www/bitrix/data/www/tempusshop.ru";
        if (!class_exists("TsLogger")) {
            $file = $root . "/local/classes/TsLogger.php";
            if (is_file($file)) {
                require_once $file;
            }
        }
        if (!class_exists("WorkersChecker")) {
            $file = $root . "/local/classes/WorkersChecker.php";
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    private static function map()
    {
        return array(
        'tempusshop.ru/bitrix/php_interface/cron_events.php' => 'cron_events_tempusshop',
        'tempus.ru/bitrix/php_interface/cron_events.php' => 'cron_events_tempru',
        'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 6' => 'catalog_export_cron_frame_php_6',
        'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 7' => 'catalog_export_cron_frame_php_7',
        'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 4' => 'catalog_export_cron_frame_php_4',
        'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 54' => 'catalog_export_cron_frame_php_54',
        'tempusshop.ru/local/cron/ms/syncOrder.php' => 'SyncOrder',
        'tempusshop.ru/admin/panel/engine/ozon/orders/cancelOrder.php' => 'panel_engine_ozon_orders_cancelOrder_php',
        'tempus.ru/local/control/orders.php' => 'tempru_local_control_orders_php',
        'tempus.ru/local/control/OrdersControl/OrderControl.php' => 'tempru_local_control_OrdersControl_OrderControl_php',
        'tempus.ru/local/rest/control.php' => 'tempru_local_rest_control_php',
        'tempusshop.ru/local/cron/other/export_kassa.php' => 'local_cron_other_export_kassa_php',
        'tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 22' => 'catalog_export_cron_frame_php_22',
        'tempusshop.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 26' => 'catalog_export_cron_frame_php_26',
        'tempusshop.ru/local/cron/marketplace/onliner/getArticles.php' => 'local_cron_marketplace_onliner_getArticles_php',
        'tempusshop.ru/local/cron/marketplace/onliner/updateItems.php' => 'onlinerUpdateItems',
        'tempusshop.ru/local/cron/marketplace/onliner/getOrders.php' => 'onlinerGetOrder',
        'tempusshop.ru/local/cron/marketplace/wb/set_item_props.php' => 'marketplace_wb_set_item_props_php',
        'tempusshop.ru/local/cron/marketplace/wb/TopItemsWB.php' => 'TopItemsWB',
        'tempusshop.ru/local/cron/marketplace/ozon/UpdateCollection.php' => 'UpdateCollection',
        'tempusshop.ru/admin/modules/descGen/cron/descGenerator.php' => 'admin_modules_descGen_cron_descGenerator_php',
        'tempusshop.ru/local/cron/parser/competitor.php 12' => 'local_cron_parser_competitor_php_12',
        'tempusshop.ru/local/cron/parser/competitor.php' => 'local_cron_parser_competitor_php',
        'tempusshop.ru/local/cron/parser/wb_alltime/cron_wb2.php' => 'parser_wb_alltime_cron_wb2_php',
        'tempusshop.ru/local/cron/parser/AvitoOrder.php' => 'local_cron_parser_AvitoOrder_php',
        'tempusshop.ru/admin/modules/promcom/cron/getTurnover.php' => 'admin_modules_promcom_cron_getTurnover_php',
        'tempusshop.ru/local/cron/ms/syncRetailCrm.php' => 'syncRetailCrm',
        'tempusshop.ru/local/cron/ms/getOrders.php' => 'local_cron_ms_getOrders_php',
        'tempusshop.ru/local/cron/ms/SyncHistory.php' => 'SyncHistory',
        'tempusshop.ru/local/cron/ms/SyncDemand.php' => 'SyncDemand',
        'tempusshop.ru/local/cron/catalog/update_price_analys.php PRICE_ID=ALL' => 'price_analys_php_PRICE_ID_ALL',
        'tempusshop.ru/local/cron/catalog/check_price_analysis.php' => 'cron_catalog_check_price_analysis_php',
        'tempusshop.ru/local/cron/catalog/updateStock.php' => 'updateStock',
        'tempusshop.ru/local/cron/UpdateTopList.php' => 'UpdateTopList',
        'tempusshop.ru/local/cron/catalog/update_barcode_alt.php' => 'cron_catalog_update_barcode_alt_php',
        'tempusshop.ru/local/cron/catalog/update_catalog_diff.php' => 'cron_catalog_update_catalog_diff_php',
        'tempusshop.ru/local/cron/catalog/update_price_table.php' => 'cron_catalog_update_price_table_php',
        'tempusshop.ru/local/cron/catalog/update_activity_items.php' => 'cron_catalog_update_activity_items_php',
        'tempusshop.ru/local/cron/catalog/update_count_section.php' => 'cron_catalog_update_count_section_php',
        'tempus.ru/local/cron/catalog/update_collection_property.php' => 'cron_catalog_update_collection_property_php',
        'tempusshop.ru/local/cron/catalog/updaterProps.php' => 'local_cron_catalog_updaterProps_php',
        'tempusshop.ru/local/cron/system/clearCache.sh' => 'local_cron_system_clearCache_sh',
        'tempusshop.ru/local/cron/system/checkFreeSpace.php' => 'local_cron_system_checkFreeSpace_php',
        'tempusshop.ru/local/cron/other/check_orders.php' => 'local_cron_other_check_orders_php',
        'tempusshop.ru/local/cron/other/clear_log.php' => 'local_cron_other_clear_log_php',
        'tempusshop.ru/local/cron/other/update_all_props.php' => 'cron_other_update_all_props_php',
        'tempus.ru/local/cron/other/subscribe_product_send_email.php' => 'other_subscribe_product_send_email_php',
        'tempusshop.ru/local/cron/other/set_flag_update_catalog_all.php' => 'set_flag_update_catalog_all_php',
        'tempusshop.ru/admin/panel/engine/sites/getProfitMS.php' => 'admin_panel_engine_sites_getProfitMS_php',
        'tempusshop.ru/admin/panel/engine/ozon/getTopMS.php' => 'admin_panel_engine_ozon_getTopMS_php',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/topAnalytics2.php' => 'panel_engine_ozon_analytics_topAnalytics2_php',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/sppAnalytics2.php IP' => 'engine_ozon_analytics_sppAnalytics2_php_IP',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/exportAnalyticsData.php' => 'panel_engine_ozon_analytics_exportAnalyticsData_php',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/countOrders.php' => 'panel_engine_ozon_analytics_countOrders_php',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/importCompetitorsData.php' => 'panel_engine_ozon_analytics_importCompetitorsData_php',
        'tempusshop.ru/admin/panel/engine/wb/getReviews.php' => 'admin_panel_engine_wb_getReviews_php',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/getAnalyticsCsv.php' => 'panel_engine_ozon_analytics_getAnalyticsCsv_php',
        'tempusshop.ru/admin/panel/engine/ozon/getPostingsStatPH.php' => 'admin_panel_engine_ozon_getPostingsStatPH_php',
        'tempusshop.ru/admin/panel/engine/ozon/getPostingsStatPD.php' => 'admin_panel_engine_ozon_getPostingsStatPD_php',
        'tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPMain.php OZON IP' => 'common_DynamicPrice_DPMain_php_OZON_IP',
        'tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPCorrector.php OZON IP' => 'common_DynamicPrice_DPCorrector_php_OZON_IP',
        'tempusshop.ru/admin/panel/engine/common/DynamicPrice/DPCorrector.php WB WR' => 'common_DynamicPrice_DPCorrector_php_WB_WR',
        'tempusshop.ru/admin/panel/engine/ozon/orders/OzonOrderFbo.php IP' => 'engine_ozon_orders_OzonOrderFbo_php_IP',
        'tempusshop.ru/admin/panel/engine/ozon/orders/OzonOrderMain.php IP' => 'engine_ozon_orders_OzonOrderMain_php_IP',
        'tempusshop.ru/admin/panel/engine/ozon/orders/OzonOrderStatus.php IP' => 'engine_ozon_orders_OzonOrderStatus_php_IP',
        'tempusshop.ru/local/cron/marketplace/ozon/UpdateOrderFBO.php' => 'UpdateOrderFBO',
        'tempusshop.ru/admin/panel/engine/ozon/cleaning/clearSalesLog.php' => 'panel_engine_ozon_cleaning_clearSalesLog_php',
        'tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=WR' => 'engine_wb_importStock_php_CABINET_WR',
        'tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=TL' => 'engine_wb_importStock_php_CABINET_TL',
        'tempusshop.ru/admin/panel/engine/wb/importStock.php CABINET=WT' => 'engine_wb_importStock_php_CABINET_WT',
        'tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=WR' => 'engine_wb_importPrices_php_CABINET_WR',
        'tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=TL' => 'engine_wb_importPrices_php_CABINET_TL',
        'tempusshop.ru/admin/panel/engine/wb/importPrices.php CABINET=WT' => 'engine_wb_importPrices_php_CABINET_WT',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php WR' => 'engine_wb_orders_WBOrderMain_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php WR' => 'engine_wb_orders_WBOrderStatus_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php WT' => 'engine_wb_orders_WBOrderMain_php_WT',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php WT' => 'engine_wb_orders_WBOrderStatus_php_WT',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderMain.php TL' => 'engine_wb_orders_WBOrderMain_php_TL',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderStatus.php TL' => 'engine_wb_orders_WBOrderStatus_php_TL',
        'tempusshop.ru/local/cron/marketplace/wb/UpdateOrderFBO.php' => 'UpdateOrderFBO_wb',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderLost.php WR' => 'engine_wb_orders_WBOrderLost_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderFBO.php WR' => 'engine_wb_orders_WBOrderFBO_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/orders/WBOrderFBO.php TL' => 'engine_wb_orders_WBOrderFBO_php_TL',
        'tempusshop.ru/admin/panel/engine/wb/importProducts.php WR' => 'panel_engine_wb_importProducts_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/importProducts.php WT' => 'panel_engine_wb_importProducts_php_WT',
        'tempusshop.ru/admin/panel/engine/wb/getFboStat.php WR' => 'panel_engine_wb_getFboStat_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/getFboStat.php TL' => 'panel_engine_wb_getFboStat_php_TL',
        'tempusshop.ru/admin/panel/engine/wb/getTopMS.php' => 'admin_panel_engine_wb_getTopMS_php',
        'tempusshop.ru/admin/panel/engine/wb/analytics/topAnalytics.php' => 'panel_engine_wb_analytics_topAnalytics_php',
        'tempusshop.ru/admin/panel/engine/wb/analytics/sppAnalytics.php' => 'panel_engine_wb_analytics_sppAnalytics_php',
        'tempusshop.ru/admin/panel/engine/wb/analytics/exportAnalyticsData.php' => 'panel_engine_wb_analytics_exportAnalyticsData_php',
        'tempusshop.ru/admin/panel/engine/wb/analytics/checkSppChange.php' => 'panel_engine_wb_analytics_checkSppChange_php',
        'tempusshop.ru/admin/panel/engine/wb/analytics/getAnalyticsCsv.php' => 'panel_engine_wb_analytics_getAnalyticsCsv_php',
        'tempusshop.ru/admin/panel/engine/wb/correctDelete.php' => 'admin_panel_engine_wb_correctDelete_php',
        'tempusshop.ru/admin/panel/engine/wb/classes/control/ItemsControl.php WR' => 'wb_classes_control_ItemsControl_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/classes/control/PriceControl.php WR' => 'wb_classes_control_PriceControl_php_WR',
        'tempusshop.ru/admin/panel/engine/wb/checkFboNew.php WR' => 'panel_engine_wb_checkFboNew_php_WR',
        'tempusshop.ru/admin/panel/engine/ozon/importProducts_nosync.php IP 1' => 'ozon_importProducts_nosync_php_IP_1',
        'tempusshop.ru/admin/panel/engine/ozon/importProducts_nosync.php IP' => 'engine_ozon_importProducts_nosync_php_IP',
        'tempusshop.ru/admin/panel/engine/ozon/getFboStat.php IP' => 'panel_engine_ozon_getFboStat_php_IP',
        'tempusshop.ru/admin/panel/engine/ozon/importPrices.php IP AVTO' => 'engine_ozon_importPrices_php_IP_AVTO',
        'tempusshop.ru/admin/panel/engine/ozon/importStock_v2.php IP AVTO' => 'ozon_importStock_v2_php_IP_AVTO',
        'tempusshop.ru/admin/panel/engine/ozon/reportStock.php IP AVTO' => 'engine_ozon_reportStock_php_IP_AVTO',
        'tempusshop.ru/admin/panel/engine/ozon/clearSales.php' => 'admin_panel_engine_ozon_clearSales_php',
        'tempusshop.ru/admin/panel/engine/ozon/importSalesGroup.php IP AVTO' => 'engine_ozon_importSalesGroup_php_IP_AVTO',
        'tempusshop.ru/admin/panel/engine/ozon/importFBOGroup.php IP AVTO' => 'engine_ozon_importFBOGroup_php_IP_AVTO',
        'tempusshop.ru/admin/panel/engine/ms/getTurnover.php' => 'admin_panel_engine_ms_getTurnover_php',
        'tempusshop.ru/admin/panel/engine/ms/getTurnoverWB.php' => 'admin_panel_engine_ms_getTurnoverWB_php',
        'tempusshop.ru/admin/panel/engine/ozon/updateProps.php' => 'admin_panel_engine_ozon_updateProps_php',
        'tempusshop.ru/admin/panel/engine/ozon/control/checkCheckFBo.php' => 'panel_engine_ozon_control_checkCheckFBo_php',
        'tempusshop.ru/local/cron/nakladnie/clear.php' => 'local_cron_nakladnie_clear_php',
        'tempusshop.ru/admin/panel/engine/sites/calculateSort.php RU' => 'panel_engine_sites_calculateSort_php_RU',
        'tempusshop.ru/local/cron/offline/pricelist.php' => 'local_cron_offline_pricelist_php',
        'tempusshop.ru/local/cron/offline/pricelist_ru.php' => 'local_cron_offline_pricelist_ru_php',
        'tempusshop.ru/local/cron/marketplace/update_collection_ozon.php' => 'cron_marketplace_update_collection_ozon_php',
        'tempus.ru/local/cron/catalog/update_price_property.php' => 'cron_catalog_update_price_property_php',
        'tempusshop.ru/local/cron/rabbitmq/updater_props.php' => 'local_cron_rabbitmq_updater_props_php',
        'tempus.ru/local/cron/elasticsearch.php' => 'tempru_local_cron_elasticsearch_php',
        'tempus.ru/local/cron/elasticsearch_taps.php' => 'tempru_local_cron_elasticsearch_taps_php',
        'tempus.ru/local/cron/elasticsearch_logs.php' => 'tempru_local_cron_elasticsearch_logs_php',
        'tempusshop.ru/local/cron/infograph/libwb/WBInfograhGenerator.php' => 'local_cron_infograph_libwb_WBInfograhGenerator_php',
        'tempusshop.ru/local/cron/infograph/libozon/OZONInfograhGenerator.php' => 'local_cron_infograph_libozon_OZONInfograhGenerator_php',
        'tempusshop.ru/admin/panel/engine/common/sheetsExport/sheetsExport.php' => 'panel_engine_common_sheetsExport_sheetsExport_php',
        'tempusshop.ru/admin/panel/engine/yandex/importPromos.php' => 'admin_panel_engine_yandex_importPromos_php',
        'tempusshop.ru/admin/panel/engine/yandex/importStock.php' => 'admin_panel_engine_yandex_importStock_php',
        'tempusshop.ru/admin/panel/engine/yandex/importPrices.php' => 'admin_panel_engine_yandex_importPrices_php',
        'tempusshop.ru/admin/panel/engine/yandex/importOrders.php' => 'admin_panel_engine_yandex_importOrders_php',
        'tempusshop.ru/admin/panel/engine/yandex/analytics/getPriceReport.php' => 'panel_engine_yandex_analytics_getPriceReport_php',
        'tempusshop.ru/admin/panel/engine/yandex/analytics/getTopMS.php' => 'panel_engine_yandex_analytics_getTopMS_php',
        'tempusshop.ru/admin/panel/engine/common/adverts/AdvertManager.php wb' => 'engine_common_adverts_AdvertManager_php_wb',
        'tempusshop.ru/admin/panel/engine/common/adverts/AdvertManager.php ozon' => 'engine_common_adverts_AdvertManager_php_ozon',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/getStockReport.php' => 'engine_ozon_analytics_stocks_getStockReport_php',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/getStockReturns.php IP' => 'ozon_analytics_stocks_getStockReturns_php_IP',
        'tempusshop.ru/admin/panel/engine/ozon/analytics/stocks/getToClientStockMovement.php' => 'engine_ozon_analytics_stocks_getToClientStockMovement_php',
        'tempusshop.ru/admin/panel/engine/yandex/analytics/getOrdersReport.php' => 'panel_engine_yandex_analytics_getOrdersReport_php',
        'tempus.ru/bitrix/php_interface/include/catalog_export/cron_frame.php 12' => 'catalog_export_cron_frame_php_12',
        'tempusshop.ru/admin/panel/engine/ozon/unarchiveProducts.php IP AVTO' => 'engine_ozon_unarchiveProducts_php_IP_AVTO',
        );
    }
}
