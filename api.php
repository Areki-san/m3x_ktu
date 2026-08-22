<?php
//$start = microtime(true);
require 'conf.php';
error_reporting(E_ALL ^ E_NOTICE);

require 'vendor/autoload.php';

require_once __DIR__ . '/excel_export.php';
require_once __DIR__ . '/html_export.php';

$employees = array();

$podr = $_POST['city'] ?? '';

function ex_alert($message) {
    echo "<script>alert('$message');</script>";
}

if (empty($_POST['from'])){
	$dateFrom = date("Y-m-d 00:00:00",strtotime('first day of this month'));
} else {
	$dateFrom = $_POST['from']." 00:00:00";
}
if (empty($_POST['to'])){
	$dateTo = date("Y-m-d 23:59:00", strtotime('now'));
} else {
	$dateTo = $_POST['to']." 23:59:00";
}

function getSettings(){
	GLOBAL $employees, $podr;

	$dblite = new SQLite3(SQL_DB);
    $dblite->busyTimeout(5000);
    $dblite->exec('PRAGMA journal_mode=WAL');

	$res = $dblite->query('SELECT * FROM ktu');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)){
		$GLOBALS['classifier'][$row['type_of_work']] = $row['work_ktu'];
	}

	$res = $dblite->query('SELECT * FROM chasy');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['chasy'][$row['tip']] = $row['stavka'];
	}

	$res = $dblite->query('SELECT * FROM fond_ktu');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['fond_ktu'][$row['otdel']] = $row['ktu'];
	}

	// ─── ИСПРАВЛЕНО: корректный prepared statement + division_id ───
    if ($podr == "m3x"){
        $stmt = $dblite->prepare('SELECT * FROM employees WHERE city != ""');
    } else {
        $stmt = $dblite->prepare('SELECT * FROM employees WHERE city = :city');
        $stmt->bindValue(':city', $podr, SQLITE3_TEXT);
    }
    $res = $stmt->execute();
    
    // Определяем день месяца запуска расчёта
    $calcDay = (int)date('j', strtotime('now'));

	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $fio = $row['fio'];
        $div = $row['division'];

		$employees[$div][$fio]['id']            = (int) $row['id'];
		$employees[$div][$fio]['Ф.И.О']         = $fio;
        $employees[$div][$fio]['division_id']   = (int) ($row['division_id'] ?? 0);
		$employees[$div][$fio]['Оклад']         = (float) $row['oklad'] ?? 0;
		
		$employees[$div][$fio]['Ставка']        = (float) $row['stavka'] ?? 0;
		$employees[$div][$fio]['Ставка час']    = (float) $row['stavka_chas'] ?? 0;
		$employees[$div][$fio]['Надбавки']      = (float) $row['nadbavka'] ?? 0;
		$employees[$div][$fio]['Доп.премия']    = (float) $row['dop_premia'] ?? 0;

        // ... внутри цикла while ($row = $res->fetchArray(...)) ...

        if ($calcDay <= edit_delta) {
            // Если расчёт производится до числа указаного в edit_delta, аванс обнуляется для нового расчёта
            $employees[$div][$fio]['Аванс']     = 0.0;
        } else {
            // Если после числа указаного в edit_delta, берём из базы данных
            $employees[$div][$fio]['Аванс']     = (float)($row['avans'] ?? 0);
        }
		$employees[$div][$fio]['Отпускные']     = (float) $row['otpusknye'] ?? 0;
		$employees[$div][$fio]['Больничные']    = (float) $row['bolnichnye'] ?? 0;
		$employees[$div][$fio]['Удержания']     = (float) $row['uderjanie'] ?? 0;

        // ─── ИНИЦИАЛИЗАЦИЯ РАСЧЁТНЫХ ПОЛЕЙ ───────
        $employees[$div][$fio]['Заявки']              = 0;
        $employees[$div][$fio]['КТУ Количество']      = 0;
        $employees[$div][$fio]['Строительные работы'] = 0;
        $employees[$div][$fio]['Часы']                = 0;
        $employees[$div][$fio]['Часы Сверхурочные']   = 0;
        $employees[$div][$fio]['Часы Выходные']       = 0;
        $employees[$div][$fio]['ЗП Часы']             = 0;
        $employees[$div][$fio]['ЗП КТУ']              = 0;
        $employees[$div][$fio]['% КТУ Личный']        = 0;
        $employees[$div][$fio]['Итого']               = 0;
        $employees[$div][$fio]['На руки']             = 0;
	}

	$res = $dblite->query('SELECT * FROM table_headers');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['headers'][] = $row['header_name'];
	}

	$res = $dblite->query('SELECT * FROM division_list');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['otdel'][] = $row['division_name'];
	}

	$dblite->close();
}

/**
 * Получение всех задач подразделения через division_id_with_staff.
 * Делает 1-2 запроса вместо 2N.
 */
