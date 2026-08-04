<?php
class ControlDataProvider
{
  private static array $config = [
    'filter' => [
      'WR' => 'active_wb',
      'WT' => 'active_wb',
      'TL' => 'active_tl',
    ],
    'price' => [
      'WR' => 'WBPRICE',
      'WT' => 'MINIMUM_PRICE_RB',
      'TL' => 'WBTL_PRICE',
    ],
    'wbarticle' => [
      'WR' => 'WBARTICLE2',
      'WT' => 'WBARTICLE2',
      'TL' => 'WBARTICLE3',
    ],
  ];

  private StocksDataProvider $sdp;

  public function __construct(
    private ?\Bitrix\Main\DB\MysqliConnection $main = null,
    private ?DBPanel $panel = null,
    private string $cabinet,
  )
  {
    $this->sdp = new StocksDataProvider( $this->main, $this->panel );
  }

  public function getDictionary():array
  {
    $strSql = "SELECT * FROM wdhs_wb_props WHERE cabinet = '{$this->cabinet}'";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ) {
      $result[ $row['article'] ] = $row['nmid'];
    }

    return $result;
  }

  public function getQuarantineData( array $dict ):array
  {
    $strSql = "SELECT * FROM ci_price_quarantine WHERE SITE_ID = 'WB'";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $nmid = $dict[ $row['ARTICLE'] ] ?? false;
      if ( !$nmid ) continue;
      $result[ $nmid ] = true;
    }

    return $result;
  }

  public function getFboData( array $dict ):array
  {
    if ( $this->cabinet != 'WR' ) return [];
    $rows = $this->panel->select(['*'], 'wb_fbo_stock_WR')->make();
    $result = [];
    foreach ( $rows as $row ){
      $nmid = $dict[ $row['article'] ] ?? false;
      if ( !$nmid ) continue;
      $result[ $nmid ] = true;
    }

    return $result;
  }

  public function getPriceData( array $dict ):array
  {
    // $strSql = "SELECT DISTINCT model FROM ci_price WHERE active_wb = 'Y' AND supplier_id NOT IN (128)";
    $strSql = "SELECT DISTINCT model FROM ci_price WHERE active_wb = 'Y'";

    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $nmid = $dict[ $row['model'] ] ?? false;
      if ( !$nmid ) continue;
      $result[ $nmid ] = true;
    }

    return $result;
  }

  public function getTopModels( array $dict ):array
  {
    // $strSql = "SELECT article FROM ci_wb_top";
    // $strSql = "SELECT model as article FROM ci_price WHERE active_wb = 'Y'";
    //
    // $rows = $this->main->Query( $strSql );
    // $result = [];
    //
    // while ( $row = $rows->Fetch() ){
    //   $nmid = $dict[$row['article']] ?? false;
    //   if ( !$nmid ) continue;;
    //   $result[$nmid] = $nmid;
    // }

    $rows = $this->sdp->getActiveItems( $this->cabinet );
    $result = [];

    foreach ( $rows as $row ){
      $nmid = $dict[ $row['model'] ] ?? false;
      if ( !$nmid ) continue;;
      $result[$nmid] = $nmid;
    }

    return $result;
  }

  public function getCabinetPriceRules():array
  {
    $strSql = "SELECT settings FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
    $rows = $this->main->Query( $strSql )->Fetch()['settings'];
    $settings = json_decode( $rows, true );

    return [
      'min' => (int)$settings['minSebes'] ?? 0,
      'max' => (int)$settings['maxSebes'] ?? 999999,
    ];
  }

  public function getExcludedModels( array $dict ):array
  {
    $strSql = "SELECT settings FROM wdhs_wb_main_settings WHERE cabinet = '{$this->cabinet}'";
    $rows = $this->main->Query( $strSql )->Fetch()['settings'];
    $settings = json_decode( $rows, true );
    $settings['exclude'] = explode(',', $settings['exclude']);

    if ( !is_array($settings['exclude']) || empty($settings['exclude']) ) return [];
    $result = [];
    foreach ( $settings['exclude'] as $row ){
      $nmid = $dict[$row] ?? false;
      if ( !$nmid ) continue;
      $result[$nmid] = true;
    }

    return $result;
  }

  public function getItemsCost( array $dict, bool $useNmid = true ):array
  {
    $reserved = $this->getReserved();
    $filter = self::$config['filter'][$this->cabinet];

    $strSql = "SELECT model, price, count FROM ci_price WHERE active_ru = 'Y' ORDER BY price ASC";
    $rows = $this->main->Query( $strSql );
    $data = [];

    while( $row = $rows->Fetch() ){
      $data[ $row['model'] ][] = [
        'price' => $row['price'],
        'count' => $row['count'],
      ];
    }
    $result = [];

    foreach ( $data as $model => $prices ){
      $nmid = $dict[$model] ?? false;
      if ( !$nmid ) continue;
      $result[ $useNmid ? $nmid : $model ] = $this->getMinPrice(
        items: $prices,
        reserved: $reserved[$model] ?? 0
       );
    }

    return $result;
  }

  public function enrichWithContext( array $nmids ):array
  {
    $dict = $this->getDictionary();
    $flipDict = array_flip( $dict );

    $models = [];
    foreach ( $nmids as $nmid ){
      $models[] = $flipDict[$nmid];
    }

    $costs = $this->getItemsCost( $dict, false );

    $items = $this->getItemsInfo( $models, $costs, $dict );

    return $items;
  }

  private function getItemsInfo( array $models, array $costs, array $dict ):array
  {
    if ( empty($models) ) return $models;

    $price = self::$config['price'][$this->cabinet];
    $wbarticle = self::$config['wbarticle'][$this->cabinet];

    $arFilter = [
      'IBLOCK_ID' => 16,
      'PROPERTY_CML2_ARTICLE' => $models
    ];
    $arSelect = ["ID", "IBLOCK_ID", "PROPERTY_{$price}", "PROPERTY_{$wbarticle}", "PROPERTY_CML2_ARTICLE"];

    $rows = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
    $result = [];

    while( $row = $rows->GetNext() ){
      $result[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ] = [
        'model' => $row["PROPERTY_CML2_ARTICLE_VALUE"],
        'nmid' => $dict[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ],
        'wbarticle' => $row["PROPERTY_{$wbarticle}_VALUE"],
        'price' => $row["PROPERTY_{$price}_VALUE"],
        'cost' => $costs[ $row["PROPERTY_CML2_ARTICLE_VALUE"] ] ?? 0
      ];
    }

    return $result;
  }

  private function getMinPrice( array $items, int $reserved ):float
  {
    foreach ( $items as $data ){
      if ( $reserved - $data['count'] < 0 ) return $data['price'];
      $reserved -= $data['count'];
    }

    return 0;
  }

  private function getReserved():array
  {
    $strSql = "SELECT * FROM ci_reserved";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $result[ $row['ARTICLE'] ] = $row['RESERVED'];
    }

    return $result;
  }

  public function getFullReserved( array $dict ):array
  {
    $strSql = "SELECT * FROM ci_reserved WHERE AVAILABLE_RU <= RESERVED";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->Fetch() ){
      $nmid = $dict[$row['ARTICLE']] ?? false;
      if ( !$nmid ) continue;
      $result[$nmid] = true;
    }

    return $result;
  }
}
 ?>
