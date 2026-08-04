<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?
class PurchaseManager
{
    private $arResult = [];
    private $arStockID = [44];
    private $db;

    public function __construct()
    {
        global $DB;
        $this->DB = $DB;
    }

    public function execute()
    {
        global $USER, $APPLICATION;

        if (!$this->checkAccess($USER, $APPLICATION)) {
            return;
        }

        $start = debug_microtime_float();

        if ($this->initModules()) {
            $this->loadSuppliers();
            $this->loadTradePlatforms();
            $this->loadPurchaseItems();
            $this->processOrderIds();
            $this->loadStockItems();
            $this->loadOrders();
            $this->enrichItemsWithOrderData();
            $this->groupItemsBySupplier();

            $this->renderSummaryResults();
            $this->displayResults();

            // Ожидание (поставщик 82)
            $this->displayWaitingItems();
        } else {
            $this->displayError();
        }

        $end = debug_microtime_float();
        //prent($end - $start, 0, 1);
    }

    private function checkAccess($USER, $APPLICATION)
    {
        $arGroups = $USER->GetUserGroupArray();

        if ($USER->isAdmin() || in_array(6, $arGroups) || in_array(19, $arGroups)) {
            $this->arResult["ACCESS"] = true;
        }

        if (in_array(12, $arGroups)) {
            $APPLICATION->AuthForm("Доступ запрещен");
            return false;
        }

        return true;
    }

    private function initModules()
    {
        return CModule::IncludeModule("panel.manager")
            && CModule::IncludeModule("iblock")
            && CModule::IncludeModule("catalog");
    }

    private function loadSuppliers()
    {
        $objSupplier = new CPanelSupplier;
        $this->arResult["SUPPLIER_LIST"] = $objSupplier->getList();

        foreach ($this->arResult["SUPPLIER_LIST"] as $arSup) {
            $this->arResult["SUPPLIER_NAME"][$arSup["id"]] = $arSup["name"];
            $this->arResult["SUPPLIER_SORT"][$arSup["id"]] = $arSup["sort"];
        }
    }

    private function loadPurchaseItems()
    {
        $strSql = "SELECT * FROM ci_purchase WHERE active = 'Y'";
        $results = $this->DB->Query($strSql, false, $err_mess__LINE__);

        while ($row = $results->Fetch()) {
            $this->arResult["ITEMS"][] = $row;
        }
    }

    private function processOrderIds()
    {
        $arStampMS = [];
        $sFilter = [];

        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            if ($arItem["order_id"] > 0) {
                $ids[] = $arItem["order_id"];
            }

            $ms_data = unserialize($arItem["ms_data"]);

            if (in_array($arItem["supp_id"], array(44, 47, 103, 141))) {
                $type = "supply_transfer";
            } else {
                $type = "supply";
            }

            if ($ms_data[$type]["id"]) {
                $arStampMS[$arItem["supp_id"]]["{$ms_data[$type]["timestamp"]}"] = $ms_data[$type]["timestamp"];
                $arItem["ms_timestamp"] = (string)$ms_data[$type]["timestamp"];
                $arItem["ms_cabinet"] = $ms_data[$type]["cabinet"];
            }

            $sFilter[md5($arItem["model"] . $arItem["supp_id"])] = "(model = '" . addslashes($arItem["model"]) . "' AND supplier_id = '" . addslashes($arItem["supp_id"]) . "')";
        }
        unset($arItem);

        // Обработка штампов времени
        foreach ($arStampMS as $sId => $arTime) {
            asort($arTime);
            $c = (is_array($arTime) ? count($arTime) : 0);
            $arNumberSeq[$sId] = array_combine($arTime, range(1, $c));
        }

