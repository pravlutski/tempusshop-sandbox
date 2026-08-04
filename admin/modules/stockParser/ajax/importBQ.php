<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/php-docs-samples/bigquery/api/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Bitrix\Main\Application,
	Bitrix\Main\Loader,
	Google\Cloud\BigQuery\BigQueryClient,
	Google\Cloud\Core\ExponentialBackoff,
	Google\Cloud\Core\Exception\NotFoundException;

if (!class_exists('SpreadsheetReader')){
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
  require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
}

if ($_FILES['supplier']['error'] === UPLOAD_ERR_OK) {
    $filename = $_FILES['supplier']['tmp_name'];
  }
  else{
    die('Ошибка загрузки файла');
  }
  clearLog();

  $xls = PHPExcel_IOFactory::load($filename);
  $xls->setActiveSheetIndex(0);
  $sheet = $xls->getActiveSheet();
  $arData = array();
  $date = '';
  $agent = '';
  $allAgents = [];
  foreach ($sheet->toArray() as $key => $row) {
    if ($key <= 1) continue;
    if ($key == 2) {
      $date = explode(' ', $row[3])[0];
      $date = str_replace('/', '-', $date);
      $date = date('Y-m-d',strtotime($date));
    }
    if ( !empty($row[1]) ){
      // $agent = str_replace('"','',$row[1]);
			$agent = $row[1];
    }
    if ( !empty($row[3]) && $row[14] != 0 && $row[3] != 'Наименование'){
      $allAgents[] = $agent;
      $arData[] = [
        'data' => [
          'agent' => $agent,
          'type' => 'stock',
          'art' => $row[3],
          'data' => $date,
          'quantity' => (int)$row[14],
          'netCost' => intval(((int)$row[16] / (int)$row[14]) * 100),
          'saleCost' => null
        ]
      ];
    }
  }

  if (!empty($arData)){
      ?>
      <p>Извлчено строк <?echo count($arData);?> по <?echo count(array_unique($allAgents));?> комиссионерам</p>
      <?php
    }else{
      ?>
      <p>Не удалось получить данные из отчета</p>
      <?php
    }

$bqConfig = [
	"keyFilePath" => "/home/bitrix/tempus_gbq/credentials/lucky-kayak-385510-f8d3ebf315cb.json",
];
$bigQuery = new BigQueryClient($bqConfig);
$bqDataset = $bigQuery->dataset('TEST');
$bqTable = $bqDataset->table('GoodsReAL');

if ( !is_array($arData) || $arData == false ){
  $writeLog('ОШИБКА! $arData пустой');
  return false;
}
$queryJobConfig = $bigQuery->query(
  "DELETE FROM `lucky-kayak-385510.TEST.GoodsReAL` WHERE type = 'stock'"
);
$queryResults = $bigQuery->runQuery($queryJobConfig);

if($queryResults->isComplete()){
	writeLog('Строки удалены');
}else{
  writeLog('Ошибка удаления строк');
}

$arData = array_chunk($arData, 200);

foreach ($arData as $key => $chunk) {
	$insertResponse = $bqTable->insertRows($chunk);

	if ( !$insertResponse->isSuccessful() ){
		writeLog('Ошибка записи');
		foreach ($insertResponse->failedRows() as $row) {
			writeLog( print_r($row, 1) );
		}
		?>
		<p>Ошибка в строках: <?echo count( $insertResponse->failedRows() );?></p>
		<?php
	}else{
		writeLog('Импорт пакета ' . $key . ' успешен');
		?>
		<p>Импорт пакета <?php echo $key;?> завершен успешно</p>
		<?php
	}
}


function writeLog($message)
{
  $logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/stockParser/logs/importStockBQ.txt';
  file_put_contents($logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
}
function clearLog()
{
  $logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/stockParser/logs/importStockBQ.txt';
  file_put_contents($logPath, '');
}

 ?>
