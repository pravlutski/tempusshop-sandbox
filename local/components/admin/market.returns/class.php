<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class ReturnsMarketplaceComponent extends CBitrixComponent
{
    public function executeComponent()
    {
		global $DB;
		$this->db = $DB;
		$this->settings = unserialize(CProSet::getOption("SETTINGS_UTILS_MARKET_RETURN"));
		$this->arResult['SALES_CHANNELS'] = $this->getSalesChannels();
		$this->arResult['WAREHOUSES'] = $this->getWarehouses();
        $this->includeComponentTemplate();
    }
    
    public function onPrepareComponentParams($arParams)
    {
        return $arParams;
    }

    protected function findProductByBarcode($barcode)
    {
        return false;
    }

    protected function checkArticleExists($article)
    {
        return false;
    }

    protected function findOrderByNumber($orderNumber)
    {
        return false;
    }

    protected function findLastShipment($article, $salesChannel)
    {
        return false;
    }

    protected function getSalesChannels()
    {
		$arSalesChannel = [];
		$strSql = "SELECT * FROM ci_ms_saleschannel WHERE TYPE = 'MARKETPLACE' ORDER BY NAME";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($this->settings["salesChannels"][$row['MS_ID']])
				$arSalesChannel[] = $row;
		}
        return $arSalesChannel;
    }

    protected function getWarehouses()
    {
        // TODO: из ci_ms_store
        return [
            '51538bd5-6cf3-11ef-0a80-10ba001db77c' => 'Дубровка 2',
            '093c792f-0ae4-11ea-0a80-0256000b6f8f' => 'Дубровка Авито',
            '92f817c0-303e-11ed-0a80-09cb0025b1e7' => 'Дубровка Списать',
            '270883fd-0ae4-11ea-0a80-01f4000b8d66' => 'Склад Ремонт',
            '6f6d2169-180c-11ea-0a80-00b30004eaef' => 'Минск',
            'c4823547-40dd-11ea-0a80-05ac000ee149' => 'Немига Ремонт',
        ];

    }

    protected function createMoyskladReturn($shipmentData, $warehouseId)
    {
        return '544644'; // Пример номера возврата
    }

    protected function addLogEntry($returnNumber, $action)
    {
        return true;
    }

    public function processAjax()
    {
    }
}