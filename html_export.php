<?php

// функция вывода расчёта зарплаты
function html(){
	GLOBAL $smarty, $is_editable_avans, $employees, $headers, $classifier, $podr, $dateFrom, $dateTo, $archiveDate;
	$stop = array("Оклад");
	//$stop = array("Аванс","Удержания","Штраф","Доп.премия","На руки");

	// сбор подробной информации о заявках
	foreach ($employees as $division_name => $division_staff) {
		foreach ($division_staff as $surname => $personal_info) {
			foreach ($headers as $array_head) {
				if(!in_array($array_head, $stop)){
					if($array_head == "Заявки" && $personal_info['Заявки'] > 0){
						$modal[] = $personal_info;
					}
				}
			}
		}
	}

	// привязываем данные к шаблону
	$smarty -> assign('employees', $employees);
	$smarty -> assign('headers', $headers);
	$smarty -> assign('stop', $stop);
    $smarty -> assign('dateFrom', date('Y-m-d', strtotime($dateFrom)));
    $smarty -> assign('dateTo', date('Y-m-d', strtotime($dateTo)));
    $smarty -> assign('arr_date', $archiveDate);
	$smarty -> assign('classifier', $classifier);
	$smarty -> assign('podr', $podr);
    // ПЕРЕДАЁМ ФЛАГ В ШАБЛОН
    $smarty->assign('is_editable_avans', $is_editable_avans);

	if(!empty($modal)){
		$smarty -> assign('modal', $modal);
	}

	$smarty -> display('api/html.tpl'); //публикуем шаблон
}

// функция вывода табеля
function timesheet_page()
{
    //GLOBAL $employees, $podr, $smarty, $dateFrom, $dateTo;
    GLOBAL $podr, $smarty, $dateFrom, $dateTo;

    // Получаем сотрудников, подразделения, настройки из ktu.db
    if (!in_array($podr, arr_city)) {
        exit("Error: bad_podr: " . $podr);
    }

    $dblite = new SQLite3('ktu.db');
    $dblite->busyTimeout(5000);
    $dblite->exec('PRAGMA journal_mode=WAL');

    // ─── 1. Подразделения выбранного города ──────────────────
    if ($podr == "m3x") {
        $res = $dblite->query("SELECT division_name FROM division_list WHERE city != ''");
    } else {
        $stmt = $dblite->prepare("SELECT division_name FROM division_list WHERE city = :city");
        $stmt->bindValue(':city', $podr, SQLITE3_TEXT);
        $res = $stmt->execute();
    }

    $rowDivision = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $rowDivision[] = $row['division_name'];
    }

    // ─── 2. Сотрудники из БД — только нужные поля ────────────
    // id и fio нужны для табеля, division для группировки
    if ($podr == "m3x") {
        $res = $dblite->query("SELECT id, fio, division, oklad FROM employees WHERE city != ''");
    } else {
        $stmt = $dblite->prepare("SELECT id, fio, division, oklad FROM employees WHERE city = :city");
        $stmt->bindValue(':city', $podr, SQLITE3_TEXT);
        $res = $stmt->execute();
    }

    $employeesDb = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $employeesDb[$row['division']][$row['fio']] = $row['id'];
    }
    $dblite->close();

    // ─── 3. Табель — ОДИН запрос к API на всех сразу ─────────
    $rawTimesheet = httpPost(getTimesheet());
    $timeSheet = json_decode((string) $rawTimesheet, true)['data'] ?? [];

    if (empty($timeSheet)) {
        // Если API не вернул табель — выводим пустой шаблон с предупреждением
        $smarty->assign('timesheet', []);
        $smarty->assign('timesheet_error', 'Не удалось получить данные табеля из API');
    }

    // ─── 4. Парсим только часы ───────────────────────────────
    $days = [994, 995, 996, 997, 998, 999]; // пропускаемые коды
    $timesheet_view = [];

    foreach ($rowDivision as $division) {
        if (empty($employeesDb[$division])) {
            continue;
        }

        foreach ($employeesDb[$division] as $fio => $empId) {
            $hNormal  = 0;
            $hOver    = 0;
            $hWeekend = 0;

            // Получаем записи табеля для конкретного сотрудника
            $userSheet = array_column($timeSheet, $empId);

            foreach ($userSheet as $dayData) {
                foreach ($dayData as $key => $value) {
                    if (in_array($value, $days)) continue;
                    switch ($key) {
                        case '1': $hNormal  += $value; break;
                        case '2': $hOver    += $value; break;
                        case '3': $hWeekend += $value; break;
                    }
                }
            }

            $timesheet_view[$division][$fio] = [
                'Часы'              => $hNormal,
                'Часы Сверхурочные' => $hOver,
                'Часы Выходные'     => $hWeekend,
                'Часы Всего'        => $hNormal + $hOver + $hWeekend,
            ];
        }

        if (!empty($timesheet_view[$division])) {
            ksort($timesheet_view[$division]);
        }
    }

    // ─── 5. Передаём в шаблон ────────────────────────────────
    $smarty->assign('podr', $podr);
    $smarty->assign('dateFrom', date('Y-m-d', strtotime($dateFrom)));
    $smarty->assign('dateTo', date('Y-m-d', strtotime($dateTo)));
    $smarty->assign('timesheet', $timesheet_view);
    $smarty->display('api/timesheet.tpl');
    exit;
}