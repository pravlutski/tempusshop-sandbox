#!/bin/bash

# Быстрый поиск PHP файлов с пробелами в начале

search_path="${1:-.}"

echo "🔍 Поиск PHP файлов с пробелами/переносами строк в начале..."
echo "📁 Каталог: $search_path"
echo "────────────────────────────────────────────────────────────"

find "$search_path" -type f -name "*.php" -exec bash -c '
    for file do
        if [ -s "$file" ]; then
            # Читаем первый байт
            first_byte=$(dd if="$file" bs=1 count=1 2>/dev/null | od -An -tx1 | tr -d " \\n")

            # Проверяем на UTF-8 BOM
            first_three=$(head -c 3 "$file" | od -An -tx1 | tr -d " \\n")
            if [ "$first_three" = "efbbbf" ]; then
                # Пропускаем BOM и проверяем 4-й байт
                first_byte=$(dd if="$file" bs=1 count=1 skip=3 2>/dev/null | od -An -tx1 | tr -d " \\n")
            fi

            case "$first_byte" in
                20|09|0a|0d|0c|0b)
                    # Определяем тип символа
                    case "$first_byte" in
                        20) sym="␣" ; type="SPACE" ;;
                        09) sym="⇥" ; type="TAB" ;;
                        0a) sym="↵" ; type="LF" ;;
                        0d) sym="↵" ; type="CR" ;;
                        0c) sym="↡" ; type="FF" ;;
                        0b) sym="↨" ; type="VT" ;;
                    esac
                    printf "%-60s %-4s 0x%-4s %-10s %-8s\n" \
                        "$file" "$sym" "$first_byte" "$type" "$(stat -c%s "$file")"
                    ;;
            esac
        fi
    done
' bash {} +

echo "────────────────────────────────────────────────────────────"
echo "✅ Поиск завершен."
