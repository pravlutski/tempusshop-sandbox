<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
global $USER;

$displaySettings = $_POST['display'];
$userId = $USER->GetId();
$path = "{$_SERVER['DOCUMENT_ROOT']}/admin/utilities/dpu/settings/{$userId}.json";
$data = json_encode([
  'displaySettings' => $displaySettings
]);
file_put_contents( $path, $data );
 ?>
