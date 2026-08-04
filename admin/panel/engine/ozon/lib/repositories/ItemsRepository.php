<?php
class ItemsRepository extends RepositoryBase
{
  public function getItems( array $select = [], array $filter = [] ):array
  {
    $rows = CIBlockElement::GetList( [], $filter, false, false, $select );
    $result = [];

    while( $row = $rows->GetNext() ){
      $result[] = $row;
    }

    return $result;
  }

  public function getPropertyDictionary():array
  {
    $arFilter = [
      'IBLOCK_ID' => ConfigProvider::getCatalogIBId()
    ];
    $rows = CIBlockProperty::GetList([], $arFilter);
    $result = [];
    while ( $row = $rows->GetNext() ){
      $result[ $row['ID'] ] = $row['CODE'];
    }

    return $result;
  }

  public function getBrands():array
  {

  }

  public function getItemsSKU():array
  {

  }

  public function getQuarnatineItems():array
  {

  }

  public function getReservedItems():array
  {

  }
}
 ?>
