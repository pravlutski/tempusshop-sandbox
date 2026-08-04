# Шаблоны для источника

Используем синтаксис SEO-полей модуля "Инфоблоки". Выражение начинается - `{=`, и заканчивается `}`, поддерживаются вложенные выражения.

Пример шаблона:
```
Предложение {=iblock_offer_field.NAME} для товара {=iblock_element_field.NAME}
```

## Функции

- upper - привести к верхнему регистру `{=upper iblock_offer_field.NAME}`;
- lower - привести к нижнему регистру `{=lower iblock_offer_field.NAME}`;
- translit - выполнить транслитерацию `{=translit iblock_offer_field.NAME}`;
- concat - объединить строки `{=concat iblock_offer_field.NAME iblock_element_field.NAME ", " }` (объединить название торгового предложения и элемента, разделитель ", ");
- limit - вывести n-слов через разделитель `{=limit iblock_offer_field.PREVIEW_TEXT iblock_element_field.PREVIEW_TEXT " .,?!" 3}` (три слова из результат объединения "Текст для анонса" для предложения и элемента) [^1] [^2];
- contrast - выбрать N "контрастных" слов, и привести к нижнему регистру `{=contrast iblock_offer_field.PREVIEW_TEXT " .,?!" 20}` [^2];
- min - выбрать минимальное числовое значение `{=min iblock_offer_property.11 iblock_element_property.5}`;
- max - выбрать максимальное числовое значение `{=max iblock_offer_property.11 iblock_element_property.5}`;
- distinct - выбрать уникальные значения `{=distinct iblock_offer_property.11 iblock_element_property.5}`.

Поддерживается расширение набора функций через событие `OnTemplateGetFunctionClass` модуля `iblock`.

## Документация Битрикс

https://dev.1c-bitrix.ru/learning/course/?COURSE_ID=43&LESSON_ID=5212

Примечания:

[^1]: html-теги считаются словами;

[^2]: работает медленно на больших строках.