        $this->arResult['NUMBER_SEQ'] = $arNumberSeq ?? [];
    }

    private function loadStockItems()
    {
        if (is_array($this->arResult["ITEMS"]) && count($this->arResult["ITEMS"]) > 0) {
            $sFilter = [];
            foreach ($this->arResult["ITEMS"] as $arItem) {
                $sFilter[md5($arItem["model"] . $arItem["supp_id"])] = "(model = '" . addslashes($arItem["model"]) . "' AND supplier_id = '" . addslashes($arItem["supp_id"]) . "')";
            }

            if (count($sFilter) > 0) {
                $add_where = "((" . implode(") OR (", $sFilter) . "))";
                $strSql = "SELECT model FROM ci_price WHERE {$add_where} GROUP BY model";
                $results = $this->DB->Query($strSql, false, $err_mess__LINE__);

                while ($row = $results->Fetch()) {
                    $this->arResult["ITEMS_STOCK"][$row["model"]] = $row["model"];
                }
            }
        }
    }

    private function loadOrders()
    {
        $ids = [];
        foreach ($this->arResult["ITEMS"] as $arItem) {
            if ($arItem["order_id"] > 0) {
                $ids[] = $arItem["order_id"];
            }
        }

        if (!empty($ids)) {
            $objService = new OrderService;
            $objService->getPropOrderFlg = false;

            $arOrder = $objService->getOrderCache(array(), $arFilter = array("ID" => $ids));

			$arIDs = array_column($arOrder, "ID");
			$arTradePlatform = [];

			if (is_array($arIDs) && count($arIDs) > 0) {
				$strSql = "SELECT ORDER_ID, TRADING_PLATFORM_ID FROM b_sale_tp_order WHERE ORDER_ID IN ('" . implode("','", $arIDs) . "')";
				$results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

				while ($row = $results->Fetch()) {
					$arTradePlatform[$row["ORDER_ID"]] = $row["TRADING_PLATFORM_ID"];
				}
			}

            foreach ($arOrder as $key => &$arItem) {
				$arItem['SITE_ID'] = $arItem['LID'];
				$arItem['TRADING_PLATFORM_ID'] = $arTradePlatform[$arItem["ID"]] ?? '';
				$arItem["FAKE_TRADING_PLATFORM_NAME"] = $this->getFakeTradingName($arItem);

                $this->arResult["ALL_ORDERS"][$arItem["ID"]] = $arItem;
            }
			unset($arItem);
        }
    }

	private function getFakeTradingName($arItem)
	{
		if ($arItem['SITE_ID'] == 's1' && $arItem['STATUS_ID'] == 'TA') {
			return 'SITES_NKZ';
		}
		if ($arItem['SITE_ID'] == 's2' && $arItem['STATUS_ID'] == 'TA') {
			return 'SITES_NEMIGA';
		}
		return $this->arResult["TRADING_LIST"][$arItem['TRADING_PLATFORM_ID']];
	}

    private function loadTradePlatforms()
    {
        $strSql = "SELECT * FROM b_sale_tp";
        $results = $this->DB->Query($strSql, false, $err_mess.__LINE__);

        while ($row = $results->Fetch()) {
            $this->arResult["TRADING_LIST"][$row["ID"]] = $row["NAME"];
        }
    }

    private function enrichItemsWithOrderData()
    {
        foreach ($this->arResult["ITEMS"] as $key => &$arItem) {
            $arItem["supp_name"] = $this->arResult["SUPPLIER_NAME"][$arItem["supp_id"]];

            if (isset($this->arResult["ALL_ORDERS"][$arItem["order_id"]])) {
                $arItem["item_active"] = "N";
                $arOrder = $this->arResult["ALL_ORDERS"][$arItem["order_id"]];
                $arItem["order_status_id"] = $arOrder["STATUS_ID"];
                $arItem["order_canceled"] = $arOrder["CANCELED"];
                $arItem["order_number_id"] = $arOrder["ORDER_ID"];
                $arItem["tp_name"] = $arOrder["FAKE_TRADING_PLATFORM_NAME"] ?? '';

                $tmp = explode(".", $arItem["order_basket_id"]);
                $order_basket_id = $tmp[0];

                // если товар отредактирован и товар удалил, то ставим флаг
                foreach ($arOrder["BASKET"] as $k => $v) {
                    if ($v["ID"] == $order_basket_id) {
                        $arItem["item_active"] = "Y";
                    }
                }
            }

            $arItem["in_stock"] = isset($this->arResult["ITEMS_STOCK"][$arItem["model"]]) ? "Y" : "N";
        }
        unset($arItem);
    }

    private function groupItemsBySupplier()
    {
        $arSort = [];

        foreach ($this->arResult["ITEMS"] as $key => $arItem) {
            $arSort[$arItem["supp_id"]] = $this->arResult["SUPPLIER_SORT"][$arItem["supp_id"]];
            $this->arResult["PRICE_GROUP"][$arItem["supp_id"]][] = $arItem;
        }

        asort($arSort);

        $tmp = [];
        foreach ($arSort as $key => $val) {
            $tmp[$key] = $this->arResult["PRICE_GROUP"][$key];
        }

        $this->arResult["PRICE_GROUP"] = $tmp;

        // Суммы по группам
        foreach ($this->arResult["PRICE_GROUP"] as $key => $arItem) {
            foreach ($arItem as $k => $v) {
                $this->arResult["PRICE_GROUP_SUM"][$key] += $v["price"];
            }
        }
    }

    private function displayResults()
    {
        $cntCol = ($this->arResult["ACCESS"] === true) ? 7 : 5;
        ?>
        <?php foreach ($this->arResult["PRICE_GROUP"] as $key => $arItem): ?>
            <div class="col-sm-12">
                <?php $txt = ""; ?>
                <table class="table purchase-item" id="purchase-item-<?= $key ?>" data-id="<?= $key ?>">
                    <thead>
                        <tr>
                            <th colspan="<?= $cntCol ?>">
                                <span class="btn-clipboard-list ico-clipboard" data-id="textarea-purchaselist-<?= $key ?>" data-clipboard-target="#textarea-purchaselist-<?= $key ?>"></span>
                                <a href="/admin/ajax/purchase/get_purchase_csv.php?supp_id=<?= $key ?>" data-supp_id="<?= $key ?>" style="cursor:pointer; color: #337ab7;" class="purchase-csv"><?= $this->arResult["SUPPLIER_NAME"][$key] ?></a>
                                <span class="badge" style="margin: 0 0 0 5px;"><?= (is_array($arItem) ? count($arItem) : 0) ?></span>
                            </th>
                        </tr>
                        <?php if ($this->arResult["ACCESS"] === true): ?>
                            <tr>
                                <th colspan="3">Цена (<? echo number_format($this->arResult["PRICE_GROUP_SUM"][$key], 0, '', ' '); ?>)</th>
                                <th colspan="4">
                                    <?php if (in_array($key, $this->arStockID)): ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                    <?php elseif ($key == 103): ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply103_clear" data-id="<?= $key ?>" style="padding: 0px 3px 0 3px;font-size: 12px;margin-right:10px;">Создать приемку</button>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply_new103" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                    <?php elseif ($key == 141): // Склад импорт ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_move_141" data-id="<?= $key ?>" style="float: right;margin-right: 5px;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                        <button type="button" class="btn btn-primary " id="ms_create_docs_141" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать документы</button>
                                    <?php elseif ($key == 144): // Склад импорт 2?>
                                        <button type="button" class="btn btn-primary " id="ms_create_move_144" data-id="<?= $key ?>" style="float: right;margin-right: 5px;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                    <?php elseif ($key == 129): // Склад москва 2 ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_move_129" data-id="<?= $key ?>" style="float: right;margin-right: 5px;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать приемку</button>
                                    <?php elseif ($key == 116): ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply116_clear" data-id="<?= $key ?>" style="padding: 0px 3px 0 3px;font-size: 12px;margin-right:10px;">Создать приемку</button>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply_new116" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                    <?php elseif ($key == 135): // Никита ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_docs_135" data-id="<?= $key ?>" style="float: right;margin-right: 5px;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать приемку</button>
                                    <?php elseif ( in_array($key, [47, 128, 149]) ): ?>
                                        <button type="button" class="btn btn-primary ms-create-docs" id="ms_create_documents" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать перемещение</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary " id="ms_create_supply" data-id="<?= $key ?>" style="float: right;padding: 0px 3px 0 3px;font-size: 12px;">Создать приемку</button>
                                    <?php endif ?>
                                </th>
                            </tr>
                        <?php endif ?>
                    </thead>
                    <tbody>
                        <?php
                        $arTxt = [];
                        foreach ($arItem as $article => $arPrice):
                            $arTxt[$arPrice["model"]] += 1;
                        ?>
                            <tr class="<?php if (($arPrice["order_id"] > 0 && in_array($arPrice["order_status_id"], array("WT", "LP", "DP", "NZ", "RD", "NA", "DB", "AB", "NO", "OT", "OS", "CA", "CS"))) || $arPrice["order_canceled"] == "Y" || $arPrice["item_active"] == "N"): ?>danger<?php elseif ($arPrice["top_id"] == 0 && ($arPrice["order_id"] > 0 && !in_array($arPrice["order_status_id"], array("CO", "SE", "TA", "PO")))): ?>warning11<?php elseif ($arPrice["top_id"] > 0): ?>success<?php endif ?>" data-orderbasketid="<?= $arPrice["order_basket_id"] ?>" data-product_id="<?= $arPrice["product_id"] ?>" data-article="<?= $arPrice["model"] ?>" data-id="<?= $arPrice["id"] ?>">
                                <td><?= $arPrice["model"] ?><?php if ($this->arResult["ACCESS"] === true && $arPrice["in_stock"] == "Y"): ?><span class="delete-price" data-id="<?= $arPrice["id"] ?>">x</span><?php endif ?><?php if ($arPrice["in_stock"] == "N"): ?> *<?php endif ?></td>
                                <?php if ($this->arResult["ACCESS"] === true): ?>
                                    <td><?= number_format($arPrice["price"], 2, ',', ' ') ?></td>
                                <?php endif ?>
                                <td><?php if ($arPrice["order_id"] > 0): ?><a href="https://tempusshop.ru/bitrix/admin/sale_order_view.php?amp%3Bfilter=Y&%3Bset_filter=Y&lang=ru&ID=<?= $arPrice['order_id'] ?>" target="_blank" style="position: relative;"><span><?= $arPrice["order_number_id"] ?></span></a><?php else: ?><?= $arPrice["order_number_id"] ?><?php endif ?></td>
                                <td><?= $arPrice["tp_name"] ?></td>
                                <td><?= $arPrice["site_id"] ?></td>
                                <td><?= $this->arResult["NUMBER_SEQ"][$arPrice["supp_id"]][$arPrice["ms_timestamp"]] ?></td>
                                <?php if ($this->arResult["ACCESS"] === true): ?>
                                    <td class="right" style="width:100px;text-align: right;">
                                        <button type="button" class="btn btn-danger delete-purchase" data-id="<?= $arPrice["id"] ?>">Удалить</button>
                                    </td>
                                <?php endif ?>
                            </tr>
                        <?php endforeach ?>
                        <tr><td style="padding: 0; line-height: 0;" colspan="<?= $cntCol ?>">&nbsp;</td></tr>
                        <tr><td style="padding: 0; line-height: 0;" colspan="<?= $cntCol ?>">&nbsp;</td></tr>
                        <tr><td style="padding: 0; line-height: 0;" colspan="<?= $cntCol ?>">&nbsp;</td></tr>
                    </tbody>
                </table>
                <?php
                $txt = "";
                foreach ($arTxt as $model => $cnt) {
                    $txt .= $model . " " . $cnt . "\r\n";
                }
                ?>
                <textarea id="textarea-purchaselist-<?= $key ?>" style="position: fixed;left: -9999px;display:none;"><?= $txt ?></textarea>
            </div>
            <script>
                $(document).ready(function() {
                    $('#purchase-item-<?= $key ?> tbody').multiSelect({
                        unselectOn: '',
                        keepSelection: true
                    });
                });
            </script>
        <?php endforeach ?>

        <div class="col-sm-12 row">
            <form action="/admin/ajax/purchase/get_purchase_csv.php" style="position: relative;" method="GET">
                <select class="form-control select_w" name="site_id[]" style="height: 68px;margin: 0 0 0 16px;" id="purchase-csv-sel" multiple>
                    <option value="s1">tempusshop.ru</option>
                    <option value="s2">tempus.by</option>
                    <option value="s3">tempusshop.pl</option>
                </select>
                <select class="form-control select_w" name="currency_list"  style="top: 0px;position: absolute;margin: 0 0px 0 8px;" id="purchase-currency-list">
                    <option value="RUB">RUB</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="BYN">BYN</option>
                    <option value="PLN">PLN</option>
                </select>
                <button type="submit" class="btn btn-primary " style="margin: 0 0 0 8px;position: absolute;bottom: 4px;">Список</button>
            </form>
        </div>

        <div class="col-sm-12 row" style="margin-top: 30px;margin-bottom:0px; padding-left:40px;">
            <b>По номеру заказа</b>
        </div>

        <div class="col-sm-12 row">
            <form action="/admin/ajax/purchase/get_purchase_by_order_csv.php" style="position: relative; margin-bottom:30px; display:flex; justify-content:spaceBetween;margin-top: 0px;justify-content: space-between; gap: 30px; padding: 20px;" method="GET">
                <input class="form-control" name="order_id"></input>
                <button type="submit" class="btn btn-primary " style="">Список</button>
            </form>
        </div>
        <?php
    }

    private function displayWaitingItems()
    {
        $this->arResult["ITEMS"] = array();
        $strSql = "SELECT * FROM ci_price WHERE supplier_id = '82'";
        $results = $this->DB->Query($strSql, false, $err_mess__LINE__);

        while ($row = $results->Fetch()) {
            $this->arResult["ITEMS"][] = $row;
        }
        ?>
        <?php if (is_array($this->arResult["ITEMS"]) && count($this->arResult["ITEMS"]) > 0): ?>
            <h4>Ожидание</h4>
            <div class="col-sm-12">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 50%"><span class="btn-clipboard" style="cursor:pointer; color: #337ab7;"></span></th>
                            <th style="width: 25%">Цена</th>
                            <th style="width: 25%"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->arResult["ITEMS"] as $key => $arItem): ?>
                            <tr>
                                <td><?= $arItem["model"] ?></td>
                                <td><?= $arItem["price"] ?></td>
                                <td class="right"></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php
    }

    private function renderSummaryResults():void
    {
      $sums = [];
      $suppGroups = [ // группировка + порядок отображения
        'def' => [],
        'nds' => [],
        'wh' => [],
      ];
      $groupsSum = [ 'def' => 0, 'wh' => 0, 'nds' => 0 ];

      foreach ( $this->arResult['PRICE_GROUP'] as $key => $arItem ){
        $sums[ $key ] = [
          'name' => $this->arResult["SUPPLIER_NAME"][$key],
          'sum' => array_sum( array_column($arItem, 'price') ),
        ];
      }
      foreach ( $this->arResult["SUPPLIER_LIST"] as $supp ){
        if ( $supp['is_warehouse'] == 'Y' ){
          $suppGroups['wh'][] = $supp['id'];
          $groupsSum['wh'] += $sums[ $supp['id'] ]['sum'];
          continue;
        }
        if ( $supp['nds'] == 'Y' ){
          $suppGroups['nds'][] = $supp['id'];
          $groupsSum['nds'] += $sums[ $supp['id'] ]['sum'];
          continue;
        }
        $suppGroups['def'][] = $supp['id'];
        $groupsSum['def'] += $sums[ $supp['id'] ]['sum'];
      }


      $dict = [
        'def' => "Поставщики",
        'nds' => "Поставщики с НДС",
        'wh' => 'Склады'
      ];
      ?>
      <!-- <h3>Сводка по объемам закупки</h3> -->

      <table style="width: 100%; margin-top: 57px;" class="table table-stripped">
      <?
        foreach ( $suppGroups as $group => $supps ){
          ?>
          <tr style="height: 40px;">
            <td><b><?=$dict[$group]?> (<? echo number_format( round($groupsSum[$group]), 0, ' ', ' ' ); ?>)</b></td>
          </tr>
          <?
          foreach ( $supps as $id ):
            if ( empty($sums[$id]) ) continue;
            ?>
            <tr>
              <td><?=$sums[$id]['name']?></td>
              <td><? echo number_format( round($sums[$id]['sum']), 0, ' ', ' ' ); ?></td>
            </tr>
            <?
          endforeach;
        }
      ?>
      </table>
      <hr>
      <?
    }

    private function displayError()
    {
        ?>
        <h2 class="color"><span>Не удалось получить список моделей(</span></h2>
        <p>Произошла непредвиденная ошибка. Пожалуйста, обновите страницу и попробуйте позже</p>
        <?php
    }
}

$purchaseManager = new PurchaseManager();
$purchaseManager->execute();
?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
