<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class CasioParser
{
    private $retryCount = 5;
    private $delayBetweenRequests = 4;
    private $proxyList = [];
    private $currentProxyIndex = 0;
    private $useProxy = true;

    public function __construct()
    {
        $this->loadFreeProxies();
    }

    private function loadFreeProxies()
    {
        // Добавляем протокол к каждому прокси
        $this->proxyList = [
           '185.80.150.196:8000:2Gapuw:umMHm0',
           '185.128.213.205:8000:2Gapuw:umMHm0',
           '185.128.212.136:8000:2Gapuw:umMHm0'
        ];

        echo "Доступно прокси: " . count($this->proxyList) . "\n";
    }

    private function getNextProxy()
    {
        if (!$this->useProxy || empty($this->proxyList)) {
            return null;
        }

        $proxy = $this->proxyList[$this->currentProxyIndex];
        $this->currentProxyIndex = ($this->currentProxyIndex + 1) % count($this->proxyList);

        return $proxy;
    }

    private function makeRequest($url, $isJson = false)
    {
        $attempt = 0;
        while ($attempt < $this->retryCount) {
            $attempt++;
            echo "Попытка $attempt для URL: $url\n";
            sleep($this->delayBetweenRequests);
            $ch = curl_init();
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_ENCODING => 'gzip, deflate',
                CURLOPT_SSL_VERIFYPEER => false, // Отключено для теста
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => $this->getRandomUserAgent(),
                CURLOPT_HTTPHEADER => [
                    'Accept: ' . ($isJson ? 'application/json' : 'text/html,application/xhtml+xml'),
                    'Accept-Language: en-US,en;q=0.9',
                    'Cache-Control: no-cache',
                    'Connection: keep-alive',
                ]
            ];
            $options[CURLOPT_SSL_CIPHER_LIST] = 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256';

            if ($proxy = $this->getNextProxy()) {
                $proxyParts = explode(':', $proxy);
                if (count($proxyParts) === 4) {
                    $proxyIpPort = $proxyParts[0] . ':' . $proxyParts[1];
                    $proxyUserPwd = $proxyParts[2] . ':' . $proxyParts[3];

                    $options[CURLOPT_PROXY] = $proxyIpPort;
                    $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP; // Используем HTTPS-прокси
                    $options[CURLOPT_PROXYUSERPWD] = $proxyUserPwd;

                    // Дополнительные настройки для HTTPS-прокси
                    $options[CURLOPT_PROXYAUTH] = CURLAUTH_ANY;  // Пробуем разные методы аутентификации

                    // Настройки для HTTPS-прокси
                    $options[CURLOPT_HTTPPROXYTUNNEL] = true;    // Туннелирование через прокси
                    $options[CURLOPT_PROXY_SSL_VERIFYPEER] = false;  // Отключаем проверку SSL для прокси
                    $options[CURLOPT_PROXY_SSL_VERIFYHOST] = 0;

                    // Возможные альтернативные методы подключения
                    $options[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;  // Принудительно используем TLS 1.2
                    $options[CURLOPT_SSL_CIPHER_LIST] = 'DEFAULT@SECLEVEL=1';  // Понижаем уровень безопасности, если нужно

                    echo "Используется прокси: $proxyIpPort (тип: HTTPS)\n";
                }
            }

            curl_setopt_array($ch, $options);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$error && $httpCode === 200) {
                return $response;
            }

            error_log("Ошибка запроса: $error (HTTP код: $httpCode)");

        }
        return false;
    }

    // ... остальные методы класса остаются без изменений ...
    private function getRandomUserAgent()
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Safari/605.1.15'
        ];
        return $agents[array_rand($agents)];
    }

    public function getProductProperties($model)
    {
        $apiUrl = 'https://finder.api.mf.marsflag.com/api/v1/finder_service/documents/f26198c1/search?q='.urlencode($model).'&page_number=1&number_per_page=10&sort_by=score&doctype=all';
        $jsonData = $this->makeRequest($apiUrl, true);

        $links = [];
        if ($jsonData) {
            $data = json_decode($jsonData, true);
            if (isset($data['organic']['docs'])) {
                foreach ($data['organic']['docs'] as $doc) {
                    $mystring = str_replace("-", "", $doc['title']);
                    $findme = str_replace("-", "", $model);
                    if (strpos($mystring, $findme) !== false && !empty($doc['url'])) {
                        $links[] = $doc['url'];
                    }
                }
            }
        }

        foreach ($links as $link) {
            echo "Пробуем получить данные с: $link\n";
            $html = $this->makeRequest($link);

            if (!$html) {
                continue;
            }

            $props = $this->parseProductPage($html);
            if (!empty($props)) {
                echo 'yra';
                return $props;
            }
        }

        return [];
    }

    private function parseProductPage($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $props = [];

        $specBlock = $xpath->query("//div[contains(@class, 'product_detail-spec')]");
        if ($specBlock->length === 0) {
            return $props;
        }

        $specItems = $xpath->query("//div[contains(@class, 'p-product_detail-spec-accordion__panel-item')]");
        foreach ($specItems as $item) {
            $titleNode = $xpath->query(".//*[contains(@class, 'p-product_detail-spec-accordion__panel-item-ttl')]", $item);
            $valueNode = $xpath->query(".//*[contains(@class, 'p-product_detail-spec-accordion__panel-item-cont')]", $item);

            if ($titleNode->length > 0 && $valueNode->length > 0) {
                $propName = trim($titleNode[0]->textContent);
                $propValue = trim($valueNode[0]->textContent);

                if (!empty($propName)) {
                    $props[$propName] = $propValue;
                }
            }
        }

        return $props;
    }
}

