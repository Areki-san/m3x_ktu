<?php
require_once __DIR__ . '/conf.php';                 // Подключаем conf.php, там создаётся $smarty
require_once __DIR__ . '/payroll_archive.php';


// === НАСТРОЙКИ ===
const API_URL       = 'http://194.50.9.10/api/send-mail';
const TEMPLATE_FILE = __DIR__ . '/payslip_template.html'; 
const SENDER_NAME   = 'Бухгалтерия M3X';

// === НАСТРОЙКИ ТЕСТИРОВАНИЯ ===
// Установите false, чтобы делать реальную массовую рассылку всем
const TEST_MODE          = true; 
//const TEST_TARGET_EMAIL  = 'anderson.frolkov@yandex.ru';  // Email для тестовой отправки
const TEST_TARGET_EMAIL  = 'Arekisan@yandex.com';           // Email для тестовой отправки

// Шаблон письма для Smarty (Smarty будет искать его в ./templates/)
define('PAYSLIP_TEMPLATE_FILE', 'payslip_template.tpl');

// === КАКОЙ АРХИВ ИСПОЛЬЗОВАТЬ ===
//
// ARCHIVE_CITY:
// 'm3x'       — все подразделения
// 'Алчевск'   — архив по Алчевску
// 'Стаханов'  — архив по Стаханову и т.д.
//
// ARCHIVE_DATE:
// ''               — взять последний архив для города
// '2026-08-17'     — взять конкретный архив по дате создания
//
// ARCHIVE_FILE_NAME:
// ''                          — искать по дате/городу
// '2026-08-17_m3x.json'       — взять конкретный файл из archive/
const ARCHIVE_CITY      = 'm3x';
const ARCHIVE_DATE      = '';
const ARCHIVE_FILE_NAME = '';

@set_time_limit(0);

$archiveDate     = $_REQUEST['archive_date'] ?? ARCHIVE_DATE;
$archiveCity     = $_REQUEST['city'] ?? ARCHIVE_CITY;
$archiveFileName = $_REQUEST['archive_file'] ?? ARCHIVE_FILE_NAME;

// Поддержка запуска из консоли:
// php mail.php 2026-08-17 m3x
// php mail.php "" m3x 2026-08-17_m3x.json
if (PHP_SAPI === 'cli') {
    $argv = $_SERVER['argv'] ?? [];

    if (isset($argv[1]) && $argv[1] !== '') {
        $archiveDate = $argv[1];
    }

    if (isset($argv[2]) && $argv[2] !== '') {
        $archiveCity = $argv[2];
    }

    if (isset($argv[3]) && $argv[3] !== '') {
        $archiveFileName = $argv[3];
    }
}

$archiveFile = pa_resolveArchiveFile(
    (string)$archiveDate,
    (string)$archiveCity,
    (string)$archiveFileName
);

$payload = pa_readArchiveFile($archiveFile);

$employees = pa_getPayloadValue($payload, 'employees', []);

if (!is_array($employees) || empty($employees)) {
    die(
        'КРИТИЧЕСКАЯ ОШИБКА: архив расчёта не найден или пуст. Файл: '
        . htmlspecialchars((string)$archiveFile, ENT_QUOTES, 'UTF-8')
    );
}

// Преобразуем новый архив в плоский список сотрудников,
$result_parse = pa_flattenArchiveEmployees($employees);

// Сразу нормализуем ключи и строковые значения: убираем лишние пробелы
foreach ($result_parse as $key => $row) {
    if (is_array($row)) {
        $result_parse[$key] = normalizeRow($row);
    }
}

// Период расчётного листа
// По умолчанию берётся месяц из: meta.dateFrom
// Если dateFrom пустой или некорректный, берётся предыдущий месяц.
$PERIOD = pa_getArchivePeriod($payload);

$log_error = "Ошибки:\n";
$log_ok    = "\nУспешно отправлено:\n";

// 2. Фильтрация для тестового режима
if (TEST_MODE) {
    $filtered_parse = [];
    $testEmail = strtolower(normalizeEmail(TEST_TARGET_EMAIL));

    foreach ($result_parse as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $email = strtolower(normalizeEmail(getFieldByNames($value, ['email'], '')));

        if ($email !== '' && $email === $testEmail) {
            $filtered_parse[$key] = $value;
            break;
        }
    }
    $result_parse = $filtered_parse;
    
    if (empty($result_parse)) {
        die("ТЕСТОВЫЙ РЕЖИМ: Сотрудник с email '" . TEST_TARGET_EMAIL . "' не найден в JSON файле.\nПроверьте ключ 'email' в ваших данных.");
    }
    
    echo "<h3 style='color: orange;'>⚠ ВНИМАНИЕ: АКТИВЕН ТЕСТОВЫЙ РЕЖИМ. Отправка только для: "
        . htmlspecialchars(TEST_TARGET_EMAIL, ENT_QUOTES, 'UTF-8')
        . "</h3>";
}

