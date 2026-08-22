<?php 
require_once 'conf.php';

/*
    function initApi(){
        
    }

    $URL = 
    $api_key = 
*/

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
        //"date_finish_from" => $dateFrom,
		//"date_finish_to" => $dateTo,
        //"division_id" => $division_id,
        "state_id" => "2"
    ];
