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
    
    /**API task:
     * Назначение: Работа с заданиями
     */
    /** get_list : Список заданий (идентификаторы)
     * Необязательные параметры (условия выборки):
     * author_employee_id - ID сотрудника - автора задания (можно через запятую)
     * closer_employee_id - ID сотрудника, который закрыл (выполнил) задание (можно через запятую)
     * customer_id - ID абонента (можно через запятую)
     * date_add_from - дата создания задания (с)
     * date_add_to - дата создания задания (до)
     * date_change_from - дата обновления задания (с)
     * date_change_to - дата обновления задания (до)
     * date_do_from - дата на которую назначено выполнение задания (с)
     * date_do_to - дата на которую назначено выполнение задания (до)
     * date_finish_from - дата выполнения задания (с)
     * date_finish_to - дата выполнения задания (до)
     * division_id - ID подразделения (можно через запятую)
     * division_id_with_staff - ID подразделения (в т.ч. с заданиями сотрудников этого подразделения) (можно через запятую)
     * employee_id - ID исполнителя (можно через запятую, используйте -1 для получения заданий без исполнителей)
     * is_expired - флаг - выводить только просроченные задания
     * house_id - ID дома работ
     * node_id - ID объекта размещения
     * state_id - ID статуса заданий (можно через запятую)
     * task_position - координаты задания (там где это возможно. В формате lat,lng. Напр: 40.245218,52.333384)
     * task_position_radius - радиус от task_position (в метрах)
     * type_id - ID типа заданий (можно через запятую)
     * watcher_employee_id - ID сотрудника-наблюдателя за заданием (можно через запятую)
     * order_by - поле для сортировки (возможные варианты: date_add, date_change, date_do, date_finish, state_id, type_id)
     * limit - лимит выборки записей
     * offset - смещение выборки
     */
    $task_get_list = [
        "key" => api_key,
		"cat" => "task",
		"action" => "get_list",        
        //"date_finish_from" => $dateFrom,
		//"date_finish_to" => $dateTo,
        //"division_id" => $division_id,
        "state_id" => "2"
    ];

    /** show : Информация о задании
     * Обязательные параметры:
     * id - id задания (можно через запятую)
     * Необязательные параметры:
     * employee_id - id сотрудника, который просматривает это задание (для фиксации в историю по заданию)
     * is_without_comments - флаг - не выводить комментарии в информации по заданию
     */
    $task_show = [
        "key" => api_key,
		"cat" => "task",
		"action" => "show",
		"is_without_comments" => "1",
		"id" => $taskId
    ];
		

/*
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
*/