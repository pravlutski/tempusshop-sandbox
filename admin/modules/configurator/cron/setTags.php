<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

class TagsSetterMP
{
  private $db;
  private $tags = [];
  private $props = [];
  private $excludeTags = [];
  private $items = [];

 public function __construct( $debug = false )
 {
   global $DB;
   $this->db = $DB;
   $this->debug = $debug;
 }

 public function run()
 {
   var_dump( date('G:i:s') );
   $this->getTagsFromTable();
   $this->getItems();
   // var_dump( count($this->items) );
   // var_dump( $this->items[0] );
   // die;
   $this->clearTags();
   $this->setTags();
   var_dump( date( 'G:i:s' ) );
 }

 public function getItems():void
 {
   $strSql = "SELECT model, bitrix_id FROM ci_price WHERE active_wb = 'Y' OR active_ozti = 'Y'";
   $res = $this->db->Query( $strSql );
   $models = [];
   while ( $row = $res->Fetch() ){
     $models[] = $row['model'];
   }
   // var_dump( count($models) );
   // var_dump( $models );
   // die;
   // $models = [ "RA-AR0010R" ];
   // $models = [ "A-158WA-1" ];
   // $models = [ "MTP-1303D-1A" ];
   // $models = [ "LTP-V007D-7E" ];
   // $models = array_chunk( $models, 1000 )[0];
   $arFilter = [
     'IBLOCK_ID' => 16,
     'PROPERTY_CML2_ARTICLE' => $models,
     // '!PROPERTY_BRAND' => 7971
   ];
   $arSelect = ['IBLOCK_ID', 'IBLOCK_SECTION_ID', 'ID', 'PROPERTY_CML2_ARTICLE'];
   foreach( $this->props as $prop ){
     $arSelect[] = "PROPERTY_{$prop}";
   }

   $result = CIBlockElement::GetList( [], $arFilter, false, false, $arSelect );

   while( $row = $result->GetNextElement() ){
     $item = $row->GetFields();

     $this->items[] = [
       'ID' => $item['ID'],
       'MODEL' => $item['PROPERTY_CML2_ARTICLE_VALUE'],
       'TAGS' => self::getTagFromArray( $item, 'str', $this->debug ),
       // 'IDTAGS' => self::getTagFromArray( $item, 'id' )
     ];
   }
 }

 public function getTagsFromTable():void
 {
   $strSql = "SELECT * FROM ci_configurator_tags WHERE active = 'Y'";
   $res = $this->db->Query( $strSql );
   $this->tags = [];
   while ( $row = $res->Fetch() ){
      // Скипаем теги без установленных для них условий
      if ( empty($row['properties_json']) || $row['properties_json'] == '{}' ) continue;
      // Удаляем раздел наручные часы из массива с разделами
      $sectionTmp = json_decode( $row['sections_json'], true );
      $section = [];
      foreach ( $sectionTmp as $s ) {
        if ( $s == 932 ) continue;
        $section[] = $s;
      }

      $this->tags[ $row['id'] ] = [
        'id' => $row['id'],
        'tag' => $row['tag_name'],
        'section' => $section,
        'options' => self::makeassoc( json_decode( $row['properties_json'], true ) ?? [] ),
        'sort_order' => $row['sort_order'],
      ];

      // собираем массив свойств для arSelect
     $opts = json_decode( $row['properties_json'], true );
     if ( empty($opts) ) continue;
     foreach ( $opts as $o ){
       if ( !in_array( $o[0], $this->props ) && !empty( $o[0] ) ){
         $this->props[] = $o[0];
       }
     }

   }
 }

