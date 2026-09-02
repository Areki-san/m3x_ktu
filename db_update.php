<?php
declare(strict_types=1);
if (is_debug){
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}


require_once 'conf.php';
require_once 'api/us_api.php';

// ─── Проверка констант ───────────────────────────────────────
if (!defined('SQL_DB')) {
    die("Ошибка: Константа SQL_DB не определена в conf.php");
}

if (!defined('arr_city')) {
    die("Ошибка: Массив arr_city не определен в conf.php");
}

$startTime = microtime(true);
echo "<h3>Начинаю обновление базы данных...</h3><br>";

// ─── Паттерн города ──────────────────────────────────────────
$city_pattern = '(' . implode('|', array_map('preg_quote', arr_city)) . ')';
echo "Паттерн для поиска города: {$city_pattern}<br>";

// ─── Подключение / создание БД ───────────────────────────────
$dbExists = file_exists(SQL_DB);
$db = new SQLite3(SQL_DB);
$db->busyTimeout(5000);
$db->exec('PRAGMA journal_mode=WAL');
$db->exec('PRAGMA synchronous=NORMAL');
$db->exec('PRAGMA foreign_keys=ON');

echo $dbExists ? "База данных найдена<br>" : "База данных не найдена, создаю новую...<br>";

// ─── Создание таблиц (идемпотентно) ─────────────────────────
$schemas = [
    "CREATE TABLE IF NOT EXISTS division_list (
        id              INTEGER PRIMARY KEY NOT NULL UNIQUE,
        division_name   TEXT NOT NULL,
        city            TEXT,
        is_set          INTEGER NOT NULL DEFAULT 0
    )",

    "CREATE TABLE IF NOT EXISTS employees (
        id              INTEGER PRIMARY KEY NOT NULL UNIQUE,
        msg_chat_id     INTEGER,
        email           TEXT,
        fio             TEXT NOT NULL,
        oklad           REAL NOT NULL DEFAULT 0,
        stavka_chas     REAL NOT NULL DEFAULT 0,
        stavka          REAL NOT NULL DEFAULT 0,
        nadbavka        REAL NOT NULL DEFAULT 0,
        avans           REAL NOT NULL DEFAULT 0,
        premia          REAL NOT NULL DEFAULT 0,
        otpusknye       REAL NOT NULL DEFAULT 0,
        bolnichnye      REAL NOT NULL DEFAULT 0,
        uderjanie       REAL NOT NULL DEFAULT 0,
        dolg            REAL NOT NULL DEFAULT 0,
        division        TEXT NOT NULL,
        division_id     INTEGER NOT NULL DEFAULT 0,
        city            TEXT NOT NULL
    )",

    "CREATE TABLE IF NOT EXISTS ktu (
        work_id	        TEXT,
        type_of_work	TEXT NOT NULL UNIQUE,
        work_ktu	    REAL NOT NULL DEFAULT 0
    )",

    "CREATE TABLE IF NOT EXISTS fond_ktu (
        otdel   TEXT NOT NULL UNIQUE,
        ktu     INTEGER NOT NULL DEFAULT 0
    )",

    "CREATE TABLE IF NOT EXISTS table_headers (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        header_name TEXT NOT NULL,
        sort_order  INTEGER NOT NULL DEFAULT 0
    )",
];

foreach ($schemas as $sql) {
    if (!$db->exec($sql)) {
        echo "Ошибка создания таблицы: " . $db->lastErrorMsg() . "<br>";
    }
}

if (!$dbExists) {
    echo "Новая база данных создана<br>";
}

