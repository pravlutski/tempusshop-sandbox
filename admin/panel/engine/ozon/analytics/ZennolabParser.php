<?php
class ZennolabParser
{
  private string $directory = '/var/www/bitrix_data/tempusshop.ru/upload/zennolab/ozon';
  private string $pattern = '/^(\d{1,2})_(\d{1,2})_(\d{4})_(\d{1,2})_(\d{1,2})_(\d{1,2})\.csv$/';
  private string $file; // Свежайший нераспаршенный файл
  private string $lastFileDate; // Время создания последнего файла

  private array $data; // Ассоциативный массив с сырыми данными

  private array $dataConfig = [
    'article' => 'strval',
    'our_price' => 'intval',
    'black' => 'intval',
    'green' => 'intval',
    'competitor' => 'intval',
    'boost' => 'intval',
    'promo' => 'strval',
    'fbo' => 'intval',
    'fbs' => 'intval',
    'a_ordersum' => 'intval',
    'a_ordersum_d' => 'intval',
    'a_shows' => 'intval',
    'a_shows_d' => 'intval',
    'a_conv' => 'intval',
    'a_conv_d' => 'floatval',
    'a_orders' => 'intval',
    'a_orders_d' => 'floatval',
  ];

  private ?DBPanel $dbPanel;
  private ?TGNotifier $bot;

  public function __construct( string|bool $input )
  {
    $this->loadModules();
    $this->inputName = $input;
  }

  private function loadModules():void
  {
    CModule::IncludeModule('panel.manager');
    $this->dbPanel = new DBPanel;
    $this->bot = new TGNotifier;
  }

  public function run( bool $importAsMainData = false, bool $forceImport = false ):void
  {
    if ( $this->inputName ){
      $this->getFileByName();
    }else{
      $this->getFreshFile();
    }
    $this->checkIfDataExists();
    $this->parseCsv();
    if ( $importAsMainData ){
      $this->importDataMaintable();
    }else{
      $this->importData( $forceImport );
    }
  }

  public function exportDataAsArray():array
  {
    $this->getFreshFile();
    $this->parseCsv();
    $result = $this->prepareToExport();

    return $result;
  }

  public function getFileList( string $date ):array
  {
    list($year, $month, $day) = explode('-', $date);
    // var_dump( $this->directory . "/".$month."_".$day."_".$year."_*_*_*.csv" );
    if ( $day[0] == 0 ) $day = $day[1];
    if ( $month[0] == 0 ) $month = $month[1];
    $files = glob($this->directory . "/".$month."_".$day."_".$year."_*_*_*.csv");
    // var_dump( $files );
    return $files;
  }

  private function prepareToExport():array
  {
    if ( empty($this->data) ) return [];
    $result = [];

    foreach ( $this->data as $row ){
      $result[ $row['article'] ] = $row;
    }

    return $result;
  }

  private function normalizeData( array $array ):array
  {
    $result = [];
    foreach ( $array as $key => $value ){
      $elem = str_replace( '%', '', $value );
      $elem = str_replace( '+', '', $value );

      if ( $value == 'null' ) $elem = false;
      if ( ($key == 'fbo' || $key == 'fbs') && $value == 'True' ) $elem = 1;
      if ( ($key == 'fbo' || $key == 'fbs') && $value == 'false' ) $elem = 0;
      if ( $key == 'promo' && $value == 'false' ) $elem = 'N';
      if ( $key == 'promo' && $value == 'Не участвует' ) $elem = 'N';
      if ( $key == 'promo' && empty($value) ) $elem = 'N';
      if ( $key == 'promo' && $value == 'True' ) $elem = 'Y';
      if ( $key == 'article' ) $elem = end( explode('_', $value) );

      $typeFunction = $this->dataConfig[$key];
      $result[$key] = $typeFunction($elem);
    }

    $result['date'] = explode(' ', $this->lastFileDate)[0];
    $result['hour'] = explode(' ', $this->lastFileDate)[1];

    return $result;
  }