function getTasksByDivision(int $divisionId): array
{
    GLOBAL $dateFrom, $dateTo;
    
    if ($divisionId <= 0) {
        return [];
    }

    // ─── Запрос 1: ID всех задач подразделения ──────────────
    $listData = ktuApiRequest([
        "key"                    => key,
        "cat"                    => "task",
        "action"                 => "get_list",
        "date_finish_from"       => $dateFrom,
        "date_finish_to"         => $dateTo,
        "state_id"               => "2",
        "division_id_with_staff" => $divisionId,  // ← ключевой параметр!
    ]);

    $ids = $listData['list'] ?? '';
    if (is_array($ids)) {
        $ids = implode(',', $ids);
    }
    $ids = trim((string) $ids, " \t\n\r\0\x0B,");

    if ($ids === '') {
        return [];
    }

    // ─── Запрос 2: Детали всех задач (с батчингом) ──────────
    // API может иметь лимит на количество ID в show, поэтому разбиваем
    $idArray = explode(',', $ids);
    $batchSize = 100;  // по 100 ID за раз
    $allTasks = [];

    foreach (array_chunk($idArray, $batchSize) as $batch) {
        $batchIds = implode(',', $batch);
        
        $taskData = ktuApiRequest([
            "key"                 => key,
            "cat"                 => "task",
            "action"              => "show",
            "is_without_comments" => "1",
            "id"                  => $batchIds,
        ]);

        $tasks = $taskData['Data'] ?? [];
        
        if (isset($tasks['type'])) {
            $tasks = [$tasks];  // одна задача как объект
        }
        
        if (is_array($tasks)) {
            $allTasks = array_merge($allTasks, $tasks);
        }
    }

    return $allTasks;
}

/**
 * Распределяет задачи подразделения по конкретным сотрудникам.
 * Возвращает массив: [employeeId => [tasks...]]
 */
function distributeTasksByEmployee(array $tasks, array $divisionEmployees): array
{
    // Множество ID сотрудников подразделения для быстрой проверки
    $validEmployeeIds = [];
    foreach ($divisionEmployees as $surname => $data) {
        $empId = (int) ($data['id'] ?? 0);
        if ($empId > 0) {
            $validEmployeeIds[$empId] = $surname;
        }
    }

    if (empty($validEmployeeIds)) {
        return [];
    }

    $tasksByEmployee = [];

    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }

        // Извлекаем исполнителей из staff.employee
        $staff = $task['staff']['employee'] ?? [];
        if (!is_array($staff)) {
            $staff = ($staff === null || $staff === '') ? [] : [$staff];
        }

        // Проверяем каждого исполнителя
        foreach ($staff as $key => $value) {
            $executorId = is_numeric($key) ? (int) $key : (int) $value;
            
            if ($executorId > 0 && isset($validEmployeeIds[$executorId])) {
                $tasksByEmployee[$executorId][] = $task;
            }
        }
    }

    return $tasksByEmployee;
}

function httpPost($data){
    static $curl = null;

    if ($curl === null) {
        $curl = curl_init();
    }

	curl_setopt_array($curl, [
        CURLOPT_URL            => URL,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
	// здесь падает при добавлении нового города если не переписан getDivision()
	// см. TODO к нему
	$response = curl_exec($curl);

    if ($response === false) {
        error_log('httpPost error: ' . curl_error($curl));
        // При ошибке закрываем и сбрасываем — 
        // в следующий вызов будет создан новый хэндл
        curl_close($curl);
        $curl = null;
        return '';
    }
    
    // ⚠️ НЕ закрываем curl при успехе — 
    // переиспользуем для следующих запросов (TCP keep-alive)
    // PHP сам закроет его при завершении скрипта
    return $response;
}

/*
	get_list
	Описание: Список заданий (идентификаторы)
	Необязательные параметры (условия выборки):
	author_employee_id - ID сотрудника - автора задания (можно через запятую)
	closer_employee_id - ID сотрудника, который закрыл (выполнил) задание (можно через запятую)
	customer_id - ID абонента (можно через запятую)
	date_add_from - дата создания задания (с)
	date_add_to - дата создания задания (до)
	date_change_from - дата обновления задания (с)
	date_change_to - дата обновления задания (до)
	date_do_from - дата на которую назначено выполнение задания (с)
	date_do_to - дата на которую назначено выполнение задания (до)
	date_finish_from - дата выполнения задания (с)
	date_finish_to - дата выполнения задания (до)
	division_id - ID подразделения (можно через запятую)
	division_id_with_staff - ID подразделения (в т.ч. с заданиями сотрудников этого подразделения) (можно через запятую)
	employee_id - ID исполнителя (можно через запятую, используйте -1 для получения заданий без исполнителей)
	house_id - ID дома работ
	is_expired - флаг - выводить только просроченные задания
	node_id - ID объекта размещения
	state_id - ID статуса заданий (можно через запятую)
	task_position - координаты задания (там где это возможно. В формате lat,lng. Напр: 40.245218,52.333384)
	task_position_radius - радиус от task_position (в метрах)
	type_id - ID типа заданий (можно через запятую)
	watcher_employee_id - ID сотрудника-наблюдателя за заданием (можно через запятую)
	order_by - поле для сортировки (возможные варианты: date_add, date_change, date_do, date_finish, state_id, type_id)
	limit - лимит выборки записей
	offset - смещение выборки
*/
function getTaskList($id){
	GLOBAL $dateFrom,$dateTo;
	return array(
		"key"=>key,
		"cat"=>"task",
		"action"=>"get_list",
		"date_finish_from"=>$dateFrom,
		"date_finish_to"=>$dateTo,
		"state_id"=>"2",
		"employee_id"=>$id
	);
}

/*
	show
	Описание: Информация о задании
	Обязательные параметры:
	id - id задания (можно через запятую)
	Необязательные параметры:
	employee_id - id сотрудника, который просматривает это задание (для фиксации в историю по заданию)
	is_without_comments - флаг - не выводить комментарии в информации по заданию
*/
function getTask($taskId){
	return array(
		"key"=>key,
		"cat"=>"task",
		"action"=>"show",
		"is_without_comments"=>"1",
		"id"=>$taskId
	);
}

function getTaskType(){
	return array(
		"key"=>key,
		"cat"=>"task",
		"action"=>"get_catalog_type"
	);
}

function getTimesheet(){
	GLOBAL $dateFrom,$dateTo;
	return array(
		"key"=>key,
		"cat"=>"employee",
		"action"=>"get_timesheet_data",
		"date_from"=>$dateFrom,
		"date_to"=>$dateTo,
		"employee_id"=>""
	);
}

// TODO переписать для атоматического добавления 'id' в массив при выборе города
function getDivision(){
	GLOBAL $podr;
	switch ($podr) {
		case 'Алчевск':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"1,9,10,12,20,23"
			);
		break;

		case 'Зоринск':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"17"
			);
		break;

		case 'Кировск':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"16"
			);
		break;

		case 'Комиссаровка':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"18"
			);
		break;

		case 'Стаханов':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"15"
			);
		break;

		case 'Петровское':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"14"
			);
		break;

		// для всех подразделений
		case 'm3x':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"1,9,10,12,14,15,16,17,18,20,23"
			);
		break;

	}
}

