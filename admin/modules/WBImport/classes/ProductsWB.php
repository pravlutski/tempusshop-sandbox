<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

class ProductsWB
{
  private $db; // Экземпляр класса БД
  private $api; // Данные для авторизации
  private $cabinet; // Кабинет
  private $arModels; // Массив для форс апдейта определнных карточек
  private $header; // Заголовки для запроса

  public $itemsForUpdate = []; // Массив с товарами для обновления карточек
  public $itemsForUpload = []; // Массив с товарам для создания карточек
  private $requestBody = []; // Итоговый массив для отправки на ВБ

  private $properties = []; // Массив для различных указателей свойств и символьных кодов свойств

  private $cardsWB = []; // Дополнительные свойства карточек (nmid, chrtid)
  public $noNmidCards = []; // Созданные карточки, для которых не указан nmid в системе
  private $recievedNmids = []; // Массив с nmid для свежесозданных карточек
  private $excludedBrands = []; // Исключенные бренды

  private $logPath = '';

  function __construct( $cabinet = 'WR', $arModels = [], $customLogPath = false )
  {
    global $DB;
    $this->db = $DB;
    $strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = '{$cabinet}'";
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    if ( $results->SelectedRowsCount() < 1 ){
      $this->writeLog('No API settings for cabinet.');
      die;
    }
    $this->api = $results->Fetch();
    $this->arModels = $arModels;
    $this->cabinet = $cabinet;
    $this->excludedBrands = [208144, 182598, 71217, 125912, 119483, 206671, 7976, 88200, 162302, 88200, 119483];
    $this->header = [
      "Content-Type: application/json",
      "Authorization: " . $this->api['api']
    ];

    $this->lastExecErrPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/products/errors/products_error.txt';
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/products/cron/products_update_'. date('Y-m-d') .'.txt';
    if ( $customLogPath ) $this->logPath = $customLogPath;

    $this->getCardIdWB(); // Получаем nmid и chrtid из таблицы
    $this->getTableValues(); // Заполняет вложенные в $properties массивы attributes и bitrix_codes
    $this->getBaseProps(); // Заполняет вложенный в $properties массив base
    $this->writeLog('Class initialized. START');
  }

  public function run():void
  {
    $this->getItems();
    // $this->buildArrayInfo( false );
    // $this->update();
    // var_dump( $this->requestBody );
    $this->buildArrayMedia( true );
    $this->updateMedia();
    // $this->getNmids();
  }

  // Мастер методы

  public function uploadCards():void
  {
    $this->buildArrayInfo( true );
    $this->uploadInfo();
  }

  public function updateCards():void
  {
    $this->buildArrayInfo( false );
    $this->updateInfo();
  }

  public function updateMediaAll():void
  {
    if ( $this->cabinet == 'WR' ){
      $this->buildArrayMedia( true ); // Создавать с инфографикой в качестве главного избражения
    }else{
      $this->buildArrayMedia( false ); // Создавать с деталкой в качестве главного избражения
    }
    $this->updateMedia();
  }

  public function getNmids():void
  {
    $this->buildArrayInfo( true );
    $this->getCreatedNmids();
    $this->saveCreatedNmids();
  }