  private function parseCsv():void
  {
    $content = file_get_contents( $this->file );
    $rows = str_getcsv($content, "\n");
    $data = [];
    $raw = [];

    foreach ($rows as $row) {
      $raw[] = str_getcsv($row, ";");
    }
    $headers = array_keys($this->dataConfig);

    foreach ( $raw as $k => $row ){
      if ( $k == 0 ) continue;
      $tmp = [];
      foreach ( $row as $p => $subrow ){
        $tmp[ $headers[$p] ] = $subrow;
      }
      $data[] = $this->normalizeData($tmp);
    }
    $this->data = $data;
  }

  private function importData( bool $forceImport = false ):void
  {

    if ( empty($this->data) || empty($this->lastFileDate) ){
      print_r("Данные файла не получены\n");
      $this->bot->sendMessage("Аналитика OZON: Данные файла не получены. Ошибка импорта\n");
      return;
    }

    $date = explode(' ', $this->lastFileDate)[0];
    $hour = explode(' ', $this->lastFileDate)[1];

    if ( !$forceImport ){
      if ( date('Y-m-d') != $date ){
        print_r("Нет файлов за сегодня\n");
        $this->bot->sendMessage("Аналитика OZON: Нет файлов парсера на текущую дату. Ошибка импорта\n");
        return;
      }

      if ( ( intval( date('G') ) - intval($hour) ) > 2 ){
        print_r("Файл устарел более чем на два часа\n");
        $this->bot->sendMessage("Аналитика OZON: Файл устарел более чем на два часа. Ошибка импорта\n");
        return;
      }
    }

    $strSql = "DELETE FROM ozon_parser_analytics_data WHERE date = '{$date}'";
    $this->dbPanel->query( $strSql );
    var_dump( $this->data );
    $this->dbPanel->insert('ozon_parser_analytics_data', $this->data);
  }

  private function importDataMaintable():void
  {
    $data = [];
    foreach ( $this->data as $value ){
      $data[] = [
        'model' => $value['article'],
        'our' => $value['our_price'],
        'sell' =>	intval($value['black']),
        'spp' => intval($spp),
        'is_fbo' => isset( $this->fboGoods[$model] ) ? 'Y' : 'N',
        'sale_name' => 'Не участвует',
        'sale_price' => $sale_price ?? 0,
        'date' => date('Y-m-d', strtotime('- 1 day')),
        'orders_count' => 0
      ];
    }
    $this->dbPanel->insert('ozon_top_analytics', $data);
  }

  private function checkIfDataExists():void // ВЕРНУТЬСЯ И ДОДЕЛАТЬ
  {
    return;
    $date = explode(' ', $this->lastFileDate)[0];
    $hour = explode(' ', $this->lastFileDate)[1];

    $rows = $this->dbPanel->select(['*'], 'ozon_parser_analytics_data')->where('date', date('Y-m-d'))->make();
  }

  private function getFreshFile():void
  {
    $files = glob($this->directory . '/*_*_*_*_*_*.csv');

    $latestFile = '';
    $latestTime = 0;

    foreach ($files as $file) {
      $fileTime = $this->extractDateTime($file);
      if ($fileTime > $latestTime) {
          $latestTime = $fileTime;
          $latestFile = $file;
      }
    }
    $this->lastFileDate = date('Y-m-d G', $latestTime);
    $this->file = $latestFile;
  }

  private function getFileByName():void
  {
    $name = $this->directory . '/' . $this->inputName;
    if ( !file_exists($name) ) die('NO FILE');

    $fileTime = $this->extractDateTime($name);

    $this->lastFileDate = date('Y-m-d G', $fileTime);
    $this->file = $name;
  }

  private function extractDateTime( string $filename )
  {
    $pattern = '/^(\d{1,2})_(\d{1,2})_(\d{4})_(\d{1,2})_(\d{1,2})_(\d{1,2})\.csv$/';
    if ( preg_match($this->pattern, basename($filename), $matches) ) {

        $date = sprintf("%04d-%02d-%02d %02d:%02d:%02d",
            $matches[3], // год
            $matches[1], // месяц
            $matches[2], // день
            $matches[4], // час
            $matches[5], // минуты
            $matches[6]  // секунды
        );

        return strtotime($date);
    }
    return 0;
  }
}

// ( new ZennolabParser( $argv[1] ?? false ) )->run()

 ?>
