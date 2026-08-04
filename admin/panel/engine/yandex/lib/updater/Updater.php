<?php
class Updater // Костыль до момента, пока не доберусь до написания классов базы
{
  public function __construct(
    private ?\Bitrix\Main\DB\MysqliConnection $main,
    private ?DBPanel $panel,
    private bool $isPanel = false
  )
  {}

  public function panel():DBPanel
  {
    return $this->panel;
  }

  public function insertOne( string $into, array $values ):void
  {
    $this->insertSome( $into, [$values] );
  }

  public function update( string $table, array $values, array $where ):void
  {
    // if ( $this->isPanel ){
    //   $wherePanel = [];
    //   foreach ( $where as $column => $value ){
    //     $wherePanel[] = [
    //       'column' => $column,
    //       'operator' => '=',
    //       'value' => $value
    //     ];
    //   }
    //   $this->panel->update( $table, $values, $wherePanel );
    //   return;
    // }

    $setStates = [];
    $whereState = [];
    foreach ( $values as $col => $val ){
      if ( $val === null ){
        $setStates[] = "`{$col}` = null";
        continue;
      }
      $setStates[] = "`{$col}` = '{$val}'";
    }
    foreach ( $where as $col => $val ){
      $whereState = "{$col} = '{$val}'";
    }

    $set = implode(',', $setStates);
    $strSql = "UPDATE {$table} SET {$set} WHERE {$whereState}";

    if ( $this->isPanel ){
      $this->panel->query( $strSql );
      return;
    }
    $this->main->Query( $strSql );
  }

  public function delete( string $table, string $field, mixed $value ):void
  {
    if ( empty($field) ) throw new Exception("empty field");
    if ( empty($value) || is_array($value) ) throw new Exception("incorrect value");

    $strSql = "DELETE FROM {$table} WHERE $field = '{$value}'";
    $this->panel->query( $strSql );
  }

  public function insertSome( string $into, array $values ):void
  {
    if ( $this->isPanel ){
      $this->panel->insert( $into, $values);
      return;
    }

    $this->buildAndProcessInsertQuery( $into, $value );
  }

  private function buildAndProcessInsertQuery( $into, $values )
  {
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

    $this->main->Query( $strSql );
  }
}
 ?>
