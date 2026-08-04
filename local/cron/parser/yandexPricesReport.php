<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class YandexPriceReport
{
  private $authToken;
  private $campaignId;
  private $logPath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/logs/yandexPriceLog.txt';
  public $filePath;

  function __construct($campaignId)
  {
    $this->authToken = 'AQAAAAAENHYaAAdWYzVCd2tdhkeatol5KfPf9uY';
    $this->campaignId = $campaignId;
  }

  public function run()
  {
    file_put_contents($this->logPath, date('d-m-Y') . ' --- START' . PHP_EOL, FILE_APPEND);
    $reportInfo = $this->getReport( $this->campaignId );
    $reportPath = $this->checkReportAvailiabilty( $reportInfo );
    $reportData = $this->parseXls( $reportPath );
    // $reportData = $this->parseXls('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/supplier-prices-v2.xlsx');
    $this->writeXls( $reportData );
    $this->writeDB($reportData);
    CProSet::setOption("YANDEX_LAST_FILE", basename($this->filePath));
    file_put_contents($this->logPath, date('d-m-Y') . ' --- DONE' . PHP_EOL, FILE_APPEND);
    file_put_contents($this->logPath, ' ' . PHP_EOL, FILE_APPEND);
  }

  public function getReport($campaignId)
  {
    $data = [
        'campaignId' => $campaignId
    ];

    $url = 'https://api.partner.market.yandex.ru/reports/prices/generate?format=FILE';
    $ch = curl_init($url);
    curl_setopt(
    			$ch,
    			CURLOPT_HTTPHEADER,
    			array(
            "Content-Type: application/json",
    				"Authorization: Bearer " . $this->authToken
    			)
    		);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    $result = curl_exec($ch);
    curl_close($ch);
    $report = json_decode($result, 1);
    // echo $report['result']['reportId'] . '<br>';
    file_put_contents($this->logPath, date('H:i:s') . ' --- ' . print_r($result, 1) . PHP_EOL, FILE_APPEND);
    return $report;
  }

  public function checkReportAvailiabilty($report)
  {
    if ( empty($report['result']['reportId']) ){
      file_put_contents($this->logPath, date('H:i:s') . ' --- NOTHING TO CHECK' . PHP_EOL, FILE_APPEND);
      return false;
    }
    //Поскольку отчет формируется долго, используется цикл с постусловием, чтобы отправить запрос на проверку готовности отчета и если он готов, скачиваем
    do {
      $url = 'https://api.partner.market.yandex.ru/reports/info/' . $report['result']['reportId'];
      $ch = curl_init($url);
      curl_setopt(
      			$ch,
      			CURLOPT_HTTPHEADER,
      			array(
      				"Content-Type: application/json",
              "Authorization: Bearer " . $this->authToken
      			)
      		);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
      $result = curl_exec($ch);
      curl_close($ch);
      $resultDecoded = json_decode($result, 1);
      file_put_contents($this->logPath, date('H:i:s') . ' --- ' . print_r($resultDecoded['result']['status'], 1) . PHP_EOL, FILE_APPEND);
      sleep(420);

      // echo $result['result']['status'] . '<br>';

    } while ( !empty( $resultDecoded['result']['status'] ) && ($resultDecoded['result']['status'] == 'PROCESSING' || $resultDecoded['result']['status'] == 'PENDING') );

    file_put_contents($this->logPath, date('H:i:s') . ' --- ' . print_r($result, 1) . PHP_EOL, FILE_APPEND);
    if ( !empty($resultDecoded['result']['file']) ){
      $url = $resultDecoded['result']['file'];
      $path = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
      $filename = 'supplier-price-api.xlsx';
      file_put_contents($path . $filename, file_get_contents($url));
    }
    return $path . $filename;
  }

  public function parseXls($filePath)
  {
    if ( $filePath == false ){
      file_put_contents($this->logPath, date('H:i:s') . ' --- NO DATA TO PARSE' . PHP_EOL, FILE_APPEND);
      return false;
    }
    file_put_contents($this->logPath, date('H:i:s') . ' --- START PARSING YANDEX REPORT' . PHP_EOL, FILE_APPEND);
    if (!class_exists('SpreadsheetReader')){
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
    }
    $xls = PHPExcel_IOFactory::load($filePath);
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $ar = array();
    foreach ($sheet->toArray() as $key => $row) {
      if ( $key < 6 && !preg_match('/[0-9]+/', $row[0]) ) continue; //Если строка не похожа на SKU/bitrix_id, пропускаем итерацию
      if ( empty( $row[4] ) ) continue; //Если столбец "Ваша цена" пустой, пропускаем итерацию
      if ( preg_match('/^TEMPUS/', $row[16]) || empty($row[16]) ) continue; //Если столбец "Магазин с лучшей ценой" - это темпус, пропускаем итерацию
      // if ( ($row[4] - $row[17]) / $row[4] * 100 > 20 ){
      //   $price = '';
      // }else{
      //   $price = empty($row[17]) ? $row[4] : $row[17] * 0.99; //Присваем цену на 1% ниже, чем у конкурента
      // }
      $ar[] = [
        'sku' => $row[0],
        'article' => end( explode( ' ', $row[1] ) ),
        'ourPrice' => $row[4],
        'bestSeller' => $row[16],
        'newPrice' => round( $row[17], 2 )
      ];
    }
    file_put_contents($this->logPath, date('H:i:s') . ' --- PARSING YANDEX REPORT IS DONE' . PHP_EOL, FILE_APPEND);
    // $this->writeDB($ar);
    return $ar;
  }

  public function writeXls($data)
  {
    if ( $data == false ){
      file_put_contents($this->logPath, date('H:i:s') . ' --- NO DATA TO WRITE XLS' . PHP_EOL, FILE_APPEND);
      return false;
    }
    file_put_contents($this->logPath, date('H:i:s') . ' --- START WRITING NEW XLSX' . PHP_EOL, FILE_APPEND);
    if (!class_exists('SpreadsheetReader')){
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
      require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
    }
    $xls = new PHPExcel();
    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $sheet->setTitle('listOne');

    $sheet->setCellValueExplicit("A1", 'SKU', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("B1", 'Товар на Маркете', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C1", 'Ваша цена на витрине, ₽', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("D1", 'Магазин с лучшей ценой на Маркете', PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("E1", 'Лучшая цена на маркете, ₽', PHPExcel_Cell_DataType::TYPE_STRING);

    foreach($data as $key => $row){
      $i = $key + 2;
      $sheet->setCellValueExplicit("A" . $i, $row['sku'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("B" . $i, $row['article'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("C" . $i, $row['ourPrice'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("D" . $i, $row['bestSeller'], PHPExcel_Cell_DataType::TYPE_STRING);
      $sheet->setCellValueExplicit("E" . $i, $row['newPrice'], PHPExcel_Cell_DataType::TYPE_STRING);
    }
    $objWriter = new PHPExcel_Writer_Excel2007($xls);
    $dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/';
    $filename = 'competetive_price.xlsx';
    $objWriter->save( $dirPath . $filename );
    file_put_contents($this->logPath, date('H:i:s') . ' --- WRITING NEW XLSX IS DONE' . PHP_EOL, FILE_APPEND);
    $this->filePath = $dirPath . $filename;
  }

  private function writeDB($data)
  {
    if ( empty($data) || !is_array($data) ){
      file_put_contents($this->logPath, date('H:i:s') . ' --- NO DATA TO PUT IN DB' . PHP_EOL, FILE_APPEND);
      return false;
    }
    file_put_contents($this->logPath, date('H:i:s') . ' --- START WRITING IN DB' . PHP_EOL, FILE_APPEND);
    global $DB;
    $strSql = "TRUNCATE TABLE ci_yandex_price";
    $DB->Query($strSql, false, $err_mess.__LINE__);
    foreach ($data as $key => $offer) {
      $strSql = "INSERT INTO ci_yandex_price (name, bitrix_id, minPrice, minPrice2, minPrice3, type_price) VALUES ('{$offer['article']}', '{$offer['sku']}', 0, '{$offer['newPrice']}', 0, 'PARTNER_FILE')";
      $DB->Query($strSql, false, $err_mess.__LINE__);
    }
    file_put_contents($this->logPath, date('H:i:s') . ' --- WRITING IN DB IS DONE' . PHP_EOL, FILE_APPEND);
  }
}

$objYaprice = new YandexPriceReport(22194883);
$objYaprice -> run();


 ?>