// 3. Проверяем существование Smarty-шаблона на диске
$templatePathOnDisk = __DIR__ . '/templates/' . PAYSLIP_TEMPLATE_FILE;
if (!is_file($templatePathOnDisk)) {
    die("КРИТИЧЕСКАЯ ОШИБКА: Файл шаблона не найден по пути: " . $templatePathOnDisk);
}

// 4. Проходим по сотрудникам (в тестовом режиме здесь будет только 1 человек)
foreach ($result_parse as $emp_id => $value) {
    if (!is_array($value)) {
        continue;
    }
    
    $value = normalizeRow($value);

    // Поддержка разных имен ключей в JSON (Ф.И.О, name, short_name)
    $FIO = $value['Ф.И.О'] ?? $value['name'] ?? $value['short_name'] ?? 'Не указано';
    $emailRaw = getFieldByNames($value, ['email'], '');
    $email = normalizeEmail($emailRaw);

    if ($email === '') {
        $log_error .= '- ' . $FIO . " (нет поля email)\n";
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $log_error .= '- ' . $FIO . ' (' . $email . ") - в US указан некорректный email\n";
        continue;
    }

    // === ИНЪЕКЦИЯ ТЕСТОВЫХ ДАННЫХ ===
    // Если полей зарплаты нет в JSON, подставляем тестовые значения
    if (TEST_MODE) {
        $testDefaults = [
            'Ставка'                => '0',
            'Ставка час'            => '310',
            'Надбавки'              => '1000',
            'Доп.премия'            => '2000',
            'Аванс'                 => '0',
            'Отпускные'             => '0',
            'Больничные'            => '0',
            'Удержания'             => '0',
            'Заявки'                => '0',
            '% КТУ Личный'          => '0',
            'КТУ Количество'        => '0',
            'Часы'                  => '120',
            'Часы Сверхурочные'     => '2',
            'Часы Выходные'         => '2',
            'ЗП Часы'               => '39184',
            'ЗП КТУ'                => '0',
            'Строительные работы'   => '0',
            'Итого'                 => '42184',
            'На руки'               => '42184',
        ];

        foreach ($testDefaults as $fieldName => $fieldValue) {
            if (!array_key_exists($fieldName, $value) || $value[$fieldName] === null || $value[$fieldName] === '') {
                $value[$fieldName] = $fieldValue;
            }
        }
    }

    // Извлекаем переменные (?? '0' защищает от отсутствия ключа)
    $RATE_HOUR      = $value['Ставка час'] ?? '0';
    $ALLOWANCES     = $value['Надбавки'] ?? '0';
    $RATE           = $value['Ставка'] ?? '0';
    $HOURS          = $value['Часы'] ?? '0';
    $OVERTIME_HOURS = $value['Часы Сверхурочные'] ?? '0';
    $WEEKEND_HOURS  = $value['Часы Выходные'] ?? '0';
    $SALARY_HOURS   = $value['ЗП Часы'] ?? '0';
    $TOTAL          = $value['Итого'] ?? '0';
    $ADVANCE        = $value['Аванс'] ?? '0';
    $BONUS          = $value['Доп.премия'] ?? '0';
    $DEDUCTIONS     = $value['Удержания'] ?? '0';
    $NET_SALARY     = $value['На руки'] ?? '0';

    $ZAYAVKI            = getFieldByNames($value, ['Заявки']);
    $KTU_PERSONAL       = getFieldByNames($value, ['% КТУ Личный']);
    $ZP_KTU             = getFieldByNames($value, ['ЗП КТУ']);
    $CONSTRUCTION_WORKS = getFieldByNames($value, ['Строительные работы', 'Строительные работы']);
    $VACATION_PAY       = getFieldByNames($value, ['Отпускные'], '0');
    $SICK_LEAVE         = getFieldByNames($value, ['Больничные'], '0');


    // Получаем дополнительные поля с учетом возможных пробелов в концах ключей
    $RATE = getFieldByNames($value, ['Ставка ', 'Ставка'], '0');

    $ZAYAVKI = getFieldByNames($value, [
        'Заявки ',
        'Заявки'
    ]);

    $KTU_PERSONAL = getFieldByNames($value, [
        '% КТУ Личный ',
        '% КТУ Личный'
    ]);

    $ZP_KTU = getFieldByNames($value, [
        'ЗП КТУ ',
        'ЗП КТУ'
    ]);

    $CONSTRUCTION_WORKS = getFieldByNames($value, [
        'Строительные работы ',
        'Строительные работы',
        'Строительные работы ',
        'Строительные работы'
    ]);

    $VACATION_PAY = (string)getFieldByNames($value, [
        'Отпускные ',
        'Отпускные'
    ], '0');

    $SICK_LEAVE = (string)getFieldByNames($value, [
        'Больничные ',
        'Больничные'
    ], '0');

    // Приводим пустые строки к нулю
    if (trim((string)$VACATION_PAY) === '') {
        $VACATION_PAY = '0';
    }

    if (trim((string)$SICK_LEAVE) === '') {
        $SICK_LEAVE = '0';
    }

    if (trim((string)$DEDUCTIONS) === '') {
        $DEDUCTIONS = '0';
    }

    /**
     * подготовка переменных для Smarty и рендер шаблона
     */
    // Флаги отображения необязательных строк
    $showZayavki            = payslipFloat($ZAYAVKI)            != 0.0;
    $showKtuPersonal        = payslipFloat($KTU_PERSONAL)       != 0.0;
    $showZpKtu              = payslipFloat($ZP_KTU)             != 0.0;
    $showRate               = payslipFloat($RATE)               != 0.0;
    $showRateHours          = payslipFloat($RATE_HOUR)          != 0.0;
    $showConstructionWorks  = payslipFloat($CONSTRUCTION_WORKS) != 0.0;
    $showVacation           = payslipFloat($VACATION_PAY)       != 0.0;
    $showSick               = payslipFloat($SICK_LEAVE)         != 0.0;

    // Передаём данные в Smarty-шаблон
    $smartyVars = [
        'fio' => $FIO,

        'zayavki'      => formatInt($ZAYAVKI),
        'show_zayavki' => $showZayavki,

        'ktu_personal'      => number_format(payslipFloat($KTU_PERSONAL), 2, ',', ' '),
        'show_ktu_personal' => $showKtuPersonal,

        'zp_ktu'         => formatMoney($ZP_KTU),
        'show_zp_ktu'    => $showZpKtu,

        'hours'          => formatInt($HOURS),
        'overtime_hours' => formatInt($OVERTIME_HOURS),
        'weekend_hours'  => formatInt($WEEKEND_HOURS),

        'rate_hour'      => formatMoney($RATE_HOUR),
        'salary_hours'   => formatMoney($SALARY_HOURS),
        'show_rate_hours'   => $showRateHours,

        'rate'      => formatMoney($RATE),
        'show_rate' => $showRate,

        'construction_works'      => formatMoney($CONSTRUCTION_WORKS),
        'show_construction_works' => $showConstructionWorks,

        'allowances' => formatMoney($ALLOWANCES),
        'bonus'      => formatMoney($BONUS),

        'vacation_pay'  => formatMoney($VACATION_PAY),
        'show_vacation' => $showVacation,

        'sick_leave'    => formatMoney($SICK_LEAVE),
        'show_sick'     => $showSick,

        'deductions'    => formatMoney($DEDUCTIONS),

        'total'         => formatMoney($TOTAL),
        'advance'       => formatMoney($ADVANCE),
        'net_salary'    => formatMoney($NET_SALARY),
    ];

    $smarty->assign($smartyVars);

    // Рендерим письмо
    $html_body = $smarty->fetch(PAYSLIP_TEMPLATE_FILE);

    // Убираем возможные пустые строки после {if}
    $html_body = preg_replace('/^[ \t]+$/m', '', $html_body);
    $html_body = preg_replace('/(\r?\n){2,}/', "\n", $html_body);
    $html_body = trim($html_body);

    // Тема письма
    $periodText = defined('PERIOD') ? PERIOD : (isset($PERIOD) ? $PERIOD : '');
    $subject = "Расчетный лист за " . $periodText . " - " . $FIO;

    // Формируем JSON payload для API
    $payload = json_encode([
        'address'     => $email,
        'subject'     => $subject,
        'body'        => $html_body,
        'sender_name' => SENDER_NAME,
        'is_html'     => true
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

     if ($payload === false) {
        $log_error .= '- ' . $FIO . ' (' . $email . ') - не удалось сформировать JSON: ' . json_last_error_msg() . "\n";
        continue;
    }

    // 6. Отправляем запрос
    $response = sendEmailRequest(API_URL, $payload);

    if ($response !== false) {
        $log_ok .= "+ " . $FIO . " (" . $email . ")\n";
    } else {
        $log_error .= "- " . $FIO . " (" . $email . ") - сбой запроса к API\n";
    }
}

// 7. Вывод результатов на страницу
echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px;'>";
echo htmlspecialchars($log_error . $log_ok, ENT_QUOTES, 'UTF-8');
echo "</pre>";


// === ФУНКЦИИ ===

/**
 * Отправляет POST-запрос с JSON-данными
 *
 * @param string $url
 * @param string $payload
 * @return mixed
 */
function sendEmailRequest($url, $payload) {
    $header = "Content-Type: application/json\r\n"
        . "Accept: application/json\r\n";
    
    $options = [
        'http' => [
            'method'        => 'POST',
            'header'        => $header,
            'content'       => $payload,
            'content'       => $payload,
            'timeout'       => 15,          // Таймаут 15 секунд
            'ignore_errors' => true
        ]
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        return false;
    }

    $status = 0;

    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})(?:\s|$)/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }
    }

    if ($status >= 400) {
        return false;
    }
    
    $decoded = json_decode($result, true);
    return $decoded !== null ? $decoded : true;
}

