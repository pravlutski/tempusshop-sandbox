<?php
class AdvertFileManager
{
  public function __construct()
  {
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
    require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
  }

  public function getCompetetitorFile():array
  {
    return $this->parseXlsx( AdvertConfigProvider::getCompetitorFilePath(), 4 );
  }

  public function getOwnFile():array
  {
    return array_map(
      function ($item) {
        return round($item * AdvertConfigProvider::getAverageCoInvest() );
      },
      $this->parseXlsx( AdvertConfigProvider::getOwnFilePath(), 1 )
    );
  }

  private function parseCsv($filename, $delimiter = ',', $enclosure = '"', $escape = '\\')
  {
    $data = [];

    if (!file_exists($filename) || !is_readable($filename)) {
      throw new Exception("Файл $filename не существует или недоступен для чтения");
    }

    if ( ($handle = fopen($filename, 'r')) !== false ) {
      // Чтение заголовков (первая строка)
      $headers = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);

      // Чтение остальных строк
      while ( ($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape) ) !== false) {

        if (count($headers) === count($row)) {
          $data[] = array_combine($headers, $row);
        } else {
          // Если количество полей не совпадает, добавляем как есть
          $data[] = $row;
          }
        }

        fclose($handle);
      }

      return $data;
  }

  private function parseXlsx( string $filename, int $priceCol = 1 ):array
  {
    $xls = PHPExcel_IOFactory::load( $filename );

    $xls->setActiveSheetIndex(0);
    $sheet = $xls->getActiveSheet();
    $result = [];

    foreach ( $sheet->toArray() as $key => $row ) {
      if ( $key == 0 ) continue;
      if ( empty( $row[0] ) || empty( $row[$priceCol] ) ) continue;
      $result[ $row[0] ] = (int) str_replace(',', '', $row[ $priceCol ]);
    }

    return $result;
  }
}
 ?>
