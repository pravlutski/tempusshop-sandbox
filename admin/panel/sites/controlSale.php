1<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>

<?
    $APPLICATION->SetPageProperty("page_h1", "Контроль заказов - модуль сайтов");
    $APPLICATION->SetTitle('Контроль заказов - модуль сайтов');

$tmp = file_get_contents("/var/www/bitrix/data/www/tempus.ru/local/control/last.txt");
$arLog = json_decode($tmp,true);

// print_r($arLog2);

function sortByCreatedAt($a, $b) {
    $getPriorityCreatedAt = function($item) {
        if (!empty($item['CRM']['createdAt'])) {
            return $item['CRM']['createdAt'];
        }
        if (!empty($item['SITE']['createdAt']) && is_string($item['SITE']['createdAt'])) {
            return $item['SITE']['createdAt'];
        }
        if (!empty($item['BACK']['createdAt'])) {
            return $item['BACK']['createdAt'];
        }
        if (!empty($item['MS']['createdAt'])) {
            return preg_replace('/\.\d+/', '', $item['MS']['createdAt']);
        }
        return '0000-00-00 00:00:00';
    };

    $aDate = $getPriorityCreatedAt($a);
    $bDate = $getPriorityCreatedAt($b);

    return strtotime($bDate) - strtotime($aDate);
}

uasort($arLog, 'sortByCreatedAt');




$printArr = [];
?>
<div class="row">
  <form method="GET" action="/admin/panel/sites/controlSale.php" class="s-form" style="display: flex;  gap: 20px;  margin-bottom: 30px;">
    <div class="input-group" style="  display: flex;
  gap: 10px;
  align-items: center;">
      <label>Кабинет</label>
      <select class="form-select form-select-sm" name="source" aria-label=".form-select-sm example">
          <option value="all" selected>Все</option>
          <option value="sites" <?if ($_GET['source'] == 'sites') { echo "selected"; }?>>Сайты</option>
          <option value="market" <?if ($_GET['source'] == 'market') { echo "selected"; }?>>Маркетплейсы</option>
      </select>
    </div>
    <div class="input-group" style="  display: flex;
  gap: 10px;
  align-items: center;">
      <label>Только ошибки</label>
      <input type="checkbox" id="errors" name="errors" />
    </div>
    <div class="input-group">
      <button type="submit" class="btn btn-warning">Поиск</button>
    </div>
  </form>
