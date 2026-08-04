<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
?>

<div class="search-stats">
    <h1>Статистика поисковых запросов</h1>

    <?php if ($arResult['ERROR']): ?>
        <div class="alert alert-danger"><?= $arResult['ERROR'] ?></div>
    <?php endif; ?>

    <!-- Фильтры -->
    <div class="stats-filters mb-4">
        <form method="get" class="form-inline">
            <div class="form-group mr-3">
                <label>С:</label>
                <input type="date" name="date_from" value="<?= $arResult['filters']['date_from'] ?>" class="form-control ml-2">
            </div>
            <div class="form-group mr-3">
                <label>По:</label>
                <input type="date" name="date_to" value="<?= $arResult['filters']['date_to'] ?>" class="form-control ml-2">
            </div>
            <div class="form-group mr-3">
                <label>Лимит:</label>
                <select name="limit" class="form-control ml-2">
                    <option value="50" <?= $arResult['filters']['limit'] == 50 ? 'selected' : '' ?>>50</option>
                    <option value="100" <?= $arResult['filters']['limit'] == 100 ? 'selected' : '' ?>>100</option>
                    <option value="200" <?= $arResult['filters']['limit'] == 200 ? 'selected' : '' ?>>200</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Применить</button>
        </form>
    </div>

    <!-- Статистика -->
    <div class="stats-summary mb-3">
        <strong>Всего уникальных запросов: <?= $arResult['total_queries'] ?></strong>
    </div>

    <!-- Таблица -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>Запрос</th>
                    <th>Кол-во поисков</th>
                    <th>Время выполнения (с)</th>
                    <th>Результаты (макс)</th>
                    <th>Результаты (мин)</th>
                    <th>Последний поиск</th>
                    <th>Примеры артикулов</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($arResult['stats'] as $stat): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($stat['query']) ?></strong></td>
                    <td><?= $stat['total_searches'] ?></td>
                    <td><?= $stat['avg_execution_time'] ?></td>
                    <td><?= $stat['max_results'] ?></td>
                    <td><?= $stat['min_results'] ?></td>
                    <td><?= $stat['last_search'] ?></td>
					<td>
						<?php if (!empty($stat['product_articles'])): ?>
							<div class="product-articles">
								<small><?= htmlspecialchars($stat['product_articles']) ?></small>
							</div>
						<?php else: ?>
							<span class="text-muted">нет результатов</span>
						<?php endif; ?>
					</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (empty($arResult['stats'])): ?>
        <div class="alert alert-info">Нет данных за выбранный период</div>
    <?php endif; ?>
</div>

<style>

</style>