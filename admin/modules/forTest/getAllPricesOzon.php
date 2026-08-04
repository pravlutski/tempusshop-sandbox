<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
set_time_limit(0);

class PriceOzon
{

  public function __construct()
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $result = $this->dbPanel->query("SELECT * FROM ozon_main_settings_TI");
    $rows = $this->dbPanel->fetchAll( $result );
    foreach ( $rows as $row ) {
    	$arSettings[$row['name']] = $row['value'];
    }
    $this->headers = [
      'Api-Key:' . $arSettings['key'],
			'Client-Id:' . $arSettings['client_id'],
			'Content-Type:application/json'
    ];
  }

  public function run(){
    $this->getItems();
    $this->getPriceInfo();
    $this->wrtieXls();
  }

  public function getItems()
  {
    $arSelect = Array("ID","IBLOCK_ID","PROPERTY_WBARTICLE");
    $arFilter = Array(
    	"IBLOCK_ID" => 16,
    	"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
    );

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    $this->items = [];
    while ( $row = $result->GetNext() ){
      $this->items[] = $row['PROPERTY_WBARTICLE_VALUE'];
    }
    // var_dump($this->items);
  }

  public function getPriceInfo():void
  {
    $chunks = array_chunk( $this->items, 1000 );
    $this->topGoods = [];
    foreach ( $chunks as $ch ){
      $data = [
        "filter" => [
          "offer_id" => $ch
        ],
        "limit" => 1000
      ];
      $url = "https://api-seller.ozon.ru/v4/product/info/prices";
      $res = $this->request( $url, $this->headers, json_encode($data) );
      foreach ( $res['result']['items'] as $item ) {
        $our = $item['price']['marketing_seller_price'];
        $sell = $item['price']['marketing_price'];
        // $spp = ( $our - $sell ) / $our * 100;
        $model = end( explode('_', $item['offer_id']) );

        $this->topGoods[ $item['offer_id'] ] = [
          'model' => $model,
          'our' => $our,
          'sell' =>	$sell,
        ];
      }
      unset( $our );
      unset( $sell );
      unset( $spp );

    }
  }

  public function request( $url, $headers = [], $body = '' )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    $res = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
      $error_msg = curl_error( $ch );
    }
    curl_close( $ch );

    if ( $error_msg ) {
      $this->writeLog('CUrl returned an error: ' . $error_msg);
      return false;
    }

    return json_decode( $res, true );
  }

  public function wrtieXls(){

    if (!class_exists('SpreadsheetReader')){
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
    }
    var_dump('writing');
    $xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('listOne');
    $i = 2;
    $sheet->setCellValueExplicit("A1", 'модель', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B1", 'наша цена', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C1", 'черная цена', PHPExcel_Cell_DataType::TYPE_STRING);
    foreach ( $this->topGoods as $e ){
        $sheet->setCellValueExplicit("A" . $i, $e['model'], PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B" . $i, $e['our'], PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C" . $i, $e['sell'], PHPExcel_Cell_DataType::TYPE_STRING);
        $i++;
    }

    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/export/';
    $filename = 'OZON.xlsx';
    $cardGroup['link'] = '/admin/modules/promcom/export/' . $filename;
    $objWriter->save( $dirPath . $filename );
  }

}

( new PriceOzon() )->run();

?>