function getEmployees(){
	return array(
		"key"=>key,
		"cat"=>"employee",
		"action"=>"get_data",
		"id"=>""
	);
}

function getEmployee($id){
	return array(
		"key"=>key,
		"cat"=>"employee",
		"action"=>"get_data",
		"id"=>$id
	);
}

// TODO - автоматизировать инициализацию начальными значениями
$ktuALL = array('Алчевск_Монтажники' => 0, 'Строители-Алчевск' => 0, 'M@trix-Зоринск' => 0, 'M@trix-Кировск' => 0, 'M@trix-Комиссаровка' => 0, 'M@trix-Стаханов' => 0, 'M@trix-Петровское' => 0);

function getCatalogType(): array
{
    static $catalog = null;
    if ($catalog === null) {
        $raw = httpPost(getTaskType());
        $catalog = json_decode((string) $raw, true)['Data'] ?? [];
    }
    return $catalog;
}

function getTimeSheetData(): array
{
    static $sheet = null;
    if ($sheet === null) {
        $raw = httpPost(getTimesheet());
        $sheet = json_decode((string) $raw, true)['data'] ?? [];
    }
    return $sheet;
}

function getEmployeesList(){
	GLOBAL $employees, $podr;
	getSettings();  // загружает оклады/ставки из БД

    $dblite = new SQLite3(SQL_DB);
    $dblite->busyTimeout(5000);
    $dblite->exec('PRAGMA journal_mode=WAL');

    // ─── фильтр по городу ───────────────────────────
    if ($podr == "m3x") {
        $res = $dblite->query('SELECT * FROM employees WHERE city != ""');
    } else {
        $stmt = $dblite->prepare('SELECT * FROM employees WHERE city = :city');
        $stmt->bindValue(':city', $podr, SQLITE3_TEXT);
        $res = $stmt->execute();
    }

    if (!$res) {
        // Обработка ошибки запроса (опционально, для отладки)
        error_log("SQLite Error: " . $dblite->lastErrorMsg());
        $dblite->close();
        return;
    }

    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $div = $row['division'] ?? 'Неизвестный отдел';
        $fio = $row['fio'] ?? 'Неизвестный сотрудник';

        $employees[$div][$fio]['id']               = (int) $row['id'];
        $employees[$div][$fio]['Ф.И.О']            = $fio;
        $employees[$div][$fio]['otdel']            = $div;
        $employees[$div][$fio]['division_id']      = (int) ($row['division_id'] ?? 0);
        // Поля, которые будут рассчитаны позже (инициализируем нулями)
        $employees[$div][$fio]['Заявки']           = 0;
        $employees[$div][$fio]['% КТУ Личный']     = 0;
        $employees[$div][$fio]['КТУ Количество']   = 0;
        $employees[$div][$fio]['Часы']             = 0;
        $employees[$div][$fio]['Часы Сверхурочные'] = 0;
        $employees[$div][$fio]['Часы Выходные']    = 0;
        $employees[$div][$fio]['ЗП Часы']          = 0;
        $employees[$div][$fio]['ЗП КТУ']           = 0;
        $employees[$div][$fio]['Строительные работы'] = 0;
        $employees[$div][$fio]['Итого']            = 0;
        $employees[$div][$fio]['На руки']          = 0;
        
        // Значения из БД (уже загружены в getSettings, но гарантируем наличие)
        $employees[$div][$fio]['Ставка']       = (float) ($row['stavka'] ?? 0);
        $employees[$div][$fio]['Ставка час']   = (float) ($row['stavka_chas'] ?? 0);
        $employees[$div][$fio]['Надбавки']     = (float) ($row['nadbavka'] ?? 0);
        $employees[$div][$fio]['Доп.премия']   = (float) ($row['dop_premia'] ?? 0);
        $employees[$div][$fio]['Аванс']        = (float) ($row['avans'] ?? 0);
        $employees[$div][$fio]['Отпускные']    = (float) ($row['otpusknye'] ?? 0);
        $employees[$div][$fio]['Больничные']   = (float) ($row['bolnichnye'] ?? 0);
        $employees[$div][$fio]['Удержания']    = (float) ($row['uderjanie'] ?? 0);
        $employees[$div][$fio]['Оклад']        = (float) ($row['oklad'] ?? 0);
        
        // Telegram chat_id
        $tg_chat_id = preg_replace('/[^0-9]/', '', (string)($row['msg_chat_id'] ?? ''));
        $employees[$div][$fio]['messenger_chat_id'] = ($tg_chat_id !== '') ? $tg_chat_id : null;

        // E-Mail
        $emailRaw = isset($row['email']) ? trim((string)$row['email']) : '';
        $employees[$div][$fio]['email'] = ($emailRaw !== '') ? $emailRaw : null;
    
    }
    $dblite->close();
}

