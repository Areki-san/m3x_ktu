<?php
    //phpinfo();
    include 'conf.php';

    if (!defined('ARCHIVE_DIR')) {
        define('ARCHIVE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'archive');
    }

    /**
     * Очищает название города для безопасного имени файла.
     * Должно работать так же, как sanitizeArchiveCity() в api.php.
     */
    function archiveSanitizeCity(string $city): string
    {
        $city = trim($city);

        if ($city === '') {
            return 'unknown';
        }

        // Пробелы заменяем на подчёркивание
        $city = preg_replace('/\s+/u', '_', $city);

        if (!is_string($city)) {
            return 'unknown';
        }

        // Оставляем буквы, цифры, дефис и подчёркивание
        $city = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $city);

        if (!is_string($city) || $city === '') {
            return 'unknown';
        }

        return $city;
    }

    /**
     * Возвращает список дат архивов для выбранного города.
     *
     * Файлы архивов имеют формат:
     * YYYY-MM-DD_city.json
     *
     * Результат:
     * ['2026-08-17', '2026-08-16', ...]
     */
    function getArchiveDatesByCity(string $city): array
    {
        $dir = ARCHIVE_DIR;

        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json');

        if (!is_array($files) || empty($files)) {
            return [];
        }

        $citySafe = archiveSanitizeCity($city);

        $dates = [];

        foreach ($files as $file) {
            $base = basename($file, '.json');

            // Пример:
            // 2026-08-17_m3x
            // 2026-08-17_Алчевск
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})_(.+)$/', $base, $matches)) {
                continue;
            }

            $fileDate = $matches[1];
            $fileCity = $matches[2];

            // Если город не указан, можно вернуть даты всех архивов.
            // Но на странице архива город обычно выбран.
            if ($city === '' || $fileCity === $citySafe) {
                $dates[$fileDate] = $fileDate;
            }
        }

        if (empty($dates)) {
            return [];
        }

        // Сортировка от новых к старым
        krsort($dates, SORT_STRING);

        return array_values($dates);
    }

    /**
     * AJAX-запрос для получения списка дат при смене города.
     *
     * Запрос:
     * archive.php?get_dates=1&city=m3x
     */
    if (isset($_GET['get_dates'])) {
        header('Content-Type: application/json; charset=utf-8');

        $city = $_GET['city'] ?? '';

        echo json_encode([
            'success' => true,
            'city'    => $city,
            'dates'   => getArchiveDatesByCity((string)$city),
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    /**
     * Город по умолчанию.
     * Можно поменять, например, на 'Алчевск'.
     */
    $selectedCity = $_GET['city'] ?? ($_POST['city'] ?? 'm3x');

    // Если arr_city доступна как массив констант, проверим выбранный город
    if (defined('arr_city') && is_array(arr_city)) {
        $cities = arr_city;

        if (!in_array($selectedCity, $cities, true)) {
            $selectedCity = $cities[0] ?? 'm3x';
        }
    }

    $arr_date = getArchiveDatesByCity((string)$selectedCity);

    if (!isset($is_editable_archive)) {
        $is_editable_archive = (date('d') <= edit_delta); 
    }

    $smarty->assign('arr_city', arr_city);
    $smarty->assign('arr_date', $arr_date);
    $smarty->assign('selected_city', $selectedCity);
    // ПЕРЕДАЁМ ФЛАГ В ШАБЛОН
    $smarty->assign('is_editable_archive', $is_editable_archive);

    $smarty->display('archive.tpl'); //публикуем шаблон
