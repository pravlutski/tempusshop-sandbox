<?php
class AdvertDataProvider // Поставщик данных
{
  public function __construct(
    private ?Bitrix\Main\DB\MysqliConnection $main = null,
    private ?DBPanel $panel = null,
    private ?AdvertApiManager $api = null
  ){}

  public function getItems():array
  {
    $result = [];
    $fbo = $this->getFboPrices();
    $sales = $this->getSalesPrices();

    $rows = CIBlockElement::GetList(
      [],
      ['IBLOCK_ID' => CProSet::IB_CATALOG, 'PROPERTY_OZON_ACTIVE' => 1943],
      false,
      false,
      ["IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "PROPERTY_OZSB_PRICE"]
    );

    var_dump( AdvertConfigProvider::getAverageCoInvest() );
    var_dump( count($sales) );
    var_dump( count($fbo) );
    while ( $row = $rows->GetNext() ) {
      $model = $row['PROPERTY_CML2_ARTICLE_VALUE'];
      $price = ($sales[ $model ] ?? $fbo[ $model ] ?? (int)$row['PROPERTY_OZSB_PRICE_VALUE']) * AdvertConfigProvider::getAverageCoInvest();
      $result[ $model ] = $price;
    }

    return $result;
  }

  private function getFboPrices():array
  {
    $rows = $this->panel->select(['*'], 'ozon_fbo_price_IP')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['article'] ] = $row['price'];
    }

    return $result;
  }

  public function getPriceData():array
  {
    $strSql = "SELECT * FROM ci_price WHERE active_os = 'Y'";
    $res = $this->main->Query( $strSql );
    $result = [];
    while ( $row = $res->Fetch() ) {
      $result[ $row['model'] ] = 1;
    }

    return $result;
  }

  public function getQuarantineData():array
  {
    $strSql = "SELECT * FROM ci_price_quarantine"; // Нельзя так делать, но сделано потому что в выгрузке остатков такая же хуйня
    $res = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $res->Fetch() ) {
      $result[$row['ARTICLE']] = 1;
    }

    return $result;
  }

  public function getSkuDictionary():array
  {
    $rows = $this->panel->select(['*'], AdvertConfigProvider::getSkuDictionaryTable() )->make();
    $result = [];

    foreach ( $rows as $row ) {
      $result[ $row['model'] ] = $row['sku'];
    }

    return $result;
  }

  public function getProfitData():array
  {
    $orderField = AdvertConfigProvider::getProfitOrderField();
    $table = AdvertConfigProvider::getProfitTable();

    $res = $this->panel->select(['*'], $table)->desc($orderField)->make();
    $result = [];

    foreach ( $res as $row ) {
      $result[ $row['model'] ] = $row[ $orderField ];
    }

    return $result;
  }

  public function getAdvertDictionary():array
  {
    $data = [];
    $advertList = $this->getAdvertList();
    foreach ( $advertList as $advertId => $advertData ){
      if ( in_array( $advertId, AdvertConfigProvider::getPreparedAdverts() ) ) continue;
      if ( !in_array( $advertData['state'], ["CAMPAIGN_STATE_RUNNING"] ) ) continue;

      $response = $this->api->getAdvertGoods( $advertId );
      $response = json_decode($response['result'], true);
      if ( !isset($response['products']) ){
        var_dump( $response );
        throw new Exception("cant get an advert data");
      }

      foreach ( $response['products'] as $product ){
        $data[ $product['sku'] ] = $advertId;
      }

    }

    return $data;
  }

  public function getSelectedAdvertDictionary():array
  {
    $data = [];

    foreach ( AdvertConfigProvider::getPreparedAdverts() as $advertId ){
      $response = $this->api->getAdvertGoods( $advertId );
      $response = json_decode($response['result'], true);

      if ( !isset($response['products']) ){
        var_dump( $response );
        throw new Exception("cant get an advert data");
      }

      foreach ( $response['products'] as $product ){
        $data[ $product['sku'] ] = $advertId;
      }

      // sleep(1);
    }

    return $data;
  }

  public function getAdvertList():array
  {
    $response = $this->api->getAdvertList();

    if ( $response['code'] != 200 ){
      var_dump( $response['result'] );
      throw new Exception("API error");
    }

    $res = json_decode( $response['result'], true );

    $result = [];
    foreach ( $res['list'] as $advert ){
      $result[ $advert['id'] ] = [
        'title' => $advert['title'],
        'state' => $advert['state'],
      ];
    }

    return $result;
  }

  public function getSettings():array
  {
    $table = AdvertConfigProvider::getSettingsTable();
    $rows = $this->panel->select(['*'], $table)->where('cabinet', 'IP')->make();
    foreach ( $rows as &$row ){
      $row['adverts'] = json_decode($row['adverts'], true);
    }
    return $rows[0];
  }

  public function getSalesPrices():array
  {
    $table = AdvertConfigProvider::getSalesLogTable();
    $lastDate = $this->panel->select(['max(date) as date'], $table)->make()[0]['date'];
    $lastDate = date( 'Y-m-d G:00:00', strtotime($lastDate) );

    $rows = $this->panel->select( ['model', 'price'], $table )->where('status', 'Y')->where('date', $lastDate, '>')->make();
    $result = [];

    foreach ( $rows as $row ){
      $result[ $row['model'] ] = $row['price'];
    }

    return $result;
  }
}
 ?>
