<?php
class FileProcessor
{
  private string $path = '/upload/tmp/purchase/return_%s.csv';

  public function __construct( private string $root ){}

  public function getFileContents( string $hash, string $divider = ';' ):array
  {
    $filename = $this->root . sprintf( $this->path, $hash );

    if ( !file_exists($filename) ) throw new Exception("File {$hash} not found");

    $content = file_get_contents( $filename );
    if ( !$content ) throw new Exception("Cannot read file {$hash}");

    $rows = array_map(
      fn($val) => iconv("WINDOWS-1251", "utf-8", $val),
      explode( PHP_EOL, $content )
    );
    array_splice( $rows, 0, 2 );
    $data = array_map( fn($val) => explode($divider, $val), $rows );
    $result = array_map( fn($val) => $this->buildAssociativeArray($val), $data );
    $result = array_filter( $result, fn($val) => !empty($val['model']) );

    return $result;
  }

  private function buildAssociativeArray( array $array ):array
  {
    $arModel = explode(' ', $array[0]);
    $model = str_replace( '"', '', end($arModel) );
    $price = str_replace( '"', '', $array[1] );

    return [ 'model' => trim( $model ), 'price' => trim( $price ), 'quantity' => 1 ];
  }
}
 ?>
