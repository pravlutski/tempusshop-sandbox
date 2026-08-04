<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

if (!class_exists('OzonAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/OzonAPI.php';
}
if (!class_exists('WildberriesAPI')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/WildberriesAPI.php';
}

class FBOBoxes extends CBitrixComponent
{
	public $settings = [];
	
    public function __construct($component = null)
    {
        parent::__construct($component);
    }

    public function onIncludeComponentLang()
    {
        $this->includeComponentLang(basename(__FILE__));
        parent::onIncludeComponentLang();
    }

    public function onPrepareComponentParams($arParams)
    {
        return $arParams;
    }

    public function executeComponent()
    {
		global $DB;
		$this->db = $DB;
		$this->dbPanel = new DBPanel;
		
		$this->settings = unserialize(CProSet::getOption("SETTINGS_UTILS_FBO_BOXES"));

		$this->logger = new TsLogger("/utils/fbo_boxes/");
		
		$this->isDebug = false;
		
		global $USER;
		if ($USER->getID() == 12677) {
			$this->isDebug = true;
		}

		//$this->test();
        try {
            $this->initSession();
            // Проверяем, это AJAX запрос или обычный
            if ($this->isAjaxRequest()) {
                $this->processActions();
            } else {
                $this->prepareTemplateData();
                $this->includeComponentTemplate();
            }
        } catch (Exception $e) {
            if ($this->isAjaxRequest()) {
                $this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
            } else {
                $this->arResult['ERROR'] = $e->getMessage();
                $this->includeComponentTemplate();
            }
        }
    }
	private function getParamsWbCabinet($cabinet = 'WR') {
		if ($cabinet == 'WR') {
			return [
				"Cookie: {$this->settings['cookie-wr']}",
				"authorizev3: {$this->settings['authorizev3-wr']}",
			];
		} else {
			return [
				"Cookie: {$this->settings['cookie-ip']}",
				"authorizev3: {$this->settings['authorizev3-ip']}",
			];
		}
		/*if ($cabinet == 'WR')
			return [
				"Cookie: external-locale=ru; _wbauid=2386893981755374228; wbx-validation-key=da7a8a07-9eca-460c-bc5c-80001eb16bc6; _ga=GA1.1.758177696.1758461879; _ga_TXRZMJQDFE=GS2.1.s1758893014$o19$g1$t1758893075$j60$l0$h0; x-supplier-id-external=e43c8829-c9ad-4cc7-beed-547f4ffe232b; __zzatw-wb=MDA0dC0cTHtmcDhhDHEWTT17CT4VHThHKHIzd2UuQ2wdYU1cJDVRP0FaW1Q4NmdBEXUmCQg3LGBwVxlRExpceEdXeiwfFghxLE8QDFxCQmllbQwtUlFRS19/Dg4/aU5ZQ11wS3E6EmBWGB5CWgtMeFtLKRZHGzJhXkZpdRVVfwtfPUh1ejFxISVjThYjSxALeyUfRTN0I1AQEBZzRV9vG3siXyoIJGM1Xz9EaVhTMCpYQXt1J3Z+KmUzPGwjZFBfKENeTnoqGg1pN2wXPHVlLwkxLGJ5MVIvE0tsP0caRFpbQDsyVghDQE1HFF9BWncyUlFRS2EQR0lrZU5TQixmG3EVTQgNND1aciIPWzklWAgSPwsmIBd8dClYfxRcPkZvbxt/Nl0cOWMRCxl+OmNdRkc3FSR7dSYKCTU3YnAvTCB7SykWRxsyYV5GaXUVDAsOFkVJb3wmbm5OG0RdUnZcSjNaTkV0cCdWPQ8Zc3ZvdittVxlRDxZhDhYYRRcje0I3Yhk4QhgvPV8/YngiD2lIYCVIXlMKJSISenErS3FPLH12X30beylOIA0lVBMhP05ybiel3Q==; cfidsw-wb=v1ouZPZTkzN6OwgRR4O97zBAucTFrJbkctyUuzTtz8AG9ZF5aSigZ9UuBtxBlK5PNbWV5E4wIeh78QdMATZ2nmRfEF+EjK0X6V1sPJUK6PsB2Xl7751iIxTb3z2cIIUS85g9DAXLHHCBq6LPVVN3a/YFMa9SjUSSP2zd26NP",
				"authorizev3: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NjU4MDU4MzYsInVzZXIiOiI1ODcwMjYzNSIsInNoYXJkX2tleSI6IjgiLCJjbGllbnRfaWQiOiJzZWxsZXItcG9ydGFsIiwic2Vzc2lvbl9pZCI6IjYwMDQwNzg3OGZkODQ3ZTQ4Y2YyMDZkZDkwMTk1Y2Y0IiwidmFsaWRhdGlvbl9rZXkiOiJmOWMxNzZmN2VkYjI2MTZhODdlZmFmNzFmMTg3Nzk0YzI2Y2IxOTNiODM3NmU5MjRjZTY1NDYzMjBiYTE2YzEzIiwidXNlcl9yZWdpc3RyYXRpb25fZHQiOjE2NzQxMzQ4NDEsInZlcnNpb24iOjJ9.KWTT0giVWgdWRBFz_r8Q5hRO2IA0_U1SS-5cm1xxM4GFSOksERS7iwIgL_M7GBUpP9Q75YLynBLU4kAmuQ2nV3TmPeS7kAvFKGA_Y4h7YwfnyjF34jx2s1Pg5N6fwTaihatCZGRC3NQhlVgJQ53EObrsl8UXJKC-Tu9eEzkwGrJxAUxW-Il9IMGIYbQ8f8ssQKDs1h3vfaokphJybY0pwQ6SPhl768H76uuW-IPSxmMWe1IDG1q5eU8KXOkpkLgFrKKApJLYXpMHl3E3Tqw8Y3Rb2lc1c86wY3VEDagJS35Ql0Mm5XreGoaGLUW-XFsnWA2ZEIVw8k4x_OtDuA2StA",
			];
		//ip   
		return [
			"Cookie: external-locale=ru; _wbauid=2386893981755374228; wbx-validation-key=da7a8a07-9eca-460c-bc5c-80001eb16bc6; _ga=GA1.1.758177696.1758461879; _ga_TXRZMJQDFE=GS2.1.s1758893014$o19$g1$t1758893075$j60$l0$h0; x-supplier-id-external=1b8230a0-56f8-4be6-9640-e48cf1365dfc; __zzatw-wb=MDA0dC0cTHtmcDhhDHEWTT17CT4VHThHKHIzd2UuQ2wdYU1cJDVRP0FaW1Q4NmdBEXUmCQg3LGBwVxlRExpceEdXeiwfFXhwKE8ODVxAQ2llbQwtUlFRS19/Dg4/aU5ZQ11wS3E6EmBWGB5CWgtMeFtLKRZHGzJhXkZpdRVVfwtfPUh1ejFxISVjThYjSxALeyUfRTN0I1AQEBZzRV9vG3siXyoIJGM1Xz9EaVhTMCpYQXt1J3Z+KmUzPGwjY0heJENcT3ooGw1pN2wXPHVlLwkxLGJ5MVIvE0tsP0caRFpbQDsyVghDQE1HFF9BWncyUlFRS2EQR0lrZU5TQixmG3EVTQgNND1aciIPWzklWAgSPwsmIBd7bChUfxJdPkRvbxt/Nl0cOWMRCxl+OmNdRkc3FSR7dSYKCTU3YnAvTCB7SykWRxsyYV5GaXUVUDkTXUBBJnMmQGtTZ0RdUXhbSgorHRF0KCdXOkFcQEdyL19uVxlRDxZhDhYYRRcje0I3Yhk4QhgvPV8/YngiD2lIYCVHVlJ+JSATeXIlS3FPLH12X30beylOIA0lVBMhP05yfMzHbg==; cfidsw-wb=fLo7YIzNi3I/jCl9/CyCZAGdd3t5J6u/5SYk38M/GZsVDpYJSUfPtr8vchiUVd1DRG0JxUAhygiFr7Tyn3SXM1N+71YwInzoyikzA5sSR3/N7Gru6+pzco/tJExFd5M3CZdMYdnX/n90g5elZnL1GoEEh8uNyutMYM8xzYid",
			"authorizev3: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3NjMyMTM4MTgsInVzZXIiOiI1ODcwMjYzNSIsInNoYXJkX2tleSI6IjgiLCJjbGllbnRfaWQiOiJzZWxsZXItcG9ydGFsIiwic2Vzc2lvbl9pZCI6IjYwMDQwNzg3OGZkODQ3ZTQ4Y2YyMDZkZDkwMTk1Y2Y0IiwidmFsaWRhdGlvbl9rZXkiOiJmOWMxNzZmN2VkYjI2MTZhODdlZmFmNzFmMTg3Nzk0YzI2Y2IxOTNiODM3NmU5MjRjZTY1NDYzMjBiYTE2YzEzIiwidXNlcl9yZWdpc3RyYXRpb25fZHQiOjE2NzQxMzQ4NDEsInZlcnNpb24iOjJ9.hpbUcMcP0QFVw7m-Bx_UKyqshAKe6RvSi4MGRs77GfgnMj5pEGqacEyjQGzVzZDp0tNybNgh1l7uwUhC56ZQAAG5LAbOhjlJ7Wsc1BREciFn8-2dLNJpdlcJp92QURu1-y-z5IhbyWbjsjy0I2fN_hNyPVnRYarR6cz4F_c-nnqMaAh_a0vR-ec4oV7c8hL0y6DsY5-pyc7HF0A8qiDRjCLecyJ7jJBOwG3kBDJTVfrCT6uLMbI2afA1qK5MX_m3MfgIGh25krWTsd9Y2QYUDOafFJR3B5B_V1jJvcqt6hKZXgt6CgSEBRRMCoMvB7NaFSp-u-g-10r7QfEP1jOuvA",
		];*/
	}
	private function test() {
		global $USER;
		if ($USER->getID() != 12677) {
			return;
		}
		return;
		$ozon = new OzonAPI('IP');
		
		/*$cargoes = [];
		for ($i = 1; $i <= $boxCount; $i++) {
			$cargoes[] = [
				'key' => 'TEMPUS_' . $i,
				'value' => [
					//'items' => [],
					'type' => 'BOX'
				]
			];
		}*/
		$cargoes = [];
		$items = [];
		$items[] = [
			'barcode' => '2023304309400',
			//'quant' => (int)$item['quantity'],
			'quantity' => 1,
		];
		$cargoes[] = [
			'key' => 'TEMPUS_1',
			'value' => [
				'items' => $items,
				'type' => 'BOX'
			]
		];
			
		$requestData = [
			'cargoes' => $cargoes,
			'cargo_id' => 1000000000026957810
		];
		
		$result = $ozon->send(action: "/v1/cargoes/update", data: $requestData, method: "POST");
		prent($result);die;
		return;
		prent('test');
		$requestData = [
			'items' => [
        ['quantity' => 4, 'quant' => 4, 'sku' => '2153743041'],
        //['quantity' => 9, 'quant' => 9, 'sku' => '2153746788'],
        ['quantity' => 8, 'quant' => 8, 'sku' => '2153741387'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2153745756'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2153742263'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153745655'],
        ['quantity' => 6, 'quant' => 6, 'sku' => '2153966658'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153745706'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153965221'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2153966498'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154031923'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031167'],
        ['quantity' => 8, 'quant' => 8, 'sku' => '2154031315'],
        ['quantity' => 13, 'quant' => 13, 'sku' => '2153738263'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154032034'],
        ['quantity' => 7, 'quant' => 7, 'sku' => '2154031309'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154031203'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154073819'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154030801'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154073292'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154070107'],
        ['quantity' => 7, 'quant' => 7, 'sku' => '2154032534'],
        ['quantity' => 6, 'quant' => 6, 'sku' => '2154030798'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154031024'],
        ['quantity' => 12, 'quant' => 12, 'sku' => '2154031281'],
        ['quantity' => 8, 'quant' => 8, 'sku' => '2154030857'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031057'],
        ['quantity' => 6, 'quant' => 6, 'sku' => '2154032075'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153952102'],
        ['quantity' => 7, 'quant' => 7, 'sku' => '2154030675'],
        ['quantity' => 7, 'quant' => 7, 'sku' => '2154031239'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154030982'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2153954316'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154026161'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154030797'],
        ['quantity' => 6, 'quant' => 6, 'sku' => '2154031046'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153959419'],
        ['quantity' => 8, 'quant' => 8, 'sku' => '2154031048'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154074333'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154031262'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154070381'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154030816'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154030661'],
        ['quantity' => 6, 'quant' => 6, 'sku' => '2154031129'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154026255'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154073326'],
        ['quantity' => 18, 'quant' => 18, 'sku' => '2154031141'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154073642'],
        ['quantity' => 6, 'quant' => 6, 'sku' => '2154030659'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031376'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073484'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154031026'],
        ['quantity' => 5, 'quant' => 5, 'sku' => '2154031016'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031197'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031234'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073461'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031333'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2154030958'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031195'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154031254'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154031276'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154030671'],
        ['quantity' => 5, 'quant' => 5, 'sku' => '2154031356'],
        ['quantity' => 4, 'quant' => 4, 'sku' => '2158281498'],
        ['quantity' => 8, 'quant' => 8, 'sku' => '2154031171'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073551'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153963874'],
        ['quantity' => 5, 'quant' => 5, 'sku' => '2154030827'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154030927'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154073342'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154030706'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031830'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154032052'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073488'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073320'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073244'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154030703'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154026289'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031075'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153960631'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154073374'],
        ['quantity' => 3, 'quant' => 3, 'sku' => '2154031123'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031142'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154031200'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154030836'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2153954753'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031210'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154031147'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2153950010'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154031178'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073263'],
        ['quantity' => 2, 'quant' => 2, 'sku' => '2154031113'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154073429'],
        ['quantity' => 1, 'quant' => 1, 'sku' => '2154031168']
			],
			'order_id' => 65188162,
			'supply_id' => 2000029138918
		];
		prent($requestData);
		
		$result = $ozon->send(action: "/v1/supply-order/content/update", data: $requestData, method: "POST");
		
		//$result = ['operation_id' => '01991477-92e5-739f-9469-f7d35b23f35a'];
		$operationId = $result['operation_id'];
		$maxAttempts = 30;
		$attempt = 0;
		$operationStatus = 'IN_PROGRESS';
		
		while ($operationStatus === 'IN_PROGRESS' && $attempt < $maxAttempts) {
			sleep(2);
			
			$statusResult = $ozon->send(
				action: "/v1/supply-order/content/update/status", 
				data: ['operation_id' => $operationId], 
				method: "POST"
			);
			prent($statusResult);
			if (isset($statusResult['status'])) {
				$operationStatus = $statusResult['status'];
				
				if ($operationStatus === 'ERROR') {
					$this->logger->log("ERROR", "Ошибка при обновлении товарных позиций", $statusResult); 
					return [
						'error' => 'Ошибка при обновлении товарных позиций (статус операции: ERROR)',
						'operation_id' => $operationId
					];
				}
				
				if ($operationStatus === 'SUCCESS') {
					break;
				}
			}
			
			$attempt++;
		}
		
		prent([$result]);
	}
    private function isAjaxRequest()
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        return $request->isPost() && $request->getPost('action');
    }

    private function initSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['FBO_DATA'])) {
            $_SESSION['FBO_DATA'] = [
                'step' => 1,
                'boxes' => [],
                'selected_supply' => null,
                'marketplace' => ''
            ];
        }
    }

    private function processActions()
    {
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        
        if ($request->isPost() && check_bitrix_sessid()) {
            $action = $request->getPost('action');
            $this->logger->log("LOG", "Action " . $action, $_REQUEST);
            switch ($action) {
                case 'select_marketplace':
                    $this->selectMarketplace();
                    break;
                    
                case 'create_boxes':
                    $this->createBoxes();
                    break;
                    
                case 'scan_product':
                    $this->scanProduct();
                    break;
                    
                case 'send_boxes':
                    $this->sendBoxes();
                    break;
				
				case 'remove_product':
					$this->removeProduct();
					break;
				
				case 'remove_box':
					$this->removeBox();
					break;
					
				case 'print_barcodes':
					$this->printBarcodes();
					break;
					
				case 'reset_to_step1':
					$this->resetToStep1();
					break;
					
                default:
                    $this->sendJsonResponse(['success' => false, 'error' => 'Неизвестное действие']);
                    break;
            }
        } else {
            $this->sendJsonResponse(['success' => false, 'error' => 'Ошибка проверки сессии']);
        }
    }

    private function selectMarketplace()
    {
        $marketplace = $this->request->getPost('marketplace');
        if (!in_array($marketplace, ['ozon', 'wb'])) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Неверный маркетплейс']);
            return;
        }
        
        $_SESSION['FBO_DATA']['marketplace'] = $marketplace;
        $this->sendJsonResponse(['success' => true]);
    }

    private function createBoxes()
    {
        $boxCount = (int)$this->request->getPost('box_count');
        $supplyId = $this->request->getPost('supply_id');
        
        if ($boxCount < 1 || $boxCount > 20) {
            $this->sendJsonResponse(['success' => false, 'error' => 'Неверное количество коробов (1-20)']);
            return;
        }
		
		$marketplace = $_SESSION['FBO_DATA']['marketplace'] ?? '';
		
		try {
			if ($marketplace === 'ozon') {
				// создаем пустые
				// $result = $this->createOzonBoxes($supplyId, $boxCount);
			} elseif ($marketplace === 'wb') {
				$result = $this->createWbBoxes($supplyId, $boxCount);
			} else {
				$this->logger->log("ERROR", "createBoxes", 'Неизвестный маркетплейс');
				throw new Exception('Неизвестный маркетплейс');
			}
		} catch (Exception $e) {
			$this->logger->log("ERROR", "createBoxes", [$e->getMessage()]);
			$this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
		}
		
		if ($result['error']) {
			$this->sendJsonResponse(['success' => false, 'error' => $result['error']]);
			return;
		}
		
		$_SESSION['FBO_DATA']['boxes'] = [];
		
		if ($marketplace === 'wb') {
			foreach ($result['boxes'] as $arItem) {
				$_SESSION['FBO_DATA']['boxes'][$arItem['market_id']] = [];
			}
		} else {
			for ($i = 1; $i <= $boxCount; $i++) {
				$key = 'TEMPUS_' . $i;
				$_SESSION['FBO_DATA']['boxes'][$key] = [];
			}
		}

		
        //$_SESSION['FBO_DATA']['boxes'] = array_fill(1, $boxCount, []);

        $_SESSION['FBO_DATA']['selected_supply'] = $this->findSupply($supplyId);
        $_SESSION['FBO_DATA']['button_print'] = false; // кнопка печати
		$_SESSION['FBO_DATA']['button_print_href'] = false;
        $_SESSION['FBO_DATA']['step'] = 2;
        
        $this->sendJsonResponse(['success' => true]);
    }
	
	private function createOzonBoxes($supplyId, $boxCount) {
		$ozon = new OzonAPI('IP');
		
		$cargoes = [];
		for ($i = 1; $i <= $boxCount; $i++) {
			$cargoes[] = [
				'key' => 'TEMPUS_' . $i,
				'value' => [
					//'items' => [],
					'type' => 'BOX'
				]
			];
		}

		$requestData = [
			'cargoes' => $cargoes,
			'delete_current_version' => true,
			'supply_id' => (int)$supplyId
		];
		
		$result = $ozon->send(action: "/v1/cargoes/create", data: $requestData, method: "POST");
			
		$this->logger->log("LOG", "Создаем пустые короба", ["requestData" => $requestData, "result" => $result]);
		
		// проверяем статус создания
		$operationId = $result['operation_id'];
		$maxAttempts = 30;
		$attempt = 0;
		$operationStatus = 'IN_PROGRESS';
		
		while ($operationStatus === 'IN_PROGRESS' && $attempt < $maxAttempts) {
			sleep(2);
			
			$statusResult = $ozon->send(
				action: "/v1/cargoes/create/info", 
				data: ['operation_id' => $operationId], 
				method: "POST"
			);
			//$this->logger->log("LOG", "статус создания пустых коробов", $statusResult);
			if (isset($statusResult['status'])) {
				$operationStatus = $statusResult['status'];
				
				if ($operationStatus === 'FAILED') {
					$this->logger->log("ERROR", "Ошибка при создании пустых коробов (статус операции: FAILED )", $operationId);
					return [
						'error' => 'Ошибка при создании пустых коробов (статус операции: FAILED )',
						'operation_id' => $operationId
					];
				}
				
				if ($operationStatus === 'SUCCESS') {
					break;
				}
			}
			
			$attempt++;
		}
		
		if ($operationStatus === 'IN_PROGRESS') {
			$this->logger->log("ERROR", "Превышено время ожидания завершения операции создания пустых коробов", $operationId);
			return [
				'error' => 'Превышено время ожидания завершения операции создания пустых коробов',
				'operation_id' => $operationId
			];
		}

		$arBoxes = [];
		foreach ($statusResult['result']['cargoes'] as $arItem) {
			$arBoxes[] = [
				'key' => $arItem['key'],
				'market_id' => $arItem['value']['cargo_id'],
			];
		}
		
		$arBoxes = sort_nested_arrays($arBoxes, ['market_id' => 'desc']);
		
		$this->logger->log("LOG", "Создание пустых коробов успешно", $arBoxes);
		
		return ['boxes' => $arBoxes];
	}
	
	private function createWbBoxes($supplyId, $boxCount) {
		
		$arSupply = $this->findSupply($supplyId);
		$cabinet = $arSupply['cabinet'];

		$wb = new WildberriesAPI($cabinet);
		$wb->changeApiUrl('https://seller-supply.wildberries.ru');

		$header = $this->getParamsWbCabinet($cabinet);
		// получаем список коробов
		
		$requestData = [
			'id' => 'json-rpc_56',
			'jsonrpc' => '2.0',
			'method' => 'ListBarcodesBoxes',
			'params' => [
				'incomeID' => (int)$supplyId,
			],
		];

		try {
			$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box", data: $requestData, method: "POST", header: $header);
		} catch (Exception $e) {
			$this->logger->log("LOG", "/ns/sm-box/supply-manager/api/v1/box", ['error' => $e->getMessage()]);
		}
		
		$this->logger->log("LOG", "result", $result);
		if (!isset($result['result'])) {
			$this->logger->log("ERROR", "Список коробов WB", $result);
			return [
				'error' => 'Ошибка получения списка коробов WB',
			];
		}
		
		$this->logger->log("LOG", "Список коробов WB", [$requestData, $result]);
		
		// очищаем
		$boxDelete = [];
		foreach ($result['result']['boxes'] as $arItem) {
			//{"params":{"incomeID":31997918,"boxCode":"WB_1430694141"},"jsonrpc":"2.0","id":"json-rpc_60"}
			$requestData = [
				'id' => 'json-rpc_60',
				'jsonrpc' => '2.0',
				'params' => [
					'incomeID' => (int)$supplyId,
					'boxCode' => $arItem['boxcode'],
				],
			];
			$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box/removeBox", data: $requestData, method: "POST", header: $header);
			
			$this->logger->log("LOG", "Удаление короба WB", [$requestData, $result]);
			
			if (!isset($result['result'])) {
				$this->logger->log("ERROR", "Ошибка удаления короба", $result);
				return [
					'error' => "Ошибка удаления короба {$arItem['boxcode']}",
				];
			}
			$boxDelete[] = $arItem['boxcode'];
		}
		
		if (count($boxDelete) > 0) {
			$requestData = [
				'id' => 'json-rpc_61',
				'jsonrpc' => '2.0',
				'params' => [
					'supplyId' => (int)$supplyId,
					'barcodesToDelete' => $boxDelete,
				],
			];
			$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box/deleteBoxBarcodes", data: $requestData, method: "POST", header: $header);
			
			
			
			if (
				!isset($result['result']['barcodes']) || 
				(is_array($result['result']['barcodes']) && count($result['result']['barcodes']) > 0)
			) {
				$this->logger->log("ERROR", "Ошибка при удалении. 2 метод", [$requestData, $result]);
				return [
					'error' => 'Ошибка при удалении',
				];
			}
			
			$this->logger->log("LOG", "Удаление короба WB. 2 метод", [$requestData, $result]);
		}

			//return [
			//	'error' => 'Ошибка при создании пустых коробов',
			//];

		// создаем новые
		$requestData = [
			'id' => 'json-rpc_187',
			'jsonrpc' => '2.0',
			'params' => [
				'supplyId' => (int)$supplyId,
				'barcodeNumber' => (int)$boxCount,
			],
		];
		$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box/createBoxBarcodes", data: $requestData, method: "POST", header: $header);
		
		if (!isset($result['result']['barcodes'])) {
			return [
				'error' => 'Ошибка при создании пустых коробов',
			];
		}
		
		$arBoxes = [];
		foreach ($result['result']['barcodes'] as $code) {
			$arBoxes[] = [
				'key' => $code,
				'market_id' => $code,
			];
		}
		
		//$arBoxes = sort_nested_arrays($arBoxes, ['market_id' => 'desc']);
		
		$this->logger->log("LOG", "Создание пустых коробов успешно", $arBoxes);
		
		return ['boxes' => $arBoxes];

		$this->logger->log("LOG", "Создаем пустые короба WB", ["requestData" => $requestData, "result" => $result]);
			return [
				'error' => serialize($supplyId) . ' asdasd',
			];
		/*$ozon = new OzonAPI('IP');
		
		$cargoes = [];
		for ($i = 1; $i <= $boxCount; $i++) {
			$cargoes[] = [
				'key' => 'TEMPUS_' . $i,
				'value' => [
					//'items' => [],
					'type' => 'BOX'
				]
			];
		}

		$requestData = [
			'cargoes' => $cargoes,
			'delete_current_version' => true,
			'supply_id' => (int)$supplyId
		];
		
		$result = $ozon->send(action: "/v1/cargoes/create", data: $requestData, method: "POST");
			
		$this->logger->log("LOG", "Создаем пустые короба", ["requestData" => $requestData, "result" => $result]);
		
		// проверяем статус создания
		$operationId = $result['operation_id'];
		$maxAttempts = 30;
		$attempt = 0;
		$operationStatus = 'IN_PROGRESS';
		
		while ($operationStatus === 'IN_PROGRESS' && $attempt < $maxAttempts) {
			sleep(2);
			
			$statusResult = $ozon->send(
				action: "/v1/cargoes/create/info", 
				data: ['operation_id' => $operationId], 
				method: "POST"
			);
			//$this->logger->log("LOG", "статус создания пустых коробов", $statusResult);
			if (isset($statusResult['status'])) {
				$operationStatus = $statusResult['status'];
				
				if ($operationStatus === 'FAILED') {
					$this->logger->log("ERROR", "Ошибка при создании пустых коробов (статус операции: FAILED )", $operationId);
					return [
						'error' => 'Ошибка при создании пустых коробов (статус операции: FAILED )',
						'operation_id' => $operationId
					];
				}
				
				if ($operationStatus === 'SUCCESS') {
					break;
				}
			}
			
			$attempt++;
		}
		
		if ($operationStatus === 'IN_PROGRESS') {
			$this->logger->log("ERROR", "Превышено время ожидания завершения операции создания пустых коробов", $operationId);
			return [
				'error' => 'Превышено время ожидания завершения операции создания пустых коробов',
				'operation_id' => $operationId
			];
		}

		$arBoxes = [];
		foreach ($statusResult['result']['cargoes'] as $arItem) {
			$arBoxes[] = [
				'key' => $arItem['key'],
				'market_id' => $arItem['value']['cargo_id'],
			];
		}
		
		$arBoxes = sort_nested_arrays($arBoxes, ['market_id' => 'desc']);
		
		$this->logger->log("LOG", "Создание пустых коробов успешно", $arBoxes);
		
		return ['boxes' => $arBoxes];*/
	}
	
	private function removeProduct()
	{
		$boxNumber = $this->request->getPost('box_number');
		$barcode = trim($this->request->getPost('barcode'));
		
		if (empty($barcode)) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Пустой штрихкод']);
			return;
		}
		
		if (!isset($_SESSION['FBO_DATA']['boxes'][$boxNumber])) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Короб не найден']);
			return;
		}
		
		$foundIndex = -1;
		foreach ($_SESSION['FBO_DATA']['boxes'][$boxNumber] as $index => $item) {
			if ($item['barcode'] === $barcode) {
				$foundIndex = $index;
				break;
			}
		}
		
		if ($foundIndex === -1) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Товар не найден в коробе']);
			return;
		}
		
		$removedProduct = $_SESSION['FBO_DATA']['boxes'][$boxNumber][$foundIndex];
		unset($_SESSION['FBO_DATA']['boxes'][$boxNumber][$foundIndex]);
		
		$_SESSION['FBO_DATA']['boxes'][$boxNumber] = array_values($_SESSION['FBO_DATA']['boxes'][$boxNumber]);
		
		$this->sendJsonResponse([
			'success' => true,
			'product' => $removedProduct,
			'box_number' => $boxNumber,
			'remaining_items' => count($_SESSION['FBO_DATA']['boxes'][$boxNumber])
		]);
	}
	
	private function removeBox()
	{
		$marketplace = $_SESSION['FBO_DATA']['marketplace'] ?? '';
		$supplyId = $_SESSION['FBO_DATA']['selected_supply']['id'] ?? '';
		$boxNumber = $this->request->getPost('box_number');
		
		if (!isset($_SESSION['FBO_DATA']['boxes'][$boxNumber])) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Короб не найден']);
			return;
		}

		if (!$marketplace || !$supplyId || !in_array($marketplace, ['ozon', 'wb'])) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Поставка не определена']);
			return;
		}
		
		if ($marketplace == 'ozon') {
			unset($_SESSION['FBO_DATA']['boxes'][$boxNumber]);
		} else {
			
			$arSupply = $this->findSupply($supplyId);
			$cabinet = $arSupply['cabinet'];

			$wb = new WildberriesAPI($cabinet);
			$wb->changeApiUrl('https://seller-supply.wildberries.ru');

			$header = $this->getParamsWbCabinet($cabinet);
		
			$requestData = [
				'id' => 'json-rpc_54',
				'jsonrpc' => '2.0',
				'params' => [
					'incomeID' => (int)$supplyId,
					'boxCode' => $boxNumber,
				],
			];
			
			$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box/removeBox", data: $requestData, method: "POST", header: $header);
			
			$this->logger->log("LOG", "Удаления короба", [$requestData, $result]);
			
			if (!isset($result['result'])) {
				$this->logger->log("ERROR", "Ошибка удаления короба", [$requestData, $result]);
				return [
					'error' => 'Ошибка обновления товарных позиций',
				];
			}
			
			// втророй запрос на удаление
			$requestData = [
				'id' => 'json-rpc_61',
				'jsonrpc' => '2.0',
				'params' => [
					'supplyId' => (int)$supplyId,
					'barcodesToDelete' => [$boxNumber],
				],
			];
			$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box/deleteBoxBarcodes", data: $requestData, method: "POST", header: $header);
			
			$this->logger->log("LOG", "Удаления короба 2", [$requestData, $result]);
			if (
				!isset($result['result']) || 
				is_array($result['result']['barcodes']) && in_array($boxNumber, $result['result']['barcodes']) > 0
			) {
				$this->logger->log("ERROR", "Ошибка при удалении. 2 метод", [$requestData, $result]);
				return [
					'error' => 'Ошибка при удалении',
				];
			}
			
			unset($_SESSION['FBO_DATA']['boxes'][$boxNumber]);
			
			//$this->sendJsonResponse(['success' => false, 'error' => 'Удаление в WB не доделано']);
			//return; 
		}

		$this->sendJsonResponse([
			'success' => true,
			'box_number' => $boxNumber,
		]);
	}
	
	
    private function sendBoxes()
    {
		$marketplace = $_SESSION['FBO_DATA']['marketplace'] ?? '';
		$supplyId = $_SESSION['FBO_DATA']['selected_supply']['id'] ?? '';
		
		$this->logger->log("LOG", "FBO_DATA", $_SESSION['FBO_DATA']);
		
		if (!$marketplace || !$supplyId) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Данные не заполнены']);
			return;
		}
			//$this->sendJsonResponse(['success' => false, 'error' => 'ввввввввввввввы']);
			//return;
		try {
			if ($marketplace === 'ozon') {
				$result = $this->sendToOzon();
			} elseif ($marketplace === 'wb') {
				$result = $this->sendToWildberries($supplyId);
			} else {
				throw new Exception('Неизвестный маркетплейс');
			}
			
			/*$_SESSION['FBO_DATA'] = [
				'step' => 1,
				'boxes' => [],
				'selected_supply' => null,
				'marketplace' => $marketplace // Сохраняем выбранный маркетплейс
			];*/
			
			if ($result['error']) {
				$this->sendJsonResponse([
					'success' => false, 
					'error' => $result['error'], 
					'errors_detail' => $result['errors_detail'],
				]);
				return;
			}
			
			$_SESSION['FBO_DATA']['button_print'] = true;
			
			$this->sendJsonResponse([
				'success' => true, 
				'message' => 'Данные успешно отправлены в ' . $marketplace,
				//'data' => $result
			]);
			
		} catch (Exception $e) {
			$this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
		}
    }	
	
	private function sendToOzon()
	{
		$ozon = new OzonAPI('IP');
		
		$supplyId = $_SESSION['FBO_DATA']['selected_supply']['id'] ?? '';
		$orderId = $_SESSION['FBO_DATA']['selected_supply']['order_id'] ?? '';
		
		$items = [];
		// сразу обновляем товарные позиции
		foreach ($_SESSION['FBO_DATA']['boxes'] as $boxItems) {
			foreach ($boxItems as $item) {
				if (!$item['sku_market']) continue;
				
				if ($items[$item['sku_market']]) {
					$items[$item['sku_market']]['quantity'] += (int)$item['quantity'];
					$items[$item['sku_market']]['quant'] += (int)$item['quantity'];
				} else {
					$items[$item['sku_market']] = [
						'quantity' => (int)$item['quantity'],
						'quant' => (int)$item['quantity'],
						'sku' => $item['sku_market'],
					];
				}
			}
		}
			
		if (!$items) {
			return [
				'error' => 'Не сформирован массив товаров для обновления',
			];
		}

		$requestData = [
			'items' => array_values($items),
			'order_id' => (int)$orderId,
			'supply_id' => (int)$supplyId,
		];

		$result = $ozon->send(action: "/v1/supply-order/content/update", data: $requestData, method: "POST");
		
		$this->logger->log("LOG", "Обновляем товарные позиции", ["requestData" => $requestData, "result" => $result]);
		
		if (!$result['operation_id']) {
			$this->logger->log("ERROR", "Ошибка обновления товарных позиций", $result);
			return [
				'error' => 'Ошибка обновления товарных позиций',
			];
		}
		
		// проверяем статус обновления товарных позиций
		$operationId = $result['operation_id'];
		$maxAttempts = 30;
		$attempt = 0;
		$operationStatus = 'IN_PROGRESS';
		
		while ($operationStatus === 'IN_PROGRESS' && $attempt < $maxAttempts) {
			sleep(2);
			
			$statusResult = $ozon->send(
				action: "/v1/supply-order/content/update/status", 
				data: ['operation_id' => $operationId], 
				method: "POST"
			);
			
			if (isset($statusResult['status'])) {
				$operationStatus = $statusResult['status'];
				
				if ($operationStatus === 'ERROR') {
					$this->logger->log("ERROR", "Ошибка при обновлении товарных позиций", $statusResult); 
					return [
						'error' => 'Ошибка при обновлении товарных позиций (статус операции: ERROR)',
						'operation_id' => $operationId
					];
				}
				
				if ($operationStatus === 'SUCCESS') {
					break;
				}
			}
			
			$attempt++;
		}
		
		if ($operationStatus === 'IN_PROGRESS') {
			return [
				'error' => 'Превышено время ожидания завершения операции обновления товарных позиций',
				'operation_id' => $operationId
			];
		}
		
		$cargoes = [];
		//$boxNumber = 1;
		
		foreach ($_SESSION['FBO_DATA']['boxes'] as $key => $boxItems) {
			$items = [];
			
			foreach ($boxItems as $item) {
				$items[] = [
					'barcode' => $item['barcode'],
					//'quant' => (int)$item['quantity'],
					'quantity' => (int)$item['quantity'],
					//'expires_at' => null
				];
			}
			
			$cargoes[] = [
				//'key' => 'TEMPUS_' . $boxNumber,
				'key' => (string) $key,
				'value' => [
					'items' => $items,
					'type' => 'BOX'
				]
			];
			
			//$boxNumber++;
		}
		
		$requestData = [
			'cargoes' => $cargoes,
			//'delete_current_version' => false,
			'delete_current_version' => true,
			'supply_id' => (int)$supplyId
		];
		
		$result = $ozon->send(action: "/v1/cargoes/create", data: $requestData, method: "POST");

		$this->logger->log("LOG", "Обновляем короба", ["requestData" => $requestData, "result" => $result]);
		
		if (!$result['operation_id']) {
			return [
				'error' => 'Ошибка при обновлении коробов. ' . ($result['error'] ? $result['error'] : ''),
				'operation_id' => $operationId
			];
		}
		
		// проверяем статус создания
		$operationId = $result['operation_id'];
		$maxAttempts = 30;
		$attempt = 0;
		$operationStatus = 'IN_PROGRESS';
		
		while ($operationStatus === 'IN_PROGRESS' && $attempt < $maxAttempts) {
			sleep(2);
			
			$statusResult = $ozon->send(
				action: "/v1/cargoes/create/info", 
				data: ['operation_id' => $operationId], 
				method: "POST"
			);

			if (isset($statusResult['status'])) {
				$operationStatus = $statusResult['status'];
				
				if ($operationStatus === 'FAILED') {
					$this->logger->log("ERROR", "Ошибка при обновлении коробов (статус операции: FAILED )", [$operationId, $statusResult]);
					return [
						'error' => 'Ошибка при обновлении коробов (статус операции: FAILED )',
						'operation_id' => $operationId
					];
				}
				
				if ($operationStatus === 'SUCCESS') {
					break;
				}
			}
			
			$attempt++;
		}
		
		if ($operationStatus === 'IN_PROGRESS') {
			$this->logger->log("ERROR", "Превышено время ожидания завершения операции обновлении коробов", $operationId);
			return [
				'error' => 'Превышено время ожидания завершения операции обновлении коробов',
				'operation_id' => $operationId
			];
		}
		
		$this->logger->log("LOG", "Обновление коробов успешно", $statusResult);
		
		// подменяем массив в сессии
		//$statusResult
		$keyMapping = [];

		// Проходим по всем cargoes из ответа API
		foreach ($statusResult['result']['cargoes'] as $cargo) {
			$oldKey = (string) $cargo['key'];
			$newKey = (string) $cargo['value']['cargo_id'];
			
			$keyMapping[$oldKey] = $newKey;
		}

		$newBoxes = [];

		foreach ($_SESSION['FBO_DATA']['boxes'] as $oldKey => $boxContent) {
			if (isset($keyMapping[$oldKey])) {
				$newBoxes[$keyMapping[$oldKey]] = $boxContent;
			}
		}

		$this->logger->log("LOG", "Новые короба", $newBoxes);
		$_SESSION['FBO_DATA']['boxes'] = $newBoxes;
		
		return [
			'success' => true,
			//'boxes_count' => count($cargoes),
			//'total_items' => array_sum(array_map('count', $_SESSION['FBO_DATA']['boxes']))
		];
	}
	
	private function sendToWildberries ($supplyId) {
		$errors = [];
		/*global $USER;
		if ($USER->getID() == 12677) {
			$barcodes = [
				[
					'barcode' => '2029026869628',
					'quantity' => 1,
					'hasExcise' => false,
					'errors' => [
						[
							'field' => 'barcode',
							'error' => 'Создайте поставку типа «Суперсейф» для этого товара'
						]
					],
					'hasError' => 1,
					'hasOptionalExpirationDate' => false,
					'imtName' => 'Часы Casio Edifice EFR-539D-1A2',
					'imgSrc' => 'https://basket-05.wbbasket.ru/vol754/part75454/75454325/images/tm/1.webp',
					'subject' => 'Часы наручные',
					'brand' => 'Casio',
					'saNm' => 'W_CASIO_EFR-539D-1A2',
					'nmID' => 75454325,
					'needExpirationDate' => false,
					'needKIZ' => false,
					'tnved' => '',
					'hasTNVED' => false,
					'isFood' => false,
					'colorName' => 'серый',
					'tsName' => '0',
					'isDelicate' => false,
					'isBoxAcceptance' => false
				],
				[
					'barcode' => '2029027123620',
					'quantity' => 1,
					'hasExcise' => false,
					'errors' => [
						[
							'field' => 'barcode',
							'error' => 'Создайте поставку типа «Суперсейф» для этого товара'
						]
					],
					'hasError' => 1,
					'hasOptionalExpirationDate' => false,
					'imtName' => 'Часы Casio Edifice EFV-C100D-1B',
					'imgSrc' => 'https://basket-05.wbbasket.ru/vol754/part75454/75454699/images/tm/1.webp',
					'subject' => 'Часы наручные',
					'brand' => 'Casio',
					'saNm' => 'W_CASIO_EFV-C100D-1B',
					'nmID' => 75454699,
					'needExpirationDate' => false,
					'needKIZ' => false,
					'tnved' => '',
					'hasTNVED' => false,
					'isFood' => false,
					'colorName' => 'серый',
					'tsName' => '0',
					'isDelicate' => false,
					'isBoxAcceptance' => false
				]
			];
			return [
				'error' => 'Есть ошибки при создании',
				'errors_detail' => $barcodes,
			];
		}*/
		$arSupply = $this->findSupply($supplyId);
		$cabinet = $arSupply['cabinet'];

		$wb = new WildberriesAPI($cabinet);
		$wb->changeApiUrl('https://seller-supply.wildberries.ru');

		$header = $this->getParamsWbCabinet($cabinet);
		
		//{"params":{"details":[{"barcode":"2028992218621","quantity":15,"preorderID":null},
		//{"barcode":"2028994633620","quantity":1,"preorderID":null},
		//{"barcode":"2028996331623","quantity":2,"preorderID":null}],"supplyID":31997918},"jsonrpc":"2.0","id":"json-rpc_89"}
		
		$items = [];
		// сразу обновляем товарные позиции
		foreach ($_SESSION['FBO_DATA']['boxes'] as $boxItems) {
			foreach ($boxItems as $item) {
				if (!$item['barcode']) continue;
				
				if ($items[$item['barcode']]) {
					$items[$item['barcode']]['quantity'] += (int)$item['quantity'];
				} else {
					$items[$item['barcode']] = [
						'barcode' => $item['barcode'],
						'quantity' => (int)$item['quantity'],
						'preorderID' => null,
					];
				}
			}
		}
			
		if (!$items) {
			return [
				'error' => 'Не сформирован массив товаров для обновления',
			];
		}
		
		$requestData = [
			'id' => 'json-rpc_89',
			'jsonrpc' => '2.0',
			'params' => [
				'details' => array_values($items),
				'supplyID' => (int)$supplyId,
			],
		];
		
		$result = $wb->send(action: "/ns/sm-preorder/supply-manager/api/v1/preorder/detailEdit", data: $requestData, method: "POST", header: $header);
		
		if (!isset($result['result'])) {
			$this->logger->log("ERROR", "Ошибка обновления товарных позиций", [$requestData, $result]);
			return [
				'error' => 'Ошибка обновления товарных позиций',
			];
		}

		$this->logger->log("ERROR", "Обновления товарных позиций", [$requestData, $result]);


		
		// отправляем короба

		/*{"params":
		{"bind":[
			{"boxcode":"WB_1430719276","barcodes":[{"barcode":"2028994633620","expirationDate":null,"quantity":2}],"quantity":1}
		],"incomeID":31997918},
		"jsonrpc":"2.0","id":"json-rpc_46"}*/
		
		$requestData = [
			'params' => [
				'bind' => [],
				'incomeID' => (int)$supplyId,
			],
			'jsonrpc' => '2.0',
			'id' => 'json-rpc_46'
		];

		foreach ($_SESSION['FBO_DATA']['boxes'] as $key => $items) {
			
			if (empty($items)) {
				//continue;
			}
			$barcodes = [];
			//$totalQuantity = 0;
			
			foreach ($items as $item) {
				$barcodes[] = [
					'barcode' => $item['barcode'],
					'expirationDate' => null,
					'quantity' => $item['quantity']
				];
				//$totalQuantity += $item['quantity'];
			}
			
			$requestData['params']['bind'][] = [
				'boxcode' => $key,
				'barcodes' => $barcodes,
				'quantity' => 1
			];
			
		}
		
		if (count($requestData['params']['bind']) > 0) {
			$result = $wb->send(action: "/ns/sm-box/supply-manager/api/v1/box/bindBarcodes", data: $requestData, method: "POST", header: $header);
			
			
			if (is_array($result['result']['boxes']) && count($result['result']['boxes']) == 0) {
				$this->logger->log("LOG", "Создание коробов WB", [$requestData, $result]);
				
				$_SESSION['FBO_DATA']['button_print_href'] = "https://seller.wildberries.ru/supplies-management/all-supplies/supply-detail?preorderId&supplyId={$supplyId}";
				$_SESSION['FBO_DATA']['button_print'] = true;
				
				return [
					'success' => true,
				];
				
			} else {
				$this->logger->log("ERROR", "Ошибка создания коробов WB", [$requestData, $result]);
				$errors[] = $result;
			}
		} else {
			$this->logger->log("ERROR", "Есть ошибки при создании коробов WB", [$requestData, $result]);
			$errors[] = $result;
		}
		
		
		return [
			'error' => 'Есть ошибки при создании',
			'errors_detail' => $errors,
		];
	}
	
    
	private function printBarcodes()
    {
		$marketplace = $_SESSION['FBO_DATA']['marketplace'] ?? '';
		$supplyId = $_SESSION['FBO_DATA']['selected_supply']['id'] ?? '';
		
		$this->logger->log("LOG", "FBO_DATA", $_SESSION['FBO_DATA']);
		
		if (!$marketplace || !$supplyId) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Данные не заполнены']);
			return;
		}
		
		try {
			if ($marketplace === 'ozon') {
				$result = $this->printBarcodesOzon();
			} elseif ($marketplace === 'wb') {
				$result = $this->printBarcodesWB($supplyId);
			} else {
				throw new Exception('Неизвестный маркетплейс');
			}
			
			if ($result['error'] || !$result) {
				$this->sendJsonResponse(['success' => false, 'error' => $result['error'] ?? 'Ошибка не определена']);
				return;
			}
			
			$filePath = 'https://tempusshop.ru' . $result['file'];
			$this->sendJsonResponse([
				'success' => true, 
				'message' => 'Файл сформирован ' . $result['file'],
				'data' => [
					'file_url' => $filePath
				]
			]);
			
		} catch (Exception $e) {
			$this->sendJsonResponse(['success' => false, 'error' => $e->getMessage()]);
		}
    }
	
	private function printBarcodesOzon()
	{
		$ozon = new OzonAPI('IP');
		
		$supplyId = $_SESSION['FBO_DATA']['selected_supply']['id'] ?? '';
		$orderId = $_SESSION['FBO_DATA']['selected_supply']['order_id'] ?? '';
		
		$requestData = [
			'supply_id' => (int)$supplyId,
		];
		
		$result = $ozon->send(action: "/v1/cargoes-label/create", data: $requestData, method: "POST");
		
		$this->logger->log("LOG", "Сгенерировать этикетки для грузомест", ["requestData" => $requestData, "result" => $result]);
		
		if (!$result['operation_id']) {
			$this->logger->log("ERROR", "Ошибка генерирации этикеток для грузомест", $result);
			return [
				'error' => 'Ошибка генерирации этикеток для грузомест',
			];
		}
		file_put_contents(
			'/var/www/bitrix/data/www/tempusshop.ru/local/components/admin/market.fbo.boxes/printBarcodesOzon.txt', 
			print_r(['create' => $result], true), 8
		);

		// проверяем статус генерирации этикеток для грузомест
		$operationId = $result['operation_id'];
		$maxAttempts = 30;
		$attempt = 0;
		$operationStatus = 'IN_PROGRESS';
		
		$file_guid = false;
		while ($operationStatus === 'IN_PROGRESS' && $attempt < $maxAttempts) {
			sleep(2);
			
			$statusResult = $ozon->send(
				action: "/v1/cargoes-label/get", 
				data: ['operation_id' => $operationId], 
				method: "POST"
			);

			if (isset($statusResult['status'])) {
				$operationStatus = $statusResult['status'];
				
				if ($operationStatus === 'ERROR') {
					return [
						'error' => 'Ошибка при генерирации этикеток для грузомест (статус операции: ERROR)',
						'operation_id' => $operationId
					];
				}
				
				if ($operationStatus === 'SUCCESS') {
					$file_guid = $statusResult['result']['file_guid'];
					break;
				}
			}
			
			$attempt++;
		}
		
		if ($operationStatus === 'IN_PROGRESS') {
			return [
				'error' => 'Превышено время ожидания завершения операции генерирации этикеток для грузомест',
				'operation_id' => $operationId
			];
		}
		
		$this->logger->log("LOG", "Файл сгенерированных этикеток " . $file_guid);
		
		
		
		try {
			$this->logger->log("LOG", "Получаем файл " . $file_guid);
			$result = $ozon->send(action: "/v1/cargoes-label/file/{$file_guid}", method: "GET");
			
			if ($result['status'] == 200) {
				
				file_put_contents($_SERVER['DOCUMENT_ROOT'] . "/upload/ozon/cargoes-label/{$file_guid}.pdf", $result['data']);
				
				return [
					'file' => "/upload/ozon/cargoes-label/{$file_guid}.pdf",
				];
				
			}

		} catch (Exception $e) {
			$this->logger->log("LOG", "Ошибка сохранения файла ", $result);
		}

		return [
			'error' => 'Ошибка сохранения файла',
		];

	}
	
	private function printBarcodesWB($supplyId) {
		$url = 'https://a.wb.ru/e/Supplier_Shipment_Packaging_PrintSettings_Print_Button_T?t=%D0%9F%D0%BE%D1%81%D1%82%D0%B0%D0%B2%D0%BA%D0%B0&u=https%3A%2F%2Fseller.wildberries.ru%2Fsupplies-management%2Fall-supplies%2Fsupply-detail%3FpreorderId%26supplyId%3D31997918&cid=7&s=2816x1760x24&w=1552x251&user_id=6437794831756626950&vbn=325&timestamp=2025-09-06T21%3A44%3A31.775Z&timezone_offset=180&timezone=Europe%2FMinsk&r=https%3A%2F%2Fseller.wildberries.ru%2F';

		$postData = [
			'cp' => [
				'PrintType' => 'qrcode',
				'supplyID' => (int) $supplyId,
				'QRPaperType' => 'thermalSticker',
				'QRStickerSize' => '58x40',
				'PrintButtonType' => 'Распечатать',
				'splits' => '{"1594":{"expID":"1594","group":"control","decision":true},"1710":{"expID":1710,"group":"partial","decision":true},"1916":{"expID":"1916","group":"control","isNewNotifications":false,"decision":true},"2231":{"expID":"2231","group":"test","isNewAbVersion":true,"decision":true},"2272":{"expID":"2272","group":"control","isNewAbVersion":false,"decision":true},"2402":{"expID":"2402","group":"control","isNewAbVersion":false,"decision":true},"2482":{"expID":"2482","group":"test","isNewAbVersion":true,"decision":true},"2525":{"expID":"2525","group":"control","isNewAbVersion":false,"decision":true},"2634":{"expID":"2634","group":"control","isNewAbVersion":false,"decision":true},"2731":{"expID":"2731","group":"control","isNewAbVersion":false,"decision":true},"3143":{"expID":"3143","group":"control","decision":true},"3162":{"expID":"3162","group":"control","isNewSettingsEnabled":false,"decision":true},"3182":{"expID":"3182","group":"control","isNewAbVersion":false,"decision":true},"3281":{"expID":"3281","group":"control","isHomePageRedesignV2":false,"decision":true},"3408":{"expID":"3408","group":"control","decision":true},"3515":{"expID":"3515","group":"control","isFeedbacksQuestionsRedesignV2":false,"decision":true},"9996":{"expID":"9996","group":"control","isNewAbVersion":false,"decision":true},"9998":{"expID":"9998","group":"control","isNewAbVersion":false,"decision":true}}',
				'uiRootVersion' => 'v1.55.1',
				'uiRootBuildTime' => 1756906968337,
				'isNewAppVersion' => false,
				'language' => 'ru',
				'idSupplier' => 'e43c8829-c9ad-4cc7-beed-547f4ffe232b',
				'idUser' => 9722245
			]
		];
		
		$jsonData = json_encode($postData, JSON_UNESCAPED_UNICODE);

		$ch = curl_init();

		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $jsonData,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Content-Length: ' . strlen($jsonData),
				'Accept: application/pdf',
			],
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => true,
		]);

		// Выполняем запрос
		$pdfContent = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

		// Проверяем на ошибки
		if (curl_errno($ch)) {
			echo 'cURL Error: ' . curl_error($ch);
			$this->logger->log("ERROR", "печать cURL Error:", [curl_error($ch)]);
			curl_close($ch);
			
			return [
				'error' => 'Ошибка генерирации этикеток для грузомест',
			];
		}

		curl_close($ch);

		// Проверяем, что ответ - PDF
		if ($httpCode === 200 && strpos($contentType, 'application/pdf') !== false) {
			$filename = '/var/www/bitrix/data/www/tempusshop.ru/local/components/admin/market.fbo.boxes/labels_' . date('Y-m-d_H-i-s') . '.pdf';
			file_put_contents($filename, $pdfContent);
		} else {
			// Если это не PDF
			if (strpos($contentType, 'application/json') !== false) {
				$responseData = json_decode($pdfContent, true);
				$this->logger->log("ERROR", "печать это не PDF", [$responseData, $httpCode, $contentType]);
			} else {
				//echo "Ответ: " . substr($pdfContent, 0, 500) . "...\n";
				$this->logger->log("ERROR", "печать pdfContent", [$pdfContent]);
			}
		}



		// Закрываем cURL
	}
	private function resetToStep1()
	{
		$_SESSION['FBO_DATA'] = [
			'step' => 1,
			'boxes' => [],
			'selected_supply' => null,
			'marketplace' => $_SESSION['FBO_DATA']['marketplace'] // Сохраняем выбранный маркетплейс
		];
		
		$this->sendJsonResponse(['success' => true]);
	}
    private function findSupply($supplyId)
    {
        $supplies = $this->getSupplies();
        foreach ($supplies as $supply) {
            if ($supply['id'] == $supplyId) {
                return $supply;
            }
        }
        return null;
    }

    private function prepareTemplateData()
    {
        $this->arResult = [
            'STEP' => $_SESSION['FBO_DATA']['step'],
            'MARKETPLACES' => [
                'ozon' => 'OZON',
                'wb' => 'Wildberries'
            ],
            'SUPPLIES' => $this->getSupplies(),
            'BOXES' => $_SESSION['FBO_DATA']['boxes'],
            'SELECTED_SUPPLY' => $_SESSION['FBO_DATA']['selected_supply'],
            'MARKETPLACE' => $_SESSION['FBO_DATA']['marketplace'],
            'ERROR' => $this->arResult['ERROR'] ?? null
        ];
    }

    private function getSupplies()
    {
		$arSupply = [];
		
		// ozon
		if ($_SESSION['FBO_DATA']['marketplace'] == 'ozon') {
			$ozon = new OzonAPI('IP');
			
			
			/*$data = [
				'operation_id' => '01990f0e-5678-7525-adb1-fd5b887354eb'
			];
			$supplyList = $ozon->send(action: "/v1/cargoes/create/info", data: $data, method: "POST");
			prent($supplyList);
			*/
			
			$data = [
				'filter' => [
					'states' => [
						'DATA_FILLING',
						'READY_TO_SUPPLY',
					],
				],
				'limit' => 10,
				'sort_by' => 'ORDER_CREATION',
				'sort_dir' => 'DESC',
			];

			$supplyList = $ozon->send(action: "/v3/supply-order/list", data: $data, method: "POST");
//prent($supplyList);
			if (is_array($supplyList['order_ids']) && count($supplyList['order_ids']) > 0) {
				$data = [
					'order_ids' => $supplyList['order_ids']
				];
				$supply = $ozon->send(action: "/v3/supply-order/get", data: $data, method: "POST");
//
				if (is_array($supply['orders']) && count($supply['orders']) > 0) {
					foreach ($supply['orders'] as $arItem) {
						$date = new DateTime($arItem['timeslot']['timeslot']['from']);
						//$date->modify(+$arItem['timeslot']['value']['timezone_info']['offset']);
						$date->modify('+10800 seconds');
						$arSupply[] = [
							'id' => $arItem['supplies'][0]['supply_id'],
							'order_id' => $arItem['order_id'],
							'name' => 'Поставка #' . $arItem['order_number'],
							'date' => $date->format('d-m-Y H:i:s'),
							'items_count' => 0,
							'marketplace' => 'ozon'
						];
					}

				}

			}
		}
prent($arSupply);
		// wb
		if ($_SESSION['FBO_DATA']['marketplace'] == 'wb') {
			$supplyAllList = [];
			foreach (['WR', 'TL'] as $cabinet) {
				$wb = new WildberriesAPI($cabinet);
				$wb->changeApiUrl('https://supplies-api.wildberries.ru');
				$data = [
					'statusIDs' => [2, 3]
				];
				$supplyList = $wb->send(action: "/api/v1/supplies?limit=100", data: $data, method: "POST");
prent($supplyList);
				if ($supplyList['error']) {
					$this->arResult['ERRORS'][] = $supplyList['error']['detail'];
				} elseif (is_array($supplyList) && count($supplyList) > 0) {
					
					foreach ($supplyList as &$ar) {
						$ar['cabinet'] = $cabinet;
					}
					unset($ar);
					
					if(!$supplyAllList) {
						$supplyAllList = $supplyList;
					} else {
						$supplyAllList = array_merge($supplyAllList, $supplyList);
					}


					/*$currentDate = new DateTime();

					$supplyActive = array_filter($supplyList, function($item) use ($currentDate) {
						if (empty($item['createDate'])) {
							return false;
						}
						
						try {
							$createDate = new DateTime($item['createDate']);
							$interval = $currentDate->diff($createDate);
							
							return ($interval->days <= 10 && $interval->invert == 1);
						} catch (Exception $e) {
							return false;
						}
					});*/
					
					/*foreach ($supplyList as $arItem) {
						sleep(1);
						$supply = $wb->send(action: "/api/v1/supplies/{$arItem["supplyID"]}", method: "GET");
						$arSupply[] = [
							'id' => $arItem["supplyID"],
							'name' => 'Поставка #' . $supply['boxTypeName'],
							'date' => $supply['createDate'],
							'items_count' => $supply['quantity'],
							'marketplace' => 'wb'
						];
						prent($supply);
					}*/
					
					//prent($supplyActive);

				}
				

			}
prent($supplyAllList);
			foreach ($supplyAllList as $arItem) {
				$date = new DateTime($arItem['supplyDate']);
				$c_name = ($arItem["cabinet"] == 'TL' ? 'IP' : $arItem["cabinet"]);
				$arSupply[] = [
					'id' => $arItem["supplyID"],
					'name' => "Поставка {$c_name} - #" . $arItem['supplyID'],
					'date' => $date->format('d-m-Y H:i:s'),
					'items_count' => 0,//$supply['quantity'],
					'marketplace' => 'wb',
					'cabinet' => $arItem["cabinet"],
				];
			}
			
		}

        return $arSupply;
    }
	
	private function scanProduct()
	{
		$boxNumber = $this->request->getPost('box_number');
		$barcode = trim($this->request->getPost('barcode'));
		
		if (empty($barcode)) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Пустой штрихкод']);
			return;
		}
		
		if (!isset($_SESSION['FBO_DATA']['boxes'][$boxNumber])) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Короб не найден']);
			$this->logger->log("ERROR", "Короб не найден", [$_SESSION['FBO_DATA'], $barcode, $boxNumber]);
			return;
		}
		
		//$productInfo = $this->getProductInfoByBarcode($barcode);
		$productInfo = $this->getProductInfoByBarcodeMarket($barcode);
		
		if (!$productInfo) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Товар не найден в системе']);
			return;
		}
		
		/*if (!$productInfo['barcode_market']) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Баркод маркета не найден']);
			return;
		}*/
		
		if ($_SESSION['FBO_DATA']['marketplace'] == 'ozon' && !$productInfo['sku_market']) {
			$this->sendJsonResponse(['success' => false, 'error' => 'Нет sku в таблице соотвествий']);
			return;
		}
		
		$product = [
			'id' => $productInfo['id'],
			'barcode' => $barcode,
			//'barcode_market' => $productInfo['barcode_market'],
			'quantity' => 1,
			'article' => $productInfo['article'],
			'sku_market' => $productInfo['sku_market'],
		];
		
		$foundIndex = -1;
		foreach ($_SESSION['FBO_DATA']['boxes'][$boxNumber] as $index => $item) {
			if ($item['barcode'] === $barcode) {
				$foundIndex = $index;
				break;
			}
		}
		
		if ($foundIndex >= 0) {
			$_SESSION['FBO_DATA']['boxes'][$boxNumber][$foundIndex]['quantity']++;
			$product['quantity'] = $_SESSION['FBO_DATA']['boxes'][$boxNumber][$foundIndex]['quantity'];
			$action = 'incremented';
		} else {
			$_SESSION['FBO_DATA']['boxes'][$boxNumber][] = $product;
			$action = 'added';
		}
		
		$this->sendJsonResponse([
			'success' => true,
			'product' => $product,
			'box_number' => $boxNumber,
			'action' => $action,
			'total_items' => count($_SESSION['FBO_DATA']['boxes'][$boxNumber])
		]);
	}
	
	private function getProductInfoByBarcodeMarket($barcode)
	{
		$item = [];
		
		$prop = $_SESSION['FBO_DATA']['marketplace'] == 'ozon' ? "AEN" : "AEN2";
		$rs = CIBlockElement::GetList([], ["=PROPERTY_{$prop}" => $barcode], false, false, ["ID", "PROPERTY_$prop", "PROPERTY_CML2_ARTICLE"]);
		if ($ar_fields = $rs->GetNext()) {
			$item = [
				'id' => $ar_fields["ID"],
				'article' => $ar_fields["PROPERTY_CML2_ARTICLE_VALUE"],
				'barcode' => $ar_fields["PROPERTY_$prop_VALUE"],
			];
			
			if ($_SESSION['FBO_DATA']['marketplace'] == 'ozon') {
				$result = $this->dbPanel->query("SELECT sku FROM ozon_sku_dict_IP WHERE model = '{$item['article']}'");
				$res = $this->dbPanel->fetchAll($result);
				if ($res) {
					$item['sku_market'] = $res[0]['sku'];
				}
			}
		}

		return $item;
	}
	
	private function getProductInfoByBarcode($barcode)
	{
		$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
		$results = $this->db->Query($strSql, false, $err_mess.__LINE__);
		
		$item = [];
		if ($row = $results->Fetch()){
			$item = [
				'id' => $row["PRODUCT_ID"],
				'article' => $row["ARTICLE"],
			];
		}
		
		if ($item['id']) {
			$rs = CIBlockElement::GetList([], ['ID' => (int) $item['id']], false, false, ["PROPERTY_AEN", "PROPERTY_AEN2"]);
			if ($ar_fields = $rs->GetNext()) {
				
				if ($_SESSION['FBO_DATA']['marketplace'] == 'ozon') {
					$barcode_market = $ar_fields["PROPERTY_AEN_VALUE"];
				} else if ($_SESSION['FBO_DATA']['marketplace'] == 'wb') {
					$barcode_market = $ar_fields["PROPERTY_AEN2_VALUE"];
				}
				
				$item['barcode_market'] = $barcode_market;
			}
			
			if ($_SESSION['FBO_DATA']['marketplace'] == 'ozon') {
				$result = $this->dbPanel->query("SELECT sku FROM ozon_sku_dict_IP WHERE model = '{$item['article']}'");
				$res = $this->dbPanel->fetchAll($result);
				if ($res) {
					$item['sku_market'] = $res[0]['sku'];
				}
			}
		}

		return $item;
	}

    private function sendJsonResponse($data)
    {
        // Очищаем весь буфер вывода
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Устанавливаем заголовки
        header('Content-Type: application/json; charset=utf-8');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        // Выводим JSON
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        
        // Завершаем выполнение
        die();
    }
}
?>