</div>
<table class="orders-table">
    <thead>
        <tr>
            <th>Номер заказа</th>
            <th>CRM</th>
            <th>SITE</th>
            <th>BACK</th>
            <th>MS</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($arLog as $orderNumber => $sections): ?>
            <?php

            $values = [];
            $allMatch = true;
            $statusM = true;
            $summM = true;
            // print_r('###<br>');
            // print_r($sections['BACK']['source'].'<br>');

            if ($_GET['source'] == 'sites') {
              if ($sections['BACK']['source'] != 'SITES' && !empty($sections['BACK']['source'])) {
                // print_r('1<br>');
                unset($arLog[$orderNumber]);
                continue;
              }
            }
            if ($_GET['source'] == 'market') {
              if ($sections['BACK']['source'] == 'SITES' || empty($sections['BACK']['source'])) {
                // print_r('2<br>');
                unset($arLog[$orderNumber]);
                continue;
              }
            }
            foreach (['CRM', 'SITE', 'BACK', 'MS'] as $section) {
                $values[$section]['summ'] = $sections[$section]['summ'] ?? 'Нет данных';
                $values[$section]['status'] = $sections[$section]['status'] ?? 'Нет данных';
                $values[$section]['createdAt'] = $sections[$section]['createdAt'] ?? 'Нет данных';
                $values[$section]['update'] = $sections[$section]['update'] ?? 'Нет данных';
                $values[$section]['source'] = $sections[$section]['source'];
            }


            $monthAgo = new DateTime('-2 weeks');
            $hasValidUpdate = false;

            foreach ($values as $sectionData) {
                if (!empty($sectionData['update']) && $sectionData['update'] != 'Нет данных') {
                    $updateDate = DateTime::createFromFormat('Y-m-d H:i:s', $sectionData['update']);
                    if ($updateDate && $updateDate >= $monthAgo) {
                        $hasValidUpdate = true;
                        break;
                    }
                }
            }

            if (!$hasValidUpdate) {
                unset($arLog[$orderNumber]);
                continue;
            }

            if (strpos($orderNumber, 'A') !== false) {
              unset($arLog[$orderNumber]);
              continue;
            }

            $key = 0;
            // print_r($values['BACK']['source']);
            if ($values['BACK']['source'] == 'SITES' || empty($values['BACK']['source'])) {
              $firstSumm = $values['CRM']['summ'];
              $firstStatus = $values['CRM']['status'];
              $key = 1;
            } else {
              unset($values['SITE']);
              unset($values['CRM']);
              $firstSumm = $values['BACK']['summ'];
              $firstStatus = $values['BACK']['status'];
            }


            foreach ($values as $sectionData) {
                if ($sectionData['summ'] !== $firstSumm ){
                    $allMatch = false;
                    $summM = false;

                    break;
                }
                if ( $sectionData['status'] !== $firstStatus) {
                   $allMatch = false;
                   $statusM = false;
                   break;
                 }

            }
            if ($allMatch == false) {
              $printArr[] = $orderNumber;
            }

            if ($allMatch != false && $_GET['errors'] == 'on') {
              continue;
            } else {
              $ordersId[] = $orderNumber;
            }
            ?>
            <tr class="<?= $allMatch ? 'match-row' : 'mismatch-row' ?>">
                <td><?= htmlspecialchars($orderNumber) ?></td>
                <?if ($key == 1) {?>
                <!-- CRM -->
                <td>
                    <div class="section-title">CRM</div>
                    <div class="property">
                        <span class="property-name">summ:</span>
                        <span class="<?= (!$summM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['CRM']['summ']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">status:</span>
                        <span class="<?= (!$statusM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['CRM']['status']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">created:</span>
                        <span>
                            <?= htmlspecialchars($values['CRM']['createdAt']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">update:</span>
                        <span>
                            <?= htmlspecialchars($values['CRM']['update']) ?>
                        </span>
                    </div>
                </td>

                <!-- SITE -->
                <td>
                    <div class="section-title">SITE</div>
                    <div class="property">
                        <span class="property-name">summ:</span>
                        <span class="<?= (!$summM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['SITE']['summ']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">status:</span>
                        <span class="<?= (!$statusM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['SITE']['status']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">created:</span>
                        <span>
                          <?= htmlspecialchars($values['SITE']['createdAt']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">update:</span>
                        <span>
                          <?= htmlspecialchars($values['SITE']['update']) ?>
                        </span>
                    </div>
                </td>
              <?} else {?>
                <td>
                    <div class="section-title">CRM</div>
                    <div class="property">
                      ЗАКАЗ ИЗ<br>МАРКЕТПЛЕЙСОВ
                    </div>
                </td>

                <!-- SITE -->
                <td>
                    <div class="section-title">SITE</div>
                    <div class="property">
                      ЗАКАЗ ИЗ<br>МАРКЕТПЛЕЙСОВ
                    </div>
                </td>
              <?}?>
                <!-- BACK -->
                <td>
                    <div class="section-title">BACK</div>
                    <div class="property">
                        <span class="property-name">summ:</span>
                        <span class="<?= (!$summM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['BACK']['summ']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">status:</span>
                        <span class="<?= (!$statusM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['BACK']['status']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">created:</span>
                        <span>
                            <?= htmlspecialchars($values['BACK']['createdAt']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">update:</span>
                        <span>
                            <?= htmlspecialchars($values['BACK']['update']) ?>
                        </span>
                    </div>
                </td>

                <!-- MS -->
                <td>
                    <div class="section-title">MS</div>
                    <div class="property">
                        <span class="property-name">summ:</span>
                        <span class="<?= (!$summM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['MS']['summ']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">status:</span>
                        <span class="<?= (!$statusM) ? 'diff-value' : '' ?>">
                            <?= htmlspecialchars($values['MS']['status']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">created:</span>
                        <span>
                            <?= htmlspecialchars($values['MS']['createdAt']) ?>
                        </span>
                    </div>
                    <div class="property">
                        <span class="property-name">update:</span>
                        <span>
                            <?= htmlspecialchars($values['MS']['update']) ?>
                        </span>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>

        <?//print_r($ordersId);?>
    </tbody>
</table>
<?//print_r(implode(',',$printArr));?>
<style>
    .match-row {
        background-color: #ddffdd; /* Зеленый для совпадающих строк */
    }
    .mismatch-row {
        background-color: #ffdddd; /* Красный для несовпадающих строк */
    }
    .diff-value {
        color: #ff0000;
        font-weight: bold;
    }
    .section-title {
        font-weight: bold;
        margin-bottom: 5px;
    }
    .property {
        margin-bottom: 3px;
    }
    .property-name {
        font-weight: bold;
    }
    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }
    .orders-table th, .orders-table td {
        border: 1px solid #ddd;
        padding: 8px;
    }
    .orders-table th {
        background-color: #f2f2f2;
    }
</style>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
