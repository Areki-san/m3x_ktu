<?php

if (!defined('PAYROLL_ARCHIVE_DIR')) {
    define('PAYROLL_ARCHIVE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'archive');
}

/**
 * Возвращает папку архивов и создаёт её, если её ещё нет.
 */
if (!function_exists('pa_getArchiveDir')) {
    function pa_getArchiveDir(): string
    {
        if (!is_dir(PAYROLL_ARCHIVE_DIR)) {
            @mkdir(PAYROLL_ARCHIVE_DIR, 0775, true);
        }

        return PAYROLL_ARCHIVE_DIR;
    }
}

/**
 * Очищает название города/подразделения для безопасного имени файла.
 */
if (!function_exists('pa_sanitizeArchiveCity')) {
    function pa_sanitizeArchiveCity(string $city): string
    {
        $city = trim($city);

        if ($city === '') {
            return 'unknown';
        }

        $city = preg_replace('/\s+/u', '_', $city);

        if (!is_string($city)) {
            return 'unknown';
        }

        $city = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $city);

        if (!is_string($city) || $city === '') {
            return 'unknown';
        }

        return $city;
    }
}

/**
 * Возвращает значение из массива с учётом возможных пробелов в ключах.
 */
if (!function_exists('pa_getPayloadValue')) {
    function pa_getPayloadValue(array $payload, string $key, $default = null)
    {
        foreach ($payload as $payloadKey => $payloadValue) {
            if (trim((string)$payloadKey) === $key) {
                return $payloadValue;
            }
        }

        return $default;
    }
}

/**
 * Возвращает значение из meta с учётом возможных пробелов в ключах.
 */
if (!function_exists('pa_getMetaValue')) {
    function pa_getMetaValue(array $payload, string $key, $default = '')
    {
        $meta = pa_getPayloadValue($payload, 'meta', []);

        if (!is_array($meta)) {
            return $default;
        }

        foreach ($meta as $metaKey => $metaValue) {
            if (trim((string)$metaKey) === $key) {
                return $metaValue;
            }
        }

        return $default;
    }
}

/**
 * Возвращает путь к архиву по дате и городу.
 *
 * Формат:
 * /archive/YYYY-MM-DD_city.json
 */
if (!function_exists('pa_getArchiveFilePath')) {
    function pa_getArchiveFilePath(string $date = '', string $city = ''): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        if ($city === '') {
            $city = $GLOBALS['podr'] ?? ($_POST['city'] ?? '');
        }

        return pa_getArchiveDir() . DIRECTORY_SEPARATOR . $date . '_' . pa_sanitizeArchiveCity($city) . '.json';
    }
}

/**
 * Ищет последний архивный файл для города.
 * Если город не указан, ищет среди всех JSON-файлов в archive/.
 */
if (!function_exists('pa_findLatestArchiveFile')) {
    function pa_findLatestArchiveFile(string $city = ''): string
    {
        $files = glob(pa_getArchiveDir() . DIRECTORY_SEPARATOR . '*.json');

        if (!is_array($files) || empty($files)) {
            return '';
        }

        if ($city !== '') {
            $suffix = '_' . pa_sanitizeArchiveCity($city) . '.json';
            $filtered = [];

            foreach ($files as $file) {
                if (strlen($file) >= strlen($suffix) && substr($file, -strlen($suffix)) === $suffix) {
                    $filtered[] = $file;
                }
            }

            $files = $filtered;

            if (empty($files)) {
                return '';
            }
        }

        usort($files, function ($a, $b) {
            $cmp = strcmp(basename($b), basename($a));

            if ($cmp !== 0) {
                return $cmp;
            }

            $ma = @filemtime($a);
            $mb = @filemtime($b);

            if ($ma === $mb) {
                return 0;
            }

            return ($mb <=> $ma);
        });

        return $files[0];
    }
}

/**
 * Определяет конкретный архивный файл.
 *
 * Приоритет:
 * 1. явно заданное имя файла archive_file;
 * 2. дата + город;
 * 3. последний архив для города.
 */
if (!function_exists('pa_resolveArchiveFile')) {
    function pa_resolveArchiveFile(string $date = '', string $city = '', string $fileName = ''): string
    {
        if ($fileName !== '') {
            $fileName = basename($fileName);

            if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'json') {
                return '';
            }

            return pa_getArchiveDir() . DIRECTORY_SEPARATOR . $fileName;
        }

        if ($date !== '') {
            return pa_getArchiveFilePath($date, $city);
        }

        return pa_findLatestArchiveFile($city);
    }
}

/**
 * Читает архивный JSON-файл.
 */
if (!function_exists('pa_readArchiveFile')) {
    function pa_readArchiveFile(string $file): array
    {
        if ($file === '' || !is_file($file) || !is_readable($file)) {
            return [];
        }

        $raw = file_get_contents($file);

        if ($raw === false || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }
}

/**
 * Загружает архив целиком.
 */
if (!function_exists('pa_loadArchive')) {
    function pa_loadArchive(string $date = '', string $city = '', string $fileName = ''): array
    {
        $file = pa_resolveArchiveFile($date, $city, $fileName);

        return pa_readArchiveFile($file);
    }
}

/**
 * Проверяет, есть ли в массиве ключ с учётом пробелов в конце/начале.
 */
if (!function_exists('pa_hasTrimmedKey')) {
    function pa_hasTrimmedKey(array $row, string $needle): bool
    {
        foreach ($row as $key => $value) {
            if (trim((string)$key) === $needle) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Преобразует вложенный архивный employees в плоский список сотрудников.
 *
 * Было:
 * [
 *     'Подразделение' => [
 *         'ФИО' => [...]
 *     ]
 * ]
 *
 * Стало:
 * [
 *     [...],
 *     [...]
 * ]
 */
if (!function_exists('pa_flattenArchiveEmployees')) {
    function pa_flattenArchiveEmployees(array $employees): array
    {
        $result = [];

        foreach ($employees as $division => $staff) {
            if (!is_array($staff)) {
                continue;
            }

            foreach ($staff as $fio => $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (!pa_hasTrimmedKey($row, 'Ф.И.О') && is_string($fio)) {
                    $row['Ф.И.О'] = $fio;
                }

                if (!pa_hasTrimmedKey($row, 'otdel') && is_string($division)) {
                    $row['otdel'] = $division;
                }

                $result[] = $row;
            }
        }

        return $result;
    }
}

/**
 * Возвращает период для расчётного листа.
 *
 * По умолчанию берёт месяц из meta.dateFrom.
 * Если dateFrom пустой или некорректный — предыдущий месяц.
 */
if (!function_exists('pa_getArchivePeriod')) {
    function pa_getArchivePeriod(array $payload): string
    {
        $months = [
            'Январь',
            'Февраль',
            'Март',
            'Апрель',
            'Май',
            'Июнь',
            'Июль',
            'Август',
            'Сентябрь',
            'Октябрь',
            'Ноябрь',
            'Декабрь',
        ];

        $date = pa_getMetaValue($payload, 'dateFrom', '');

        if (trim((string)$date) === '') {
            $date = pa_getMetaValue($payload, 'dateTo', '');
        }

        $date = trim((string)$date);

        $ts = $date === '' ? false : strtotime($date);

        if (!$ts) {
            $ts = strtotime('first day of previous month');
        }

        $monthIndex = (int)date('n', $ts) - 1;

        if ($monthIndex < 0 || $monthIndex > 11) {
            $monthIndex = 0;
        }

        return $months[$monthIndex] . ' ' . date('Y', $ts);
    }
}
