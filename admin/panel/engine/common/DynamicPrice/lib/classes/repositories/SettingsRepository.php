<?php
class SettingsRepository extends RepositoryBase
{
  // Конструктор унаследован

  public function getCoeffients():array
  {
    $coefficientTable = ConfigProvider::getCoefficientsTable();
    $rows = $this->panel->select(['*'], $coefficientTable)->make();
    $result = [];

    foreach ( $rows as $row ){
      $hour = $row['hour'] == 24 ? 0 : $row['hour'];
      $result[ $hour ] = $row['coefficient'];
    }

    return $result;
  }

  public function getDefaults():array
  {
    $defaultSettingsTable = ConfigProvider::getDefaultSettingsTable();
    $marketplace = ConfigProvider::getMarketplace();
    $cabinet = ConfigProvider::getCabinet();

    $rows = $this->panel->select( ['*'], $defaultSettingsTable )->where('cabinet', $cabinet)->make();
    $settings = $rows[0];
    $settings['isPeriod'] = boolval( $settings['isPeriod'] );

    return $settings;
  }


}
 ?>
