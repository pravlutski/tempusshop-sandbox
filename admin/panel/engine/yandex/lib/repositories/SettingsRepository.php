<?php
class SettingsRepository extends RepositoryBase implements RepositoryInterface
{
  public function getMainSettings( string|bool $cabinet = false ):array
  {

    $query = $this->panel->select( ['*'], Config::instance()->getTableName('main_settings') );

    if ( $cabinet !== false ){
      $query = $query->where('cabinet', $cabinet);
      return $query->make()[0] ?? [];
    }

    return $query->make();
  }

  public function getAuthData( string $cabinet ):array
  {

    $query = $this->panel->select( ['businessId', 'api'], Config::instance()->getTableName('main_settings') )->where('cabinet', $cabinet)->make();
    return $query[0] ?? [];
  }

  public function getCampaignsList( string $cabinet ):array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('campaigns_list'))->where('cabinet', $cabinet)->make();
  }

  public function getCampaignsMatchList( string $cabinet ):array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('campaigns_match_list'))->where('cabinet', $cabinet)->make();
  }

  public function getPromosList( string $cabinet ):array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('promos_list'))->where('cabinet', $cabinet)->make();
  }

  public function getPromosSettings( string $cabinet ):array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('promos_settings'))->where('cabinet', $cabinet)->make();
  }

  public function getAgents():array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('agents'))->make();
  }

  public function findAgent( string $code ):array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('agents'))->where('code', $code)->make();
  }

  public function getOrderStatusMap():array
  {
    return $this->panel->select(['*'], Config::instance()->getTableName('orders_status'))->make();
  }

  public function getSuppliersStockSettings():array
  {
    $strSql = "SELECT id, settings_type_sklad as settings FROM ci_suppliers";
    $rows = $this->main->Query( $strSql );
    $result = [];

    while ( $row = $rows->fetch() ){
      if ( empty($row['settings']) ) continue;
      $result[ $row['id'] ] = json_decode($row['settings'], true);
    }

    return $result;
  }
}

 ?>
