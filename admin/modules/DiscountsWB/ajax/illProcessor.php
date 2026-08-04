 <?php
 require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
 CModule::IncludeModule("panel.manager");
 CModule::IncludeModule("maxyss.wb");

 require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

 use PhpOffice\PhpSpreadsheet\Spreadsheet;
 use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


 if (!class_exists('SpreadsheetReader')){
   require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
   require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
   require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
 }

 if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
     $filename = $_FILES['file']['tmp_name'];
     copy($_FILES['file']['tmp_name'], '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/autoAdv/temp/illiquid_base.xlsx');
   }else{
     die('Ошибка загрузки файла');
   }

  $settingsPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/settings/settingsIll.json';
  $arSettingsIll = json_decode(file_get_contents($settingsPath), true);
  if ( !empty($_POST['cabinet']) ){
    if ( $_POST['cabinet'] == 'WR' ){
      $arSettings = CMaxyssWb::settings_wb('WR');
    }else{
      $arSettings = CMaxyssWb::settings_wb('DEFAULT');
    }
  }else{
    die('Не получен кабинет из формы');
  }

  // var_dump($_POST['cabinet']);
  // die;

  $auth = $arSettings['AUTHORIZATION'];

 $url = 'https://statistics-api.wildberries.ru/api/v1/supplier/stocks?dateFrom=2020-01-01';
 $headers = [
   "Content-Type: application/json",
   "Authorization: {$auth}"
 ];

 $ch = curl_init($url);
 curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
 $resCurl = curl_exec($ch);
 curl_close($ch);
 $result = json_decode($resCurl,1);
 $dataStocks = [];
 if ( is_array($result) && empty($result['error']) && empty($result['message']) ){
   foreach($result as $card){
     if ( array_key_exists($card['nmId'], $dataStocks) ){
       $dataStocks[$card['nmId']] = $dataStocks[$card['nmId']] + $card['quantity'];
     }else{
       $dataStocks[$card['nmId']] = $card['quantity'];
     }

   }
 }else{
   var_dump($result);
   echo json_encode(['error' => $result]);
   die();
 }


 $xls = PHPExcel_IOFactory::load($filename);
 $xls->setActiveSheetIndex(0);
 $sheet = $xls->getActiveSheet();
 $ar = array();
 foreach ($sheet->toArray() as $key => $row) {
   if ($key == 0) continue;
   if ( $row[$arSettingsIll['nmid_col']] == 'Артикул WB' ) continue;
   if ( !preg_match('/[0-9]+$/', $row[$arSettingsIll['nmid_col']]) ){
    echo json_encode(['error' => 'nmid не соответствует шаблону. Проверьте настройки колонок']);
    die;
   }
   $nmid = intval( trim($row[$arSettingsIll['nmid_col']]) );
   $ar[$nmid] = [
     'turnover' => !empty($row[$arSettingsIll['turnover_col']]) ? intval($row[$arSettingsIll['turnover_col']]) : 0,
     'quantity' => intval(!empty($dataStocks[$nmid]) ? $dataStocks[$nmid] : 0),
     'currentDisc' => !empty($row[10]) ? intval($row[10]) : 0,
   ];
   // file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/temp/shapka.txt',$nmid . ' ' . $ar[$nmid]['turnover'] . ' ' . $ar[$nmid]['quantity'] . PHP_EOL, FILE_APPEND);
 }

 if ( empty($ar) ){
   echo json_encode(['error' => 'Ошибка парсинга документа WB']);
   die();
 }

 $discounts = [];
 $showDiscounts = [
   'onSale' => [],
   'notOnSale' => []
 ];

 foreach ($ar as $nmid => $data) {
   if ( ($data['turnover'] >= 40 && $data['turnover'] <= 999) && $data['quantity'] > 0){
     if ($data['currentDisc'] != $arSettingsIll['discMax']){
       $showDiscounts['onSale'][] = $nmid;
       $discounts[] = [
         'nmid' => $nmid,
         'discount' => intval($arSettingsIll['discMax'])
       ];
     }
   }else{
     if ($data['currentDisc'] != $arSettingsIll['discMin']){
       $showDiscounts['notOnSale'][] = $nmid;
       $discounts[] = [
         'nmid' => $nmid,
         'discount' => intval($arSettingsIll['discMin'])
       ];
     }
   }
 }
 if (empty($showDiscounts)){
   $showDiscounts = ['onSale' => [], 'notOnSale' => []];
 }
 $showDiscounts['discMin'] = $arSettingsIll['discMin'];
 $showDiscounts['discMax'] = $arSettingsIll['discMax'];
 // var_dump($discounts);
 // die;
 $xls = new PHPExcel();
 $xls->setActiveSheetIndex(0);
 $sheet = $xls->getActiveSheet();
 $sheet->setTitle('listOne');

 foreach ($discounts as $key => $pos) {
   $index = $key + 1;
   $sheet->setCellValueExplicit("A" . $index , $pos['nmid'], PHPExcel_Cell_DataType::TYPE_STRING);
   $sheet->setCellValueExplicit("B" . $index , $pos['discount'], PHPExcel_Cell_DataType::TYPE_STRING);
 }

 $objWriter = new PHPExcel_Writer_Excel2007($xls);
 $dirPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/DiscountsWB/temp/';
 $filename = 'illiquid_discounts.xlsx';
 $objWriter->save( $dirPath . $filename );

 echo json_encode($showDiscounts);
  ?>
