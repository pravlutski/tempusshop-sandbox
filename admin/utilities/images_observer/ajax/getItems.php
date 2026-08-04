<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

function getNextElement($currentKey, $array, $returnKey = false) {
    if (!is_array($array) || empty($array)) {
        return null;
    }

    $keys = array_keys($array);
    $currentIndex = array_search($currentKey, $keys, true);

    // Строгая проверка на false, т.к. 0 тоже валидный индекс
    if ($currentIndex === false) {
        return null;
    }

    // Проверяем, не последний ли это элемент
    if ($currentIndex === count($keys) - 1) {
        return null;
    }

    $nextKey = $keys[$currentIndex + 1];

    return $returnKey ? $nextKey : $array[$nextKey];
}

$lastElement = $_POST['last'];
$action = $_POST['action'];

$arFilter = [
  'IBLOCK_ID' => 1054,
];
$arSelect = [
  'IBLOCK_ID',
  'ID',
  'NAME',
  'PROPERTY_CARD_ID',
  'PROPERTY_REVIEW_SOURCE',
  'PROPERTY_REVIEW_TEXT',
  'PROPERTY_REVIEW_AUTHOR',
  'PROPERTY_REVIEW_IMAGES',
  'PROPERTY_REVIEW_IMAGES_MODERATED',
];

$res = CIBlockElement::GetList( ['ID' => 'ASC'], $arFilter, false, false, $arSelect );
$items = [];
while ( $row = $res->GetNext() ){
  $images = $row['PROPERTY_REVIEW_IMAGES_VALUE'];
  $moderated = $row['PROPERTY_REVIEW_IMAGES_MODERATED_VALUE'] ?? [];
  foreach ( $images as $key => $link ){
    $items[ $row['ID'] . '_' . $key ] = [
      'name' => $row['NAME'],
      'card' => $row['PROPERTY_CARD_ID_VALUE'],
      'source' => $row['PROPERTY_REVIEW_SOURCE_VALUE'],
      'text' => $row['PROPERTY_REVIEW_TEXT_VALUE'],
      'author' => $row['PROPERTY_REVIEW_AUTHOR_VALUE'] ?? 'unknown',
      'image' => $link,
      'is_moderated' => in_array($link, $moderated) ? true : false,
    ];
  }

}


if ( empty($lastElement) ){
  $id = array_key_first( $items );
}else{
  switch ($action){
    case 'next':
      $id = getNextElement( $lastElement, $items, true );
      break;
    case 'prev':
      $id = getPrevElement( $lastElement, $items, true );
      break;
  }
}

$picture = $items[ $id ];
 ?>

 <div class="item-container">
   <div class="item-content">
     <div class="item-image">
       <img src="<?=$picture['image']?>" alt="">
     </div>
     <div class="item-info">
       <h3 class="item-name"><?=$picture['NAME']?></h3>
       <p class="quote">@<?=$picture['author']?></p>
       <p class="item-text"><?=$picture['TEXT']?></p>
       <?php if ( $picture['moderated'] ): ?>
         <p class="item-moderated mod-good">Одобрено</p>
       <?php endif; ?>
     </div>
   </div>
   <div class="item-control">
     <button class="nav-btn nav-prev" data-id="<?=$id?>" data-action="prev">Назад</button>
     <button class="nav-btn nav-next" data-id="<?=$id?>" data-action="next">Вперёд</button>
     <button class="nav-btn like-btn" data-id="<?=$id?>" data-action="next">Одобрить</button>
   </div>
 </div>