/**
 * Получение и обработка задач одного сотрудника (поштучные запросы к API).
 * UserSide API не поддерживает батчинг employee_id через запятую,
 * поэтому делаем 2 запроса на сотрудника.
 */
function getKTU($employeeID, $surname, $division){
	$employeeID = (int) $employeeID;
    if ($employeeID <= 0) {
        return;
    }

	GLOBAL $employees, $catalogType;

	// Ленивая загрузка каталога типов
    if (!is_array($catalogType) || empty($catalogType)) {
        $raw = httpPost(getTaskType());
        $catalogType = json_decode((string) $raw, true)['Data'] ?? [];
    }

	// Строительные типы заявок (static — инициализируется один раз)
	// array_flip() позволяет проверять наличие через isset() быстрее, чем in_array().
	static $stroikaTypes = null;
	if ($stroikaTypes === null) {
		$stroikaTypes = array_flip([65,74,19,6,24,31,23,21,20,75,77,82]);	//номера строительных заявок
	}

	if (!isset($employees[$division][$surname])) {
        $employees[$division][$surname] = [];
    }

	// Работаем через ссылку, чтобы не писать каждый раз длинный путь.
    $emp = &$employees[$division][$surname];

	// ─── Запрос 1: Список задач сотрудника ────────────────────
    $listData = ktuApiRequest(getTaskList($employeeID));	// ← API запрос на сотрудника
	$ids = $listData['list'] ?? '';

	// На случай, если API вдруг вернет массив, а не строку.
    if (is_array($ids)) {
        $ids = implode(',', $ids);
    }

	// Убираем лишние запятые и пробелы по краям.
    $ids = trim((string)$ids, " \t\n\r\0\x0B,");

	// Вариант ответа:
    // {"list":"","count":0,"Result":"OK"}
    if ($ids === '') {
        return;
    }

	// ─── Запрос 2: Детали задач ───────────────────────────────
    $taskData = ktuApiRequest(getTask($ids));			// ← API запрос на сотрудника
    $tasks = $taskData['Data'] ?? [];

    if (!is_array($tasks) || empty($tasks)) {
        return;
    }

    // Если API вернул одну заявку как объект, а не массив,
    // приводим к массиву, чтобы дальше обрабатывать одинаково.
    if (isset($tasks['type'])) {
        $tasks = [$tasks];
    }

	// ─── Обработка задач ──────────────────────────────────────
	$zayavki = [];
    $stroikaSum = 0.0;
	$catalog = is_array($catalogType) ? $catalogType : [];

	foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }

        $typeId = (int)($task['type']['id'] ?? 0);

        if ($typeId <= 0) {
            continue;
        }

        // Обычная заявка.
        if (!isset($stroikaTypes[$typeId])) {
            $typeName = (string)($task['type']['name'] ?? '');

            // Считаем количество заявок по типу.
            // Это потом позволит обновить динамические столбцы одним циклом.
            if ($typeName !== '') {
                $zayavki[$typeName] = ($zayavki[$typeName] ?? 0) + 1;
            }
            continue;
        }

        // Строительная заявка.
        $amount = $catalog[$typeId]['amount'] ?? 0;
        $volume = $task['volumeCustom'] ?? 0;
        $staff = $task['staff']['employee'] ?? [];

        // Если staff.employee пришел не массивом.
        if (!is_array($staff)) {
            $staff = ($staff === null || $staff === '') ? [] : [$staff];
        }

        $staffCount = count($staff);
        // Защита от деления на 0.
        if ($staffCount > 0) {
            $stroikaSum += round(($amount * $volume) / $staffCount, 2);
        }
    }

    // ─── Обновляем данные сотрудника ──────────────────────────
	if (!empty($zayavki)) {
        // Обновляем динамические столбцы по названиям типов заявок.
        // Например: $employees[$division][$surname]['Тип заявки'] += количество.
        foreach ($zayavki as $workName => $count) {
            $emp[$workName] = ($emp[$workName] ?? 0) + $count;
        }

        // Общее количество обычных заявок.
        $emp['Заявки'] = array_sum($zayavki);

        // КТУ по classifier (кроме строителей)
		// Для строителей КТУ считается отдельно по часам.
        if ($division !== 'Строители-Алчевск') {
            $classifier = $GLOBALS['classifier'] ?? [];
            $ktuCount = 0;

            foreach ($zayavki as $workName => $count) {
                if (isset($classifier[$workName])) {
                    $ktuCount += $classifier[$workName] * $count;
                } else {
                    // Если нужно видеть, для какого типа работы нет КТУ:
                    // error_log("getKTU: нет КТУ для '{$workName}', подразделение {$division}");
                }
            }

            if ($ktuCount > 0) {
                $emp['КТУ Количество'] = ($emp['КТУ Количество'] ?? 0) + $ktuCount;
                $GLOBALS['ktuALL'][$division] =
                    ($GLOBALS['ktuALL'][$division] ?? 0) + $ktuCount;
            }
        }
    }

    if ($stroikaSum != 0.0) {
        // Название ключа оставляем как в текущей системе:
        // "Строительные работы", чтобы не ломать шаблоны/экспорт.
        $emp['Строительные работы'] =
            ($emp['Строительные работы'] ?? 0) + $stroikaSum;
    }
}