  public function getItems():void
  {
    $wbTopID = $this->getWBTop();
    if ( empty($wbTopID) ){
      $this->writeLog("Data from ci_wb_top is not gathered");
      return;
    }
    if ( empty($this->arModels) ){
      $arFilter = [
        'IBLOCK_ID' => 16,
        'ID' => array_keys($wbTopID),
      ];
    }else{
      $arFilter = [
        'IBLOCK_ID' => 16,
        'ACTIVE' => 'Y',
        'PROPERTY_CML2_ARTICLE' => $this->arModels
      ];
    }
    $this->itemsForUpdate = [];
    $this->itemsForUpdate = [];
    $arSelect = ['ID', 'IBLOCK_ID', 'DETAIL_PICTURE', 'PROPERTY_WBARTICLE2','PROPERTY_WBARTICLE3', 'PROPERTY_BRAND'];
    $this->fillSelectList($arSelect); // Дописываем в фильтр указанные в таблице атрибутов свойства битрикса

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ( $row = $result->GetNext() ){
      if ( in_array($row['PROPERTY_BRAND_VALUE'], $this->excludedBrands) ) continue;
      $card = [
        'ID' => $row['ID'],
        'NMID' => $this->cardsWB[$row['ID']]['nmid'] ?? '',
        'CHRTID' => $this->cardsWB[$row['ID']]['chrtid'] ?? '',
        'DETAIL_PICTURE' => $row['DETAIL_PICTURE'],
        'AFTER_PIC' => $row['PROPERTY_FACE']['VALUE_ENUM_ID'] == 1872 ? 'https://tempusshop.ru/upload/wb_after_img.jpg' : 'https://tempusshop.ru/upload/wb_after_img_cif.jpg',
        'VENDORCODE' => $this->cabinet == 'WR' ? $row['PROPERTY_WBARTICLE2_VALUE'] : $row['PROPERTY_WBARTICLE3_VALUE']
      ];
      if ( empty($card['VENDORCODE']) ){
        $this->writeLog("Card {$card['ID']} has no vendorcode. Skipped. Method: getItems");
        continue;
      }
      $this->fillCardArray($row, $card);

      if ( empty($card['NMID']) ){
        $this->itemsForUpload[] = $card;
      }else{
        $this->itemsForUpdate[] = $card;
      }
    }
    $this->writeLog( 'Items for upload: ' . count($this->itemsForUpload) );
    $this->writeLog( 'Items for update: ' . count($this->itemsForUpdate) );
  }

  private function buildArrayInfo( bool $isNew ):void
  {
    if ( $isNew == true ){
      $items = $this->itemsForUpload;
    }else{
      $items = $this->itemsForUpdate;
    }
    if ( empty($items) ){
      $this->writeLog('There is no items to work with: [buildArrayInfo] FLAG: ' . $isNew ? 'true' : 'false');
      return;
    }
    $this->requestBody = [];
    foreach ( $items as $card ){
      $attributes = $this->makeAttrArray( $card );
      $brandId = $card[ $this->properties['base']['brand'] ];
      $brand = $this->properties['brand'][$brandId];
      $name = $card[ $this->properties['base']['title'] ];
      $desc = $card[ $this->properties['base']['description'] ];
      $desc = is_array( $desc ) ? $desc["TEXT"] : $desc;
      $skus = $card[ $this->properties['base']['skus'] ];

      $bodyTmp = [
        'brand' => $brand,
        'title' => $name,
        'description' => self::cutDescription( strval($desc), 2000 ),
        'vendorCode' => $card['VENDORCODE'],
        'dimensions' => [
          'length' => 20,
          'width' => 10,
          'height' => 10,
        ],
        'sizes' => [
          [
            'skus' => [ strval($skus) ]
          ]
        ],
        'characteristics' => $attributes
      ];

      if ( $isNew == true ){
        $this->requestBody[] = [
          'subjectID' => 60,
          'variants' => [$bodyTmp]
        ];
        $this->noNmidCards[] = [
          'bitrix_id' => $card['ID'],
          'vendorCode' => $bodyTmp['vendorCode'],
        ];
      }else{
        $bodyTmp['nmID'] = intval( $card['NMID'] );
        $bodyTmp['sizes'][0]['chrtID'] = intval( $card['CHRTID'] );
        $this->requestBody[] = $bodyTmp;
      }
    }
    $this->writeLog( $isNew ? 'Upload request body prepared.' : 'Update request body prepared.');
  }

