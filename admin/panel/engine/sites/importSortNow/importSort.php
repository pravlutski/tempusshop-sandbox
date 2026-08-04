<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

function csv_to_array(string $csv_file_path, string $delimiter = ',', string $enclosure = '"', string $escape = '\\', bool $header = true): array|false
{
    $data = [];

    if (($handle = fopen($csv_file_path, 'r')) !== false) {
        $header_row = null;

        if ($header) {
            // Получаем строку заголовков
            $header_row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape);
            if ($header_row === false) {
                fclose($handle);
                return false; // Ошибка чтения заголовка
            }
        }

        while (($row = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
            if ($header) {
                // Создаем ассоциативный массив
                $data_row = [];
                if (count($header_row) !== count($row)) {
                    // Количество столбцов не совпадает - обработка ошибки (например, пропустить строку)
                    // Можно добавить логирование ошибки
                    continue; // Пропускаем строку
                }

                for ($i = 0; $i < count($header_row); $i++) {
                    $data_row[$header_row[$i]] = $row[$i];
                }
                $data[] = $data_row;
            } else {
                // Создаем простой массив
                $data[] = $row;
            }
        }

        fclose($handle);
        return $data;
    } else {
        return false; // Ошибка открытия файла
    }
}

$csv_file = 'sort.csv';
$data = csv_to_array($csv_file, ';', '"', '\\', false);
$models = [];
foreach ( $data as $arModel ){
  $models[ $arModel[0] ] = [
    'sort' => intval($arModel[1]),
    'id' => 0
  ];
}

$arFilter = [
  'IBLOCK_ID' => 16,
  'PROPERTY_CML2_ARTICLE' => array_keys($models),
];

$arSelect = [ 'IBLOCK_ID', 'ID', 'PROPERTY_CML2_ARTICLE' ];

$result = CIBlockElement::GetList(
  array(),
  $arFilter,
  false,
  false,
  $arSelect
);

while ( $row = $result->GetNext() ){
  $models[ $row['PROPERTY_CML2_ARTICLE_VALUE'] ]['id'] = $row['ID'];
}
var_dump($models);
die;
foreach ( $models as $model => $arData ){
  if ( !empty($arData['id']) ){
    // CIBlockElement::SetPropertyValueCode(
    //   $arData['id'],
    //   "SORT",
    //   array('VALUE' => $arData['sort'])
    // );
    var_dump($model);
  }
  die;
}
 ?>
