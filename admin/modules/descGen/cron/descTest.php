<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

class TestRich
{

  function __construct()
  {

  }

  public function run(){
    $this->getItems();
    $this->updateRich();
  }

  public function getItems(){
    $arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK","PROPERTY_OZON_ID","PROPERTY_WBARTICLE");
    $arFilter = Array(
      "IBLOCK_ID" => 16,
      "ID" => 1002
    );
//Array("nPageSize"=>50)
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){

      $this->items[] = [
        "ID" => $el["ID"],
        "BRAND_ID" => $el["PROPERTY_BRAND_VALUE"],
        "COLLECTION" => $el["PROPERTY_COLLECTION_VALUE"],
        "ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLLECTION_UPDATE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLORTERM" => $el["PROPERTY_COLORTERM_VALUE"],
        'TYPE' => array_shift($el['PROPERTY_126_VALUE']),
        'DETAIL_PICTURE' => $el['DETAIL_PICTURE'],
        "TIMESTAMP_X" => $el['TIMESTAMP_X'],
        "CATALOG_QUANTITY" => $el['CATALOG_QUANTITY'],
        "COLORTERM_UPDATE" => "",
        "IMAGE_MARKETPLACE" => $el['PROPERTY_IMAGE_MARKETPLACE_VALUE'],
        "NAME_MARKETPLACE" => $el['PROPERTY_NAME_MARKETPLACE_VALUE'],
        "MECHANISM" => $el['PROPERTY_MECHANISM_VALUE'],
        "GLASS" => $el['PROPERTY_GLASS_VALUE'],
        "CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
        "WR" => $el['PROPERTY_WR_VALUE'],
        "FACE" => $el['PROPERTY_FACE_ENUM_ID'],
        "LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
        "OZON_ARTICLE" => $el['PROPERTY_WBARTICLE_VALUE'],
        "OZON_ID" => $el['PROPERTY_OZON_ID_VALUE'],
        "RICH_DESC" => 'Ударопрочная модель многофункциональных часов с автоматической светодиодной подсветкой повышенной яркости при тусклом, неясном свете. Точность хода: не хуже +/-15 секунд в месяц. Срок службы батареи 2 года. Элемент питания CR1220. Электрическая светодиодная подсветка с функцией автоматической подсветки при наклоне часов к лицу. Точное измерение прошедшего времени одним нажатием кнопки. Максимальное время измерения 100 часов. Таймер с функцией автоповтора. Максимальное время измерения 24 часа. 5 ежедневных будильников. Функция повтора сигнала будильника (Snooze). Автоматический календарь. Особая ударопрочная конструкция защищает от ударов и вибрации. Магнитоустойчивость по 1 разряду Японского промышленного стандарта (Соответствует стандарту MOC-ISO 764) обеспечивает устойчивость к воздействию магнитных полей. Минеральное стекло устойчивое к возникновению царапин. Ремешок из полимерного материала. Водозащита до 20 АТМ. Размеры корпуса 55 мм (по оси заводной головки) х 51,2 мм (по вертикали) х 16,9 мм (толщина) / 70 грамм.ASD123екунд в месяц. Срок службы батареи 2 года. Элемент питания CR1220. Электрическая светодиодная подсветка с функцией автоматической подсветки при наклоне часов к лицу. Точное измерение прошедшего времени одним нажатием кнопки. Максимальное время измерения 100 часов. Таймер с функцией автоповтора. Максимальное время измерения 24 часа. 5 ежедневных будильников. Функция повтора сигнала будильника (Snooze). Автоматический календарь. Особая ударопрочная конструкция защищает от ударов и вибрации. Магнитоустойчивость по 1 разряду Японского промышленного стандарта (Соответствует стандарту MOC-ISO 764) обеспечивает устойчивость к воздействию магнитных полей. Минеральное стекло устойчив',
      ];
    }
  }

  /*
  {
			"content": [
				{
					"widgetName": "raShowcase",
					"type": "roll",
					"blocks": [
						{
							"imgLink": "",
							"img": {
								"src": "https://cdn1.ozone.ru/s3/multimedia-g/6741025324.jpg",
								"srcMobile": "https://cdn1.ozone.ru/s3/multimedia-6/6741025350.jpg",
								"alt": "",
								"position": "to_the_edge",
								"positionMobile": "to_the_edge"
							}
						}
					]
				},{
				      "widgetName": "raShowcase",
				      "type": "tileXL",
				      "blocks": [
				        {
				          "img": {
				            "src": "https://cdn1.ozone.ru/s3/multimedia-1-1/6916770901.jpg",
				            "srcMobile": "https://cdn1.ozone.ru/s3/multimedia-1-2/7094174870.jpg",
				            "alt": "",
				            "position": "to_the_edge",
				            "positionMobile": "to_the_edge",
				            "widthMobile": 900,
				            "heightMobile": 1200
				          },
				          "imgLink": "",
				          "title": {
				            "content": [],
				            "size": "size4",
				            "align": "left",
				            "color": "color1"
				          },
				          "text": {
				            "size": "size2",
				            "align": "left",
				            "color": "color1",
				            "content": [
				              ""
				            ]
				          }
				        },
				        {
				          "img": {
				            "src": "https://cdn1.ozone.ru/s3/multimedia-1-2/7094174870.jpg",
				            "srcMobile": "https://cdn1.ozone.ru/s3/multimedia-1-1/6916770901.jpg",
				            "alt": "",
				            "position": "to_the_edge",
				            "positionMobile": "to_the_edge",
				            "widthMobile": 680,
				            "heightMobile": 1100
				          },
				          "imgLink": "",
				          "title": {
				            "content": [],
				            "size": "size4",
				            "align": "left",
				            "color": "color1"
				          },
				          "text": {
				            "size": "size2",
				            "align": "left",
				            "color": "color1",
				            "content": [
				              ""
				            ]
				          }
				        }
				      ]
				    },{
      "widgetName": "raTextBlock",
      "title": {
        "content": [
          "Мужские наручные часы Casio G-Shock GA-100-1A1"
        ],
        "size": "size5",
        "color": "color1",
        "align": "center"
      },
      "theme": "tertiary",
      "padding": "type2",
      "gapSize": "s",
      "text": {
        "size": "size3",
        "align": "left",
        "color": "color1",
        "content": [
          "Ударопрочная модель многофункциональных часов с автоматической светодиодной подсветкой повышенной яркости при тусклом, неясном свете. Точность хода: не хуже +/-15 секунд в месяц. Срок службы батареи 2 года. Элемент питания CR1220. Электрическая светодиодная подсветка с функцией автоматической подсветки при наклоне часов к лицу. Точное измерение прошедшего времени одним нажатием кнопки. Максимальное время измерения 100 часов. Таймер с функцией автоповтора. Максимальное время измерения 24 часа. 5 ежедневных будильников. Функция повтора сигнала будильника (Snooze). Автоматический календарь. Особая ударопрочная конструкция защищает от ударов и вибрации. Магнитоустойчивость по 1 разряду Японского промышленного стандарта (Соответствует стандарту MOC-ISO 764) обеспечивает устойчивость к воздействию магнитных полей. Минеральное стекло устойчивое к возникновению царапин. Ремешок из полимерного материала. Водозащита до 20 АТМ. Размеры корпуса 55 мм (по оси заводной головки) х 51,2 мм (по вертикали) х 16,9 мм (толщина) / 70 грамм.ASD123екунд в месяц. Срок службы батареи 2 года. Элемент питания CR1220. Электрическая светодиодная подсветка с функцией автоматической подсветки при наклоне часов к лицу. Точное измерение прошедшего времени одним нажатием кнопки. Максимальное время измерения 100 часов. Таймер с функцией автоповтора. Максимальное время измерения 24 часа. 5 ежедневных будильников. Функция повтора сигнала будильника (Snooze). Автоматический календарь. Особая ударопрочная конструкция защищает от ударов и вибрации. Магнитоустойчивость по 1 разряду Японского промышленного стандарта (Соответствует стандарту MOC-ISO 764) обеспечивает устойчивость к воздействию магнитных полей. Минеральное стекло устойчив Другие наименования модели: GA-100-1A1ER, GA-100-1A1DR, GA-100-1A1HDR",
          ""
        ]
      }
    },{
      "widgetName": "raShowcase",
      "type": "tileSecondary",
      "blocks": [        {
          "img": {
            "src": "https://cdn1.ozone.ru/s3/multimedia-c/6741268464.jpg",
            "srcMobile": "https://cdn1.ozone.ru/s3/multimedia-c/6741268464.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "fill"
          },
          "imgLink": "",
          "title": {
            "content": [
              "Кварцевый механизм"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "Кварцевые часы имеют высокую точность и стабильность хода, что позволяет им показывать точное время с минимальной погрешностью."
            ]
          }
        },        {
          "img": {
            "src": "https://cdn1.ozone.ru/s3/multimedia-d/6741268465.jpg",
            "srcMobile": "https://cdn1.ozone.ru/s3/multimedia-d/6741268465.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "Минеральное стекло"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "Минеральное стекло устойчиво к царапинам, что делает его отличным выбором для часов, которые часто используются в повседневной жизни."
            ]
          }
        },        {
          "img": {
            "src": "https://cdn1.ozone.ru/s3/multimedia-e/6741268466.jpg",
            "srcMobile": "https://cdn1.ozone.ru/s3/multimedia-e/6741268466.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "Водозащита WR200"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "Часы подходят, чтобы погружаться на глубину до 40м и находиться в воде не более 2 часов."
            ]
          }
        },        {
          "img": {
            "src": "https://cdn1.ozone.ru/s3/multimedia-f/6741268467.jpg",
            "srcMobile": "https://cdn1.ozone.ru/s3/multimedia-f/6741268467.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "Полимерный корпус"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "Полимерные материалы легче, чем металлы, но при этом обладают достаточной прочностью и жесткостью, чтобы обеспечить надежную защиту часов."
            ]
          }
        }      ]
		    }
		  ],
		  "version": 0.3
		}
  */

  public function updateRich(){
    foreach($this->items as $key => &$arItem){
    //spravochnik
    $imgs = CFile::GetPath($arItem['IMAGE_MARKETPLACE']);

    switch ($arItem['MECHANISM']) {
      case 'Кварцевые':
        $c1 = 'Кварцевый механизм';
        $c2 = 'Кварцевые часы имеют высокую точность и стабильность хода, что позволяет им показывать точное время с минимальной погрешностью.';
        break;
      case 'Механические':
        $c1 = 'Механические часы с ручным подзаводом';
        $c2 = 'Часы, использующие пружинный источник энергии. Ручной подзавод.';
        break;
      case 'Автоматические с ручным подзаводом':
        $c1 = 'Автоматические часы с ручным подзаводом';
        $c2 = 'Используется специальный механизм, который вращается при движении руки и заводит часы автоматически. Также возможен ручной завод.';
        break;
      case 'Автоматические':
        $c1 = 'Механические часы с автоматическим подзаводом';
        $c2 = 'Используется специальный механизм, который вращается при движении руки и заводит часы автоматически.';
        break;
      case 'Автокварц (кинетик)':
        $c1 = 'Автокварц (кинетик)';
        $c2 = 'Гибридный механизм, сочетающий преимущества кварцевых часов и механических с автоподзаводом.';
        break;
      case 'Процессор':
        $c1 = 'Smart-часы';
        $c2 = 'Компьютеризированные наручные часы с расширенной функциональностью.';
        break;
      default:
        $c1 = '';
        $c2 = '';
        break;
    }

    switch ($arItem['GLASS']) {
      case 'Минеральное':
        $a1 = 'Минеральное стекло';
        $a2 = 'Минеральное стекло устойчиво к царапинам, что делает его отличным выбором для часов, которые часто используются в повседневной жизни.';
        break;
      case 'Органическое':
        $a1 = 'Органическое стекло';
        $a2 = 'За счёт гибкости стекло из акрила практически невозможно разбить. В случае удара циферблат и стрелки часов останутся без повреждений.';
        break;
      case 'Сапфировое':
        $a1 = 'Сапфировое стекло';
        $a2 = 'Сапфир обладает очень высокой твердостью и поцарапать его практически невозможно. Материал не тускнеет и не теряет со временем свой первоначальный вид.';
        break;
      default:
        $a1 = '';
        $a2 = '';
        break;
    }

    switch ($arItem['CASE']) {
      case 'Латунь':
        $b1 = 'Латунный корпус';
        $b2 = 'Латунные часы имеют высокую прочность и долговечность, что делает их надежным выбором для повседневного использования.';
        break;
      case 'Нержавеющая сталь':
        $b1 = 'Стальной корпус';
        $b2 = 'Высококачественный стальной сплав с высокими антикоррозийными свойствами. Выдерживает большие нагрузки и не деформируется со временем.';
        break;
      case 'Полимер':
        $b1 = 'Полимерный корпус';
        $b2 = 'Полимерные материалы легче, чем металлы, но при этом обладают достаточной прочностью и жесткостью, чтобы обеспечить надежную защиту часов.';
        break;
      case 'Карбон':
        $b1 = 'Карбоновый корпус';
        $b2 = 'Легкий и сверх-прочный полиуретановый корпус с углеродным армированием.';
        break;
      case 'Алюминий':
        $b1 = 'Алюминиевый корпус';
        $b2 = 'Алюминий является очень легким материалом, что делает часы комфортными для ношения на руке.';
        break;
      case 'Дерево':
        $b1 = 'Корпус из дерева';
        $b2 = 'Часы изготавливлены из натурального гипоаллергенного материала. Дерево устойчиво и к ультрафиолетовому излучению, и к процессу окисления.';
        break;
      case 'Каучук':
        $b1 = 'Каучуковый корпус';
        $b2 = 'Износостойкий гипоаллергенный материал, устойчивый к деформации и температурным перепадам.';
        break;
      case 'Керамика':
        $b1 = 'Керамический корпус';
        $b2 = 'Легкий гипоаллергенный материал, устойчивый к царапинам и механическим повреждениям. При длительной носке изделия сохраняют исходный внешний вид.';
        break;
      case 'Титан':
        $b1 = 'Титановый корпус';
        $b2 = 'Гипоаллергенный материал повышенной прочности с высокими антикоррозийными свойствами, устойчивый к температурным перепадам.';
        break;
      default:
        $b1 = '';
        $b2 = '';
        break;
    }

    switch ($arItem['WR']) {
      case 'WR30m':
        $d1 = 'Водозащита WR30';
        $d2 = 'Часы защищены от попадания мелких брызг и капель, однако не предназначены для длительного контакта с водой.';
        break;
      case 'WR50m':
        $d1 = 'Водозащита WR50';
        $d2 = 'Часы способны стойко выдержать мытье рук, попадание брызг и капель дождя.';
        break;
      case 'WR100m':
        $d1 = 'Водозащита WR100';
        $d2 = 'Часы могут быть использованы для плавания и других водных видов спорта, но не предназначены для длительного погружения под воду.';
        break;
      case 'WR150m':
        $d1 = 'Водозащита WR150';
        $d2 = 'Часы могут быть использованы для плавания и других водных видов спорта, но не предназначены для длительного погружения под воду.';
        break;
      case 'WR180m':
        $d1 = 'Водозащита WR180';
        $d2 = 'Часы могут быть использованы для плавания и других водных видов спорта, но не предназначены для длительного погружения под воду.';
        break;
      case 'WR200m':
        $d1 = 'Водозащита WR200';
        $d2 = 'Часы подходят, чтобы погружаться на глубину до 40м и находиться в воде не более 2 часов.';
        break;
      case 'WR300m':
        $d1 = 'Водозащита WR300';
        $d2 = 'Профессиональные дайверские часы.';
        break;
      case 'WR500m':
        $d1 = 'Водозащита WR500';
        $d2 = 'Профессиональные дайверские часы.';
        break;
      case 'WR600m':
        $d1 = 'Водозащита WR600';
        $d2 = 'Профессиональные дайверские часы.';
        break;
      case 'WR1000m':
        $d1 = 'Водозащита WR1000';
        $d2 = 'Профессиональные дайверские часы.';
        break;
      default:
        $d1 = '';
        $d2 = '';
        break;
    }


    if ($arItem['FACE'] == 1872) {
      $srcPm = 'https://i.ibb.co/Jrb0Pbd/Tempus-WB-new1.jpg';
    } else {
      $srcPm = 'https://i.ibb.co/Qf60CJF/Tempus-WB-new1.jpg';
    }

    $rich = '{
      "content": [
        {
          "widgetName": "raShowcase",
          "type": "roll",
          "blocks": [
            {
              "imgLink": "",
              "img": {
                "src": "https://i.ibb.co/zrkYqzC/1600-200-Tempus-rich-pc.jpg",
                "srcMobile": "https://i.ibb.co/ZBt6Htn/1600-200-Tempus-new-rich-mobile.jpg",
                "alt": "",
                "position": "to_the_edge",
                "positionMobile": "to_the_edge"
              }
            }
          ]
        },';

    $rich .= '{
              "widgetName": "raShowcase",
              "type": "tileXL",
              "blocks": [
                {
                  "img": {
                    "src": "https://tempusshop.ru' . CFile::GetPath($arItem['IMAGE_MARKETPLACE']) . '",
                    "srcMobile": "'.$srcPm.'",
                    "alt": "",
                    "position": "to_the_edge",
                    "positionMobile": "to_the_edge",
                    "widthMobile": 900,
                    "heightMobile": 1200
                  },
                  "imgLink": "",
                  "title": {
                    "content": [],
                    "size": "size4",
                    "align": "left",
                    "color": "color1"
                  },
                  "text": {
                    "size": "size2",
                    "align": "left",
                    "color": "color1",
                    "content": [
                      ""
                    ]
                  }
                },
                {
                  "img": {
                    "src": "'.$srcPm.'",
                    "srcMobile": "https://tempusshop.ru' . CFile::GetPath($arItem['IMAGE_MARKETPLACE']) . '",
                    "alt": "",
                    "position": "to_the_edge",
                    "positionMobile": "to_the_edge",
                    "widthMobile": 680,
                    "heightMobile": 1100
                  },
                  "imgLink": "",
                  "title": {
                    "content": [],
                    "size": "size4",
                    "align": "left",
                    "color": "color1"
                  },
                  "text": {
                    "size": "size2",
                    "align": "left",
                    "color": "color1",
                    "content": [
                      ""
                    ]
                  }
                }
              ]
            },';

    $rich .= '{
      "widgetName": "raTextBlock",
      "title": {
        "content": [
          "'.$arItem['NAME_MARKETPLACE'].'"
        ],
        "size": "size5",
        "color": "color1",
        "align": "center"
      },
      "theme": "tertiary",
      "padding": "type2",
      "gapSize": "s",
      "text": {
        "size": "size3",
        "align": "left",
        "color": "color1",
        "content": [
          '.self::divideTextByblocks($arItem["RICH_DESC"]).'
        ]
      }
    },';

    $rich .= '{
      "widgetName": "raShowcase",
      "type": "tileSecondary",
      "blocks": [';

    //
    //if (!empty($arItem['MECHANISM'])) {
      $rich .= '        {
          "img": {
            "src": "https://i.ibb.co/9Vz6wzL/1.jpg",
            "srcMobile": "https://i.ibb.co/9Vz6wzL/1.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "fill"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$c1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$c2.'"
            ]
          }
        },';
    //}

    //if (!empty($arItem['GLASS'])) {
      $rich .= '        {
          "img": {
            "src": "https://i.ibb.co/Dw316JR/2.jpg",
            "srcMobile": "https://i.ibb.co/Dw316JR/2.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$a1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$a2.'"
            ]
          }
        },';
    //}

    //if (!empty($arItem['WR'])) {
      $rich .= '        {
          "img": {
            "src": "https://i.ibb.co/pRjJ4n0/3.jpg",
            "srcMobile": "https://i.ibb.co/pRjJ4n0/3.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$d1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$d2.'"
            ]
          }
        },';
    //}

    //if (!empty($arItem['CASE'])) {
      $rich .= '        {
          "img": {
            "src": "https://i.ibb.co/Lt3gqtF/4.jpg",
            "srcMobile": "https://i.ibb.co/Lt3gqtF/4.jpg",
            "alt": "",
            "position": "to_the_edge",
            "positionMobile": "to_the_edge"
          },
          "imgLink": "",
          "title": {
            "content": [
              "'.$b1.'"
            ],
            "size": "size4",
            "align": "left",
            "color": "color1"
          },
          "text": {
            "size": "size3",
            "align": "left",
            "color": "color1",
            "content": [
              "'.$b2.'"
            ]
          }
        }';
    //}

    $rich .= '      ]
        }
      ],
      "version": 0.3
    }';

    // CIBlockElement::SetPropertyValueCode($arItem['ID'], "rich_ozon", array('VALUE' => $rich));
    print_r( $rich );
    }
  }

  static function divideTextByblocks( $string )
  {
    if ( strpos( $string, '%BR%' ) ){
      $descBlocks = explode( '%BR%', $string );
      // var_dump($descBlocks);
      $result = '';
      foreach ( $descBlocks as $paragraph ){
        $result .= "\"{$paragraph}\",";
      }
      $result .= '""';
    }else{
      $result = '
      "' . $string . '",
      ""
      ';
    }
    return $result;
  }
}

(new TestRich)->run();

 ?>