function timesheet($employeeID, $surname, $division){
	//GLOBAL $employees, $timeSheet;
	GLOBAL $employees;

	$employeeID = (int) $employeeID;
    if ($employeeID <= 0) {
        return;
    }

	$timeSheet = getTimeSheetData();  // ← ленивая загрузка

	// 1. Пропускаемые коды дней через array_flip — isset() быстрее in_array()
    static $days = null;
    if ($days === null) {
        $days = array_flip([994, 995, 996, 997, 998, 999]);
    }
	
	$timeSheetUser = array_column($timeSheet, $employeeID);
	
	// 2. Локальные счётчики вместо многократного обращения к глобальному массиву
    $hNormal  = 0;
    $hOver    = 0;
    $hWeekend = 0;

	foreach ($timeSheetUser as $chasy) {
        if (!is_array($chasy)) {
            continue;
        }
        foreach ($chasy as $key => $value) {
            // Защита от нечисловых значений и пропуск специальных кодов
            if (!is_numeric($value) || isset($days[(int) $value])) {
                continue;
            }
            // Явное приведение к float — убирает "non-numeric" warning
            $val = (float) $value;
            switch ($key) {
                case '1': $hNormal  += $val; break;
                case '2': $hOver    += $val; break;
                case '3': $hWeekend += $val; break;
            }
        }
    }

	// 3. Ссылка на сотрудника — пишем один раз в конце
    $emp = &$employees[$division][$surname];

    $emp['Часы']              += $hNormal;
    $emp['Часы Сверхурочные'] += $hOver;
    $emp['Часы Выходные']     += $hWeekend;

	// 4. Расчёт ЗП Часы с защитой от отсутствующих ключей
    $rate     = (float) ($emp['Ставка час'] ?? 0);
    $chasyCfg = $GLOBALS['chasy'] ?? [];

    $emp['ЗП Часы'] =
        ($rate * $hNormal  * ($chasyCfg['Обычные']     ?? 0)) +
        ($rate * $hOver    * ($chasyCfg['Сверхурочные'] ?? 0)) +
        ($rate * $hWeekend * ($chasyCfg['Выходной']     ?? 0));
}

/**
 * Обработка задач одного сотрудника (без API-запросов).
 */
function processEmployeeTasks(int $employeeID, string $surname, string $division, array $tasks): void
{
    GLOBAL $employees;

    if ($employeeID <= 0 || empty($tasks)) {
        return;
    }

    static $stroikaTypes = null;
    if ($stroikaTypes === null) {
        $stroikaTypes = array_flip([65, 74, 19, 6, 24, 31, 23, 21, 20, 75, 77, 82]);
    }

    if (!isset($employees[$division][$surname])) {
        $employees[$division][$surname] = [];
    }

    $emp = &$employees[$division][$surname];

    $zayavki = [];
    $stroikaSum = 0.0;
    $catalog = getCatalogType();  // ← используем lazy loading функцию

    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }

        $typeId = (int) ($task['type']['id'] ?? 0);
        if ($typeId <= 0) {
            continue;
        }

        // Обычная заявка
        if (!isset($stroikaTypes[$typeId])) {
            $typeName = (string) ($task['type']['name'] ?? '');
            if ($typeName !== '') {
                $zayavki[$typeName] = ($zayavki[$typeName] ?? 0) + 1;
            }
            continue;
        }

        // Строительная заявка
        $amount = (float) ($catalog[$typeId]['amount'] ?? 0);
        $volume = (float) ($task['volumeCustom'] ?? 0);
        $staff = $task['staff']['employee'] ?? [];

        if (!is_array($staff)) {
            $staff = ($staff === null || $staff === '') ? [] : [$staff];
        }

        $staffCount = count($staff);
        if ($staffCount > 0 && $amount != 0.0) {
            $stroikaSum += round(($amount * $volume) / $staffCount, 2);
        }
    }

    if (!empty($zayavki)) {
        foreach ($zayavki as $workName => $count) {
            $emp[$workName] = ($emp[$workName] ?? 0) + $count;
        }

        $emp['Заявки'] = ($emp['Заявки'] ?? 0) + array_sum($zayavki);

        // КТУ по classifier (кроме строителей)
        if ($division !== 'Строители-Алчевск') {
            $classifier = $GLOBALS['classifier'] ?? [];
            $ktuCount = 0;

            foreach ($zayavki as $workName => $count) {
                if (isset($classifier[$workName])) {
                    $ktuCount += $classifier[$workName] * $count;
                }
            }

            if ($ktuCount > 0) {
                $emp['КТУ Количество'] = ($emp['КТУ Количество'] ?? 0) + $ktuCount;
                $GLOBALS['ktuALL'][$division] =
                    ($GLOBALS['ktuALL'][$division] ?? 0) + $ktuCount;
            }
        }
    }

    if ($stroikaSum != 0.0) {
        $emp['Строительные работы'] =
            ($emp['Строительные работы'] ?? 0) + $stroikaSum;
    }
}

