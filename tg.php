<?php

const TOKEN	= '219392017:AAFWxVozC-o6X4u4wI74s_t7SHLSd6WuB7o';
const BASE_URL	= 'https://api.telegram.org/bot' . TOKEN . '/';

$time = date('H:m:s');
$log_error = "Нет Tg ID:\n";
$log_ok = "\nТабульки доставленны:\n";

// получаем данные из JSON файла
$string  = file_get_contents( __DIR__ . '/division_staff.json' );
$result_parse = json_decode($string, TRUE);
// выводим данные из JSON файла на страницу
//var_dump($result_parse);
//echo "</br>";

foreach ($result_parse as $emp_json => $value) {
	$FIO	= $value['Ф.И.О'];

	$Hourly_rate	= $value['Ставка час'];
	$Overheads	= $value['Надбавки'];
	$Rate		= $value['Ставка'];

	$Hours		= $value['Часы'];
	$Hours_Overtime	= $value['Часы Сверхурочные'];
	$Weekend_Hours	= $value['Часы Выходные'];
	$Salary_Hours	= $value['ЗП Часы'];

	$Tasks		= $value['Заявки'];
	$BE_Personal	= $value['% КТУ Личный'];
	$BE_SALARY	= $value['ЗП КТУ'];
	$Construction_work	= $value['Строительные работы'];

	$Total		= $value['Итого ЗП'];
	$Advance	= $value['Аванс'];
	$Additional_bonus	= $value['Премия'];
	$Withholdings	= $value['Удержания'];
	$Penalty	= $value['Штраф'];
	$On_hand	= $value['На руки'];

	$cash   = $value['На руки'];

	$str = 'Ф.И.О: ' . $FIO
		 . "\r\nСтавка час: " . "\t" . $Hourly_rate
		 . "\r\nНадбавки: " . "\t" . $Overheads
		 . "\r\nСтавка: " . "\t" . $Rate
		 . "\r\nЧасы: " . "\t" . $Hours
		 . "\r\nЧасы Сверхурочные: " . "\t" . $Hours_Overtime
		 . "\r\nЧасы Выходные: " . "\t" . $Weekend_Hours
		 . "\r\nЗП Часы: " . "\t" . $Salary_Hours;
	// если нет заявок то не выводим их в табульку
	if ($Tasks != 0)
		$str .=	"\r\nЗаявки: " . "\t" . $Tasks
		 . "\r\nКТУ Личный: " . "\t" . $BE_Personal . '%'
		 . "\r\nЗП КТУ: " . "\t" . $BE_SALARY;
	if ($Construction_work != 0)
		$str .=	"\r\nСтроительные работы: " . "\t" . $Construction_work;
	
	$str .=	"\r\nИтого ЗП: " . "\t" . $Total
		 . "\r\nАванс: " . "\t" . $Advance
		 . "\r\nПремия: " . "\t" . $Additional_bonus
		 . "\r\nУдержания: " . "\t" . $Withholdings
		 . "\r\nШтраф: " . "\t" . $Penalty
		 . "\r\nНа руки: " . "\t" . $On_hand;
	

	//echo $str ,'</br>';

	// TODO: 
	$chat_id = $value['messenger_chat_id'];
	if (is_null($chat_id)) {
		$log_error .= $FIO . " нет Tg chat_id\n";
	}
	else {
		$log_ok .= $FIO . " chat_id:" . $chat_id . "\n";
		sendRequest('sendMessage', ['chat_id' => $chat_id, 'text' => $str]);
		// вывод на страницу результата вызова функции
		//sendRequest('sendMessage', ['chat_id' => 899030452, 'text' => $str]);
		//var_dump ( sendRequest('sendMessage', ['chat_id' => 899030452, 'text' => $str]) );
	}
}


// вывод инфы о доставке табулек в тг
echo $log_error;
echo $log_ok;

$params = [
	'chat_id' => 899030452,
	'text' => 'ktu _ test _ tg' . $time
];

function sendRequest($method, $params = [])
{
	if (!empty($params)) {
		$url = BASE_URL . $method . '?' . http_build_query($params);
	}
	else {
		$url = BASE_URL . $method;
	}

	return json_decode(
		file_get_contents($url),
		JSON_OBJECT_AS_ARRAY
	);
}

//var_dump ( sendRequest('sendMessage', $params) );
