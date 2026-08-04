<?php
class DPUtils
{
  public static function buildWhereInCondition( string $field, array $data ):string
  {
    $template = "%s IN (%s)";

    $dataFormatted = array_map(function($item){
      return "'".$item."'";
    }, $data);

    $string = implode( ',', $dataFormatted );

    return sprintf( $template, $field, $string );
  }
}
 ?>