 public function getTagFromArray( array $item, string $param, bool $debug = false ):string
 {
   $checkArray = [];
   $tagList = [];
   $tagStr = '';
   // Счетчики для дебага
   $countDuplicates = 0;
   $countSkip = 0;
   $countCondDM = 0;

   foreach ( $this->tags as $id => $tag ){
     if ( isset($tagList[ $tag['tag'] ]) ){
        $countDuplicates++;
        continue;
     }

     if ( !empty($tag['section']) && !in_array( $item['IBLOCK_SECTION_ID'], $tag['section'] ) ){
       $countSkip++;
       continue;
     }

     $checkArray[$id] = true;

     // Перебираем каждый параметр каждого тега
     foreach ( $tag['options'] as $optName => $optValues ){
       foreach ( $optValues as $value ){
         // Проверяем является ли значение числом
         if ( preg_match('/[0-9]+/', $value) ){

           if ( $debug ){
             var_dump('PREG MATCHED ('. $id . ' --- ' .$tag['tag']. ')');
             var_dump( 'option name: ' . $optName );
             var_dump( 'Value: ' . $value );
             var_dump ( 'Prop: ' );
             var_dump( $item["PROPERTY_{$optName}_VALUE"] );
             print_r( "\n" );
           }
           // Проверяем на то, является ли свойство множественным
           if ( is_array( $item["PROPERTY_{$optName}_VALUE"] ) ){

             if ( $debug ){
               var_dump( $optName . ' -- is an array' );
               var_dump( 'prop value: ' . ($item["PROPERTY_{$optName}_VALUE"][$value] ?? 'does not exist') );
               print_r( "-------------\n" );
             }

             // Проверяем свойство на заполненность нужными значениями
             if ( !isset( $item["PROPERTY_{$optName}_VALUE"][$value] ) ){
               $checkArray[$id] = false;
               $countCondDM++;
               break 2;
             }
             if ( $debug ){
               var_dump( 'tag ' .$tag['tag']. ' applied' );
             }
           }else{

             if ( $debug ){
               var_dump( $optName . ' -- is not an array' );
               var_dump( 'prop enum_id: ' . $item["PROPERTY_{$optName}_ENUM_ID"] );
               var_dump( 'expected tag value: ' . $value );
               var_dump( 'item value: ' . $item["PROPERTY_{$optName}_VALUE"] );
             }

             if ( !empty( $item["PROPERTY_{$optName}_ENUM_ID"] ) ){
               if ( $item["PROPERTY_{$optName}_ENUM_ID"] != $value ){

                 if ( $debug ){
                   var_dump( 'enumID compairing result: ' . ($item["PROPERTY_{$optName}_ENUM_ID"] == $value ? 'true' : 'false') );
                 }

                 $checkArray[$id] = false;
                 $countCondDM++;
                 break 2;
               }
               if ( $debug ){
                 var_dump( 'tag ' .$tag['tag']. ' applied' );
               }
             }else{
               if ( intval($item["PROPERTY_{$optName}_VALUE"]) != intval($value) ){

                 if ( $debug ){
                   var_dump( 'value compairing result: ' . ($item["PROPERTY_{$optName}_VALUE"] == $value ? 'true' : 'false') );
                 }

                 $checkArray[$id] = false;
                 $countCondDM++;
                 break 2;
               }
               if ( $debug ){
                 var_dump( 'tag ' .$tag['tag']. ' applied' );
               }
             }

             if ( $debug ){
               print_r( "-------------\n" );
             }

           }
         }else{

            if ( $debug ){
              var_dump('value is not numeric: ' . $value );
            }

           if ( $item["PROPERTY_{$optName}_VALUE"] != $value ){
             $checkArray[$id] = false;
             $countCondDM++;
             break 2;
           }
           if ( $debug ){
             var_dump( 'tag ' .$tag['tag']. ' applied' );
           }
         }

       }
     }

     if ( $checkArray[$id] ){
       if ( $param == 'str' ){
         $tagList[ $tag['tag'] ] = [
           'tag' => $tag['tag'],
           'sort' => $tag['sort_order']
         ];
       }else{
         $tagList[ $tag['tag'] ] = [
           'tag' => $tag['id'],
           'sort' => $tag['sort_order']
         ];
       }
     }
   }

   if ( $debug ){
     print_r("#################################\n");
     var_dump('skipped duplicates: ' . $countDuplicates);
     var_dump('skipped cause section: ' . $countSkip);
     var_dump('skipped cause condition does not match: ' . $countCondDM);
     var_dump( 'tags count: ' . count($this->tags) );
     var_dump( "section: " . $item["IBLOCK_SECTION_ID"] );
     print_r("#################################\n");
   }

   usort( $tagList, function($a,$b){
     return $b['sort'] <=> $a['sort'];
   });
   foreach ( $tagList as $key => $tag ){
     $str = self::validateTag($tag['tag']);
     if ( !$str ) continue;
     $tagListTmp[] = $str;
   }
   foreach ( $tagListTmp as $key => $tag ){
     if ( $key == count($tagListTmp) - 1 ){
       $tagStr .= $tag;
       continue;
     }
     $tagStr .= $tag . ';';
   }

   $strRes = self::removeDuplicateTags($tagStr, 3, $this->debug);
   if ( $debug ) print_r("#################################\n");
   $strRes = mb_substr($strRes, 0, 255);
   $strRes = mb_substr($strRes, 0, mb_strrpos($strRes, ';') );

   return $strRes;
 }

