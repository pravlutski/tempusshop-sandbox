<?php
  $_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
  $DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

  define("NO_KEEP_STATISTIC", true);
  define("NOT_CHECK_PERMISSIONS", true);

  require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
  require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/CronWorkerGuard.php';
  if (!CronWorkerGuard::startFromArgv()) {
  	exit;
  }

  use PhpOffice\PhpSpreadsheet\Spreadsheet;
  use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

  class CalculateSort
  {
      private $dbPanel;

      private $itemsMS = [];
      private $itemsTMP = [];
      private $itemsBX = [];
      private $items_cp = [];
      private $itemsSort = [];
      private $itemsTail = [];

      private $sections = [];
      private $sortList = [];
      private $brands = [];

      private $site_id;
      private $property;
      private $table;
      private $filter;

      private $lastIndex;

      public function __construct( $cabinet = "RU" )
      {
        if ( !in_array($cabinet,["RU", "BY"]) ) die('INVALID SITE ID');
        CModule::IncludeModule('panel.manager');

        $this->cabinet = $cabinet;
        $this->dbPanel = new DBPanel;

        $this->module = 'calc_sort_ru';

        if ( $cabinet == "RU" ){
          $this->site_id = 's1';
          $this->property = 'SORT';
          $this->table = 'sites_sort_list_ru';
          $this->filter = 'active_ru';
          $this->availability = 512;
        } else {
          $this->site_id = 's2';
          $this->property = 'SORT_BY';
          $this->table = 'sites_sort_list_by';
          $this->filter = 'active_by';
          $this->availability = [492,493];
        }
        $this->triggers = new TsTriggers;
      }

      public function run()
      {
        $arStat = [
    			'status' => 'PROCESS',
    			'status_text' => 'Запуск скрипта',
    			'percent' => 0,
    			'time_start' => date('Y.m.d G:i:s')
    		];
    		$this->updateStatus($this->module, $arStat);
        $this->updateStatus($this->module, ['status_text' => 'Получение настроек', 'percent' => '10']);

        $this->getBrands();
        $this->getSections();
        $this->getSortList();
        $this->getCiPriceData();

        $this->updateStatus($this->module, ['status_text' => 'Получение товаров', 'percent' => '30']);
        $this->getItemsMS();
        $this->getItemsBX();
        $this->updateStatus($this->module, ['status_text' => 'Расчет позиций', 'percent' => '50']);
        $this->sortByList();
        $this->sortTailItems();
        $this->exportTable();

        $this->updateStatus($this->module, ['status_text' => 'Обновление свойства', 'percent' => '70']);

        $this->setSortValues();

        $arStat = [
  				'status' => 'COMPLETED',
  				'status_text' => 'Завершено',
  				'percent' => '100',
  				'time_end' => date('Y.m.d G:i:s')
  			];
  			$this->updateStatus($this->module, $arStat);
      }

      private function getCiPriceData(){
        global $DB;
        $strSql = "SELECT model FROM ci_price WHERE (active_ru ='Y' OR active_by = 'Y') AND count > 0";
        $res = $DB->Query( $strSql, false, $err_mess.__LINE__ );
        while ( $row = $res->Fetch() ){
          $this->items_cp[] = $row['model'];
        }
      }

      public function getItemsMSLEG()
      {
        $ms = new MoySkladAPI( $this->site_id );
        $arFilter = [
          'momentFrom' => date('Y-m-d' , strtotime('- 6 months')),
          'momentTo' => date('Y-m-d')
        ];
        $ms->getListProfitByAgent( 0, false, $arFilter );
        $profit = $ms->MSPosition;
        usort($profit, function($a, $b) {
          return $b['SELLSUM'] <=> $a['SELLSUM'];
        });
        print_r( 'itemsMS (raw) -- ' . count($profit) . "\n" );
        foreach ( $profit as $arVal ){
          if ( in_array($arVal['ARTICLE'], $this->items_cp) ){
            $this->itemsSort[ $arVal['ARTICLE'] ] = $arVal['SELLSUM'];
          }
        }

      }

      public function getItemsMS()
      {
        $tableRU = $this->dbPanel->select( ['*'], 'ms_profit_ru_12' )->make();
        $tableBY = $this->dbPanel->select( ['*'], 'ms_profit_by_12' )->make();
        foreach ( $tableRU as $item ){
          $profit[ $item['model'] ] = [
            'model' => $item['model'],
            'quantity' => $item['quantity'],
            'index' => 'ru'
          ];
        }
        foreach ( $tableBY as $item ){
          if ( isset($profit[ $item['model'] ]) ) continue;

          $profit[ $item['model'] ] = [
            'model' => $item['model'],
            'quantity' => $item['quantity'],
            'index' => 'by'
          ];
        }
        usort($profit, function($a, $b) {
          return $b['quantity'] <=> $a['quantity'];
        });
        print_r( 'itemsMS (raw) -- ' . count($profit) . "\n" );
        foreach ( $profit as $arVal ){
          if ( in_array($arVal['model'], $this->items_cp) ){
            $this->itemsSort[ $arVal['model'] ] = $arVal['quantity'];
          }
        }
        print_r( 'itemsMS (filtered) -- ' . count($this->itemsSort) . "\n" );
        // var_dump($this->itemsSort);
        // file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/sort1.txt", print_r($this->itemsSort, 1) );
        // die;
      }

      public function getItemsBX()
      {
        if ( empty($this->itemsSort) ){
          die('Нет моделей от МС');
        }
        $arModel = array_keys($this->itemsSort);


        $arSelect = [
          'ID',
          'IBLOCK_ID',
          'IBLOCK_SECTION_ID',
          'PROPERTY_CML2_ARTICLE',
          'PROPERTY_BRAND',
        ];

        $arFilter = [
          "IBLOCK_ID" => 16,
          "PROPERTY_CML2_ARTICLE" => $arModel
        ];

        $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
        $this->itemsBX = [];

        print_r( 'itemsBX (raw) -- ' .$result->SelectedRowsCount() . "\n" );

        while ( $row = $result->GetNextElement() ){

          $item = $row->GetFields();
          $sectionName = $this->sections[ $item['IBLOCK_SECTION_ID'] ];
          $brandName = $this->brands[ $item['PROPERTY_BRAND_VALUE'] ];
          $this->itemsTMP[ $item['PROPERTY_CML2_ARTICLE_VALUE'] ] = [
            'id' => $item['ID'],
            'brand' => $brandName,
            'section' => $sectionName,
            'model' => $item['PROPERTY_CML2_ARTICLE_VALUE'],
          ];
        }

        foreach ( $this->itemsSort as $model => $quan ){
          if ( isset($this->itemsTMP[$model]) ){
            // $tmp[] = $this->itemsTMP[$model];
            $tmp[] = [
              'id' =>$this->itemsTMP[$model]['id'],
              'brand' => $this->itemsTMP[$model]['brand'],
              'section' => $this->itemsTMP[$model]['section'],
              'model' => $this->itemsTMP[$model]['model'],
              'sells' => $quan
            ];
          }
        }

        // file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/sort2.txt", print_r($tmp, 1) );
        print_r( 'BX sorted (raw) -- ' .$result->SelectedRowsCount() . "\n" );

        $this->divideByAllGroups( $tmp );

        print_r( 'BX sorted (groups) -- ' .count($this->itemsBX) . "\n" );
        unset($tmp);
      }

      public function sortByListLEg()
      {
        $counter = array_sum(array_map( 'count', $this->itemsBX ));
        print_r( 'counter -- ' . $counter . "\n");

        $flatArray = [];
        $index = 1;
        // Присваиваем товарам индексы горизонтально
        for ( $i = 0; $i < $counter; $i++ ){
          $k = 0;
          foreach ( $this->itemsBX as $group => $elem ) {
            // Пропускаем пустые группы
            if ( empty($elem) ) continue;
            // Выносим группу в отдельную переменную, сбрасывая внутренние индексы э
            $elemP = array_values($elem);
            if ( isset($flatArray[ $elemP[$k]['model'] ]) ) continue;
            $flatArray[ $elemP[$k]['model'] ] = [
              'id' => $elemP[$k]['id'],
              'brand' => $elemP[$k]['brand'],
              'section' => $elemP[$k]['section'],
              'index' => $index
            ];
            unset( $this->itemsBX[$group][array_key_first($elem)] );
            unset( $elemP );
            $index++;
          }
        }
        $this->flatArray = $flatArray;
      }

      public function sortByList()
      {
          $flatArray = [];
          $index = 1;

          // 1. Создаем копию для работы, чтобы не модифицировать исходный массив
          $workGroups = $this->itemsBX;

          // 2. Продолжаем, пока есть товары в группах
          while (true) {
              $hasItems = false;

              // 3. Проходим по группам в строгом порядке из $this->sortList
              foreach ($this->sortList as $groupName) {
                  if ( !isset($workGroups[$groupName]) ) continue;

                  // 4. Берем первый элемент группы
                  $item = reset($workGroups[$groupName]);
                  if ($item === false) continue;

                  $flatArray[$item['model']] = [
                      'id' => $item['id'],
                      'brand' => $item['brand'],
                      'section' => $item['section'],
                      'index' => $index++
                  ];

                  // 5. Сохраняем последний установленный индекс для дальнейшей работы с "хвостом"
                  $this->lastIndex = $flatArray[ $item['model'] ]['index'];

                  // 6. Удаляем обработанный элемент
                  array_shift($workGroups[$groupName]);
                  $hasItems = true;
              }

              // 6. Прекращаем, если больше нет товаров
              if (!$hasItems) break;
          }

          $this->flatArray = $flatArray;
          file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/sort1.txt", print_r($this->flatArray, 1) );
      }

      private function divideByAllGroups( array $array )
      {
        // Сначала сортируем все товары по убыванию продаж
        usort($array, function($a, $b) {
            return $b['sells'] <=> $a['sells'];
        });

        foreach ( $array as $arModel ){

          $isBrandIn = in_array( $arModel['brand'], $this->sortList );
          $isSectionIn = in_array( $arModel['section'], $this->sortList );

          if ( $isBrandIn || $isSectionIn ){
            $key = $isSectionIn ? 'section' : 'brand';
            $this->itemsBX[ $arModel[$key] ][] = [
              'id' => $arModel['id'],
              'brand' => $arModel['brand'],
              'section' => $arModel['section'],
              'model' => $arModel['model'],
              'sells' => $arModel['sells'],
            ];
            continue;
          }

          $this->itemsTail[] = [
            'id' => $arModel['id'],
            'brand' => $arModel['brand'],
            'section' => $arModel['section'],
            'model' => $arModel['model'],
            'sells' => $arModel['sells'],
          ];

        }

        // Делаем каждое название группы уникальным, добавляя индекс
        foreach ($this->sortList as $brand) {
          if (isset($counts[$brand])) {
              $counts[$brand]++;
              $indexedBrands[] = $brand . $counts[$brand];
          } else {
              $counts[$brand] = 1;  // Первый раз встречаем бренд
              $indexedBrands[] = $brand;
          }
        }

        // Сортируем товары по группам равными частями, если названия групп в списке повторяются
        foreach ( $counts as $group => $rep){
          if ( $rep < 1) continue;
          if ( empty($this->itemsBX[$group]) ) continue;
          $subGroupSize = ceil( count($this->itemsBX[$group]) / $rep );
          $arGroup = array_chunk( $this->itemsBX[$group], $subGroupSize );
          for ( $i = 0; $i <= $rep; $i++ ){
            if ( $i == 0 ){
              $tmp[$group] = $arGroup[$i];
            }else{
              $tmp[$group . $i + 1] = $arGroup[$i];
            }
          }
        }
        $sorted = [];
        foreach ( $indexedBrands as $groupName ){
          $sorted[$groupName] = $tmp[$groupName] ?? [];
        }

        $this->itemsBX = $sorted;
        $this->sortList = array_keys( $sorted );
        // file_put_contents( "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/sort3.txt", print_r($this->itemsBX, 1) );
      }

      private function sortTailItems():void
      {
        // На всякий еще раз сортируем все товары по убыванию продаж
        usort($this->itemsTail, function($a, $b) {
          return $b['sells'] <=> $a['sells'];
        });

        $flatArray = [];
        $index = $this->lastIndex + 1;
        var_dump($this->lastIndex);
        foreach ( $this->itemsTail as $item ){
          if( isset($this->flatArray[$item['model']]) ){
            var_dump('How is it Possible?');
            continue;
          }
          $this->flatArray[ $item['model'] ] = [
              'id' => $item['id'],
              'brand' => $item['brand'],
              'section' => $item['section'],
              'index' => $index++
          ];
        }

      }

      private function setSortValues()
      {
        if ( empty($this->flatArray) ) die("no data to set");
        // var_dump( $this->flatArray );
        // die;
        // Получаем все товары инфоблока
        $arFilter = [
          'IBLOCK_ID' => 16,
          // 'PROPERTY_AVAILABILITY_'.$this->cabinet => $this->availability
          '!SORT' => 9999
        ];
        $arSelect = ['IBLOCK_ID', 'ID'];
        $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
        $elements = [];
        while ( $row = $result->GetNext() ){
          $elements[] = [
            'id' => $row["ID"],
            'index' => 9999
          ];
        }

        $el = new CIBlockElement;

        // Сбрасываем индекс сортировки
        foreach ( $elements as $key => $model ){
          $arFields = [ $this->property => $model['index'] ];
          // print_r("{$model['id']} -- {$model['index']}\n");
          $el->Update($model['id'], $arFields);
        }

        // Устанавливаем новый индекс
        foreach ( $this->flatArray as $key => $model ){
          $arFields = [ $this->property => $model['index'] ];
          $r = $el->Update($model['id'], $arFields);
          if ( !$r ){
            var_dump( $model['id'] . ' --- ОШИБКА' );
          }else{
            var_dump( $model['id'] . ' --- GOOD' );
          }
        }
      }

      public function exportTable()
      {
        require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

        if (!class_exists('SpreadsheetReader')){
          require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
          require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
          require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
        }

        $xls = new PHPExcel();
        $xls->setActiveSheetIndex(0);
        $sheet = $xls->getActiveSheet();
        $sheet->setTitle('listOne');

        $sheet->setCellValueExplicit("A1", 'Модель', PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B1", 'Номер', PHPExcel_Cell_DataType::TYPE_STRING);
        $sheet->getColumnDimension("A")->setWidth(25);

        $i = 2;
        foreach ( $this->flatArray as $model => $ar ){
          $sheet->setCellValueExplicit("A{$i}", $model, PHPExcel_Cell_DataType::TYPE_STRING);
          $sheet->setCellValueExplicit("B{$i}", $ar['index'], PHPExcel_Cell_DataType::TYPE_STRING);
          $i++;
        }
        $objWriter = new PHPExcel_Writer_Excel2007($xls);
        $dirPath = $_SERVER['DOCUMENT_ROOT'] . '/admin/panel/engine/sites/';
        $filename = 'sort.xlsx';
        $objWriter->save( $dirPath . $filename );
      }

      public function getSortList()
      {
        $strSql = "SELECT * FROM {$this->table}";
        $res = $this->dbPanel->query( $strSql );
        $rows = $this->dbPanel->fetchAll($res);
        foreach ($rows as $key => $value) {
          $this->sortList[] = $value['group_name'];
        }
      }

      public function getSections()
      {
        $res = CIBlockSection::GetList(
        	Array("SORT"=>"ASC"),
          Array("IBLOCK_ID" => 16),
        	false,
        	Array('ID','NAME'),
        	false
        );

        while ( $item = $res->GetNext() ){
          $this->sections[ $item['ID'] ] = $item['NAME'];
        }

      }

      public function getBrands()
      {
        $arFilter = Array(
        	"IBLOCK_ID" => CProSet::IB_BRANDS,
        );
        $result = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME"));
        while ( $arFields = $result->GetNext() ){
        	$this->brands[ $arFields["ID"] ] = $arFields["NAME"];
        }

      }

      public function updateStatus( string $code, array $arStat ):void
      {
        if ( empty($arStat) ) return;
        $strSql = "UPDATE sites_agents SET ";
        foreach ($arStat as $field => $value) {
          if ( array_key_last($arStat) == $field ){
            $str = "{$field} = '{$value}'";
          }else{
            $str = "{$field} = '{$value}', ";
          }
          $strSql .= $str;
        }
        $strSql .= " WHERE code = '{$code}'";
        try{
          $this->dbPanel->query( $strSql );
        }catch( Throwable $ignored){
          print_r('Не удалось обновить статус' . $ignored . "\n");
        }
      }
  }

  ( new CalculateSort( $argv[1] ?? "RU" ) )->run();
 ?>