/**
 * Нормализует ключи и строковые значения
 *
 * @param array $row
 * @return array
 */
function normalizeRow(array $row): array
{
    $out = [];

    foreach ($row as $key => $value) {
        $key = trim((string)$key);

        if ($key === '') {
            continue;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if (!array_key_exists($key, $out)) {
            $out[$key] = $value;
        } elseif (($out[$key] === null || $out[$key] === '') && $value !== null && $value !== '') {
            $out[$key] = $value;
        }
    }

    return $out;
}

/**
 * Возвращает значение по одному из возможных имен ключа
 *
 * @param array $row
 * @param array $names
 * @param mixed $default
 * @return mixed
 */
function getFieldByNames(array $row, array $names, $default = null)
{
    foreach ($names as $name) {
        $name = trim((string)$name);

        if ($name !== '' && array_key_exists($name, $row)) {
            return $row[$name];
        }
    }

    foreach ($names as $name) {
        $name = trim((string)$name);

        if ($name === '') {
            continue;
        }

        foreach ($row as $key => $value) {
            if (trim((string)$key) === $name) {
                return $value;
            }
        }
    }

    return $default;
}

/**
 * Нормализует email
 *
 * @param mixed $email
 * @return string
 */
function normalizeEmail($email): string
{
    if ($email === null || $email === false) {
        return '';
    }

    $email = trim((string)$email);

    if ($email === '') {
        return '';
    }

    // Удаляем все пробельные символы, которые могут случайно попасть в email
    $email = preg_replace('/\s+/', '', $email);

    // На случай, если в конце случайно оказалась точка
    $email = rtrim($email, '.');

    return $email;
}

/**
 * Преобразует значение в float
 *
 * @param mixed $value
 * @return float
 */
function payslipFloat($value): float
{
    if ($value === null || $value === false || $value === []) {
        return 0.0;
    }

    $value = trim((string)$value);

    if ($value === '') {
        return 0.0;
    }

    $value = str_replace(',', '.', $value);
    $value = preg_replace('/[^0-9\.\-]/', '', $value);

    if ($value === '' || $value === '-' || $value === '.') {
        return 0.0;
    }

    return (float)$value;
}



/**
 * Форматирует денежную сумму
 *
 * @param mixed $value
 * @return string
 */
function formatMoney($value): string
{
    $float = payslipFloat($value);

    if ($float == 0.0) {
        return '0';
    }

    $str = number_format($float, 2, '.', '');

    if (strpos($str, '.') !== false) {
        $str = rtrim(rtrim($str, '0'), '.');
    }

    if ($str === '-0') {
        $str = '0';
    }

    return $str;
}

/**
 * Форматирует целое значение
 *
 * @param mixed $value
 * @return string
 */
function formatInt($value): string
{
    return (string)(int)round(payslipFloat($value));
}