// ─────────── (только в режиме update_mode) ──────────────────
if (update_mode){
// ============================================================
// МИГРАЦИЯ: обновление структуры таблицы employees
// ============================================================
    echo "<h4>Проверяю структуру таблицы employees...</h4><br>";

    // Получаем текущие колонки таблицы
    $columns_info = [];
    $result = $db->query("PRAGMA table_info(employees)");
    while ($col = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns_info[$col['name']] = $col;
    }

    // Определяем, нужна ли миграция
    $need_migration = false;

    // Проверяем, есть ли новые колонки
    if (!isset($columns_info['email']))       { $need_migration = true; echo "  - Отсутствует колонка 'email'<br>"; }
    if (!isset($columns_info['oklad']))       { $need_migration = true; echo "  - Отсутствует колонка 'oklad'<br>"; }
    if (!isset($columns_info['bolnichnye']))  { $need_migration = true; echo "  - Отсутствует колонка 'bolnichnye'<br>"; }
    if (!isset($columns_info['otpusknye']))   { $need_migration = true; echo "  - Отсутствует колонка 'otpusknye'<br>"; }
    if (!isset($columns_info['dolg']))        { $need_migration = true; echo "  - Отсутствует колонка 'dolg'<br>"; }

    // Проверяем, есть ли колонка, которую нужно удалить
    if (isset($columns_info['shtraf']))       { $need_migration = true; echo "  - Лишняя колонка 'shtraf' (будет удалена)<br>"; }

    // Проверяем, есть ли колонка, которую нужно удалить
    if (isset($columns_info['dop_premia']))   { $need_migration = true; echo "  - Колонка 'premia' будет переименована в premia<br>"; }

    // Проверяем, есть ли UNIQUE на fio (нужно убрать)
    if (isset($columns_info['fio'])) {
        $idx_result = $db->query("PRAGMA index_list(employees)");
        while ($idx = $idx_result->fetchArray(SQLITE3_ASSOC)) {
            if ($idx['unique']) {
                $idx_info = $db->query("PRAGMA index_info({$idx['name']})");
                while ($idx_col = $idx_info->fetchArray(SQLITE3_ASSOC)) {
                    if ($idx_col['name'] === 'fio') {
                        $need_migration = true;
                        echo "  - Обнаружен UNIQUE индекс на 'fio' (будет убран)<br>";
                    }
                }
            }
        }
    }

    if ($need_migration) {
        echo "<b>Выполняю миграцию таблицы employees...</b><br>";

        $db->exec("BEGIN TRANSACTION");

        try {
            // 1. Создаём новую таблицу с целевой структурой
            $db->exec("
                CREATE TABLE IF NOT EXISTS employees_new (
                    id              INTEGER PRIMARY KEY NOT NULL UNIQUE,
                    msg_chat_id     INTEGER,
                    email           TEXT,
                    fio             TEXT NOT NULL,
                    oklad           REAL NOT NULL DEFAULT 0,
                    stavka_chas     REAL NOT NULL DEFAULT 0,
                    stavka          REAL NOT NULL DEFAULT 0,
                    nadbavka        REAL NOT NULL DEFAULT 0,
                    premia          REAL NOT NULL DEFAULT 0,
                    uderjanie       REAL NOT NULL DEFAULT 0,
                    avans           REAL NOT NULL DEFAULT 0,
                    bolnichnye      REAL NOT NULL DEFAULT 0,
                    otpusknye       REAL NOT NULL DEFAULT 0,
                    dolg            REAL NOT NULL DEFAULT 0,
                    division        TEXT NOT NULL,
                    division_id     INTEGER NOT NULL DEFAULT 0,
                    city            TEXT NOT NULL
                )
            ");

            // 2. Копируем данные из старой таблицы (если она существует)
            if (isset($columns_info['id'])) {
                $db->exec("
                    INSERT INTO employees_new (
                        id, fio,
                        oklad,
                        stavka_chas, stavka, nadbavka, premia, uderjanie,
                        avans, bolnichnye, otpusknye, dolg,
                        division, division_id, city
                    )
                    SELECT
                        id, fio,
                        oklad,
                        stavka_chas, stavka, nadbavka, premia, uderjanie,
                        avans, bolnichnye, otpusknye, dolg,
                        division, division_id, city
                    FROM employees
                ");
                echo "  ✓ Данные скопированы в новую структуру<br>";
            }

            // 3. Удаляем старую таблицу
            $db->exec("DROP TABLE IF EXISTS employees");

            // 4. Переименовываем новую таблицу
            $db->exec("ALTER TABLE employees_new RENAME TO employees");

            $db->exec("COMMIT");
            echo "<b style='color:green;'>✓ Миграция таблицы employees завершена успешно!</b><br>";

        } catch (Exception $e) {
            $db->exec("ROLLBACK");
            die("<b style='color:red;'>Ошибка миграции: " . $e->getMessage() . "</b>");
        }
    } else {
        echo "✓ Структура таблицы employees актуальна, миграция не требуется.<br>";
    }
    echo "<hr>";
// ============================================================
// КОНЕЦ МИГРАЦИИ
// ============================================================

// ============================================================
// Обновление порядка и названий заголовков (table_headers)
// ============================================================
    echo "<h4>Обновляю заголовки таблицы (table_headers)...</h4><br>";

    $db->exec('BEGIN TRANSACTION');
    $db->exec("DROP TABLE IF EXISTS table_headers");
    $db->exec("CREATE TABLE IF NOT EXISTS table_headers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        header_name TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0
    )");

    // определяет порядок заголовков таблиц расчётов
    $new_headers = [
        'Ф.И.О',
        'Заявки',
        '% КТУ Личный',
        'Часы',
        'Часы Сверхурочные',
        'Часы Выходные',
        'Ставка час',
        'ЗП Часы',
        'Ставка',
        'ЗП КТУ',
        'Строительные работы',
        'Надбавки',
        'Премия',
        'Удержания',
        'Итого ЗП',
        'Оклад',
        'Аванс',
        'На руки',
        'Больничные',
        'Отпускные',
        'Всего за мес.',
        'Долг'
    ];

    $stmt = $db->prepare("INSERT INTO table_headers (header_name, sort_order) VALUES (?, ?)");
    foreach ($new_headers as $order => $name) {
        $stmt->bindValue(1, $name, SQLITE3_TEXT);
        $stmt->bindValue(2, $order, SQLITE3_INTEGER);
        $stmt->execute();
        $stmt->reset();
    }
    $stmt->close();
    $db->exec('COMMIT');
    echo "  ✓ Заголовки обновлены.<br><hr>";;
}

echo "<hr>";
// ============================================================
// 1. Получаем АКТУАЛЬНЫЙ список подразделений из API
// ============================================================
    echo "<h4>Получаю список подразделений...</h4><br>";
    $divisions_list_response = usApiRequet($get_division_list); // <-- Сохраняем в отдельную переменную!

    if (empty($divisions_list_response['data'])) {
        echo "Ошибка: Не получены данные о подразделениях<br>";
        echo "<pre>"; print_r($divisions_list_response); echo "</pre>";
        $db->close();
        exit;
    }

    $actual_div_ids = array_keys($divisions_list_response['data']); // Запоминаем актуальные ID для очистки в конце
    echo "Получено " . count($divisions_list_response['data']) . " подразделений<br>";


    // Используем UPSERT вместо SELECT + UPDATE/INSERT
    $stmtUpsertDiv = $db->prepare("
        INSERT INTO division_list (id, division_name, city)
        VALUES (:id, :name, :city)
        ON CONFLICT(id) DO UPDATE SET
            division_name = excluded.division_name,
            city          = excluded.city
    ");
    $stmtFond = $db->prepare("INSERT OR IGNORE INTO fond_ktu (otdel) VALUES (?)");
    $db->exec('BEGIN TRANSACTION');

    foreach ($divisions_list_response['data'] as $id => $value) {
        $division_name = $value['name'] ?? 'Неизвестно';
        
        // Ищем город в комментарии
        $city_name = '';
        if (isset($value['comment']) && preg_match('/' . $city_pattern . '/', $value['comment'], $m)) {
            $city_name = $m[0];
        }
        
        echo "Подразделение ID {$id}: {$division_name} (город: {$city_name})<br>";
        
        // Проверяем, существует ли уже запись
        $stmtUpsertDiv->bindValue(':id',   (int) $id,          SQLITE3_INTEGER);
        $stmtUpsertDiv->bindValue(':name', $division_name,      SQLITE3_TEXT);
        $stmtUpsertDiv->bindValue(':city', $city_name,          SQLITE3_TEXT);
        $stmtUpsertDiv->execute();
        $stmtUpsertDiv->reset();

        if ($city_name && in_array($city_name, arr_city, true)) {
            $stmtFond->bindValue(1, $division_name, SQLITE3_TEXT);
            $stmtFond->execute();
            $stmtFond->reset();
        }
    }

    $db->exec('COMMIT');
    $stmtUpsertDiv->close();
    $stmtFond->close();
    echo "<hr>";

    // ─── Обновление сотрудников ──────────────────────────────────
    echo "<h4>Получаю список опрашиваемых подразделений...</h4>";
    $div_array = [];
    $res = $db->query('SELECT id FROM division_list');  // ВАЖНО: используем ВСЕ подразделения
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $div_array[] = (int) $row['id'];
    }

    if (empty($div_array)) {
        echo "Нет подразделений для опроса. Пропускаем обновление сотрудников.<br>";
    } else {
        $div_str = implode(',', $div_array);
        echo "Будут опрошены подразделения с ID: {$div_str}<br>";

        echo "<h4>Получаю список сотрудников...</h4>";
        $staff_response = usApiRequet($get_division, $div_str); // <-- Используем переменную для данных по сотрудникам 

        if (empty($staff_response['data'])) {
            echo "Ошибка: Не получены данные о сотрудниках<br><pre>";
            print_r($staff_response);
            echo "</pre>";
        } else {
            $emp_array = [];
            $processed_count = 0;

            // Подготавливаем UPSERT-запрос один раз
            $stmtUpsertEmp = $db->prepare("
                INSERT INTO employees (id, msg_chat_id, email, fio, division, division_id, city)
                VALUES (:id, :chat, :email, :fio, :div, :div_id, :city)
                ON CONFLICT(id) DO UPDATE SET
                    msg_chat_id = excluded.msg_chat_id,
                    email       = excluded.email,
                    fio         = excluded.fio,
                    division    = excluded.division,
                    division_id = excluded.division_id,
                    city        = excluded.city
            ");

            $db->exec('BEGIN TRANSACTION');

            foreach ($staff_response['data'] as $division_id => $division_data) {
                $division_name = $division_data['name'] ?? 'Неизвестно';
                $city_name = '';
                if (isset($division_data['comment']) && preg_match('/' . $city_pattern . '/', $division_data['comment'], $m)) {
                    $city_name = $m[0];
                }

                echo "<h5>Подразделение: {$division_name} (ID: {$division_id}, город: {$city_name})</h5>";

                if (!isset($division_data['staff']['work']) || !is_array($division_data['staff']['work'])) {
                    echo "Нет данных о сотрудниках в этом подразделении<br>";
                    continue;
                }

                echo "Найдено сотрудников: " . count($division_data['staff']['work']) . "<br>";

                // Массив для отслеживания уже обработанных ID в рамках текущего подразделения
                $processed_in_this_div = [];    // Защита от дублей внутри отдела

                foreach ($division_data['staff']['work'] as $work_data) {
                    $employee_id = (int) ($work_data['employee_id'] ?? 0);
                    if ($employee_id <= 0) continue;
                    
                    // Пропускаем сотрудника, если он уже встречался в этом подразделе
                    if (in_array($employee_id, $processed_in_this_div, true)) {
                        continue; 
                    }
                    $processed_in_this_div[] = $employee_id;
                    
                    $emp_array[] = $employee_id;
                    $user = usApiRequet($get_data, $employee_id);
                    if (empty($user['data'][$employee_id])) {
                        echo "  ✗ Сотрудник ID {$employee_id}: данные не получены<br>";
                        continue;
                    }

                    $emp = $user['data'][$employee_id];
                    $fio = $emp['name'] ?? 'Неизвестный сотрудник';
                    $chatId = isset($emp['messenger_chat_id'])
                        ? preg_replace('/[^0-9]/', '', $emp['messenger_chat_id'])
                        : '0';
                    $email_raw = $emp['email'] ?? '';
                    $email = is_string($email_raw) ? strtolower(trim($email_raw)) : '';

                    echo "  Сотрудник ID {$employee_id}: {$fio} (e-mail: {$email} / chat_id: {$chatId})<br>";

                    // UPSERT — один запрос вместо SELECT + UPDATE/INSERT
                    $stmtUpsertEmp->bindValue(':id',     $employee_id,   SQLITE3_INTEGER);
                    $stmtUpsertEmp->bindValue(':chat',   (int) $chatId,  SQLITE3_INTEGER);
                    $stmtUpsertEmp->bindValue(':email',  $email,         SQLITE3_TEXT);
                    $stmtUpsertEmp->bindValue(':fio',    $fio,           SQLITE3_TEXT);
                    $stmtUpsertEmp->bindValue(':div',    $division_name, SQLITE3_TEXT);
                    $stmtUpsertEmp->bindValue(':div_id', (int) $division_id, SQLITE3_INTEGER);
                    $stmtUpsertEmp->bindValue(':city',   $city_name,     SQLITE3_TEXT);
                    $stmtUpsertEmp->execute();
                    $stmtUpsertEmp->reset();

                    $processed_count++;
                    //echo "    ✓ Обновлены данные из API (ручные настройки сохранены)<br>";
                }
            }

            // Удаляем неактуальных сотрудников
            if (!empty($emp_array)) {
                $emp_array = array_unique($emp_array);
                $placeholders = implode(',', array_fill(0, count($emp_array), '?'));
                $stmtDel = $db->prepare("
                    DELETE FROM employees
                    WHERE division_id IN ({$div_str})
                    AND id NOT IN ({$placeholders})
                ");
                $i = 1;
                foreach ($emp_array as $eid) {
                    $stmtDel->bindValue($i++, $eid, SQLITE3_INTEGER);
                }
                $stmtDel->execute();
                $stmtDel->close();
                
                $deleted = $db->changes();
                if ($deleted > 0) {
                    echo "<br>Удалено неактуальных сотрудников: {$deleted}<br>";
                }
            }

            $db->exec('COMMIT');
            $stmtUpsertEmp->close();

            echo "<br>Всего обработано сотрудников: {$processed_count}<br>";
            echo "Всего ID в массиве: " . count($emp_array) . "<br>";
        }
    }

    // ─── Удаление неактуальных подразделений ─────────────────────
    // ВАЖНО: Используем $actual_div_ids, полученные из get_division_list, а не из get_division!
    if (!empty($actual_div_ids)) {
        $actual_ids_str = implode(',', array_map('intval', $actual_div_ids));
        $db->exec("DELETE FROM division_list WHERE id NOT IN ({$actual_ids_str})");
        $deleted_divs = $db->changes();
        if ($deleted_divs > 0) {
            echo "Удалено неактуальных подразделений из БД: {$deleted_divs}<br>";
        }
    } else {
        echo "<b style='color:red;'>Внимание: Не удалось получить актуальный список подразделений для очистки.</b><br>";
    }

    // ─── Статистика ──────────────────────────────────────────────
    echo "<hr><h4>Итоговая статистика:</h4>";

    $empCount = $db->querySingle("SELECT COUNT(*) FROM employees");
    $divCount = $db->querySingle("SELECT COUNT(*) FROM division_list");

    echo "Всего сотрудников в базе: {$empCount}<br>";
    echo "Всего подразделений в базе: {$divCount}<br>";

    $elapsed = round(microtime(true) - $startTime, 3);
    echo "<h3 style='color:green;'>Обновление завершено за {$elapsed} сек.</h3>";

    $db->close();
