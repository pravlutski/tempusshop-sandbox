<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule("panel.manager");
/**
 *
 */
class AutoAdvMain
{
  private $groups = []; //Склейки неликвида
  private $advIds = []; //ИД рекламных кампаний, за которыми склейки закреплены
  private $toDelete = []; //ИД кампаний, которые подлежат удалению
  private $toSave = []; //ИД кампаний, которые фиксируются
  private $reportDRR = [];
  private $logPath;
  private $auth;
  private $headers;
  private $advSettings;

  public function __construct(){
    $this->auth = CMaxyssWb::settings_wb('WR')["AUTHORIZATION"];
    $this->logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/promcom/logs/autoAdv/autoAdvMain.txt';
    $this->headers = [
      "Content-Type: application/json",
      "Authorization: {$this->auth}"
    ];
    $this->advSettings = self::getAdvSettings();
  }

  public function run()
  {
    $this->writeLog(' ');
    $this->writeLog('START');
    $this->getAdvIds(); //Получаем ИД всех активных автоматически созданных кампаний
    $this->getReports(); //Получаем статистику всех кампаний
    $this->checkIfWorth(); //Проверяем, были ли добавления в корзину по кампаниям
    $this->saveGroupAdv(); //Если были, то спасаем склейку от перезаписывания
    $this->endAdv(); //Завершаем кампании, если никто ничего в корзину не добавлял

    $this->updateGroupsFromMS(); //Пересобираем склейки

    $this->getItemsDB(); //Получаем все артикулы, для которых кампании не созданы
    $this->createAdv(); //Создаем кампании
    $this->writeLog('END');
  }

  public function getAdvIds()
  {
    global $DB;
    $strSql = "SELECT aaw.advId, iw.nmid
      FROM auto_adv_wb AS aaw
      JOIN illiquid_wb AS iw ON aaw.completeId = iw.completeId
      WHERE aaw.advId IS NOT NULL";

    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $advIds = [];
    while ( $row = $resultDB->Fetch() ){
      $advIds[$row['advId']][] = $row['nmid'];
    }
    if ( !empty($advIds) ){
      $this->writeLog('ITEMS EXTRACTED SUCCESSFULLY');
      $this->advIds = $advIds;
    }else{
      $this->writeLog('THERE IS NO ADVIDS');
      $this->advIds = false;
    }
  }

  public function getReports()
  {
    if ($this->advIds == false){
      return false;
    }
    $data = [];

    foreach ($this->advIds as $id => $goods) {
      $data[] = [
        'id' => (int)$id,
        'interval' => [
          'begin' =>date("Y-m-d", strtotime("-4 day")),
          'end' => date("Y-m-d")
        ]
      ];
    }

    $url = 'https://advert-api.wb.ru/adv/v2/fullstats';
    $result = $this->curl($url, $data);
    $reports = json_decode($result, 1);

    if ( !empty($reports) && is_array($reports) ){
      foreach ($reports as $adv) {
        $this->reportDRR[$adv['advertId']] = ['spentSum' => $adv['sum']];
      }
    }else{
      $this->reportDRR = false;
      return false;
    }
    unset($data);
    $sales = 0;
    foreach ($this->advIds as $id => $goods) {
      $data = [
        'nmIDs' => $goods,
        'period' => [
          'begin' =>date("Y-m-d h:i:s", strtotime("-4 day")),
          'end' => date("Y-m-d h:i:s")
        ],
        'page' => 1
      ];

      $url = 'https://seller-analytics-api.wildberries.ru/api/v2/nm-report/detail';
      $result = $this->curl($url, $data);
      $result = json_decode($result, 1);

      if ( empty($result['error']) && is_array($result) ){
        foreach ( $result['data']['cards'] as $card ){
          $sales += $card['statistics']['selectedPeriod']['ordersSumRub'];
        }
        $this->reportDRR[$id]['salesSum'] = $sales == 0 ? $this->reportDRR[$id]['spentSum'] : $sales;
        $this->writeLog('GOT REPORTS!');
      }
      sleep(20);
    }
  }

