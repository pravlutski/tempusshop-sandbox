<?
/**
 * проверка занятости обработчиков
 */
class WorkersChecker {
    private $logger;
    private $connection;
    private $table = 'ci_worker_busy';
    private $arData = [];
    private $shutdownRegistered = false;

    public function __construct ($workerID = "") {
		global $DB;
        $this->logger = new TsLogger("/".__CLASS__."/");
        $this->connection = Bitrix\Main\Application::getConnection();
        $this->db = $DB;

        //if(!$workerID) exit();
        $this->workerID = $workerID;
    }
	
    public function check() {
        $this->logger->log("LOG", "Старт проверки обработчиков");
        $this->getWorkersData($this->workerID);

        return $this->arData;
    }

    private function getWorkersData() {
        $sql = "SELECT * FROM ".$this->table;
        if (!empty($this->workerID)) {
            $sql .= " WHERE WORKER_ID = '".$this->workerID."'";
            $this->logger->log("LOG", "Получаем данные по обработчику ".$this->workerID);
        } else {
            $this->logger->log("LOG", "Получаем список обработчиков");
        }

        $res = $this->connection->query($sql);
        while($arItem = $res->fetch()) {
            $this->arData[$arItem["WORKER_ID"]] = $arItem;
        }

        $this->logger->log("LOG", "Получено, всего обработчиков ".count($this->arData));
    }

    public function setForceWorker($arWorker = []) {
        if(!$arWorker) return;
        $in = array("NEED_START" => "'Y'");
        $this->db->Update($this->table, $in, "WHERE WORKER_ID IN ('".implode("','", $arWorker)."')", $err_mess.__LINE__);
    }
    public function getForceWorker() {
        $sql = "SELECT * FROM ".$this->table." WHERE NEED_START = 'Y'";
        $res = $this->connection->query($sql);
        while($arItem = $res->fetch()) {
            $arData[$arItem["WORKER_ID"]] = $arItem;
        }
        return $arData;
    }

    public function getWorker($workerID = 0) {
        $sql = "SELECT * FROM ".$this->table." WHERE WORKER_ID = '{$workerID}'";
        $res = $this->connection->query($sql);
        if($arItem = $res->fetch()) {
            return $arItem;
        }
        return false;
    }

    public function checkStatus() {
        $this->getWorkersData();
        return ($this->arData[$this->workerID]["IS_BUSY"] == "Y" ? false : true);
    }

    public function updateStatus($status = "N") {

        $this->logger->log("LOG", "Обновляем статус обработчику " . $this->workerID);

        if(!$this->workerID) return false;

        $insDate = date('Y-m-d H:i:s', time());

        if ($status == "Y") {
            $in = array(
                "TIME_START" => "'".$this->db->ForSql($insDate)."'",
                "TIME_CHECK" => "'".$this->db->ForSql($insDate)."'",
                "NEED_START" => "'N'",
            );
        } else {
            $in = array(
                "TIME_END" => "'".$this->db->ForSql($insDate)."'",
                // last activity = finish, иначе длинный прогон сразу даёт ложную тревогу
                "TIME_CHECK" => "'".$this->db->ForSql($insDate)."'",
            );
        }

        $in["IS_BUSY"] = "'".$status."'";

        $this->db->Update($this->table, $in, "WHERE WORKER_ID = '{$this->workerID}'", $err_mess.__LINE__);

        $this->logger->log("LOG", "Статус " . $this->workerID . " изменен на " . $status);

        // die()/fatal не доходят до updateStatus("N") в конце скрипта —
        // снимаем флаг сами, иначе checkWorkers думает что процесс завис
        if ($status == "Y" && !$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            $self = $this;
            register_shutdown_function(function () use ($self) {
                $self->updateStatus("N");
            });
        }

    }
}
