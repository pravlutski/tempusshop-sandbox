<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

set_time_limit(0);
ini_set('display_errors', '1');
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if(
    !CModule::IncludeModule("main") || !CModule::IncludeModule("iblock") || 
    !CModule::IncludeModule("panel.manager") || !CModule::IncludeModule("sale") || 
    !CModule::IncludeModule("currency") || !CModule::IncludeModule("catalog")
) {
    echo "Failed to include modules\n";
    exit(1);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/local/lib/RabbitMQConnector.php');

class PriceUpdateCollector {
    private $batch = [];
    private $productArticles = []; // product_id => article
    private $lastActivityTime;
    private $logger;
    private $isProcessing = false;
    private $timeout = 10;
    private $maxBatchSize = 1000;
    private $scriptPath;
    
    public function __construct() {
        $this->lastActivityTime = time();
        $this->logger = new TsLogger("rabbitmq/price_update/");
        $this->scriptPath = $_SERVER['DOCUMENT_ROOT'] . '/local/rabbitmq/price_update_processor.php';
    }
    
    public function addOrder($orderData) {
        $orderId = $orderData['ORDER_ID'];
        
        // Собираем артикулы из заказа
        $orderArticles = [];
        $productIds = array_column($orderData['ITEMS'], 'PRODUCT_ID');
        
        if (empty($productIds)) {
            $this->logger->log("DEBUG", "Заказ #{$orderId} без товаров, пропускаем");
            return;
        }
        
        $articles = $this->getArticlesByProductIds($productIds);
        
        if (empty($articles)) {
            $this->logger->log("ERROR", "Не найдены артикулы для заказа #{$orderId}");
            return;
        }
        
        $articleKey = md5(implode(',', $articles));
        $orderKey = "order_{$orderId}";
        
        if (isset($this->batch[$orderKey])) {
            $oldArticles = $this->batch[$orderKey]['articles'];
            $oldKey = md5(implode(',', $oldArticles));
            
            if ($oldKey === $articleKey) {
                $this->logger->log("DEBUG", "Артикулы не изменились для заказа #{$orderId}, пропускаем");
                return;
            }
            
            $this->logger->log("DEBUG", "Артикулы изменились для заказа #{$orderId}", [
                'old' => $oldArticles,
                'new' => $articles
            ]);
        }
        
        $this->batch[$orderKey] = [
            'order_id' => $orderId,
            'articles' => $articles,
            'product_ids' => $productIds,
            'timestamp' => time()
        ];
        
        $this->lastActivityTime = time();
        
        $this->logger->log("DEBUG", "Добавлен заказ #{$orderId}, артикулов: " . count($articles), [
            'articles' => $articles,
            'batch_size' => count($this->batch)
        ]);
        
        $this->checkAndProcess();
    }
    
    public function checkAndProcess() {
        $currentTime = time();
        $idleTime = $currentTime - $this->lastActivityTime;
        $batchSize = count($this->batch);
        
        if ($batchSize === 0) {
            return;
        }
        
        // херабора. если прошло 10 сек или лимит то запускаем
        if ($idleTime >= $this->timeout || $batchSize >= $this->maxBatchSize) {
            $this->runProcessor();
        }
    }
    
    private function runProcessor() {
        if ($this->isProcessing) {
            return;
        }
        
        if (empty($this->batch)) {
            return;
        }
        
        $this->isProcessing = true;
        
        $allArticles = [];
        $orders = [];
        $productIds = [];
        
        foreach ($this->batch as $key => $data) {
            $allArticles = array_merge($allArticles, $data['articles']);
            $orders[] = $data['order_id'];
            $productIds = array_merge($productIds, $data['product_ids']);
        }
        
        $allArticles = array_unique($allArticles);
        $productIds = array_unique($productIds);
        
        $this->logger->log("LOG", "Запуск обработчика", [
            'orders' => $orders,
            'articles_count' => count($allArticles),
            'products_count' => count($productIds),
            'batch_size' => count($this->batch)
        ]);
        
        $data = [
            'orders' => $orders,
            'articles' => $allArticles,
            'product_ids' => $productIds,
            'timestamp' => time()
        ];
        
        $jsonData = json_encode($data);
        $command = "php {$this->scriptPath} " . escapeshellarg($jsonData) . " > /dev/null 2>&1 &";
        exec($command);
        
        $this->logger->log("LOG", "Процессор запущен", [
            'command' => $command
        ]);
        
        $this->reset();
        $this->isProcessing = false;
    }
    
    private function getArticlesByProductIds($productIds) {
        if (empty($productIds)) {
            return [];
        }
        
        if (!is_array($productIds)) {
            $productIds = [$productIds];
        }
        
        $productIds = array_unique(array_map('intval', $productIds));
        $ids = implode(',', $productIds);
        $arArticles = [];
        
        $sql = "
            SELECT 
                be.ID as PRODUCT_ID,
                bep.PROPERTY_123 as ARTICLE
            FROM b_iblock_element be
            INNER JOIN b_iblock_element_prop_s16 bep ON be.ID = bep.IBLOCK_ELEMENT_ID
            WHERE be.IBLOCK_ID = 16 
                AND be.ID IN ({$ids})
        ";
        
        $connection = Bitrix\Main\Application::getConnection();
        $result = $connection->query($sql);
        
        while ($row = $result->fetch()) {
            if (!empty($row['ARTICLE'])) {
                $arArticles[] = $row['ARTICLE'];
            }
        }
        
        return array_unique($arArticles);
    }
    
    private function reset() {
        $this->batch = [];
        $this->lastActivityTime = time();
    }
    
    public function forceProcess() {
        $this->checkAndProcess();
    }
}

$collector = new PriceUpdateCollector();
$rabbit = RabbitMQConnector::getInstance();

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGALRM, function() use ($collector) {
        $collector->forceProcess();
        pcntl_alarm(1);
    });
    pcntl_alarm(1);
}

$rabbit->consume(RabbitMQConnector::TYPE_PRICE_UPDATE, function(array $data) use ($collector) {
    $collector->addOrder($data);
    
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
}, true);

register_shutdown_function(function() use ($collector) {
    $collector->forceProcess();
});
?>