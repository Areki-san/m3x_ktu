#! /bin/bash

CONF_FILE="conf.php"

# Проверяем, существует ли файл
if [ -f "$CONF_FILE" ]; then
    echo "Предупреждение: файл $CONF_FILE уже существует."
    exit 1
fi

# Запрашиваем у пользователя значения URL и API‑ключа
read -p "Введите URL (API-URL): " USER_URL
read -s -p "Введите API-ключ (API-KEY): " USER_API_KEY
echo  # Переводим строку после скрытого ввода

# Генерируем содержимое файла
cat > "$CONF_FILE" << EOF
<?php
	// Получаем полный путь к скрипту, который подключает этот файл
	\$callingScript = \$_SERVER['SCRIPT_FILENAME'];

	// Определяем, из какого каталога вызывается скрипт
	if (strpos(\$callingScript, '/ktu_test/') !== false) {
		// Конфигурация для тестовой версии
		define("is_debug", true);
		define("update_mode", true);
		define("site_name", "ТЕСТОВАЯ ВЕРСИЯ");
	} else {
		// Конфигурация для рабочей версии (по умолчанию)
		define("is_debug", false);
		define("update_mode", false);
		define("site_name", "Бабло");
	}
    
    define("edit_delta", 9);
    define("site_ver", "ver_20260826");

    define("URL", "$USER_URL");
    define("api_key", "$USER_API_KEY");
    define("key", api_key);

    define('SQL_DB', __DIR__ . '/ktu.db');

    define('arr_city', array(
	"m3x",
        "Алчевск",
        "Зоринск",
        "Кировск",
	"Комиссаровка",
        "Стаханов",
        "Петровское"
    ));

    define('arr_setings_division', array(
        "Алчевск_Монтажники",
        "Строители-Алчевск",
        "Тех.помощь_Алчевск",
        "Абонентский_отдел",
        "Контакт-центр",
        "M@trix-Алчевск",
        "M@trix-Зоринск",
        "M@trix-Кировск",
	"M@trix-Комиссаровка",
        "M@trix-Стаханов",
        "M@trix-Петровское"
    ));

    // подключение библиотеки Smarty
    require_once 'smarty3/Smarty.class.php';

    $smarty = new Smarty(); //создаем новый экземпляр класса
    $smarty->template_dir = './templates/'; //задаем адреса для рабочих папок
    $smarty->compile_dir = './templates/compile/';
    $smarty->cache_dir = './templates/cache/';
    $smarty->caching = false; //выключаем кэширование
    $smarty->debugging = false; //включаем режим отладки(отладочную консоль)

    $smarty -> assign('site_name', site_name);
    $smarty -> assign('site_ver', site_ver);

EOF

# Устанавливаем владельца файла www-data
chown www-data:www-data "$CONF_FILE"

echo "Файл $CONF_FILE успешно создан и настроен. Владелец: www-data."
