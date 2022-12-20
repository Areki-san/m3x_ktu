<?php 
require 'conf.php';


function function_alert($message) {
    echo "<script>alert('$message');</script>";
}
//проверим существует ли БД
//если БД нет то создадим её и таблицу в ней msages
    if(!file_exists(SQL_DB)){
        function_alert("База данных не найдена, будет создана новая проверьте настройки<br>");
        $db = new SQLite3(SQL_DB);
        $create_table_db = [
            "CREATE TABLE api_config (
	            URL     TEXT,
	            api_key TEXT
            )",
            "CREATE TABLE chasy (
	            tip     TEXT NOT NULL UNIQUE,
	            stavka  INTEGER NOT NULL
            )",
            "CREATE TABLE division_list (
                id              INTEGER PRIMARY KEY NOT NULL UNIQUE, 
                division_name   TEXT NOT NULL UNIQUE,
                is_set          INTEGER NOT NULL DEFAULT 0)",
            "CREATE TABLE employee_inf (
	            name    TEXT NOT NULL UNIQUE
            )",
            "CREATE TABLE employees (
	            id          INTEGER PRIMARY KEY NOT NULL UNIQUE,
	            fio         TEXT NOT NULL UNIQUE,
	            stavka_chas INTEGER NOT NULL DEFAULT 0,
	            stavka      INTEGER NOT NULL DEFAULT 0,
	            nadbavka	INTEGER NOT NULL DEFAULT 0,
	            division	TEXT NOT NULL,
                division_id INTEGER NOT NULL DEFAULT 0,
	            city        TEXT NOT NULL
            )",
            "CREATE TABLE fond_ktu (
	            otdel   TEXT NOT NULL UNIQUE,
	            ktu     INTEGER NOT NULL
            )",
            "CREATE TABLE hours (
	            type    TEXT NOT NULL UNIQUE,
	            rate    INTEGER NOT NULL
            )",
            "CREATE TABLE ktu (
	            work_id         TEXT,
	            type_of_work    TEXT NOT NULL UNIQUE,
	            work_ktu        INTEGER NOT NULL
            )",
            "CREATE TABLE premiya (
	            id          INTEGER NOT NULL,
	            surname     TEXT NOT NULL,
	            summa       INTEGER NOT NULL,
	            date        INTEGER NOT NULL
            )",
            "CREATE TABLE shtrafy (
	            id      INTEGER NOT NULL,
	            surname	TEXT NOT NULL,
	            summa	INTEGER NOT NULL,
	            date	INTEGER NOT NULL
            )",
            "CREATE TABLE sqlite_sequence(name,seq)",
            "CREATE TABLE table_headers (
	            id          INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
	            header_name TEXT NOT NULL
            )"
    ];
        foreach ($create_table_db as $query){
            $db->exec($query);
        }
        //$db->query($sql);
        function_alert("Новая база данных создана<br>");
    }else{
//если бд есть то просто подключ. к ней
        if ($is_debug){echo "База данных найдена<br>";}
        $db = new SQLite3(SQL_DB);
    }
/*
    function initApi(){
        
    }

    $URL = 
    $api_key = 
*/
    $dateFrom = date("Y-m-d 00:00:00",strtotime('first day of this month'));
	$dateTo = date("Y-m-d 22:00:00", strtotime('now'));

    function usApiRequet($param, $id = null){
        $param["id"] = $id;
        $uri = URL.http_build_query($param);
        if (is_debug){echo "api request: ". $uri . "\n</br>\n";}
	    $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ch, CURLOPT_URL, $uri);
	    return json_decode(curl_exec($ch),true);
    }

    function getTaskList($division_id){
        $taskList = usApiRequet($userTaskList);
        return $taskList['list'];
    }

    //$param = array();
    //$main = usApiRequet($param);
    //echo $main . "\n";
    //echo "</br>\n";