 static private function validateTag( string $tag ):string
 {
   $prepositions = [
     "в",
     "для",
     "на",
     "с",
     "от",
     "до",
     "из",
     "под",
     "за",
     "по",
     "без",
     "со",
     "через",
     'мм',
     'к'
   ];
   $wordArray = explode( ' ', $tag );

   if ( in_array( mb_strtolower( end($wordArray) ), $prepositions ) ) return false;
   if ( count($wordArray) == 2 && in_array(mb_strtolower( $wordArray[0] ), $prepositions) ) return false;
   if ( count($wordArray) == 1 && !mb_strpos($wordArray[0], 'час') ) return false;

   return $tag;
 }

 static public function makeassoc( array $fckingJSON ):array
 {
   if ( empty($fckingJSON) ) return [];
   $result = [];
   foreach ($fckingJSON as $key => $value) {
     if ( is_array($value) && !empty( $value[1] ) ){
       $result[ $value[0] ][] = $value[1];
     }
   }

   return $result;
 }

 static private function removeDuplicateTags(string $tagsStr, int $maxDistance, bool $debug = false): string
 {
     $tagsAr = explode(';', $tagsStr);
     $uniqueTags = [];

     foreach ($tagsAr as $tag) {
         $duplicate = false;

         foreach ($uniqueTags as $utag) {
             // 1. Проверяем, являются ли теги просто перестановкой слов
             $tagWords = explode(' ', $tag);
             $utagWords = explode(' ', $utag);

             if (count($tagWords) == count($utagWords)) {
                 sort($tagWords);
                 sort($utagWords);
                 if ($tagWords == $utagWords) {
                    if ( $debug ){
                      var_dump("{$tag} - {$utag} | order issue, no Levenshtein. DUPLICATE");
                    }
                     $duplicate = true; // Это перестановка слов
                     break;
                 }else{
                   $tagStr = implode( ' ', $tagWords );
                   $utagStr = implode( ' ', $utagWords );
                   // $dist = self::levenshteinDistance($tagStr, $utagStr);
                   $dist = levenshtein($tagStr, $utagStr);
                   if ( $dist <= $maxDistance ){
                     if ( $debug ){
                       var_dump("{$tagStr} - {$utagStr} | order issue and cannot pass Levenshtein. DUPLICATE");
                     }
                     $duplicate = true;
                     break;
                   }
                 }
             }

             // 2. Если это не просто перестановка, используем расстояние Левенштейна
             // $distance = self::levenshteinDistance($tag, $utag);
             $distance = levenshtein($tag, $utag);
             if ($distance <= $maxDistance) {
               if ( $debug ){
                 var_dump("{$tag} - {$utag} | cannot pass Levenshtein. DUPLICATE");
               }
                 $duplicate = true; // Теги "похожи" по Левенштейну
                 break;
             }
             unset($tagStr);
             unset($utagStr);
             unset($tagWords);
             unset($utagWords);
         }

         if (!$duplicate) {
             $uniqueTags[] = $tag;
         }
     }

     return implode(';', $uniqueTags);
 }

 public function setTags():void
 {
   if ( empty( $this->items ) ) die('$this->items is empty');

   foreach ( $this->items as $item ){
     print_r( $item["ID"] . ' --- set' . PHP_EOL);
     CIBlockElement::SetPropertyValuesEx(
       intval( $item["ID"] ),
       16,
       array('MARKETPLACE_WB_TAGS' => $item['TAGS'])
     );
   }
 }

 public function clearTags():void
 {
   if ( empty( $this->items ) ) die('$this->items is empty');

   foreach ( $this->items as $item ){

     print_r( $item["ID"] . ' --- del' . PHP_EOL);
     CIBlockElement::SetPropertyValuesEx(
       intval( $item["ID"] ),
       16,
       array('MARKETPLACE_WB_TAGS' => '')
     );

   }
 }

}

( new TagsSetterMP($argv[1] ?? false) )->run();
 ?>