  public function checkIfWorth()
  {
    if ($this->reportDRR == false){
      return false;
    }
    foreach ($this->reportDRR as $advertId => $report) {
      if ( ($report['spentSum']/$report['salesSum'])*100 <= $this->advSettings['minDRR'] ){
        $this->toSave[] = (int)$advertId;
      }else{
        $this->toDelete[] = (int)$advertId;
      }
    }
    $this->writeLog('ADVERTS WILL BE SAVED: ' . count($this->toSave));
    $this->writeLog('ADVERTS WILL BE DELETED: ' . count($this->toDelete));
  }

  public function saveGroupAdv()
  {
    if ( empty($this->toSave) ){
      $this->writeLog('NOTHING TO SAVE');
      return false;
    }
    global $DB;
    $data = implode(',',$this->toSave);
    $strSql = "UPDATE auto_adv_wb SET worthToSave = 1 WHERE advId IN ({$data})";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $this->writeLog('GROUPS SAVED SUCCESSFULLY');
  }

  public function endAdv()
  {
    if ( empty($this->toDelete) ){
      $this->writeLog('NOTHING TO DELETE');
      return false;
    }
    global $DB;

    $data = implode(',',$this->toDelete);
    $strSql = "UPDATE auto_adv_wb SET worthToSave = 0, pausedToDelete = 1 WHERE advId IN ({$data})";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);

