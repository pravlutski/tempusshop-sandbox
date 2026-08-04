<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

class fboTestCheck
{
  private $fromMS;
  private $answer;
  private $stockFbo;
  private $items;

  public function getItemsWB(){
    $base_url = WB_BASE_URL;
    $path = "/api/v1/supplier/stocks";

    //$data_string = array('dateFrom' => date("Y-m-d\TH:i:s"));
    $data_string = array('dateFrom' => '2020-01-01');
    $author = CMaxyssWb::get_setting_wb("AUTHORIZATION", "WR");
    $api = new RestClient([
      'base_url' => 'https://statistics-api.wildberries.ru',
      'curl_options' => array(
          CURLOPT_POST => true,
          CURLOPT_SSL_VERIFYPEER => false,
          CURLOPT_SSL_VERIFYHOST => false,
          CURLOPT_RETURNTRANSFER => TRUE,
          CURLOPT_HEADER => TRUE,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
              'Content-Type: application/json',
              'Authorization: ' . $author,
          )
      )
  ]);
  $path = '/api/v1/supplier/stocks?dateFrom=2020-01-01';
  $str_result = $api->post($path, []);
  //print_r(json_decode($str_result->response,true));
  $arResult = json_decode($str_result->response,true);
  foreach ($arResult as $key => $value) {
    if (isset($this->stockFbo[$value['supplierArticle']])) {
      $this->stockFbo[$value['supplierArticle']] =  intval($this->stockFbo[$value['supplierArticle']]) + intval($value['quantity']);
    } else {
      $this->stockFbo[$value['supplierArticle']] =  intval($value['quantity']);
    }
  }
  // print_r($this->stockFbo);
  //print_r($arResult);
}

public function getItems(){
    $arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_WBPRICE","PROPERTY_WBARTICLE2","PROPERTY_TYPEOFSKLAD");
    $arFilter = Array(
      "IBLOCK_ID" => CProSet::IB_CATALOG,
      "!PROPERTY_WBARTICLE2" => false,
      //"ID" => 5045,
      //"SECTION_ID" => 558
      //"ID" => 178901
    );
    //$arFilter["!ID"] = 14124;
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){
      if (isset($this->stockFbo[$el["PROPERTY_WBARTICLE2_VALUE"]])) {
        if (empty($el['PROPERTY_WBARTICLE2_VALUE']) or $el['PROPERTY_WBARTICLE2_VALUE'] == '') {
            $this->arLog['GET_ITEMS']['ERRORS']['NO_ARTICLE'][] = $el['ID'];
        }	else if (empty($el['PROPERTY_WBPRICE_VALUE']) or $el['PROPERTY_WBPRICE_VALUE'] == 0) {
            $this->arLog['GET_ITEMS']['ERRORS']['NO_PRICE'][] = $el['ID'];
        } else {
          $arSection = getSectionsElement($el["ID"]);
          // if ($arSection[1]['ID'] == '558') {
          $this->items[$el["PROPERTY_WBARTICLE2_VALUE"]] = [
            "ID" => $el["ID"],
            "ARTICLE" => $el['PROPERTY_CML2_ARTICLE_VALUE'],
            "WB_ARTICLE" => $el["PROPERTY_WBARTICLE2_VALUE"],
            "PRICE" => $el["PROPERTY_WBPRICE_VALUE"],
          ];
        }
      }
    }
    //print_r(count($this->items));
  }

  public function GetTurnoverByInterval()	{
    $this->fromMS = array();
    $ms = new MoyskladAPI('s1');
    for ($i = 3; $i < 9; $i = $i + 3){
      $msItems = $ms->getTurnover($i);
      foreach ($msItems as $key => $value) {
        if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0') and (empty($this->fromMS[$value['assortment']['article']]))) {
          $this->fromMS[$value['assortment']['article']] = ($value['income']['sum'] / 100) / $value['income']['quantity'];
        }
      }
      sleep(1);
    }
  }

  public function GetTurnover()	{
    $this->fromMS = array();
    $ms = new MoyskladAPI('s1');
    $msItems = $ms->getTurnover(3);
    foreach ($msItems as $key => $value) {
      if (!empty($value['assortment']['article']) and ($value['income']['quantity'] != '0')) {
        $this->fromMS[$value['assortment']['article']] = ($value['income']['sum'] / 100) / $value['income']['quantity'];
      }
    }
  }

  public function checkIfFBO(){
    if (isset($this->fromMS[$this->items[$key]['ARTICLE']])) {
      $this->answer[$this->items[$key]['ARTICLE']] = 1;
    }
  }

  function run() {
    $this->getItems();
    // $this->getTurnover();
    $this->GetTurnoverByInterval();
    $this->checkIfFBO();
    // var_dump( count($this->answer) );
    var_dump( count($this->fromMS) );
  }
}

(new fboTestCheck())->run();

 ?>
