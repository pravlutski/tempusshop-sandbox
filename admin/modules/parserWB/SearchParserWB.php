<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/php-docs-samples/bigquery/api/vendor/autoload.php';
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Application,
	Bitrix\Main\Loader,
	Google\Cloud\BigQuery\BigQueryClient,
	Google\Cloud\Core\ExponentialBackoff,
	Google\Cloud\Core\Exception\NotFoundException;

CModule::IncludeModule('maxyss.wb');

class SearchParserWB
{
  private $dataWB;
  private $sortedData;
  private $arSettings;
  private $logPath;

  function __construct()
  {
    $this->arSettings = [
      'keywords' => ['часы мужские casio', 'часы casio', 'casio', 'мужские кварцевые часы', 'casio мужские наручные часы'],
			// 'keywords' => ['часы casio'],
      'maxPage' => 3,
      'baseUrl' => 'https://search.wb.ru/exactmatch/ru/common/v5/search'
    ];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/parserWB/logs/SearchParserWB.txt';
		$this->auth = CMaxyssWb::settings_wb('WR')['AUTHORIZATION'];
		$this->headers = [
      "Content-Type: application/json",
      "Authorization: {$this->auth}"
    ];
  }

  public function run()
  {
    $this->getDataWB();
    $this->sortDataWB();
		$this->writeDB();
    // var_dump($this->sortedData);
		// file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/parserWB/parser.txt', print_r($this->sortedData, 1));
  }

	private function getAdvItems()
	{
		$url = 'https://advert-api.wb.ru/adv/v1/promotion/count';
		$result = $this->curl($url);
		$campaings = json_decode($result, true);
		$campaings = $campaings["adverts"];
		$advIDs = [];
		foreach ($campaings as $elem){
		  if ( $elem["status"] == 9 && $elem["type"] == 8 ){ //Берем только кампании со статусом "Идут показы"
		    foreach ($elem["advert_list"] as $value) {
		      $advIDs[] = $value["advertId"];
		    }
		  }
		}

		// $advChunksTmp = array_chunk($advIDs, 100);
    $data = [];
    foreach ( $advIDs as $value ){
      $data[] = ['id' => $value];
    }
    $advChunks = array_chunk($data, 100, true);
		$advNmids = [];
		foreach ($advChunks as $chunk) {
			// $url = 'https://advert-api.wb.ru/adv/v1/promotion/adverts';
      $url = 'https://advert-api.wb.ru/adv/v2/fullstats';
			$resultCurl = $this->curl($url, $chunk);
			$result = json_decode($resultCurl, 1);
			// var_dump($result);
      // die;
			if ( is_array($result) && empty($result['message']) && empty($result['error']) ){
				// $advNmids = array_merge($advNmids, $result['autoParams']['nms']);
        foreach ($result as $advId) {
          foreach ($advId['days'][0]['apps'] as $app) {
            foreach ($app['nm'] as $value) {
              if ( !in_array($value, $advNmids) ){
                $advNmids[] = $value['nmId'];
              }
            }
          }
        }
			}else{
				$this->writeLog('Не удалось получить nmid для кампании ' . $resultCurl);
			}
			sleep(1);
		}
    // var_dump( count($advNmids) );
		return $advNmids;
	}

  public function getDataWB()
  {
    foreach ($this->arSettings['keywords'] as $kword) {
      for ($i = 1; $i <= $this->arSettings['maxPage']; $i++) {
        $arQuery = [
          'ab_testing' => 'false',
          'appType' => '1',
          'curr' => 'rub',
          'dest' => '-1257786',
          'query' => $kword,
          'resultset' => 'catalog',
          'sort' => 'popular',
          'spp' => '30',
          'suppressSpellcheck' => 'false',
          // 'page' => $i
        ];
				if ($i != 1){
					$arQuery['page'] = $i;
				}
        $query = http_build_query($arQuery);
        $url = $this->arSettings['baseUrl'] . '?' . $query;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
        $resCurl = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($resCurl,true);
        $this->dataWB[$kword][$i] = $result['data']['products'];
				$this->writeLog('Я был на странице ' . $i);
        sleep(2);
      }
    }
		if ( !empty($this->dataWB) ){
			$this->writeLog('Парсер получил данные');
		}else{
			$this->writeLog('Парсер не получил данные');
		}
  }

  public function sortDataWB()
  {
		if ( empty($this->dataWB) ){
			return false;
		}
		$advNmids = $this->getAdvItems();
		// var_dump($advNmids);
    foreach($this->dataWB as $keyword => $arPage){
      foreach ($arPage as $page => $products) {
        foreach($products as $position => $card){
          if ($card['supplier'] == 'TEMPUS - Наручные часы' && $card['subjectId'] == 60){
						$cardType = 0;
						if (!empty($card['panelPromoId'])){
							$cardType = 2;
						}
						if ( !empty($card['log']) || in_array( intval($card['id']), $advNmids ) ){
							$cardType = 1;
						}
            $this->sortedData[] = [
              'parseDate' => date('Y-m-d'),
              'keyword' => strval($keyword),
              'page' => intval($page),
							// 'supplier' => $card['supplier'],
              'position' => intval($position + 1),
							'globalPosition' => intval($position + 1 + ($page - 1) * 100),
              'nmid' => intval($card['id']),
              'name' => strval($card['name']),
              'cardType' => intval($cardType)
            ];
          }
        }
      }
    }
    if ( !empty($this->sortedData) ){
      $this->writeLog('Получены позиции Темпуса: ' . count($this->sortedData));
    }else{
      $this->writeLog('Позиций на заданных страницах нет или при получении данных возникла ошибка');
    }
  }

	private function writeDB()
	{
		if ( empty($this->sortedData) ){
			$this->writeLog('Запись в БД произведена не будет');
			return false;
		}
		global $DB;
		$dataChunks = array_chunk($this->sortedData, 100);
		foreach ($dataChunks as $chunk){
			$this->fuckYouBitrixORM("parser_data_wb", $chunk);
			sleep(1);
		}
	}

	public function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

	private function curl($url, $data = false)
	{
		$ch = curl_init($url);
		curl_setopt($ch,CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ( $data != false ){
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	public function fuckYouBitrixORM($tableName , $arrayData)
	{
		global $DB;
	  $cardSample = $arrayData[0];
	  $fields = [];
	  foreach ($cardSample as $key => $value) {
	    $fields[] = $key;
	  }
	  if (empty($fields) || count($fields) < 2) return false;
	  $strSql = "INSERT INTO {$tableName} " . '(';

		$i = 0;
	  foreach ($fields as $fname) {
	    $strSql .= (count($fields) - 1 != $i) ? "{$fname}," : $fname;
			$i++;
	  }
	  $strSql .= ') VALUES ';
	  $c = 0;
	  foreach ($arrayData as $card){
	    $strSql .= '(';
			$k = 0;
	    foreach ($card as $field) {
	      $strSql .= (count($card) - 1 != $k) ? "'{$field}'," : "'{$field}'";
				$k++;
	    }
	    $strSql .= ( count($arrayData) - 1 != $c ) ? '),' : ')';
	    $c++;
	  }
		// var_dump($strSql);
	  $DB->Query($strSql, false, $err_mess.__LINE__);
	}

}

$objSearch = new SearchParserWB();
$objSearch->run();

 ?>