// NEW function solary
function new_calc_solary($division)
{
	GLOBAL $employees;

	if (empty($employees[$division])) {
        return;
    }

	// Гарантируем инициализацию $ktuALL
    if (!isset($GLOBALS['ktuALL'][$division])) {
        $GLOBALS['ktuALL'][$division] = 0;
    }

    // ─── Получаем division_id из первого сотрудника ─────────
    $divisionId = 0;
    foreach ($employees[$division] as $data) {
        $divisionId = (int) ($data['division_id'] ?? 0);
        if ($divisionId > 0) {
            break;
        }
    }

    // ─── Пакетное получение задач подразделения ─────────────
    $allTasks = getTasksByDivision($divisionId);
    $tasksByEmployee = distributeTasksByEmployee($allTasks, $employees[$division]);

    // ─── Обработка задач для каждого сотрудника ─────────────
    if ($division === "Строители-Алчевск") {
        foreach ($employees[$division] as $surname => $data) {
            $empId = (int) ($data['id'] ?? 0);
            if ($empId <= 0) continue;

            $empTasks = $tasksByEmployee[$empId] ?? [];
            processEmployeeTasks($empId, $surname, $division, $empTasks);
            timesheet($empId, $surname, $division);

            // КТУ по часам для строителей
            $employees[$division][$surname]['КТУ Количество'] =
                ($employees[$division][$surname]['Часы'] ?? 0) +
                ($employees[$division][$surname]['Часы Сверхурочные'] ?? 0) +
                ($employees[$division][$surname]['Часы Выходные'] ?? 0);

            $GLOBALS['ktuALL'][$division] += 
                $employees[$division][$surname]['КТУ Количество'];
        }
    } else {
        foreach ($employees[$division] as $surname => $data) {
            $empId = (int) ($data['id'] ?? 0);
            if ($empId <= 0) continue;

            $empTasks = $tasksByEmployee[$empId] ?? [];
            processEmployeeTasks($empId, $surname, $division, $empTasks);
        }
    }

    // ─── Второй проход: расчёт процентов и итогов ─────────────
    $ktuAllDiv = $GLOBALS['ktuALL'][$division] ?? 0;

    foreach ($employees[$division] as $surname => $data) {
        $empId = (int) ($data['id'] ?? 0);

        if ($division !== "Строители-Алчевск" && $empId > 0) {
            timesheet($empId, $surname, $division);
        }

        // % КТУ Личный
        $ktuCount = $employees[$division][$surname]['КТУ Количество'] ?? 0;
        if ($ktuAllDiv > 0 && $ktuCount > 0) {
            $employees[$division][$surname]['% КТУ Личный'] =
                round(($ktuCount / $ktuAllDiv) * 100, 2);
        } else {
            $employees[$division][$surname]['% КТУ Личный'] = 0;
        }

        // ЗП КТУ
        $fondKtu = $GLOBALS['fond_ktu'][$division] ?? 0;
        $pct = $employees[$division][$surname]['% КТУ Личный'] ?? 0;
        $employees[$division][$surname]['ЗП КТУ'] =
            floor($fondKtu * ($pct / 100));

        // Итого
        $employees[$division][$surname]['Итого'] =
            ($employees[$division][$surname]['Ставка'] ?? 0) +
            ($employees[$division][$surname]['ЗП Часы'] ?? 0) +
            ($employees[$division][$surname]['ЗП КТУ'] ?? 0) +
            ($employees[$division][$surname]['Строительные работы'] ?? 0) +
            ($employees[$division][$surname]['Надбавки'] ?? 0) +
            ($employees[$division][$surname]['Доп.премия'] ?? 0) +
            ($employees[$division][$surname]['Отпускные'] ?? 0) +
            ($employees[$division][$surname]['Больничные'] ?? 0) -
            ($employees[$division][$surname]['Удержания'] ?? 0);

        // На руки
        $employees[$division][$surname]['На руки'] =
            ($employees[$division][$surname]['Итого'] ?? 0) -
            ($employees[$division][$surname]['Аванс'] ?? 0);
    }

    ksort($employees[$division]);
}

function main(){
	GLOBAL $podr;
	getEmployeesList();
	if (in_array($podr, arr_city)){
		$dblite = new SQLite3(SQL_DB);
		// тут пробегаем по всем подразделениям или только по одному городу
		if($podr == "m3x"){$res = $dblite->query("SELECT * FROM division_list WHERE city != ''");}
		else {
            $stmt = $dblite->prepare("SELECT * FROM division_list WHERE city = :city");
            $stmt->bindValue(':city', $podr, SQLITE3_TEXT);
            $res = $stmt->execute();
        }

		while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
			$rowDivision[] = $row['division_name'];
		}
		$dblite->close();

	}else{
		exit("Error: bad_podr: ".$podr);
	}

	foreach ($rowDivision as $row) {
		new_calc_solary($row);
	}
}

// вспомогательная функция для getKTU()
function ktuApiRequest(array $params): array
{
    $raw = httpPost($params);

    if ($raw === false || $raw === null || $raw === '') {
        return [];
    }

    $data = json_decode((string)$raw, true);

    return is_array($data) ? $data : [];
}