  private function buildArrayMedia( bool $useInfograph ):void
  {
    if ( empty($this->itemsForUpdate) ) {
      $this->writeLog('There is no items to work with: [buildArrayMedia]');
      return;
    }

    $baseUrl = 'https://tempusshop.ru';
    $this->requestBody = [];

    foreach ( $this->itemsForUpdate as $card ) {
      if ( empty($card['NMID']) ){
        $this->writeLog( $card['ID'] . ' has no nmid and is not supposed to be here' );
        continue;
      }
      $arMedia = [
        'infograph' => [ $card[$this->properties['base']['cover']] ],
        'detail_pic' => [ $card['DETAIL_PICTURE'] ],
        'video' => $card[$this->properties['base']['video']] != '' ? [ $baseUrl . $card[$this->properties['base']['video']] ] : '',
        'more_photo' => $card[$this->properties['base']['more_photo']],
        'after' => [ $card['AFTER_PIC'] ],
      ];

      $arMedia = array_filter( $arMedia );

      if ( empty($arMedia['video']) ) {
        unset( $arMedia['video'] );
      }

      foreach ( $arMedia as $key => &$propImg ) {
        if ( $key == 'video' || $key == 'after' ) continue;
        if ( is_array($propImg) ) {
          foreach ( $propImg as $k => $img ){
            $filePath = $_SERVER['DOCUMENT_ROOT'] . CFile::GetPath( $img );
            if ( $key == 'more_photo'){
              $propImg[$k] = $this->resizeImageLegacy( $filePath, intval($card['NMID']) );
              continue;
            }
            $propImg[$k] = str_replace( $_SERVER['DOCUMENT_ROOT'], $baseUrl, $filePath );
          }
        } else {
          $filePath = CFile::GetPath( $propImg );
          if ( $key == 'more_photo'){
            $propImg[$k] = $this->resizeImageLegacy( $filePath, intval($card['NMID']) );
            continue;
          }
          $propImg[$k] = str_replace( $_SERVER['DOCUMENT_ROOT'], $baseUrl, $filePath );
        }
      }
      if ( $useInfograph ){
        if ( !empty($arMedia['infograph']) ) {
          // Если инфографика плохая, используем деталку
          if ( getimagesize($arMedia['infograph'][0])[0] >= 700 && getimagesize($arMedia['infograph'][0])[1] >= 900 ){
            unset( $arMedia['detail_pic'] );
          }else{
            unset($arMedia['infograph']);
          }
        }
      }else{
        unset( $arMedia['infograph'] );
      }
      // Максимум можно передать 30 изображений для карточки, но надо оставить 2 места под обложку и памятку
      $arMedia['more_photo'] = array_slice( array_filter( $arMedia['more_photo'] ), 0, 28 );
      $this->requestBody[] = [
        'nmId' => intval($card['NMID']),
        'data' => array_merge( ...array_values($arMedia) ),
      ];
    }
    $this->writeLog( 'Media request body prepared for '. count($this->requestBody) . ' card(s)' );

  }

