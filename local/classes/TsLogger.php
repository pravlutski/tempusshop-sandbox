<?
/**
 * Class TsLogger
 */
class TsLogger {
    private $loggerLogPath = '/logger/';
    public $lastError = '';
    public $lastLog = '';
    private $config = ["enable" => true];
    public $rootFolder = '/home/bitrix/logs/';
	public $debugData = [];
    /**
     * TempusLogger constructor.
     * @param string $folderPath
     */
    public function __construct ($folderPath = "", $enable = true) {
        if (!empty($folderPath)) {
            $this->loggerLogPath = $folderPath;
            $folderExists = false;
            $logFolders = $this->GetLoggerSettings();

            if (!empty($logFolders)) {
                foreach ($logFolders as $folder) {
                    if ($folder == $folderPath && file_exists($this->rootFolder . $folder)) {
                        $folderExists = true;
                        break;
                    }
                }
            }

            if (!$folderExists || empty($logFolders)) {
                $logFolders[] = $folderPath;
                $this->SetLoggerSettings($logFolders);
                mkdir($this->rootFolder.$folderPath, 0777, true);
            }
        }
		$this->uniqueID = uniqid();

        $this->config["enable"] = $enable;
    }

    private function GetLoggerSettings () {
        //$logFolders = \CProSet::getOption("LOG_FOLDERS");
		$logFolders = \Bitrix\Main\Config\Option::get("main", "LOG_FOLDERS");
        if (!empty($logFolders)) {
            $logFolders = json_decode($logFolders);
        }

        return $logFolders ? $logFolders : [];
    }

    /**
     * @param array $values
     *
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     */
    private function SetLoggerSettings ($values = []) {
		$values = array_unique($values);
        $logFolders = json_encode($values);
		
		\Bitrix\Main\Config\Option::set("main", "LOG_FOLDERS", $logFolders);
    }

    /**
     * @param string $type
     * @param string $str
     * @param array  $data
     *
     * @return bool|string
     */
    public function log($type = "LOG", $str = "", $data = []) {
        if (!$this->config["enable"]) {
            return false;
        }

        if (empty($type)) {
            $this->log("ERROR", 'No logger type');
            return $this->lastError;
        }

        if (empty($str)) {
//            $this->log("ERROR", 'Empty logger string');
//            return $this->lastError;
        }

		$str = date("d.m.Y H:i:s")." ".$type." - ".$this->uniqueID." - ".$str;
        $defaultFileName = '.'.date("d.m.Y").'_loggerLog';

        if (!empty($data)) {
            $str .= "\r\n".print_r($data, true);
        }

        switch ($type) {
            case "ERROR":
                $fileName = '.'.date("d.m.Y").'_loggerErrors';
                $this->lastError = ["ERROR" => $str];
//                $str .= "\r\nBug trace\r\n".print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true);
                break;
            case "LOG":
            case "DEBUG":
                $fileName = '.'.date("d.m.Y").'_loggerLog';
                $this->lastLog = $str;
                break;
            default:
                $this->log("ERROR", 'Unknown type for log');
                return $this->lastError;
                break;
        }

        if (!is_dir($this->rootFolder.$this->loggerLogPath)) {
			$tr = explode("/", $this->loggerLogPath);
			$tr = array_diff($tr, array(''));
			if(count_($tr) > 1){
				$pathFolder = "";
				foreach($tr as $folder){
					$pathFolder .= "/" . $folder;
					mkdir($this->rootFolder.$pathFolder, 0777);
				}
			}else{
				mkdir($this->rootFolder.$this->loggerLogPath, 0777);
			}
			
            
        }

        if ($type != "LOG") {
            $filePath = $this->rootFolder.$this->loggerLogPath.$fileName;
        }

        $defaultFilePath = $this->rootFolder.$this->loggerLogPath.$defaultFileName;

        $str .= "\r\n";
        if ($type == "ERROR") {
            file_put_contents($filePath, $str, FILE_APPEND);
        }

        file_put_contents($defaultFilePath, $str, FILE_APPEND);

        return true;
    }
	
    /**
     * Дебаг, замер времени начало
     *
     * @param string $function
     */
    public function StartDebugTime($function = "") {
        if (empty($function)) {
            $trace = debug_backtrace();
			$function = $trace[1]["function"];
			if(!$function) return;
        }

        $start = microtime(true);
        $this->debugData[$function] = ["Function" => $function, "Start" => $start];
    }

    /**
     * Дебаг, замер времени конец
     *
     * @param string $function
     */
    public function EndDebugTime($function = "") {
        if (empty($function)) {
            $trace = debug_backtrace();
			$function = $trace[1]["function"];
			if(!$function) return;
        }

        $finish = microtime(true);
        $diff = $finish - $this->debugData[$function]["Start"];
        $diff = round($diff, 4);
        $this->debugData[$function]["Time"] = $diff;
    }
	
    public function clearFolders () {
        $this->log("LOG", "Удаление старых логов");

        $folders = $this->GetLoggerSettings();
        $nowTime = strtotime(date("d.m.Y H:i:s"));
        $needTime = 604800; // неделя

        $this->log("LOG", "Получены каталоги ".print_r($folders, true));

        if (!empty($folders)) {
            foreach ($folders as $folder) {
                $this->log("LOG", "Проверяем каталог {$folder}");

                $files = [];
                $RFolder = $folder;
                //$folder = realpath(dirname(__FILE__). '/../..').$folder;
                $folder = "/home/bitrix/logs" . $folder;
                $files = scandir($folder);

                if (!empty($files)) {
                    $this->log("LOG", "Проверяем файлы");

                    foreach ($files as $file) {
                        if ($file == "." || $file == "..") {
                            continue;
                        }

                        $fileSize = filesize($folder.$file);
                        $fileTime = filectime($folder.$file);

                        if ($fileSize > 5368709120) { // файл больше 5 ГБ
                            $this->log("LOG", "Файл ".$RFolder.$file." большого размера ".($fileSize / 1024)." MB, удаляем");
                            @unlink($folder.$file);
                        }

                        if (($nowTime - $fileTime) > $needTime) {
                            $this->log("LOG", "Файл ".$RFolder.$file." будет удален");

                            if (file_exists($folder.$file)) {
                                @unlink($folder.$file);
                            }
                        } else {
                            $this->log("LOG", "Файл ".$RFolder.$file." не нужно удалять");
                        }
                    }
                } else {
                    $this->log("LOG", "В каталоге {$RFolder} нет файлов");
                }
            }
        }

        $this->log("LOG", "Удаление завершено");
    }
}
?>