// пункт настроек оплаты сотруднику
function setPeople($division){
	GLOBAL $smarty;
	//echo($division." <br>");
	$dblite = new SQLite3(SQL_DB);
	$ktuFondDivision = $dblite->query("SELECT * FROM fond_ktu WHERE otdel = '$division'");

	$resKtu[] = $ktuFondDivision->fetchArray(SQLITE3_ASSOC);
	$smarty -> assign('resKtu', $resKtu);

	$res = $dblite->query("SELECT * FROM employees WHERE division = '$division' ORDER BY fio ASC");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$resPeople[] = $row;
	}
	$dblite->close();

	$smarty -> assign('resPeople', $resPeople);
	$smarty -> display('api/setPeople.tpl'); //публикуем шаблон
}

// пункт настроек "Фонд КТУ"
function setFondKTU(){
    global $smarty;

	$dblite = new SQLite3(SQL_DB);
	$res = $dblite->query("SELECT * FROM fond_ktu ORDER BY otdel ASC");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$resKtu[] = $row;
	}
    $dblite->close();

    $smarty -> assign('resKtu', $resKtu);
	$smarty -> display('api/setFondKTU.tpl'); //публикуем шаблон

}

// пункт настроек "КТУ Работы"
function setKTU(){
    global $smarty;

	$dblite = new SQLite3(SQL_DB);
	$res = $dblite->query("SELECT * FROM ktu");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$resKtu[] = $row;
	}
	$dblite->close();

    $smarty -> assign('resKtu', $resKtu);
	$smarty -> display('api/setKTU.tpl'); //публикуем шаблон

}

// пункт настроек "Часы"
function setChasy(){
	global $smarty;

	$dblite = new SQLite3(SQL_DB);
	$res = $dblite->query("SELECT * FROM chasy");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$rowChasy[] = $row;
	}

	$dblite->close();

	$smarty -> assign('rowChasy', $rowChasy);
	$smarty -> display('api/setChasy.tpl'); //публикуем шаблон
}

function settings($set){
	switch ($set) {
		case 'fondKTU':
			setFondKTU();
			break;
		case 'ktu':
			setKTU();
			break;
		case 'chasy':
			setChasy();
			break;
		case (in_array($set, arr_setings_division)):
			setPeople($set);
			break;
	}
}

if (!defined('ARCHIVE_DIR')) {
    define('ARCHIVE_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'archive');
}
/**
 * Возвращает папку архивов и создаёт её, если её ещё нет.
 */
function getArchiveDir(): string
{
    if (!is_dir(ARCHIVE_DIR)) {
        @mkdir(ARCHIVE_DIR, 0775, true);
    }

    return ARCHIVE_DIR;
}

/**
 * Очищает название города/подразделения для безопасного имени файла.
 */
function sanitizeArchiveCity(string $city): string
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
 * Возвращает полный путь к файлу архива.
 *
 * Формат:
 * /archive/YYYY-MM-DD_подразделение.json
 */
function getArchiveFilePath(string $date = '', string $city = ''): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    if ($city === '') {
        $city = $GLOBALS['podr'] ?? ($_POST['city'] ?? '');
    }

    return getArchiveDir() . DIRECTORY_SEPARATOR . $date . '_' . sanitizeArchiveCity($city) . '.json';
}

/**
 * Сохраняет текущий расчёт в архив.
 *
 * Вызывать после main(), до excel()/html().
 */
function saveCalcArchive(): bool
{
    global $employees, $headers, $classifier, $podr, $dateFrom, $dateTo;

    $city = (string)($podr ?? ($_POST['city'] ?? ''));

    $payload = [
        'meta' => [
            'version'    => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'podr'       => $city,
            'dateFrom'   => $dateFrom ?? '',
            'dateTo'     => $dateTo ?? '',
        ],
        'headers'    => $headers ?? [],
        'classifier' => $classifier ?? [],
        'employees'  => $employees ?? [],
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {
        error_log('saveCalcArchive json_encode error: ' . json_last_error_msg());
        return false;
    }

    $file = getArchiveFilePath(date('Y-m-d'), $city);

    $result = file_put_contents($file, $json, LOCK_EX);

    if ($result === false) {
        error_log('saveCalcArchive file_put_contents error: ' . $file);
        return false;
    }

    return true;
}

/**
 * Читает JSON-архив из файла.
 */
function readCalcArchiveFile(string $file): array
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

/**
 * Загружает архив по дате и городу.
 */
function loadCalcArchiveByDateCity(string $date, string $city): array
{
    return readCalcArchiveFile(getArchiveFilePath($date, $city));
}

/**
 * Загружает архив из загруженного пользователем файла.
 */
function loadCalcArchiveFromUpload(): array
{
    if (empty($_FILES['archive_file'])) {
        return [];
    }

    if ($_FILES['archive_file']['error'] !== UPLOAD_ERR_OK) {
        return [];
    }

    $ext = strtolower(pathinfo($_FILES['archive_file']['name'], PATHINFO_EXTENSION));

    if ($ext !== 'json') {
        return [];
    }

    // Ограничим размер архива, например 20 МБ
    if ($_FILES['archive_file']['size'] > 20 * 1024 * 1024) {
        return [];
    }

    return readCalcArchiveFile($_FILES['archive_file']['tmp_name']);
}

/**
 * Подгружает служебные справочники из БД, если их нет в архиве.
 */
function loadArchiveDictionaries(): void
{
    global $classifier, $headers;

    try {
        $dblite = new SQLite3(SQL_DB);
        $dblite->busyTimeout(5000);
        $dblite->exec('PRAGMA journal_mode=WAL');

        if (empty($classifier)) {
            $classifier = [];

            $res = $dblite->query('SELECT * FROM ktu');

            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $classifier[$row['type_of_work']] = $row['work_ktu'];
            }
        }

        if (empty($headers)) {
            $headers = [];

            $res = $dblite->query('SELECT * FROM table_headers');

            while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                $headers[] = $row['header_name'];
            }
        }

        $dblite->close();
    } catch (Throwable $e) {
        error_log('loadArchiveDictionaries: ' . $e->getMessage());
    }
}