  private function resizeImageLegacy( string $filePath, int $nmid ):string|null // Метод ресайза из старого модуля
  {
    if ( empty($filePath) ) return null;
    if ( !file_exists($filePath) ) return null;
    if ( strpos($filePath, '.mp4') !== false && (filesize($filePath) / 1000000) < 50 ) {
      return str_replace( $_SERVER['DOCUMENT_ROOT'], $baseLink, $filePath);
    }
    $baseLink = 'https://tempusshop.ru';
    $resol = getimagesize($filePath);
    $filename = "_" . md5( $filePath . serialize($resol) );
    $filenameNmid = $nmid . "_" . md5( $filePath . serialize($resol) );

    $new_file = "{$_SERVER['DOCUMENT_ROOT']}/resize_wb/{$filenameNmid}.jpg";
    if ( file_exists($new_file) ) return str_replace( $_SERVER['DOCUMENT_ROOT'], $baseLink, $new_file );

    $new_file = "{$_SERVER['DOCUMENT_ROOT']}/resize_wb/{$filename}.jpg";
    if ( file_exists($new_file) ) return str_replace( $_SERVER['DOCUMENT_ROOT'], $baseLink, $new_file );

    if ( intval($resol[0]) >= 900 && intval($resol[1]) >= 1200 ) {
      if ( $resol['mime'] != 'image/webp' ){
        return str_replace( $_SERVER['DOCUMENT_ROOT'], $baseLink, $filePath );
      }
      $percent = 1;

      $newwidth = $resol[0] * $percent;
      $newheight = $resol[1] * $percent;

      $thumb = imagecreatetruecolor($newwidth, $newheight);
      $source = imagecreatefromwebp($filePath);

      imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $resol[0], $resol[1]);
      imagejpeg($thumb, $new_file, 100);

      return str_replace( $_SERVER['DOCUMENT_ROOT'], $baseLink, $new_file );
    }
    else if (intval($resol[0]) > 225 and intval($resol[1]) > 225) {
      if ( $resol[0] == $resol[1] ) {
        $percent = 1200 / intval( $resol[0] );
      }
      else if ( $resol[0] < $resol[1] ) {
        $percent = 1200 / intval( $resol[0] );
      }
      else if ( $resol[1] < $resol[0] ){
        $percent = 1200 / intval( $resol[1] );
      }
      else {
        $percent = 1200 / intval( $resol[1] );
      }

      $newwidth = $resol[0] * $percent;
      $newheight = $resol[1] * $percent;

      $thumb = imagecreatetruecolor( $newwidth, $newheight );
      switch ( $resol['mime'] ){
        case 'image/webp':
          $source = imagecreatefromwebp( $filePath );
          break;
        case 'image/bmp':
          $source = imagecreatefrombmp( $filePath );
          break;
        case 'image/png':
          $source = imagecreatefrompng( $filePath );
          break;
        default:
          $source = imagecreatefromjpeg( $filePath );
          break;
      }
      imagecopyresized( $thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $resol[0], $resol[1] );
      imagejpeg($thumb, $new_file, 100);

      return str_replace( $_SERVER['DOCUMENT_ROOT'], $baseLink, $new_file );
    }
    else{
      return null;
    }
  }

  private function updateMedia():void
  {
    if ( empty($this->requestBody) ){
      $this->writeLog( 'Request body is empty. Method: updateMedia' );
      return;
    }
    $counter = 0;
    foreach ( $this->requestBody as $data ){
      $res = $this->request(
        'https://content-api.wildberries.ru/content/v3/media/save',
        $this->header,
        json_encode( $data )
      );
      if ( $res['http_code'] != 200 || empty( $res ) ){
        $this->writeLog( "WB returned an error for {$data['nmId']}: " . PHP_EOL . print_r($res, 1) );
        continue;
      }
      $counter++;
    }
    $this->writeLog( "Media for {$counter} card(s) was updated" );
  }

  private function uploadInfo():void
  {
    if ( empty($this->requestBody) ){
      $this->writeLog( 'Request body is empty. Method: upload' );
      return;
    }
    if ( count($this->requestBody) > 100 ){
      $arData = array_chunk( $this->requestBody, 100 );
      $this->writeLog( "Request body has more than 100 cards. Divided into smaller groups" );
      foreach ($arData as $key => $chunk) {
        $res = $this->request(
          'https://content-api.wildberries.ru/content/v2/cards/upload',
          $this->header,
          json_encode( $chunk ),
          'POST'
        );
        if ( $res['http_code'] != 200 ){
          $this->writeLog( "Error! Group {$key} was not uploaded: " . print_r($res, 1) );
          continue;
        }
        $this->writeLog( "Success! Group {$key} was uploaded. Code: " . $res['http_code'] );
        sleep(5);
      }
    }else{
      $res = $this->request(
        'https://content-api.wildberries.ru/content/v2/cards/upload',
        $this->header,
        json_encode( $this->requestBody ),
        'POST'
      );
      if ( $res['http_code'] != 200 ){
        $this->writeLog( "Error! Cards was not uploaded: " . print_r($res, 1) );
        return;
      }
      $this->writeLog( "Success! Cards was uploaded. Code: " . $res['http_code'] );
    }
  }

  private function updateInfo():void
  {
    if ( empty($this->requestBody) ){
      $this->writeLog( 'Request body is empty. Method: update' );
      return;
    }
    if ( count($this->requestBody) > 3000 ){
      $arData = array_chunk( $this->requestBody, 3000 );
      $this->writeLog( "Request body has more than 3000 cards. Divided into smaller groups" );
      foreach ( $arData as $key => $chunk ){
        $res = $this->request(
          'https://content-api.wildberries.ru/content/v2/cards/update',
          $this->header,
          json_encode( $chunk ),
          'POST'
        );
        if ( $res['http_code'] != 200 ){
          $this->writeLog( "Error! Group {$key} was not updated: " . print_r($res, 1) );
          continue;
        }
        $this->writeLog( "Success! Group {$key} was updated. Code: " . $res['http_code'] );
        sleep(5);
      }
    }else{
      $res = $this->request(
        'https://content-api.wildberries.ru/content/v2/cards/update',
        $this->header,
        json_encode( $this->requestBody, JSON_UNESCAPED_UNICODE ),
        'POST'
      );
      if ( $res['http_code'] != 200 ){
        $this->writeLog( "Error! Cards was not updated: " . print_r($res, 1) );
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/products/cron/updateError.txt', print_r($this->requestBody,1));
        return;
      }
      $this->writeLog( "Success! Cards was updated. Code: " . $res['http_code'] );
    }
  }

  private function getCreatedNmids():void
  {
    if ( empty($this->noNmidCards) ){
      $this->writeLog( 'noNmidCards is empty. Method: getCreatedNmids' );
      return;
    }
    $this->writeLog( 'Trying to get info about ' . count($this->noNmidCards) . ' cards' );
    foreach ( $this->noNmidCards as $elem ){
      $data = [
        'settings' => [
          'sort' => [
            'ascending' => false,
          ],
          'filter' => [
            'withPhoto' => -1,
            'textSearch' => strval( $elem['vendorCode'] )
          ],
          'cursor' => [
            'limit' => 5
          ],
        ],
      ];
      $res = $this->request(
        'https://content-api.wildberries.ru/content/v2/get/cards/list?locale=ru',
        $this->header,
        json_encode( $data ),
        'POST'
      );
      if ( empty($res) || $res['http_code'] != 200 || empty($res['cards']) ){
        $this->writeLog( "WB returned an error for {$elem['bitrix_id']} or card was not found: " . print_r($res, 1) );
        continue;
      }

      foreach ( $res['cards'] as $card ){
        if ( $card['vendorCode'] != $elem['vendorCode'] ) continue;
          $model = end( explode('_', $elem['vendorCode']) );
          $this->recievedNmids[] = [
            'bitrix_id' => $elem['bitrix_id'],
            'model' => $model,
            'nmid' => $card['nmID'],
            'chrtid' => $card['sizes'][0]['chrtID']
          ];
      }
      sleep(2);
    }

  }

  private function saveCreatedNmids():void
  {
    if ( empty($this->recievedNmids) ){
      $this->writeLog( 'recievedNmids is empty. Method: saveCreatedNmids' );
      return;
    }
    $this->writeLog( count($this->recievedNmids) . " nmids will be saved" );
    foreach ($this->recievedNmids as $card ) {
      $strSql = "SELECT 1 FROM wdhs_wb_props WHERE cabinet = '{$this->cabinet}' AND article = '{$card['model']}'";
      $result = $this->db->Query( $strSql, false, $err_mess.__LINE__ );
      if ( $result->SelectedRowsCount() > 0 ){
        $this->writeLog( "Data for {$card['model']} already exists" );
        continue;
      }
      $bitrix_id = $card['bitrix_id'];
      $article = $card['model'];
      $nmid = $card['nmid'];
      $chrtid = $card['chrtid'];
      $cabinet = $this->cabinet;

      $strSql = "INSERT INTO wdhs_wb_props (bitrix_id, article, nmid, chrtid, cabinet) VALUES ('{$bitrix_id}', '{$article}', '{$nmid}', '{$chrtid}', '{$cabinet}')";
      $this->db->Query( $strSql, false, $err_mess.__LINE__);
      $this->writeLog( "Data for {$card['model']} saved successfully" );
    }
  }

  public function request( $url, $headers = [], $body = '', $customReq = 'GET' )
  {
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    // curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $customReq );
    $res = curl_exec( $ch );
    if ( curl_errno( $ch ) ) {
      $error_msg = curl_error( $ch );
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close( $ch );

    if ( $error_msg ) {
      $this->writeLog('CUrl returned an error: ' . $error_msg);
      return false;
    }
    $result = json_decode( $res, true );
    $result['http_code'] = $http_code;
    return $result;
  }

  public function getWBTop():array
  {
    $strSql = "SELECT * FROM ci_wb_top";
    $arIDs = array();
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $results->Fetch() ){
      $arIDs[$row["bitrix_id"]] = $row["article"];
    }
    return $arIDs;
  }

  public function getTableValues():void
  {
    $strSql = "SELECT * FROM wdhs_wb_product_props";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    $codesBX = [];
    $tableValues = [];
    while ( $row = $result->Fetch() ){
      $tableValues[$row['char_id']] = $row;

      if ( empty($row['bitrix_id']) ) continue;
      $codesBX[$row['bitrix_id']] = 1;
    }
    $strSql = "SELECT * FROM wdhs_wb_product_base";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $result->Fetch() ){
      if ( empty($row['bitrix_id']) ) continue;
      $codesBX[$row['bitrix_id']] = 1;
    }
    $res = CIBlockProperty::GetList(
        ["name" => "asc"],
        ["IBLOCK_ID" => 16],
        ["ID" => array_keys($codesBX)]
    );
    while ( $row = $res->Fetch() ) {
      if ( empty( $codesBX[$row['ID']] ) ) continue;
      $codesBX[$row["ID"]] = mb_strtoupper($row["CODE"]);
    }
    $this->properties = [
      'attributes' => $tableValues,
      'bitrix_codes' => $codesBX,
    ];
  }

  private function getBaseProps():void
  {
    $brands = [];
    $result = CIBlockElement::GetList(
      [],
      ["IBLOCK_ID" => CProSet::IB_BRANDS],
      false,
      false,
      ["ID", "NAME"]
    );
    while( $row = $result->GetNext() ){
      $brands[ $row["ID"] ] = $row["NAME"];
    }
    $this->properties['brand'] = $brands;
    unset( $result );
    unset( $row );

    $strSql = "SELECT * FROM wdhs_wb_product_base";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $result->Fetch() ){
      $this->properties['base'][ $row['field'] ] = $this->properties['bitrix_codes'][ $row['bitrix_id'] ];
    }
  }

  private function getDependency( int $char_id, string $option_name ):array|bool
  {
    if ( $option_name == '' ) return false;
    $strSql = "SELECT * FROM wdhs_wb_product_props_dependencies WHERE option_name = '{$option_name}' AND char_id = '{$char_id}'";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    if ( $result->SelectedRowsCount() > 0 ){
      $value = $result->Fetch()['value'];
      $value = json_decode($value);
      return $value ?? false;
    }else{
      return false;
    }
  }

  private function isDepend( int $property_id, int $char_id ):bool
  {
    $strSql = "SELECT 1 FROM wdhs_wb_product_props_dependencies WHERE property_id = '{$property_id}' AND char_id = '{$char_id}'";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    if ( $result->SelectedRowsCount() > 0 ){
      return true;
    }else{
      return false;
    }
  }

  private function makeAttrArray( array $card ):array
  {
    $attributes = [];
    foreach ( $this->properties['attributes'] as $prop ){
      if ( !empty($prop['custom_value']) ){
        $attributes[] = [
          'id' => intval($prop['char_id']),
          'value' => json_decode( $prop['custom_value'] ),
        ];
      }
      elseif( !empty($prop['bitrix_id']) ){
        $value = $this->getProperty( $prop['char_id'], $card );
        $attributes[] = [
          'id' => intval($prop['char_id']),
          'value' => $this->getProperty( $prop['char_id'], $card )
        ];
      }
      // else{
      //   $attributes[] = [
      //     'id' => intval($prop['char_id']),
      //     'value' => []
      //   ];
      // }
    }
    return $attributes;
  }

  private function getProperty( int $char_id, array $card ):array
  {
    // Достаем строку из массива с соответствиями свойств ВБ
    $tableRow = $this->properties['attributes'][$char_id];
    // Достаем символьный код свойства битрикс, т.к. в таблице хранятся только ID свойств
    $propCode = $this->properties['bitrix_codes'][$tableRow['bitrix_id']];
    // Проверям наличие зависимостей для свойства
    if ( $this->isDepend($tableRow['bitrix_id'], $char_id) ){
      if ( is_array($card[$propCode]) ){
        $result = [];
        // Если свойство - массив, перебираем и получаем зависимости
        foreach ( $card[$propCode] as $propValue ){
          if ( $dep = $this->getDependency($char_id, $propValue) ){
            $result = array_merge($result, $dep);
          }
        }
        // Обрезаем массив до разрешенного количества элементов
        if ( $tableRow['max_count'] > 0){
          return array_slice( $result, 0, $tableRow['max_count'] );
        }
        return $result;
      }
    }else{
      if ( is_array($card[$propCode]) ){
        $result = [];
        // Если свойство - массив, перебираем и получаем зависимости
        foreach ( $card[$propCode] as $propValue ){
          $result[] = $propValue;
        }
        // Обрезаем массив до разрешенного количества элементов
        if ( $tableRow['max_count'] > 0){
          return array_slice( $result, 0, $tableRow['max_count'] );
        }
        return $result;
      }
    }
    // Если свойство не является массивом, просто возвращаем его значение
    $depValue = $this->getDependency($char_id, $card[$propCode]);
    return $depValue == false ? [ $card[$propCode] ] : $depValue;
  }

  private function getCardIdWB():void
  {
    $strSql = "SELECT * FROM wdhs_wb_props WHERE cabinet = '{$this->cabinet}'";
    $result = $this->db->Query($strSql, false, $err_mess.__LINE__);
    $this->cardsWB = [];
    while ( $row = $result->Fetch() ){
      $this->cardsWB[ $row['bitrix_id'] ] = $row;
    }
  }

  private function fillSelectList( array &$arSelect ):void
  {
    foreach ( $this->properties['bitrix_codes'] as $prop ){
      $arSelect[] = 'PROPERTY_' . $prop;
    }
  }

  private function fillCardArray( array $row, array &$card ):void
  {
    foreach ( $this->properties['bitrix_codes'] as $prop ){
      $card[$prop] = $row["PROPERTY_{$prop}_VALUE"] ?? '';
    }
  }

  public function writeLog( string $message ):void
  {
    file_put_contents( $this->logPath, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
  }

  static function cutDescription( string $description, int $maxChars ):string
  {
    $text = mb_substr( $description, 0, 2000 );
    $lastDot = strripos( $text, '.' );
    $result = mb_substr( $text, 0, $lastDot);
    return $result;
  }
}

 ?>