// API методы:
    // API employee:
    // Назначение: Сотрудники
    // get_division_list : Список подразделений
    $get_division_list = [
	    "key" => api_key,
	    "cat" => "employee",
	    "action" => "get_division_list"
    ];
    // get_division : Получение информации о подразделении
    // Необязательные параметры:
    // id - ID подразделения (можно через запятую)
    $get_division = [
        "key" => api_key,
	    "cat" => "employee",
        "action" => "get_division"
        //"id" => $id
    ];

    // get_data
    // Описание: Получение информации о сотруднике
    // Необязательные параметры:
    // id - id сотрудника для выборки (можно через запятую)
    $get_data = [
        "key" => api_key,
	    "cat" => "employee",
        "action" => "get_data"
    ];

    // API task:
    // Назначение: Работа с заданиями
    // get_list : Список заданий (идентификаторы)
    $task_get_list = [
        "key" => api_key,
		"cat" => "task",
		"action" => "get_list",        
        "date_finish_from" => $dateFrom,
		"date_finish_to" => $dateTo,
        //"division_id" => $division_id,
        "state_id" => "2"
    ];

    // Список подразделений:
    if (is_debug){
        echo "<h4>Список подразделений:</h4>\n";
        echo "</br>\n";
    }    
    $main = usApiRequet($get_division_list);
    foreach($main['data'] as $id => $value){
//	    echo "$id : ".$value['name']."<br>";
        //Добавить запись
        $txt = $value['name'];
        $sql = "INSERT INTO division_list (id, division_name) VALUES ($id, '$txt')";
        $db->exec($sql);
    }

    $div_array = array();
    $res = $db->query('SELECT * FROM division_list');
    while ($row = $res->fetchArray()) {
        //echo "{$row['id']} {$row['is_set']} \n";
        if($row['is_set'] == 1){array_push($div_array, $row['id']);} // собираем id опрашиваемых подразделений в массив
    }

    $div_str = implode(",", $div_array);
    if($div_str == ''){$div_str = null;}
    //echo "id опрашиваемых подразделений: $div_str<br>";

    if (is_debug){
        echo "<h4>Список сотрудников:</h4>\n";
        print_r($div_str);
    }
    $main = usApiRequet($get_division, $div_str);
    $emp_array = array();
    foreach($main['data'] as $id => $value){
	    preg_match("(Алчевск|Стаханов|Петровское|Зоринск|Комисаровка|Кировск)", $value['comment'], $city);
        echo "$id : ".$value['name']. $city[0]."<br>";
        $division_id = $id;
        $division_name = $value['name'];

        foreach($value['staff']['work'] as $id => $vvalue){
            if (is_debug){echo "div_id:".$division_id." id: ".$vvalue['employee_id']." - ";}
            else{echo "id: ".$vvalue['employee_id']." - ";}
            array_push($emp_array, $vvalue['employee_id']); // собираем id сотрудников в массив
            $user = usApiRequet($get_data, $vvalue['employee_id']);
            print_r ($user['data'][$vvalue['employee_id']]['name']);
            echo "<br>";

            //Добавить запись
            $employee_id = $vvalue['employee_id'];
            $employee_name = $user['data'][$vvalue['employee_id']]['name'];
            $sql = "INSERT INTO employees (id, fio, division, city) VALUES ($employee_id, '$employee_name', '$division_name', '$city[0]')";
            $db->exec($sql);

            //Редактировать запись
            // структура обновления данных сотрудника 
            $em = ["division" => $division_name,
                    "division_id" => $division_id,
                    "city" => $city[0]];
            
            foreach($em as $key => $va){
                $sql = "UPDATE employees SET $key='$va' WHERE id=$employee_id";
                $query = $db->exec($sql);
                //echo $sql;
                //echo '</br>Количество изменённых строк: ', $db->changes();
            }
        }
    }
    // удаляем из базы несуществуюх сотрудников:
    $res = $db->query('SELECT id FROM employees');
    while ($row = $res->fetchArray()) {
        if(in_array($row['id'], $emp_array)){
            if (is_debug){echo "вхождение: ".$row['id']." найдено</br>";}
        }
        else{
            $sql = "DELETE FROM employees WHERE id=$row[id]";
            $query = $db->exec($sql);
            if (is_debug){echo "удалена запись id: ".$row['id']."</br>";}
        }
    }
    if (is_debug){
        echo "<br>emp_array:<br>";
        print_r(implode(",", $emp_array));
    }
/*
    //Редактировать запись
            $sql = "UPDATE employees SET stavka_chas=160 WHERE id=2";
            $query = $db->exec($sql);
            if ($query) {
                echo 'Количество изменённых строк: ', $db->changes();
            }

            $sql = "UPDATE employees SET division_id=160 WHERE id=2";
            $query = $db->exec($sql);
            if ($query) {
                echo 'Количество изменённых строк: ', $db->changes();
            }

    echo "</br>\n";
    echo "<h4>Закрытые заявки за текущий месяц:</h4>\n";
    //$main = usApiRequet($task_get_list);
    //print_r($main['list']);

    echo "</br>\n";
    $tasksList = getTaskList("20");
    foreach($tasksList as $value){
	    echo $value."<br>";
    }*/
    function_alert("База обновлена");
?>