/**
 * Применяет загруженный архив к глобальным переменным,
 * которые используют excel() и html().
 */
function applyCalcArchive(array $payload): bool
{
    global $employees, $headers, $classifier, $podr, $dateFrom, $dateTo, $created_at;

    if (!isset($payload['employees']) || !is_array($payload['employees'])) {
        return false;
    }

    if (!empty($payload['meta']['podr'])) {
        $podr = $payload['meta']['podr'];
    } elseif (empty($podr)) {
        $podr = $_POST['city'] ?? '';
    }

    if (!empty($payload['meta']['dateFrom'])) {
        $dateFrom = $payload['meta']['dateFrom'];
    }

    if (!empty($payload['meta']['dateTo'])) {
        $dateTo = $payload['meta']['dateTo'];
    }

    if (!empty($payload['meta']['created_at'])) {
        $created_at = $payload['meta']['created_at'];
    }

    if (empty($dateFrom)) {
        $dateFrom = date("Y-m-d 00:01:00", strtotime('first day of this month'));
    }

    if (empty($dateTo)) {
        $dateTo = date("Y-m-d 23:59:00");
    }

    $employees = $payload['employees'];

    if (isset($payload['headers']) && is_array($payload['headers'])) {
        $headers = $payload['headers'];
    }

    if (isset($payload['classifier']) && is_array($payload['classifier'])) {
        $classifier = $payload['classifier'];
    }

    // Если вдруг архив старый и не содержит headers/classifier,
    // попробуем достать их из базы.
    if (empty($headers) || empty($classifier)) {
        loadArchiveDictionaries();
    }

    return true;
}

switch ($_POST['nastr']) {
	case 'settings':
		settings($_POST['tip']);
		break;

	case 'timesheet':
        timesheet_page();
        break;

	case 'zarplata':
        $startTime = microtime(true);
		main();
        saveCalcArchive();		// Сохраняем расчёт в архив
        // Явно запрещаем редактирование аванса при первичном расчёте
        $GLOBALS['is_editable_avans'] = false;
		excel();
		html();
        $elapsed = round(microtime(true) - $startTime, 3);
        echo "<h3 style='color:green;'>Расчёт завершен за {$elapsed} сек.</h3>";
		break;
	
	// Загрузка архива по дате и городу из папки /archive/
	case 'zarplata_from_archive':
        global $archiveDate;
        // Получаем дату архива из POST (она корректно приходит из формы)
        $archiveDate = $_POST['archive_date'] ?? date('Y-m-d');
        $payload = loadCalcArchiveByDateCity(
            $_POST['archive_date'] ?? date('Y-m-d'),
            $_POST['city'] ?? ''
        );

        if (!applyCalcArchive($payload)) {
            exit('Error: archive not found or empty');
        }

        // ══════════════════════════════════════════════
        // РАСЧЕТ ФЛАГА is_editable_avans ДЛЯ АРХИВА
        // ══════════════════════════════════════════════
        
        // Вариант А: Разрешаем редактирование, если дата архива 
        // отличается от сегодняшней не более чем на edit_delta дней
        $now = strtotime(date('Y-m-d'));
        $calcDate = strtotime($archiveDate);
        $diffDays = abs($now - $calcDate) / 86400;
        $GLOBALS['is_editable_avans'] = ($diffDays <= edit_delta);
        //$GLOBALS['is_editable_avans'] = true;
        /* 
        // Вариант Б (если логика должна быть такой же, как при первичном расчете):
        // Разрешаем редактирование только если текущий день месяца <= edit_delta
        // $GLOBALS['is_editable_avans'] = ((int)date('j') <= edit_delta); 
        */
        // ══════════════════════════════════════════════

        html();
        break;

    case 'generate_excel':
        // Загружаем данные из архивного JSON, используя ту же логику, что и для отображения
        $archiveDate = $_POST['archive_date'] ?? date('Y-m-d');
        $city = $_POST['city'] ?? '';
        
        if (empty($city) || empty($archiveDate)) {
            echo json_encode(['success' => false, 'error' => 'Не указаны параметры']);
            exit;
        }
        
        // Используем функцию загрузки архива. 
        // Она корректно ищет файл по шаблону YYYY-MM-DD_city.json
        $payload = loadCalcArchiveByDateCity($archiveDate, $city);
        
        // applyCalcArchive автоматически установит глобальные переменные:
        // $employees, $headers, $classifier, $podr, $dateFrom, $dateTo
        if (!$payload || !applyCalcArchive($payload)) {
            echo json_encode(['success' => false, 'error' => 'Архив не найден или повреждён']);
            exit;
        }
        
        // Генерируем Excel (функция excel() возьмёт данные из глобальных переменных)
        excel();
        
        echo json_encode(['success' => true]);
        break;
}

?>
