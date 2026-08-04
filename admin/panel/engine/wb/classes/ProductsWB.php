<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

class ProductsWB
{
  private $db; // Экземпляр класса основной БД
  private $dbPanel; // Экземпляр класса panel БД
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

  private $capsBrands = [
    118278, // Восток
    37280, // Anne Klein
    7989, // TIMEX
    40232, // Luch
    7973, // ORIENT
    43588, // Citizen
    119483, // Longines
    75585, // Seiko
    75586, // Guess
  ]; // Бренды, которые ВБ признает только капсом

  private $imgQualityThreshold; // Минимальный процент качества изображения
  private $baseUrl; // Урл сайта
  private $root; // DOCUMENT ROOT
  private $hashTable; // Таблица с хэшами изображений
  private $skipHash; // Флаг проверки хэша

  private $logPath = ''; // Путь к логу
  private $module = ''; // Псевдоним выполняемого скрипта ( для прогресс бара )

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

    CModule::includeModule('panel.manager');

    $this->dbPanel = new DBPanel;
    $this->api = $results->Fetch();
    $this->arModels = $arModels;
    $this->cabinet = $cabinet;
    $this->root = $_SERVER['DOCUMENT_ROOT'];
    $this->header = [
      "Content-Type: application/json",
      "Authorization: " . $this->api['api']
    ];
    $this->logPath = $this->root . '/admin/panel/engine/wb/logs/products/cron/'.$this->cabinet.'/products_'. date('Y-m-d') .'.txt';
    if ( $customLogPath ) $this->logPath = $customLogPath;

    // Вынести в отдельный конфиг
    $this->module = 'importProducts_' . $this->cabinet;
    $this->hashTable = 'wb_image_hashes_' . $this->cabinet;
    $this->imgQualityThreshold = 85; // Вычислено методом тыка
    $this->baseUrl = "https://tempusshop.ru";
    //$this->excludedBrands = [208144, 182598, 71217, 125912, 119483, 206671, 7976, 88200, 162302, 88200, 119483];
    $this->excludedBrands = [206671];
    $this->skipHash = false;