$parser = new CasioParser();
$models = ["EFA-120","EFR-512","EFA-120","GA-100","GA-110","EFR-510","DW-5600","GW-3000","SGW-100","G-9300","GW-A1000","EQS-A500","EFR-526","AWG-M100","PRG-240","GX-56","GA-120","G-2900","BEM-506","EF-339","GW-A1100","G-7710","PRG-550","G-9000","AMW-700","GA-200","GA-1000","SGW-300","GA-150","PRW-3000","PRW-5000","EF-316","GR-8900","GW-9400","AW-591","EFA-122","PRW-5100","SGW-500","EQW-A1200","EF-509","PRG-270","GWX-8900","GD-100","GW-4000","EQW-A1000","EFR-520","EF-545","PRW-2500","EF-539","EFA-131","GA-300","G-7900","G-9100","GD-110","GW-6900","EF-552","EF-500","EFR-527","EFA-121","EF-558","G-8900","GW-9200","EFR-533","EF-328","DW-6900","ERA-200","GLX-150","GW-2310","BGD-141","PRW-2000","PRG-250","GW-3500","EQS-500","EFR-528","ECW-M300","GB-6900","GW-M5610","GLS-100","PRG-260","EFR-524","EFR-523","GA-201","EFR-515","EF-132","PRW-1500","PRW-1300","GDF-100","EFR-501","G-7700","EF-129","GLX-5600","AW-590","SGW-200","EF-125","EF-121","BEM-106","EF-343","EF-550","EF-527","EFA-132","SGW-400","G-9200","GAC-100","MTP-1314","EQW-A1110","EFE-504","EQW-M600","MTD-1057","EQW-M1100","LIN-164","AMW-702","DB-E30","EFR-531","BG-6902","BG-6903","BG-5600","LTP-2069","SHN-5503","SHE-3024","SHE-5517","BG-169","BLX-102","SHE-5020","SHN-3013","BLX-100","BG-3002","SHN-5003","BG-3000","SHE-4507","SHE-4509","SHE-3802","BG-6900","BGA-152","BG-5601","BG-1001","SHN-5000","SHE-5019","SHE-4021","LW-200","DW-6930","BGA-153","LTP-1235","BGD-500","LTP-1373","LTP-1314","SHE-3800","LTP-1366","SHE-5017","BG-6901","SHE-3504","LRW-250","LDF-52","LTP-1303","SHE-3500","LTP-1129","SHN-3014","LTP-1234","LTP-1154","SHE-4508","SHE-3022","BA-112","SHE-3028","LTP-1369","LTP-1141","LTP-1281","LTP-1308","LTP-1342","EFA-134","EQW-M1000","GW-9110","GW-9010","ERA-300","DW-D5600","EFA-133","MTP-1192","EFA-135","EFB-101","W-96","MTP-1342","GD-X6900","EF-547","EF-130","EFR-518","MTP-1300","PRW-6000","AE-2000","AMW-703","AMW-704","AMW-710","GB-X6900","EFR-519","AQW-101","GA-113","MRP-700","MRW-200","AW-80","G-9330","AQ-S810","LIN-169","F-91","SHE-3801","EF-304","GD-350","GLS-8900","EFA-112","PRX-7001","EMA-100","EF-126","GW-7900","W-201","EF-342","EQW-M710","GB-5600","F-200","F-201","GD-120","EFR-513","EFR-529","W-800","AW-49","DB-36","AE-1100","EF-562","EF-329","DBC-32","BGA-170","LIN-163","LIN-165","BGA-110","EFR-505","W-S200","GA-310","W-215","DB-360","EFR-522","W-210","W-212","EF-106","BEM-116","WVA-105","MTF-E001","BA-111","A-163","AQ-164","G-7500","LCW-M100","F-105","ERA-100","BEM-308","AQ-S800","BA-110","WVA-109","BEM-111","WV-200","EFR-101","AQF-102","GW-M5630","W-735","BGA-131","EF-133","BEM-100","AE-1000","EFR-507","MTD-1075","EF-512","A-168","AQ-160","MTP-1370","GWX-5600","LIN-168","EFM-502","LCW-M170","STB-1000","AW-90","W-S210","GBA-400","PRG-280","GWN-1000","GPW-1000","GD-400","GA-400","DW-5030","ERA-201","EQW-T620","EQW-A1400","EQB-500","EFR-539","EFR-538","EFR-536","EFR-535","EFR-534","EFR-103","EFR-102","STL-S100","MTP-1374","GF-8230","EFX-510","GW-9430","EFB-504","GW-M5600","BGA-180","W-S220","GW-A1130","EFR-537","GW-9230","GR-9110","EFR-104","WVA-470","MRP-703","SHE-3503","AW-82","WVA-430","WVA-M640","PRW-500","BLX-5600","SHE-4512","WVA-M630","AMW-705","EFR-100","GA-303","G-001","SHE-3029","BGD-180","EF-543","LIW-M1100","EFA-118","EQS-A1000","BG-5606","SHE-4031","BLX-103","LCW-M150","GLX-6900","SHE-4503","EF-540","WVQ-M410","BG-1005","AQW-100","SHE-4505","EF-131","GD-200","BGD-501","BGA-301","EF-305","EF-340","BGA-200","GMD-S6900","SHE-4024","EF-335","BGA-132","A-158","A-159","AD-S800","AE-1200","AE-1300","AQ-180","AQ-190","AW-48","AQF-100","AQ-230","AW-81","B640WB-1A","B640WC-5A","B640WD-1A","BEL-100","BEM-119","BEM-120","BEM-121","BEM-126","BEM-307","BEM-309","BEM-508","BEM-511","CHF-100","CHR-100","MTD-1053","DBC-611","EF-327","EF-128","EF-312","EF-324","EF-326","EF-332","EF-333","EF-334","EF-503","EF-565","EFE-501","EFE-503","EFE-505","EFR-500","EFR-503","EFR-521","EFR-532","EFR-540","EQS-1100","F-108","G-1400","GD-X6930","GMA-S110","SET-30","HDC-600","HDD-600","HS-3V-1","LA-20","LCF-10","LCF-20","LCF-21","LCW-M160","LCW-M300","LDF-20","LIW-M610","LRW-200","LTP-1128","LTP-1261","LTP-1280","LTP-1301","LTP-1302","LTP-1334","LTP-1336","LTP-1341","LTP-1355","LTP-1362","LTP-1363","LTP-1364","LTP-1365","LTP-1367","LTP-1368","LTP-1374","LW-24","MQ-24","MTD-1076","MRW-S300","MTD-1062","MTD-1063","MTD-1065","MTD-1066","MTD-1073","MTD-1077","MTD-1078","MTP-1128","MTP-1129","MTP-1141","MTP-1142","MTP-1154","MTP-1183","MTP-1188","MTP-1191","MTP-1200","MTP-1213","MTP-1219","MTP-1221","MTP-1222","MTP-1228","MTP-1229","MTP-1234","MTP-1235","MTP-1236","MTP-1258","MTP-1259","MTP-1260","MTP-1261","MTP-1263","MTP-1264","MTP-1290","MTP-1291","MTP-1302","MTP-1303","MTP-1306","MTP-1308","MTP-1310","MTP-1318","MTP-1328","MTP-1336","MTP-1341","MTP-1343","MTP-1346","MTP-1347","MTP-1350","MTP-1351","MTP-1352","MTP-1353","MTP-1354","MTP-1355","MTP-1365","MTP-1369","MTP-1372","MTP-1383","PRG-110","PRG-505","PRG-510","SHE-3026","SHE-3030","SHE-3502","SHE-3508","SHE-3507","SHE-3803","SHE-4032","SHE-4035","SHE-4510","SHE-4800","SHE-5021","SHE-5023","SHE-5512","SHE-5516","SHN-3008","SHN-3011","SHN-3017","SHN-4015","SHN-4020","SHN-5016","SPS-300","STR-300","W-202","W-211","W-213","W-216","W-42","W-734","W-752","W-753","W-756","W-87","W-93","WV-58","WV-59","A-178","WS-300","WS-110","SHN-5013","SHN-5012","SHN-4016","SHN-3020","W-43","SHN-3012","SHE-5513","SHE-4022","SHE-3031","SHE-3023","MW-600","MTP-V003","MTP-V002","MTP-V001","MTP-E106","MTP-E105","MTP-E104","MTP-E103","MTP-E102","MTP-E101","MTP-1381","MTP-1380","MTP-1379","MTP-1378","MTP-1377","MTP-1376","MTP-1375","MTP-1335","MTP-1330","MTP-1315","MTP-1305","MTP-1296","MTP-1292","MTP-1247","MTP-1246","MTP-1244","MTP-1243","MTP-1239","MTP-1216","MTP-1215","MTP-1214","MTP-1175","MTP-1170","MTP-1096","MTP-1095","MTP-1094","MTP-1093","MTD-1060","MQ-38","MDV-302","LTP-V002","LTP-V001","LTP-E104","LTP-E103","LTP-2087","LTP-2086","LTP-2085","LTP-2084","LTP-2083","LTP-1389","LTP-1387","LTP-1386","LTP-1385","LTP-1384","LTP-1383","LTP-1382","LTP-1381","LTP-1380","LTP-1260","LTP-1359","LTP-1177","LTP-1358","LTP-1315","LTP-1310","LTP-1283","LTP-1275","LTP-1274","LTP-1253","LTP-1242","LTP-1238","LTP-1237","LTP-1215","LTP-1183","LTP-1170","LTP-1169","LTP-1095","LTP-1094","LTD-2001","LDF-51","LDF-50","LDF-21","LA-680","HS-70","GRX-5600","GMA-110","GF-1000","GAC-110","G-6900","DW-290","EFR-517","EFA-119","EF-556","EF-341","EF-336","BEM-507","MTP-E301","MTP-E201","MTP-1384","LTP-2088","EFA-110","BGA-171","BGA-160","BEM-501","BEM-311","BEM-130","BEL-130","BG-5607","BGA-117","BGA-120","BGA-130","BGA-150","BGA-201","EFR-543","EFR-544","EFR-541","EFR-542","MTP-E302","LTP-V004","B640WB-1B","MTP-V004","MTP-E303","BEM-302","MTP-E107","MTP-E108","LTP-V005","MTP-V005","MTP-1299","CA-53","MTP-1253","A-500","BGA-151","EF-521","LW-22","MCW-100","A-164","LTP-1259","LTP-1236","LTP-1264","MQ-76","MTD-1070","MTD-1071","MTD-1079","SHE-4027","SHE-4028","SHE-4518","SHE-4804","SHN-4019","SHN-5502","STR-101","EFR-546","EFR-547","GW-9300","HS-80","PRW-3500","SHE-3032","HS-6-1","AQ-163","BGA-112","BGA-300","EF-309","MTG-S1000","MTP-1340","W-755","SHE-3807","SHE-3806","SHE-3034","PRG-300","MTP-E305","MTP-E304","MTP-E202","MTP-E114","MTP-E113","MTP-E112","MTP-E111","MTP-1329","LTP-E402","LTP-E401","LTP-E306","LTP-E114","LTP-E113","LTP-E304","LTP-E111","LTP-1391","AE-2100","AWG-M510","BA-120","BGA-190","ECB-500","EFR-302","EFR-549","EFR-550","EQB-510","ERA-500","GA-1100","SGW-1000","SGW-450","SHE-3033","GF-8250","GN-1000","MTD-1080","GST-W100","GST-W110","SHE-3040","SHE-3041","EF-546","LQ-400","STL-S110","STL-S300","LA-670","DW-9052","EQW-T1010","LX-S700","PRW-3100","WVA-M650","LTP-V006","LTP-V007","MTP-V006","MTP-V007","MTP-V008","AEQ-100","AEQ-110","AW-582","BGA-134","BGA-210","CA-506","EFR-106","EFR-303","EFR-552","EFR-553","GST-200","GST-210","GWG-1000","LCW-M180","LCW-M500","LTP-1263","LTP-E117","LTP-E118","MQ-71","MRW-S310","MTG-G1000","MTP-E306","MTP-E307","MTP-S101","MW-240","SHE-4045","W-59","EFR-304","AL-190","AMW-320","AMW-340","AMW-370","BEM-110","BEM-112","BG-5602","BG-5603","BGA-100","BGA-101","BGA-102","BGA-103","BGA-105","BGA-111","AW-E10","BEM-105","BEM-310","BEM-509","BEM-512","DB-380","EF-524","F-94","FT-500","HDA-600","LA-201","LQ-139","LQ-142","LTP-1130","LTP-1131","LTP-1165","LTP-1191","LTP-1208","LTP-1230","LTP-1233","LTP-1335","LTP-1390","LTP-1392","LTP-1393","LTP-E102","LTP-E116","LTP-E301","LTR-18","LTR-19","LW-201","MQ-27","MTD-1069","MTD-1072","MTD-1074","MTF-117","MTF-118","MTP-1130","MTP-1131","MTP-1165","MTP-1169","MTP-1174","MTP-1233","MTP-1240","MTP-1265","MTP-1274","MTP-1275","MTP-1317","MTP-1339","MTP-1373","MTP-1382","MTP-E115","MTP-E116","MW-59","SDB-100","SHN-3016","W-740","SHN-5010","MTR-102","LTP-1379","MDV-106","MTF-110","LOV-15","LTP-1096","LTP-1241","LTP-1295","LTP-1296","LTP-1300","LTP-1333","LTP-1353","LTP-1357","EF-317","EF-544","EF-548","LTP-1378","LTP-1388","LTP-2037","LTP-2089","LTP-E120","LTP-E123","LTP-E308","MTD-1082","MTP-1325","MTP-1326","MTP-1327","MTP-1344","MTP-1345","MTP-E119","MTP-E308","MTP-X100","MTP-X300","BGA-185","ESK-300","GG-1000","LTP-2064","LTP-TW100","LTP-TW101","MTP-TW100","MTP-TW101","LX-500","PRW-3510","SHE-3044","SHE-3045","SHE-3047","PRW-6100","HS-3V-1B","LW-23","PRW-S3100","PRW-S3500","PRX-8000","SHE-3035","SHE-3048","SHE-3808","SHE-4029","MTP-E124","MTP-E125","MTP-E126","MTP-E127","G-100","EQB-600","GST-S100","BA-125","BGA-220","GAX-100","LTP-E403","LTP-E404","MTP-VS01","MTP-VS02","EF-510","EFA-116","ETD-300","ETD-310","BEM-150","BEM-151","BEM-152","BGA-141","SHE-3036","SHE-3809","EFB-301","EFB-302","EFB-508","EFV-500","EFV-510","G-5600","PRW-S6100","LTP-V300","MRW-210","EQS-700","GW-A1030","GWN-Q1000","W-736","MTP-E128","MTP-E129","MTP-E309","MTP-V300","MTP-V301","BGD-140","CPW-500","GST-S110","GA-500","SHB-100","EFR-554","EQB-700","SHB-200","AEQ-200","PRW-7000","LTP-E406","LTP-E407","MTD-100","MTD-300","MTP-1400","MTP-1401","MTP-E130","MTP-E200","LTP-E115","MTP-E310","MTP-E400","BGA-230","GLS-6900","SHE-4033","LIW-M700","PRG-600","MTP-E131","MTP-E311","LTP-1410","LTP-E408","EFR-555","EFV-520","GA-700","BGA-195","ERA-600","LTP-E122","LTP-E128","LTP-E129","LTP-E133","LTP-E409","LTP-E410","MTP-E133","EQW-T640","SHE-3050","SHE-4050","BEM-154","LTP-E134","EFR-556","AE-3000","BEM-313","LA-11","LAW-25","LTP-E312","LTP-E405","MTP-E134","MTP-E312","GMA-S120","GST-W120","GST-W130","LOV-16","LTP-1282","LTP-E121","SGW-600","SHE-3043","SHE-3049","SHE-3051","SHE-4524","SHN-5014","SHE-4034","SHE-4525","LTP-E135","MTP-E313","MTP-VX01","EQB-501","AMW-810","BGA-240","CPA-100","EFB-530","GA-710","SHE-3052","MTP-E136","MTP-E137","BGD-560","BG-1006","WV-M60","BEM-520","BGA-225","SHE-3056","SHE-3057","LTP-E139","LTP-E314","MTP-E138","MTP-E139","MTP-E314","W-217","EFV-530","SHE-3046","BGA-123","BGA-124","EF-337","EFB-500","EFR-516","GLS-5600","EFB-550","EFB-560","EFR-557","LTP-E141","GST-S300","MRW-400","GST-W300","GST-W310","AWR-M100","BG-1302","BGA-116","BGA-121","BGA-133","G-1250","G-300","LTP-E142","LTP-E315","GR-7900","GST-S120","LOV-11","LOV-12","PRG-130","SHE-3025","SHE-3501","SHE-4025","SHE-4026","SHE-4502","SHE-5018","MTD-120","MTD-320","PRG-S510","EQS-600","LW-203","SHE-3054","LTP-E140","SHE-3055","GWF-D1000","BEM-312","LDF-12","EQB-800","SHE-3505","SHE-5515","GAW-100","MTF-115","LTP-E143","MTD-1085","GPW-2000","LTP-1356","EFB-510","EFR-558","EFV-540","GA-800","MTP-E145","PRG-650","LTP-E145","BGS-100","GAS-100","GMA-S130","GST-B100","LTH-1060","MTH-1060","EFR-559","AMW-S820","B650WB-1B","B650WC-5A","B650WD-1A","SHE-3058","MTP-SW300","GST-S310","DW-5750","EQS-800","MCW-200","MTP-SW310","MTP-SW320","EQW-T650","GA-735","GG-1035","GST-410","SHE-3059","SHE-3511","SHE-3512","HDC-700","EFR-560","EFR-561","EFV-550","SHE-3060","AE-1400","LTP-E146","LTP-E147","GWG-100","MSG-400","PRW-6600","EFS-S510","EFS-S520","EFS-S500","EFV-100","GBA-800","GST-400","AMW-830","GSG-100","SHE-3805","MTP-E315","MTP-VD01","EQB-900","SHE-4047","MTP-E149","MTP-E150","LTP-E151","MTP-E203","DW-5035","DW-5735","LTP-E152","MCW-110","MTP-E316","BEM-104","DB-520","MTF-304","MTP-1337","WVA-104","W-S300","EF-530","EF-549","EF-559","G-101","G-1200","GA-810","G-731","LTP-E153","MTP-E204","MTP-E205","PRG-330","BGA-142","BGA-161","CA-56","EF-553","EFR-563","SHE-3062","DW-6935","DW-5635","LTP-E148","EFR-504","EF-520","EF-523","EF-526","PRW-60","EFV-560","SHE-4051","GWX-5700","BGA-250","BLX-560","EFV-C100","BEM-108","AMW-840","MTD-330","SHE-4049","MSG-S200","EQS-900","GST-S330","MTP-V01","LCW-M510","EFV-570","TRT-110","SHE-3061","SHE-3064","LX-610","GR-B100","W-218","LTP-E154","MTP-1404","MTP-1405","MTP-E317","MTP-V302","MWC-100","ERA-110","EFS-S530","EFS-S540","ECB-800","LA-690","GBD-800","BSA-B100","MTP-E318","MTP-E319","LTP-E157","GPR-B1000","GST-W330","LTP-E155","LTP-E156","MTD-1086","MTD-1087","EFR-564","EFV-580","EQS-910","MQ-1000","BGA-255","GW-B5600","DW-5900","SHE-4052","SHE-3066","MTP-E120","MTP-E320","DW-5700","EFR-S565","HS-6-1E","DW-D5500","EQS-920","MTP-E158","MTP-EX100","MTP-EX300","MTP-SW330","MTR-100","MTR-200","MTR-501","EF-338","EF-515","BEM-304","BEM-SL100","GST-S130","GA-135","LTP-E159","LTP-VT01","LWS-1000","LWS-2000","MRW-220","MTP-E159","MTP-VT01","WS-1000","WS-2000","BA-135","BGD-525","MTS-100","MDV-303","LTS-100","LTP-E160","GMW-B5000","BAX-100","GA-2000","GWR-B1000","ECB-900","HS-3V-1R","BLX-570","MTP-SW340","LTP-E02","LTP-E03","LTP-E04","LTP-E05","LTP-E06","LTP-E07","LTP-E08","LTP-E09","LTP-E10","LTP-E11","LTP-E12","LTP-E01","MTG-B1000","EFV-110","SHE-4055","MSG-C100","EFR-566","EFS-S550","EFV-120","A-700","AMW-709","BEM-303","BEM-305","MRG-G1000","MTP-1280","GST-B200","BA-130","PRW-50","ERA-120","AMW-330","WSD-F20","PAG-240","GWM-850","GWM-500","GW-9404","GA-140","GG-B100","EFR-S107","EFR-S567","EFV-130","EQB-1000","LWS-1100","WS-1100","LTP-E163","LTP-E162","SHE-3065","GA-2100","GMA-S140","SHE-4056","BGD-570","EFR-568","GM-5600","GMA-B800","PRT-B50","SHE-4532","SHE-3067","BGA-260","EFR-569","EFV-590","MTP-1298","SHE-4057","SHE-3068","SHE-4533","A-1000","BGA-143","MTP-VD300","DW-291","EFR-570","LTP-1298","LTP-1299","LTP-1311","LTP-E164","LTP-E165","LTP-E166","MTP-1319","MTP-1371","WS-1200","WSC-1250","DW-5610","ECB-10","GA-2110","GM-6900","EFS-S560","LT-V007","LB-611","B640WCG-5E","GW-2320","LTP-1328","LTP-1360","LTR-17","BG-1200","BG-1201","EF-531","GF-8235","MSG-S500","B640WBG-1B","B640WDG-7E","B640WGG-9E","GMD-B800","EFR-571","EFV-600","PRW-70","B640WCG-5A","MWA-100","GST-B300","GA-900","EQB-1100","EFR-S108","B-640","LTP-E412","LTP-E413","LTP-E414","LTP-E415","MTP-E170","MTS-110","MWD-100","GR-B200","GM-S5600","GM-110","BGA-270","W-737","ECB-20","EFS-S570","GBD-H1000","PRW-30","MTP-E321","MTP-E171","TQ-141","TQ-143","TQ-142","TQ-140","TQ-369","TQ-367","TQ-359","TQ-266","TQ-218","TQ-148","PQ-31","PQ-30","DQ-981","DQ-750","DQ-747","DQ-583","DQ-543","ID-11","DQ-748","DQ-582","ID-16","ID-15","ID-14","GBD-100","PRT-B70","EFS-560","GWF-A1000","MRG-G2000","MRG-B1000","AMW-870","LTP-E175","MTP-E172","MTP-E173","MTP-E175","MTP-E180","MTP-E330","MTG-B2000","GBX-100","B640WMR-5A","LTP-E411","LTP-E167","LW-204","MTP-E500","MTP-E501","W-219","EFV-610","LWA-300","EFS-S590","EFR-S572","AE-1500","SHE-4541","SHE-4540","SHE-4539","MSG-S600","GMA-S2100","SHE-3069","GBA-900","MTP-B105","MTP-B100","PRG-30","GXW-56","EQS-930","DWE-5600","A-171","GST-B400","BGA-280","SHE-4538","LA670WETG-9A","EFS-S580","AW-500","EFA-114","SHE-4543","GBD-200","GA-2200","GM-2100","SHE-4544","MSG-B100","GM-S2100","EQB-1200","ECB-S100","WS-2100","MTP-B110","A-100","SHE-4535","EF-518","MDV-501","LTP-1377","MTP-B300","BEM-107","SLV-21","SHE-4547","SHE-4546","MTP-VD02","MTP-VC01","MTP-B305","MTP-B115","GWG-2000","EFV-620","GAE-2100","EFS-S600","AMW-880","GW-5000","EQW-A2000","MTP-E505","MTP-E195","MTP-B200","WS-1300","SHE-4534","SHE-4062","MTP-2022","LTP-1318","AWM-500","EFV-630","EFV-140","DB-37","LTP-B115","LTP-2022","SHE-3516","EFS-S610","EFR-573","MW-610","LTP-B110","AEQ-120","LTP-VT02","EFB-680","MTP-E600","SHE-4060","LTP-1287","WS-1400","GST-B500","GA-B2100","SHE-3517","MTP-B125","MTP-B120","LWS-1200","GLS-5500","EQS-940","BLX-565","BGD-565","MTP-VD200","ECB-30","BGA-290","EFV-C110","GMA-S2200","LTP-V009","MTG-B3000","MTP-B205","MTP-B310","MTP-E350","MTP-E700","MDV-107","EFS-S620","EFB-108","SHE-4550","EFB-700","PRW-61","SHE-4551","PRW-6621","PRW-6611","PRG-340","BGA-275","AQ-800","ECB-2000","MTP-VD03","MTP-E710","MTP-E705","MTP-E605","SLV-22","SHE-4548","SHE-4059","LOV-21","AMW-500","BGA-310","MSG-C150","EQB-2000","GA-B001","DW-B5600","DW-5000","GM-B2100","GSW-H1000","GM-S110","GW-8230","LOV-22","LTP-2023","MTD-125","MTD-130","MTP-E715","PRW-6900","SHE-4554","MWD-110","A1000RCB-1E","MTP-B135","MTP-B130","ECB-950","BAX-125","LF-10","GWG-2040","SHE-4556","PRW-6630","MTP-W500","GMD-S5600","GM-S5640","GM-S2140","GM-S114","GM-5640","GM-2140","GM-114","ECB-40","G-B001","WS-1500","MTP-E1715","BGD-5650","GBD-H2000","MTP-M305","SHE-4558","EFV-640","EFB-710","GLX-S5600","BGA-320","TQ-228","SHE-4559","PRW-35","GA-114","DWE-5657","GA-2140","DW-6640","MTP-M105","MTP-M300","MTP-M100","LWS-2200","MTP-RS105","MTP-RS100","PRW-73","PRG-601","LTP-E176","LF-20","WS-1600","A-120","DW-H5600","CSP-100","DW-5040","DWE-5640","GMA-S114","GMA-S2140","A-1100","CA-500","EFR-574","GW-9500","LTP-B140","MDV-10","MTP-B140","MTP-B145","ECB-2200","DW-6940","MTP-E725","MTP-E720","DQ-541","PQ-10","TQ-362","EU222-GS-DISP1LBC","EU222-EF-DISP1LBA","EU222-C-DISP1B","IQ-152","IQ-151","IQ-133","IQ-126","IQ-06","IQ-05","EFV-650","PRW-3400","SLV-19","OCW-S100","OCW-S3400","OCW-S5000","OCW-T150","OCW-T200","OCW-T4000","MTP-B155","MTP-B160","LW-205","EQS-950","LTP-V2023","EQW-T660","EQW-T630","SHE-4560","OCW-G1100","EFS-S630","MWQ-100","LA-700","PRX-8001","OCW-S7000","PRW-31","GMA-P2100","WS-1700","OCW-T2600","OCW-T5000","OCW-T6000","GWG-B1000","PRW-51","MTP-E515","MTP-E510","GPR-H1000","EFV-150","GST-B600","GW-S5600","SHE-4562","SHE-4563","EFV-C120","LTP-B165","LTP-2024","GD-B500","MW-620","EQS-960","GA-2300","GR-B300","BGD-10","OCW-S6000","MTP-E335","MTP-E340","MTP-E730","WS-B1000","GBM-2100","TRN-50","LTP-B150","LQ-24","GBD-300","PRJ-B001","GMS-S5600","GA-220","GM-2110","EFB-730","EFS-S641","GA-010","GD-010","GM-700","MTP-B170","MTP-B175","GMD-B300","GMD-S5610","ABL-100","","EFS-S640","MTD-135","GMC-B2100","GM-S2110","GM-S1110","MTP-B180","A-130","AE-1600","ECB-S10","EFB-109","EFR-575","GCW-B5000","GMA-P2125","EFB-670","LTP-B170","LTP-VT03","LTP-VT04","MRW-230","MTP-B146","MTP-E735","MTP-VT03","MTP-VT04","MTS-RS100","MWA-300","OCW-S400"];
$properties = [];

foreach ($models as $model) {
    $properties[$model] = $parser->getProductProperties($model);
}

if (empty($properties)) {
    echo "Не удалось получить данные о продукте. Возможные решения:\n";
    echo "1. Попробуйте позже\n";
    echo "2. Добавьте больше рабочих прокси\n";
    echo "3. Проверьте доступность сайта\n";
} else {
    echo "Найденные характеристики:\n";
    print_r($properties);
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/test/casio/pars.txt", print_r(json_encode($properties, JSON_PRETTY_PRINT),true));
}
