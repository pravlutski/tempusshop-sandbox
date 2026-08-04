<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="rabbitmq-status">
    <h2>Статус RabbitMQ</h2>
    
    <div class="connection-status status-<?= strtolower($arResult['CONNECTION_STATUS']) ?>">
        Соединение: <?= $arResult['CONNECTION_STATUS'] ?>
        <?php if ($arResult['ERROR']): ?>
            <div class="error-detail"><?= htmlspecialcharsbx($arResult['ERROR']) ?></div>
        <?php endif; ?>
    </div>
    
    <table class="status-table">
        <thead>
            <tr>
                <th>Очередь</th>
                <th>Статус</th>
                <th>Сообщений</th>
                <th>Потребителей</th>
                <th>Dead Letters</th>
                <?/*<th>Последняя активность</th>*/?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($arResult['QUEUES'] as $queueName => $queueInfo): ?>
            <tr>
                <td><?= htmlspecialcharsbx($queueName) ?></td>
                <td class="status-<?= strtolower($queueInfo['STATUS']) ?>">
                    <?= $queueInfo['STATUS'] ?>
                    <?php if ($queueInfo['ERROR'] ?? false): ?>
                        <div class="error-detail"><?= htmlspecialcharsbx($queueInfo['ERROR']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $queueInfo['MESSAGES'] ?></td>
                <td><?= $queueInfo['CONSUMERS'] ?></td>
                <td><?= $queueInfo['DEAD_LETTERS'] ?></td>
				<?/*<td><?= $queueInfo['LAST_ACTIVITY'] ?? 'N/A' ?></td>*/?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="last-updated">
        Обновлено: <?= date('d.m.Y H:i:s') ?>
    </div>
</div>
<script>
    // Автообновление каждые 30 секунд
    setTimeout(function() {
        location.reload();
    }, 30000);
</script>