    $this->getCardIdWB(); // Получаем nmid и chrtid из таблицы
    $this->getTableValues(); // Заполняет вложенные в $properties массивы attributes и bitrix_codes
    $this->getBaseProps(); // Заполняет вложенный в $properties массив base
    $this->writeLog('Class initialized. START');
  }

  // ПУБЛИЧНЫЕ МЕТОДЫ

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
    if ( $this->cabinet == 'WR' || $this->cabinet == 'WT' ){
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

    // $arFilter = [
    //   'IBLOCK_ID' => 16,
    //   'ACTIVE' => 'Y',
    //   'PROPERTY_CML2_ARTICLE' => array("MW-240-1E")
    // ];

    $this->itemsForUpload = [];
    $this->itemsForUpdate = [];
    $arSelect = ['ID', 'IBLOCK_ID', 'DETAIL_PICTURE', 'PROPERTY_WBARTICLE2','PROPERTY_WBARTICLE3', 'PROPERTY_BRAND', 'PROPERTY_INFO_TOP','PROPERTY_INFO_WBIP_IMAGE', 'PROPERTY_INFO_WB_PRIORITY'];
    $this->fillSelectList($arSelect);

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ( $row = $result->GetNext() ){
      if ( in_array($row['PROPERTY_BRAND_VALUE'], $this->excludedBrands) ) continue;
      $card = [
        'ID' => $row['ID'],
        'NMID' => $this->cardsWB[$row['ID']]['nmid'] ?? '',
        'CHRTID' => $this->cardsWB[$row['ID']]['chrtid'] ?? '',
        'DETAIL_PICTURE' => $row['DETAIL_PICTURE'],
        'AFTER_PIC' => $row['PROPERTY_FACE']['VALUE_ENUM_ID'] == 1872 ? "{$this->baseUrl}/upload/analog.png" : "{$this->baseUrl}/upload/digital.png",
        'VENDORCODE' => ($this->cabinet == 'WR' || $this->cabinet == 'WT') ? $row['PROPERTY_WBARTICLE2_VALUE'] : $row['PROPERTY_WBARTICLE3_VALUE'],
        'INFOTOP' => $row['PROPERTY_INFO_TOP_VALUE'],
        'WBIP' => $row['PROPERTY_INFO_WBIP_IMAGE_VALUE'],
        'INFOGRAPH_PRIORITY' => $row['PROPERTY_INFO_WB_PRIORITY_VALUE'],
        'ADVERT_PIC' => $this->cabinet == 'WR' ? 'https://tempusshop.ru/upload/tempus_brand.png' : ''
      ];
      if ( empty($card['VENDORCODE']) ){
        $this->writeLog("Card {$card['ID']} has no vendorcode. Skipped. Method: getItems");
        continue;
      }
      $this->fillCardArray($row, $card);
      // $card['MORE_PHOTO'] = self::mergeInfoGraph( $card['MORE_PHOTO'], $card['INFOTOP'] );

      if ( empty($card['NMID']) ){
        $this->itemsForUpload[] = $card;
      }else{
        $this->itemsForUpdate[] = $card;
      }
    }
    $this->writeLog( 'Items for upload: ' . count($this->itemsForUpload) );
    $this->writeLog( 'Items for update: ' . count($this->itemsForUpdate) );
  }

  // СЛУЖЕБНЫЕ МЕТОДЫ

  private static function mergeInfoGraph(array $more_photo, array $info_top ):array
  {
    $result = [];
    if ( is_array($more_photo) ){
      if ( empty($more_photo) ) return array_merge( $more_photo, $info_top );
      foreach ( $more_photo as $key => $value ){
        if ( $key == 0 ){
          $result[] = $value;
          $result = array_merge( $result, $info_top );
          continue;
        }
        $result[] = $value;
      }
    }

    return $result;
  }

  private function mergeAdvertPic( array $infotop, string $advertPic, array $video, array $data ):array
  {
    if ( empty($advertPic) ) return $data;
    $result = [];
    $insertKey = empty($video) ? 1 : 2;

    foreach ( $data as $k => $pic ){
      $result[] = $pic;
      if ( $k == $insertKey ){
        $result[] = $advertPic;
      }
    }

    return $result;
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
      // if (in_array($brand,array('Orient',"Citizen","Longines","Seiko"))) {
      //   $brand = strtoupper($brand);
      // }
      $bodyTmp = [
        'brand' => $brand,
        'title' => $name,
        'description' => self::cutDescription( strval($desc), 2000 ),
        'vendorCode' => $card['VENDORCODE'],
        'dimensions' => [
          'length' => 20,
          'width' => 10,
          'height' => 10,
          'weightBrutto' => 0.2
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

    $this->requestBody = [];
    $skippedCauseHashMatch = 0;

    foreach ( $this->itemsForUpdate as $card ) {
      if ( empty($card['NMID']) ){
        $this->writeLog( $card['ID'] . ' has no nmid and is not supposed to be here' );
        continue;
      }
      $arMedia = [
        'infograph' => [ $card[$this->properties['base']['cover']] ],
        'detail_pic' => [ $card['DETAIL_PICTURE'] ],
        'video' => $card[$this->properties['base']['video']] != '' ? [ $this->baseUrl . $card[$this->properties['base']['video']] ] : '',
        'more_photo' => $card[$this->properties['base']['more_photo']],
        'after' => [ $card['AFTER_PIC'] ],
        // 'wbip' => [$card['WBIP']]
        'wbip' => []
      ];

      if ( ($this->cabinet == 'WR' || $this->cabinet == 'WT') && !empty( $card['INFOGRAPH_PRIORITY'] ) ){
        // print_r( 'Switched to infograph_priority property' . PHP_EOL );
        // $arMedia['infograph'] = [ $card['INFOGRAPH_PRIORITY'] ];
      }

      $arMedia = array_filter( $arMedia );

      if ($this->cabinet != 'WR' || $this->cabinet == 'WT') {
        unset( $arMedia['video'] );
      }

      if ($this->cabinet == 'WR' || $this->cabinet == 'WT') {
        unset( $arMedia['wbip'] );
      }
      if ( $this->cabinet == 'TL' ){
        unset( $arMedia['after'] );
      }

      if ( empty($arMedia['video']) ) {
        unset( $arMedia['video'] );
      }

      foreach ( $arMedia as $key => &$propImg ) {
        if ( $key == 'video' || $key == 'after' ) continue;
        if ( is_array($propImg)) {

            foreach ( $propImg as $k => $img ){
              if ( !empty($img)) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . CFile::GetPath( $img );
                if ( $key == 'more_photo' || $key == 'detail_pic' ){
                  $propImg[$k] = $this->resizeImageLegacy( $filePath, intval($card['NMID']) );
                  continue;
                }
                $propImg[$k] = str_replace( $this->root, $this->baseUrl, $filePath );
              } else {
                unset($propImg[$k]);
              }
            }

        } else {
          if ( !empty($propImg)) {
            $filePath = CFile::GetPath( $propImg );
            if ( $key == 'more_photo'){
              $propImg[$k] = $this->resizeImageLegacy( $filePath, intval($card['NMID']) );
              continue;
            }
            $propImg[$k] = str_replace( $this->root, $this->baseUrl, $filePath );
          }
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
        if ( !empty($arMedia['wbip']) ) {
          $arMedia['infograph'] = $arMedia['wbip'];
        } else {
          unset($arMedia['infograph']);
        }
      }

      // Максимум можно передать 30 изображений для карточки, но надо оставить 2 места под обложку и памятку
      $arMedia['more_photo'] = array_slice( array_filter( $arMedia['more_photo'] ?? [] ), 0, 27 );

      // Фильтруем по качеству изображений
      $data = $this->filterByImageQuality( array_merge( ...array_values($arMedia) ), intval($card['NMID']) );
      $data = $this->mergeAdvertPic( $card['INFOTOP'], $card['ADVERT_PIC'], $arMedia['video'] ?? [], $data );
      // var_dump($data);
      // die;
      // Если хэш строки совпадают с сохраненными, повтороно не выгружаем
      if ( $this->checkImagesHash( $data, $card['NMID'] ) ) {
        $skippedCauseHashMatch += 1;
        continue;
      }
      $this->requestBody[] = [
        'nmId' => intval($card['NMID']),
        // 'data' => array_merge( ...array_values($arMedia) ),
        'data' => $data,
      ];
    }
    $this->writeLog( "Media will not be uploaded for {$skippedCauseHashMatch} card(s). Reason: hash string has not changed" );
    $this->writeLog( "Media request body prepared for ". count($this->requestBody) . " card(s)" );
  }

  private function checkImagesHash( array $arMedia, int $nmid ):bool
  {
    if ( empty($nmid) || empty($arMedia) ) return false;
    if ( $this->skipHash ) return false;

    $arHash = [];
    foreach ( $arMedia as $image ){
      $file = str_replace( $this->baseUrl, $this->root, $image ); // Подменяем урл на локальный путь
      if ( !file_exists( $file ) ) continue;
      $arHash[] = md5_file( $file ); // Записываем хэш строку файла в массив
    }
    $hashString = implode( '|', $arHash ); // Объединяем в одну большую строку

    // Примечание:
    // Можно было бы по-хорошему добавить sort, чтобы нормализовать массив и потом писать, но мы будем считать изменение порядка картинок тождественным изменению количественного состава выгрузки

    $res = $this->dbPanel->select( ['*'], $this->hashTable )->where( 'nmid', $nmid )->make(); // Получаем хэш строку из базы, если она там есть

    if (  !empty($res) ){
      if ( $res[0]['hash'] == $hashString ) return true;

      $strSql = "UPDATE {$this->hashTable} SET hash = '{$hashString}' WHERE nmid = '{$nmid}'"; // Времено. TODO: избавиться от сырых запросов
      $this->dbPanel->query( $strSql );

      return false;
    }

    $strSql = "INSERT INTO {$this->hashTable} (nmid, hash) VALUES ('{$nmid}', '{$hashString}')";
    $this->dbPanel->query( $strSql );

    return false;
  }

  private function filterByImageQuality( array $arMedia, int $nmid ):array
  { // Оценка качества изображений на основе таблиц квантования

    $data = array_map(function($item){
      return str_replace( $this->baseUrl, $this->root, $item );
    }, $arMedia);
    $result = [];
    foreach ( $data as $item ){
      if ( end( explode('.', $item) ) == 'mp4' ){
        $result[] = str_replace( $this->root, $this->baseUrl, $item );
        continue;
      }
      if ( end( explode('.', $item) ) == 'png' ){
        $result[] = str_replace( $this->root, $this->baseUrl, $item );
        continue;
      }
      if (empty($item)) {
        continue;
      }
      $content = file_get_contents($item);

      // Ищем маркер DQT (Define Quantization Table)
      $dqtPos = strpos($content, "\xDB");
      if ($dqtPos === false) {
        print_r( "nmid - {$nmid}" );
        print_r("Файл - {$item}\n");
        print_r("Таблицы квантования не найдены\n");
        return $arMedia;
      }

      // Читаем данные таблицы (упрощённо)
      $dqtData = substr($content, $dqtPos + 4, 64); // 64 байта для 8x8
      $quantizationTable = unpack("C64", $dqtData);

      // Оцениваем качество (условный алгоритм)
      $avgQuantValue = array_sum($quantizationTable) / 64;
      $estimatedQuality = max(0, min(100, 100 - ($avgQuantValue / 2)));

      if ( $estimatedQuality >= $this->imgQualityThreshold ){
        $result[] = str_replace( $this->root, $this->baseUrl, $item );
      }
      // print_r( str_replace( $this->root, $this->baseUrl, $item ) . " - {$estimatedQuality}\n" );
    }

    return $result;
  }

  private function resizeImageLegacy( string $filePath, int $nmid ):string|null // Метод ресайза из старого модуля
  {
    if ( empty($filePath) ) return null;
    if ( !file_exists($filePath) ) return null;
    if ( strpos($filePath, '.mp4') !== false && (filesize($filePath) / 1000000) < 50 ) {
      return str_replace( $this->root, $this->baseUrl, $filePath);
    }

    $resol = getimagesize($filePath);
    $filename = "_" . md5( $filePath . serialize($resol) );
    $filenameNmid = $nmid . "_" . md5( $filePath . serialize($resol) );

    $new_file = "{$this->root}/resize_wb/{$filenameNmid}.jpg";
    if (file_exists($new_file) && filesize($new_file) > 0) {
        return str_replace($this->root, $this->baseUrl, $new_file);
    }

    $new_file = "{$this->root}/resize_wb/{$filename}.jpg";
    if (file_exists($new_file) && filesize($new_file) > 0) {
        return str_replace($this->root, $this->baseUrl, $new_file);
    }


    if ( intval($resol[0]) >= 900 && intval($resol[1]) >= 1200 ) {
      if ( $resol['mime'] != 'image/webp' ){
        return str_replace( $this->root, $this->baseUrl, $filePath );
      }
      $percent = 1;

      $newwidth = $resol[0] * $percent;
      $newheight = $resol[1] * $percent;

      $thumb = imagecreatetruecolor($newwidth, $newheight);
      $source = imagecreatefromwebp($filePath);

      imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $resol[0], $resol[1]);
      imagejpeg($thumb, $new_file, 100);

      return str_replace( $this->root, $this->baseUrl, $new_file );
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

      return str_replace( $this->root, $this->baseUrl, $new_file );
    }
    else{
      return null;
    }
  }

  private function updateMedia():void
  {
    if ( empty($this->requestBody) ){
      $this->writeLog( 'Request body is empty. Method: updateMedia' );
      $arStat = [
        'status' => 'ERROR',
        'percent' => '100',
        'status_text' => 'Ошибка выгрузки изображений',
      ];
      $this->updateStatus( $this->module, $arStat );
      return;
    }
    $percent = 50;

    $arStat = [
      'status' => 'IN_PROCESS',
      'percent' => $percent,
      'status_text' => 'Идёт выгрузка изображений',
    ];
    $this->updateStatus( $this->module, $arStat );

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
      $percent = $percent + ( 30 / count($this->requestBody) );
      $this->updateStatus( $this->module, ['percent' => $percent] );
      $counter++;
      sleep(1);
    }
    $this->writeLog( "Media for {$counter} card(s) was updated" );
  }

  private function uploadInfo():void
  {
    if ( empty($this->requestBody) ){
      $this->writeLog( 'Request body is empty. Method: upload' );
      return;
    }
    if ( count($this->requestBody) > 3 ){
      $arData = array_chunk( $this->requestBody, 5 );
      $this->writeLog( "Request body has more than 5 cards. Divided into smaller groups" );
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
      $this->writeLog( "CARD-BODY: " . print_r($this->requestBody, 1) );
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
    if ( count($this->requestBody) > 1000 ){
      $arData = array_chunk( $this->requestBody, 1000 );
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
      if ( count($this->noNmidCards) != 0 ){
        $percent = $percent + ( 30 / count($this->noNmidCards) );
      }
      $this->updateStatus( $this->module, ['percent' => $percent] );
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
    $filter = [
      'WR' => 'active_wb',
      'TL' => 'active_wbtl',
      'WT' => 'active_wb',
    ];

    $strSql = "SELECT * FROM ci_wb_top";
    $arIDs = array();
    $results = $this->db->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $results->Fetch() ){
      $arIDs[$row["bitrix_id"]] = $row["article"];
    }
    if ( $this->cabinet == 'WR' || $this->cabinet == 'TL' ){
			$strSql = "SELECT * FROM ci_price WHERE {$filter[$this->cabinet]} = 'Y'";
			$result = $this->db->Query( $strSql );
			while ( $row = $result->Fetch() ){
				$arIDs[ $row['bitrix_id'] ] = $row['model'];
			}
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

  private function getBrandsAliases():array
  {
    $rows = $this->db->Query( "SELECT * FROM wdhs_wb_product_brand_aliases" );
    $result = [];

    while ( $row = $rows->Fetch() ) {
      $result[ $row['brand_id'] ] = $row['brand_name'];
    }

    return $result;
  }

  private function getBaseProps():void
  {
    $brands = [];
    $brandAliases = $this->getBrandsAliases();

    $result = CIBlockElement::GetList(
      [],
      ["IBLOCK_ID" => CProSet::IB_BRANDS],
      false,
      false,
      ["ID", "NAME"]
    );
    while( $row = $result->GetNext() ){
      $brands[ $row["ID"] ] = $brandAliases[ $row['ID'] ] ?? $row["NAME"];
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
    print_r( $message . PHP_EOL );
    file_put_contents( $this->logPath, date('Y-m-d G:i:s') . ' --- ' . $message . PHP_EOL, FILE_APPEND );
  }

  static function cutDescription( string $description, int $maxChars ):string
  {
    $text = mb_substr( $description, 0, $maxChars );
    $lastDot = strripos( $text, '.' );
    $result = mb_substr( $text, 0, $lastDot);
    return $result;
  }

  function updateStatus( string $code, array $arStat ):void
  {
    if ( empty($arStat) ) return;
    $strSql = "UPDATE wb_agents SET ";
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
    }
  }
}
 ?>
