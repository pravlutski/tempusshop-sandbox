<?php

/**
 * @global CMain $APPLICATION
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if (!CModule::IncludeModule('panel.manager')) {
	return;
}

opcache_reset();

CModule::IncludeModule("iblock");

$APPLICATION->SetTitle('Метрика по посещаемости сайтов');

global $DB;

class customPrice
{
	public static function getList()
	{
		global $DB;

		$strSql = "SELECT * FROM `ci_site_metrika`";

		return $DB->Query($strSql, false);
	}
}

$customPrice = new customPrice();

$price_elements = $customPrice->getList();

?>
	<link href="/admin/modules/configurator/style.css" rel="stylesheet">
	<h1 class="page-header">
		Метрика по посещаемости сайтов
	</h1>

	<div class="row">
		<div class="col-sm-12">
			<table class="table tablesorter">
				<thead>
				<tr>
					<th>
						<span>ID</span>
					</th>
					<th>
						<span>IP</span>
					</th>
					<th>
                        <span>
                            Домен
                        </span>
					</th>
					<th>
                        <span>
                            Страница
                        </span>
					</th>
					<th>
                        <span>
                            GET запрос(ы)
                        </span>
					</th>
					<th>
                        <span>
							Время
                        </span>
					</th>
					<th>
                        <span>
                            Дата
                        </span>
					</th>
				</tr>
				</thead>
				<tbody>
				<?php while ($row = $price_elements->Fetch()): ?>
					<tr>
						<td><?=$row['id'];?></td>
						<td><?=$row['ip'];?></td>
						<td><?=$row['domain'];?></td>
						<td><?=$row['page'];?></td>
						<td><?=$row['get'];?></td>
						<td><?=$row['time'];?></td>
						<td><?=$row['date'];?></td>
					</tr>
				<?php endwhile;?>
				</tbody>
			</table>
		</div>
	</div>
	<script src="/admin/modules/custom_analiz_price/sort.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
			$("table").tablesorter({
				widgets: ['zebra'],
				headers: {
				}
			});
		});
	</script>
<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