    foreach ($this->toDelete as $advertId) {
      $url = 'https://advert-api.wb.ru/adv/v0/pause?id=' . $advertId;
      $result = $this->curl($url);
      if ( $result == null ){
        $this->writeLog('ADVERT '.$advertId.' ENDED SUCCESSFULLY');
      }else{
        $this->writeLog('ADVERT '.$advertId.' END ERROR: ' . print_r($result, 1));
      }
      usleep(200000);
    }
  }

  public function updateGroupsFromMS()
  {
    $this->writeLog('START UPDATING GROUPS');
    //Получаем товары с МС Хронос
    $accounts = ['msk', 's1'];
    $fromMS = [];
    foreach ($accounts as $acc){
      if ($acc == 'msk'){
        $allStores = [
          "83c00532-0f74-11ee-0a80-143a0014a102",
          "796d5aa2-bab0-11ee-0a80-03440010c9e0",
          // "97706d75-5b6f-11ee-0a80-14cc002bb00d",
          // "e7c0d649-55ef-11ee-0a80-1186002ba09f"
        ];
      }else{
        $allStores = ["79ed7d71-0aa6-11ea-0a80-004200039aa4"];
      }
      $objMS = new MoyskladAPI( $acc );
      foreach ($allStores as $store_id) {
        $filter = "filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$store_id}";
        $objMS->getStock(0, $filter);
      }
      foreach ($objMS->MSPosition as $value) {
        if ( $value['stockDays'] < $this->advSettings['stockDays'] ) continue;
        $fromMS[$value["XML_ID"]] = [
          'stockDays' => $value["stockDays"],
          'price' => $value['PRICE'],
          'stock' => $value['stock'],
          'account' => $acc
        ];
      }

      if ( is_array($objMS->MSPosition) && !empty($objMS->MSPosition) ){
        $this->writeLog('GOT GOODS FROM MS. ACCOUNT: ' . $acc);
      } else{
        $this->writeLog('ERROR IN GETTING GOODS FROM MS. ACCOUNT: ' . $acc);
      }
    }

    if( empty($fromMS) ){
      $this->writeLog('CRITICAL ERROR. NO GOODS GOT FROM MS. ($fromMS is empty)');
      return false;
    }

    global $DB;
    $strSql = "DELETE FROM illiquid_wb WHERE completeId NOT IN (SELECT completeId FROM auto_adv_wb WHERE worthToSave = 1)";
    $DB->Query($strSql, false, $err_mess.__LINE__);
    // $strSql = "DELETE FROM auto_adv_wb WHERE worthToSave = 0";
    // $DB->Query($strSql, false, $err_mess.__LINE__);

    $strSql = "SELECT iw.bitrixId, iw.nmid
     FROM auto_adv_wb as aaw
     LEFT JOIN illiquid_wb as iw ON aaw.completeId = iw.completeId
     WHERE aaw.worthToSave = 1";

    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $excludeIDs = [];
    while ( $row = $resultDB->Fetch() ){
      $excludeIDs[$row['bitrixId']] = $row['nmid'];
    }

    //Получаем артикул ВБ и прочее из битрикса
    $arFilter = Array(
      "!ID" => array_keys($excludeIDs),
      "IBLOCK_ID"	=> 16,
      "XML_ID" => array_keys($fromMS),
      "!PROPERTY_TYPE" => false,
      "!PROPERTY_CML2_ARTICLE" => false,
      "!PROPERTY_WBARTICLE2" => false,
      "!PROPERTY_PROP_MAXYSS_NMID_CREATED_WB" => false
    );

    $arSelect = array("ID", "IBLOCK_ID", "XML_ID", "PROPERTY_CML2_ARTICLE","PROPERTY_FACE" ,"PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_TYPE", "PROPERTY_WBARTICLE2");
    $rs = CIBlockElement::GetList( array(), $arFilter, false, false, $arSelect );

    //Формируем массив и фильтруем по количеству дней на складе
    $filtered = [];
     while($art = $rs->GetNext()){
      if ( floor($fromMS[$art["XML_ID"]]['stockDays']) > $this->advSettings['stockDays'] && $fromMS[$art["XML_ID"]]['stock'] > 0 ){
        foreach ($art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_DESCRIPTION'] as $key => $value) {
          if ($value == 'WR') {
            $nmid = $art['PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE'][$key];
          }
        }

        $filtered[] = [
          'bitrixId' => $art['ID'],
          'wbarticle' => $art['PROPERTY_WBARTICLE2_VALUE'],
          'nmid' => $nmid,
          'face' => $art['PROPERTY_FACE_VALUE'],
          'article' => $art['PROPERTY_CML2_ARTICLE_VALUE'],
          'type' => array_values( $art['PROPERTY_TYPE_VALUE'] )[0],
          'stockDays' => $fromMS[$art["XML_ID"]]['stockDays'],
          'price' => $fromMS[$art["XML_ID"]]['price'] / 100, //Делим на 100, так как себес в МС хранится в копейках
          'account' => $fromMS[$art["XML_ID"]]['account']
        ];
      }
    }

    if ( is_array($filtered) && !empty($filtered) ){
      $this->writeLog('GOT INFO FROM BITRIX');
    } else{
      $this->writeLog('ERROR IN GETTING INFO FROM BITRIX');
    }

    $groups = [];
    foreach ( $filtered as $value ){
      $this->divideIntoSubGroups( $groups, $this->convertType( trim($value['type']) ), $value );
    }
    $groups = $this->getGoodsFbo($groups);
    //Содержимое последних подгрупп собираем в массивы по 7 элементов
    $this->splitIntoChunks($groups);
    // var_dump($groups);
    $this->writeSectionsDB($groups);
    $this->writeLog('GROUPS UPDATED');
  }

  public function getItemsDB()
  {
    global $DB;
    $strSql = "SELECT DISTINCT iw.completeId, iw.nmid FROM illiquid_wb as iw
      LEFT JOIN auto_adv_wb as aaw ON iw.completeId = aaw.completeId
      WHERE (aaw.worthToSave = 0 OR aaw.worthToSave IS NULL)";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $groups = [];
    while ( $row = $resultDB->Fetch() ){
      $groups[ $row['completeId'] ][] = (int)$row['nmid'];
    }
    if ( is_array($groups) && !empty($groups) ){
      // $this->groups = array_unique($groups);
      $this->groups = $groups;
      $this->writeLog('ITEMS EXTRACTED SUCCESSFULLY');
    }else{
      $this->writeLog('THERE IS NO ITEMS TO EXTRACT FROM THE TABLE');
      $this->groups =  false;;
    }
  }

  public function createAdv()
  {
    if ( empty($this->groups) ){
      return false;
    }
    global $DB;
    $createdAdvs = '';
    // $this->groups = array_chunk($this->groups, 3, true)[0];
    foreach ($this->groups as $completeId => $group) {
      if ($completeId != 'male_above_9k_analog_1') continue;
      $data = [
        'type' => 8,
        'name' => $this->generateName($completeId),
        'subjectId' => 60,
        'sum' => $this->advSettings['startBudget'],
        'btype' => 1,
        'on_pause' => false,
        'nms' => $group,
        'cpm' => $this->advSettings['startCpm']
      ];
      // var_dump($data);
      // die;
      $url = 'https://advert-api.wb.ru/adv/v1/save-ad';
      $result = $this->curl($url, $data);

      if ( gettype($result) == 'string' && preg_match('/[0-9]+/', $result) ){
        $this->writeLog('CREATED: ' . $result);
        $result = (int)$result;
        $actualCpm = $this->advSettings['startCpm'];
        $creationDate = date('Y-m-d');
        $strSql = "INSERT INTO auto_adv_wb (advId, completeId, actualCpm, creationDate) VALUES ('{$result}', '{$completeId}','{$actualCpm}','{$creationDate}')";
        $DB->Query($strSql, false, $err_mess.__LINE__);
        break;
      }else{
        $result = json_decode($result, 1);
        if ( !empty($result['error']) ){
          $this->writeLog('ERROR: ' . $result['error']);
        }
      }
      sleep(20);
    }
  }

  //Вспомогательные функции
  private function writeLog($message)
  {
    file_put_contents($this->logPath, date('d-m-Y G:i:s'). ' --- ' . $message . PHP_EOL, FILE_APPEND);
  }

  private function generateName($completeId)
  {
    preg_match('/[0-9]+$/', $completeId, $counter);
    $modified = preg_replace('/_[0-9]+$/', '', $completeId);
    $names = [
      'female_above_9k_analog' => 'Женские >9к аналоговые АВТО',
      'female_above_9k_digital' => 'Женские >9к цифровые АВТО',
      'female_above_9k_combined' => 'Женские >9к аналогово-цифровые АВТО',
      'female_below_9k_analog' => 'Женские 7к-9к аналоговые АВТО',
      'female_below_9k_digital' => 'Женские 7к-9к цифровые АВТО',
      'female_below_9k_combined' => 'Женские 7к-9к аналогово-цифровые АВТО',
      'female_below_7k_analog' => 'Женские 3к-7к аналоговые АВТО',
      'female_below_7k_digital' => 'Женские 3к-7к цифровые АВТО',
      'female_below_7k_combined' => 'Женские 3к-7к аналогово-цифровые АВТО',
      'female_below_3k_analog' => 'Женские 1к-3к аналоговые АВТО',
      'female_below_3k_digital' => 'Женские 1к-3к цифровые АВТО',
      'female_below_3k_combined' => 'Женские 1к-3к аналогово-цифровые АВТО',

      'male_above_9k_analog' => 'Мужские >9к аналоговые АВТО',
      'male_above_9k_digital' => 'Мужские >9к цифровые АВТО',
      'male_above_9k_combined' => 'Мужские >9к аналогово-цифровые АВТО',
      'male_below_9k_analog' => 'Мужские 7к-9к аналоговые АВТО',
      'male_below_9k_digital' => 'Мужские 7к-9к цифровые АВТО',
      'male_below_9k_combined' => 'Мужские 7к-9к аналогово-цифровые АВТО',
      'male_below_7k_analog' => 'Мужские 3к-7к аналоговые АВТО',
      'male_below_7k_digital' => 'Мужские 3к-7к цифровые АВТО',
      'male_below_7k_combined' => 'Мужские 3к-7к аналогово-цифровые АВТО',
      'male_below_3k_analog' => 'Мужские 1к-3к аналоговые АВТО',
      'male_below_3k_digital' => 'Мужские 1к-3к цифровые АВТО',
      'male_below_3k_combined' => 'Мужские 1к-3к аналогово-цифровые АВТО',

      'uni_above_9k_analog' => 'Унисекс >9к аналоговые АВТО',
      'uni_above_9k_digital' => 'Унисекс >9к цифровые АВТО',
      'uni_above_9k_combined' => 'Унисекс >9к аналогово-цифровые АВТО',
      'uni_below_9k_analog' => 'Унисекс 7к-9к аналоговые АВТО',
      'uni_below_9k_digital' => 'Унисекс 7к-9к цифровые АВТО',
      'uni_below_9k_combined' => 'Унисекс 7к-9к аналогово-цифровые АВТО',
      'uni_below_7k_analog' => 'Унисекс 3к-7к аналоговые АВТО',
      'uni_below_7k_digital' => 'Унисекс 3к-7к цифровые АВТО',
      'uni_below_7k_combined' => 'Унисекс 3к-7к аналогово-цифровые АВТО',
      'uni_below_3k_analog' => 'Унисекс 1к-3к аналоговые АВТО',
      'uni_below_3k_digital' => 'Унисекс 1к-3к цифровые АВТО',
      'uni_below_3k_combined' => 'Унисекс 1к-3к аналогово-цифровые АВТО',

      'child_above_9k_analog' => 'Детские >9к аналоговые АВТО',
      'child_above_9k_digital' => 'Детские >9к цифровые АВТО',
      'child_above_9k_combined' => 'Детские >9к аналогово-цифровые АВТО',
      'child_below_9k_analog' => 'Детские 7к-9к аналоговые АВТО',
      'child_below_9k_digital' => 'Детские 7к-9к цифровые АВТО',
      'child_below_9k_combined' => 'Детские 7к-9к аналогово-цифровые АВТО',
      'child_below_7k_analog' => 'Детские 3к-7к аналоговые АВТО',
      'child_below_7k_digital' => 'Детские 3к-7к цифровые АВТО',
      'child_below_7k_combined' => 'Детские 3к-7к аналогово-цифровые АВТО',
      'child_below_3k_analog' => 'Детские 1к-3к аналоговые АВТО',
      'child_below_3k_digital' => 'Детские 1к-3к цифровые АВТО',
      'child_below_3k_combined' => 'Детские 1к-3к аналогово-цифровые АВТО',
    ];

    return $names[$modified] . ' ' . $counter[0];
  }

  private function divideIntoSubGroups(&$groups, $key, $value)
  {
    $faceType = $value['face'] == 'Аналоговый' ? 'analog' : 'digital';
    $faceType =  $value['face'] == 'Аналогово-цифровой' ? 'combined' : $faceType;
    if ( $value['price'] > 0 && $value['price'] <= 3000 ){
      $groups[$key]['below_3k'][$faceType][] = $value;
    }
    else if ( $value['price'] > 3000 && $value['price'] <= 7000 ){
      $groups[$key]['below_7k'][$faceType][] = $value;
    }
    elseif ( $value['price'] > 7000 && $value['price'] <= 9999 ) {
      $groups[$key]['below_9k'][$faceType][] = $value;
    }
    elseif ( $value['price'] > 9999 ) {
      $groups[$key]['above_9k'][$faceType][] = $value;
    }
  }

  private function splitIntoChunks(&$groups)
  {
    foreach ($groups as &$typeGroup){
      foreach ($typeGroup as &$priceGroup){
        foreach ($priceGroup as &$faceGroup) {
          shuffle($faceGroup);
          $faceGroup = array_chunk($faceGroup, 50);
        }
      }
    }
  }

  private function convertType($type)
  {
    $allTypes = [
      'Мужские' => 'male',
      'Женские' => 'female',
      'Унисекс' => 'uni',
      'Детские' => 'child'
    ];
    return $allTypes[$type];
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

  static function getAdvSettings()
  {
    global $DB;
    $strSql = "SELECT * FROM auto_adv_wb_settings WHERE id = 1";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $advSettings = [];
    while ( $row = $resultDB->Fetch() ){
      $advSettings = [
        'stockDays' => (int)$row['stockDays'],
        'startBudget' => (int)$row['startBudget'],
        'startCpm' => (int)$row['startCpm'],
        'refillBudget' => (int)$row['refillBudget'],
        'minDRR' => (int)$row['minDRR'],
        'minCart' => (int)$row['minCart'],
        'dailySpent' => (int)$row['dailySpent'],
        'allSpent' => (int)$row['allSpent'],
        'ordersCount' => (int)$row['ordersCount'],
        'stepCpmChange' => (int)$row['stepCpmChange']
      ];
    }
    return $advSettings;
  }

  private function getGoodsFbo($goodsFbo)
  {
    global $DB;
    $strSql = "SELECT * FROM illiquid_wb_fbo_tmp";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    while ( $row = $resultDB->Fetch() ){
      $goodsFbo[ $row['groupType'] ][ $row['sectionType'] ][ $row['faceType'] ][] = [
        'bitrixId' => $row['bitrixId'],
        'wbarticle' => 'WATCH',
        'nmid' => $row['nmid'],
        'face' => $row['faceType'],
        'article' => $row['model'],
        'type' => $row['groupType'],
        'stockDays' => 10,
        'price' => 10,
        'account' => 'fbo',
      ];
    }
    return $goodsFbo;
  }

  private function writeSectionsDB(&$groups)
  {
    global $DB;
    $strSql = "SELECT * FROM auto_adv_wb WHERE worthToSave = 1 GROUP BY completeId";
    $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
    $excludeIDs = [];
    while ( $row = $resultDB->Fetch() ){
      $excludeIDs[] = $row['completeId'];
    }
    foreach ($groups as $type => &$typeGroup){
      foreach ($typeGroup as $priceType => &$priceGroup){
        foreach ($priceGroup as $faceType => &$faceGroup) {
          $key = 0;
          foreach ($faceGroup as &$cardGroup) {
            foreach ($cardGroup as $card) {

              $model = $card['article'];
              $nmid = (int)$card['nmid'];
              $group = $type;
              $groupId = $key + 1;
              $section = $priceType;
              $face = $faceType;
              $bitrixId = $card['bitrixId'];
              $completeId = $type . '_' . $section . '_' . $face . '_' . $groupId;
              $account = $card['account'];
              while ( in_array($completeId, $excludeIDs) ){
                $key++;
                $groupId = $key + 1;
                $completeId = $type . '_' . $section . '_' . $face . '_' . $groupId;
              }
              // $strSql = "INSERT INTO illiquid_wb (bitrixId, model, groupType, faceType, sectionType, nmid, groupId, completeId, account)
              // VALUES ('{$bitrixId}', '{$model}', '{$group}', '{$face}','{$section}', '{$nmid}','{$groupId}', '{$completeId}', '{$account}')";
              // $DB->Query($strSql, false, $err_mess.__LINE__);
              $dataImport[] = [
                'bitrixId' => $bitrixId,
                'model' => $model,
                'groupType' => $group,
                'faceType' => $face,
                'sectionType' => $section,
                'nmid' => $nmid,
                'groupId' => $groupId,
                'completeId' => $completeId,
                'account' => $account,
              ];
              // $strSql = "INSERT INTO auto_adv_wb (completeId)
              // VALUES ('{$completeId}')";
              // $DB->Query($strSql, false, $err_mess.__LINE__);
            }
            $key++;
          }
        }
      }
    }
    $chunks = array_chunk($dataImport, 200);
    foreach ( $chunks as $key =>$chunk ){
      $this->fuckYouBitrixORM('illiquid_wb', $chunk);
      $this->writeLog( 'Импортирован пакет ' . $key );
    }
  }

  function fuckYouBitrixORM($tableName , $arrayData)
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

$objAdv = new AutoAdvMain();
$objAdv->run();

 ?>
