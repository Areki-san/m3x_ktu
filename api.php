<?php
//$start = microtime(true);
require 'conf.php';
//error_reporting(E_ALL ^ E_NOTICE);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$employees = array();

$podr = $_POST['city'];
//$podr = "Алчевск";

//$_POST['nastr'] = 'zarplata';

function ex_alert($message) {
    echo "<script>alert('$message');</script>";
}

if(empty($_POST['from'])){
	$dateFrom = date("Y-m-d 00:01:00",strtotime('first day of this month'));
}else{
	$dateFrom = $_POST['from']." 00:01:00";
}
if(empty($_POST['to'])){
	$dateTo = date("Y-m-d 22:00:00", strtotime('now'));
}else{
	$dateTo = $_POST['to']." 22:00:00";
}

function getSettings(){
	GLOBAL $employees,$podr;
	$dblite = new SQLite3('ktu.db');

	$res = $dblite->query('SELECT * FROM ktu');
	while($row = $res->fetchArray(SQLITE3_ASSOC)){
		$GLOBALS['classifier'][$row['type_of_work']] = $row['work_ktu'];
	}

	$res = $dblite->query('SELECT * FROM chasy');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['chasy'][$row['tip']] = $row['stavka'];
	}

	$res = $dblite->query('SELECT * FROM fond_ktu');
	while($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['fond_ktu'][$row['otdel']] = $row['ktu'];
	}

	$stmt = $dblite->prepare('SELECT * FROM employees WHERE city=:city');
	$stmt->bindValue(':city', $podr, SQLITE3_TEXT);
	$res = $stmt->execute();
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$employees[$row['division']][$row['fio']]['Ставка час'] = $row['stavka_chas'];
		$employees[$row['division']][$row['fio']]['Надбавки'] = $row['nadbavka'];
		$employees[$row['division']][$row['fio']]['Ставка'] = $row['stavka'];
		//$employees[$row['division']][$row['fio']]['Ф.И.О'] = $row['fio'];
	}
	
	$res = $dblite->query('SELECT * FROM table_headers');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['headers'][] = $row['header_name'];
	}
	
	$res = $dblite->query('SELECT * FROM division_list');
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$GLOBALS['otdel'][] = $row['division_name'];
	}
}

function httpPost($data){
	$curl = curl_init(URL);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$response = curl_exec($curl);
	curl_close($curl);
	return $response;
}

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

function getTask($taskId){
	return array(
		"key"=>key,
		"cat"=>"task",
		"action"=>"show",
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

function getDivision(){
	GLOBAL $podr;
	switch ($podr) {
		case 'Алчевск':
			return array(
			"key"=>key,
			"cat"=>"employee",
			"action"=>"get_division",
			"id"=>"9,10,12,20,24"
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

$catalogType = json_decode(httpPost(getTaskType()),true)['Data']; //получение информации о типе заявки
$timeSheet = json_decode(httpPost(getTimesheet()),true)['data']; //получение часов для всех
$ktuALL = array('Алчевск_Монтажники' => 0, 'Стажеры' => 0, 'Строители-Алчевск' => 0, 'M@trix-Стаханов' => 0, 'M@trix-Петровское' => 0);

function getEmployeesList(){
	GLOBAL $employees;
	getSettings();
	$divisionList = json_decode(httpPost(getDivision()),true)['data'];//информация о подразделениях
	$employeesList = json_decode(httpPost(getEmployees()),true)['data'];//информация о сотрудниках
	foreach ($divisionList as $divisionInfo) {
		foreach ($divisionInfo['staff']['work'] as $divisionEmployee) {
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['id'] = $divisionEmployee['employee_id'];
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Ф.И.О'] = $employeesList[$divisionEmployee['employee_id']]['name'];
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['otdel'] = $divisionInfo['name'];
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Заявки'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['% КТУ Личный'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['КТУ Количество'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Часы'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Часы Сверхурочные'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Часы выходные'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['ЗП Часы'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['ЗП КТУ'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Стротельные работы'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Итого'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Аванс'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Удержания'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Штраф'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Доп.премия'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['На руки'] = 0;
			$employees[$divisionInfo['name']][$employeesList[$divisionEmployee['employee_id']]['name']]['Долг'] = 0;
		}
	}
}


function getKTU($employeeID, $surname, $division){
	GLOBAL $employees, $catalogType;
	$tmp = 0;
	$zayavki = array();
	$stroika = array(65,74,19,6,24,31,23,21,20,75,77); //номера строительных заявок
	$tasksList = json_decode(httpPost(getTaskList($employeeID)),true)['list']; //получение списка заявок сотрудника
    $tasksCount = json_decode(httpPost(getTaskList($employeeID)),true)['count']; //получение кол-ва заявок сотрудника
	$tasks = json_decode(httpPost(getTask($tasksList)),true)['Data']; //получение информации о всех заявках сотрудника
	//echo "<pre>";
	//print_r($tasksList);
	//echo "<br>";
	//print_r($tasks);
    //ex_alert($employeeID);
    //ex_alert($tasksCount);
    if ($tasksCount == 1){
        //print_r($tasks);
        //echo "<br>...<br>";
        //print_r($tasks['type']['id']);
        //echo "<br>...<br>";

        if(!in_array($tasks['type']['id'], $stroika)){
            $employees[$division][$surname][$tasks['type']['name']] ++;
            $zayavki[$tasks['type']['name']] ++;
            $tmp++;
        }else{
            $employees[$division][$surname]['Стротельные работы'] += 
                round(($catalogType[$tasks['type']['id']]['amount'] * 
                $tasks['volumeCustom']) / 
                count($tasks['staff']['employee']),2);
        }


    }else{

        foreach ($tasks as $taskID => $taskDetail) {
        		if (!in_array($taskDetail['type']['id'], $stroika)) {
			        $employees[$division][$surname][$taskDetail['type']['name']] ++;
			        $zayavki[$taskDetail['type']['name']] ++;
			        $tmp++;
		        }else{
			        //echo $taskID." - (".$catalogType[$taskDetail['type']['id']]['amount']." * ".$tasks[$taskID]['volumeCustom'].") / ".count($tasks[$taskID]['staff']['employee'])."<br>";
			        $employees[$division][$surname]['Стротельные работы'] += 
				        round(($catalogType[$taskDetail['type']['id']]['amount'] * 
				        $tasks[$taskID]['volumeCustom']) / 
				        count($tasks[$taskID]['staff']['employee']),2);
	        }
	        }

    }
	
	if(!empty($zayavki)){
		if($division != 'Строители-Алчевск'){
			foreach ($zayavki as $key => $value) {
				$employees[$division][$surname]['КТУ Количество'] += $GLOBALS['classifier'][$key] * $value;
			}
		}
		$employees[$division][$surname]['Заявки'] = $tmp;
	}
	if($division != 'Строители-Алчевск'){
		$GLOBALS['ktuALL'][$division] += $employees[$division][$surname]['КТУ Количество'];
	}
}


function timesheet($employeeID, $surname, $division){
	GLOBAL $employees, $timeSheet;
	$days = array(994, 995, 996, 997, 998, 999);
	$timeSheetUser = array_column($timeSheet, $employeeID); 
	foreach ($timeSheetUser as $chasy) {
		foreach($chasy as $key => $value){
			if(!in_array($value, $days)){
				switch ($key) {
					case '1':
						$employees[$division][$surname]['Часы'] += $value;
					break;
					case '2':
						$employees[$division][$surname]['Часы Сверхурочные'] += $value;
					break;
					case '3':
						$employees[$division][$surname]['Часы выходные'] += $value;
					break;
				}
			}
		}
	}
	$employees[$division][$surname]['ЗП Часы'] = 
		($employees[$division][$surname]['Ставка час'] * $employees[$division][$surname]['Часы'] * $GLOBALS['chasy']['Обычные']) + 
		($employees[$division][$surname]['Ставка час'] * $employees[$division][$surname]['Часы Сверхурочные'] * $GLOBALS['chasy']['Сверхурочные']) + 
		($employees[$division][$surname]['Ставка час'] * $employees[$division][$surname]['Часы выходные'] * $GLOBALS['chasy']['Выходной']);
}


function montagniki($division){
	GLOBAL $employees;
	foreach ($employees[$division] as $surname => $usercode) {
		getKTU($usercode['id'], $surname, $division);
	}
	foreach ($employees[$division] as $surname => $data) {
        $employees[$division][$surname]['% КТУ Личный'] = round(($employees[$division][$surname]['КТУ Количество'] / $GLOBALS['ktuALL'][$division]) * 100, 2);
		$employees[$division][$surname]['ЗП КТУ'] = floor($GLOBALS['fond_ktu'][$division] * ($employees[$division][$surname]['% КТУ Личный'] / 100));
		timesheet($data['id'],$surname,$division);
		$employees[$division][$surname]['Итого'] = 
			$employees[$division][$surname]['ЗП КТУ'] + 
			$employees[$division][$surname]['ЗП Часы'] + 
			$employees[$division][$surname]['Стротельные работы'] + 
			$employees[$division][$surname]['Ставка'] +
			$employees[$division][$surname]['Надбавки'];
	}
	ksort($employees[$division]);
}

// Стажёры:
function stag($division){
	GLOBAL $employees;
	foreach ($employees[$division] as $surname => $usercode) {
		getKTU($usercode['id'], $surname, $division);
	}
	foreach ($employees[$division] as $surname => $data) {
        $employees[$division][$surname]['% КТУ Личный'] = round(($employees[$division][$surname]['КТУ Количество'] / $GLOBALS['ktuALL'][$division]) * 100, 2);
		$employees[$division][$surname]['ЗП КТУ'] = floor($GLOBALS['fond_ktu'][$division] * ($employees[$division][$surname]['% КТУ Личный'] / 100));
		timesheet($data['id'],$surname,$division);
		$employees[$division][$surname]['Итого'] = 
			$employees[$division][$surname]['ЗП КТУ'] + 
			$employees[$division][$surname]['ЗП Часы'] + 
			$employees[$division][$surname]['Стротельные работы'] + 
			$employees[$division][$surname]['Ставка'] +
			$employees[$division][$surname]['Надбавки'];
	}
	ksort($employees[$division]);
}


function stroiteli($division){
	GLOBAL $employees;
	foreach ($employees[$division] as $surname => $data) {
		getKTU($data['id'],$surname,$division);
		timesheet($data['id'],$surname,$division);
		$employees[$division][$surname]['КТУ Количество'] = $employees[$division][$surname]['Часы'] + $employees[$division][$surname]['Часы Сверхурочные'] + $employees[$division][$surname]['Часы выходные'];
		$GLOBALS['ktuALL'][$division] += $employees[$division][$surname]['КТУ Количество'];
	}
	foreach ($employees[$division] as $surname => $data) {
		$employees[$division][$surname]['% КТУ Личный'] = round(
			(
				$employees[$division][$surname]['КТУ Количество'] / 
				$GLOBALS['ktuALL'][$division]
			) * 100, 2
		);
		$employees[$division][$surname]['ЗП КТУ'] = floor(
			$GLOBALS['fond_ktu'][$division] * (
				$employees[$division][$surname]['% КТУ Личный'] / 100
			)
		);
		$employees[$division][$surname]['Итого'] = 
			$employees[$division][$surname]['ЗП КТУ'] + 
			$employees[$division][$surname]['ЗП Часы'] + 
			$employees[$division][$surname]['Стротельные работы'] + 
			$employees[$division][$surname]['Надбавки'];
	}
	ksort($employees[$division]);
}

function tehpom($division){
	GLOBAL $employees;
	foreach ($employees[$division] as $surname => $data) {
		timesheet($data['id'],$surname,$division);
		$employees[$division][$surname]['Итого'] = 
			$employees[$division][$surname]['Надбавки'] + 
			$employees[$division][$surname]['Ставка'] + 
			$employees[$division][$surname]['ЗП Часы'];
	}
	ksort($employees[$division]);
}

function callCenter($division){
	GLOBAL $employees;
	foreach ($employees[$division] as $surname => $data) {
		timesheet($data['id'],$surname,$division);
		$employees[$division][$surname]['Итого'] = 
			$employees[$division][$surname]['Надбавки'] + 
			$employees[$division][$surname]['Ставка'] + 
			$employees[$division][$surname]['ЗП Часы'];
	}
	ksort($employees[$division]);
}


function main(){
	GLOBAL $podr;
	getEmployeesList();
	switch ($podr) {
		case 'Алчевск':
			montagniki('Алчевск_Монтажники');
            stag('Стажеры');
			stroiteli('Строители-Алчевск');
			tehpom('Тех.помощь Алчевск');
			callCenter('Контакт-центр');
		break;
		
		case 'Стаханов':
			montagniki('M@trix-Стаханов');
		break;
		
		case 'Петровское':
			montagniki('M@trix-Петровское');
		break;
	}
}


function excel(){

	GLOBAL $employees,$classifier,$headers,$podr;

	$spreadsheet = new Spreadsheet();
	$spreadsheet->createSheet();
	$sheet = $spreadsheet->setActiveSheetIndex(0);
	$sheet_2 = $spreadsheet->setActiveSheetIndex(1);
	$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
	$sheet->setTitle('Ведомость');
	$sheet_2
		->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
		->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
		->setScale(59);
	$sheet_2->setTitle('Табель');
	//$sheet_2->getPageSetup()->setScale(59);

	$styleArray = array(
		'alignment' => array(
	        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
	        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
	    ),
	    'borders' => array(
	        'allBorders' => array(
	            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
	        ),
	    ),
	);

	$tabelStyle = array(
		'alignment' => array(
	        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
	        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
	    ),
	    'borders' => array(
	        'allBorders' => array(
	            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
	        ),
	    ),
	);

	$column = range('A', 'Z');
	$column_pos_1 = 0;
	$column_pos_2 = 1;
	$column_pos_3 = 2;
	
	$count = 0;
	
	$line = 1;
	$sheet_2_line = 1;
	$sheet_2_line_2 = 1;

	foreach ($employees as $division_name => $division_staff) {
		
		$sheet->mergeCells('A'.$line.':'.'R'.$line);
		$sheet->setCellValue('A'.$line, $division_name);
		$sheet->getRowDimension($line)->setRowHeight(20);
		$sheet->getStyle('A'.$line)->applyFromArray($styleArray);
		$line++;
		$cell = 'A';
		
		foreach ($headers as $table_header) {
			$sheet->setCellValue($cell.$line, $table_header);
			$sheet->getRowDimension($line)->setRowHeight(30);
			$sheet->getStyle($cell.$line)->applyFromArray($styleArray);
			$sheet->getColumnDimension($cell)->setAutoSize(true);
			$cell++;
		}
		$line++;
		
		foreach ($division_staff as $surname => $personal_info) {
			$cell = 'A';
			$sheet_2_line = $sheet_2_line_2;
			foreach ($headers as $array_head) {
				if($array_head == "На руки"){
					$sheet
						->setCellValue($cell.$line, '=L'.$line.'-M'.$line.'-N'.$line.'-O'.$line.'+P'.$line)
						->getStyle($cell.$line)->applyFromArray($styleArray);
				}else{
					$sheet
						->setCellValue($cell.$line, $personal_info[$array_head])
						->getStyle($cell.$line)->applyFromArray($styleArray);
				}
				
				$sheet_2
					->setCellValue($column[$column_pos_1].$sheet_2_line,$array_head)
					->setCellValue($column[$column_pos_2].$sheet_2_line,"='Ведомость'!$cell$line")
					->getStyle($column[$column_pos_1].$sheet_2_line.':'.$column[$column_pos_2].$sheet_2_line)->applyFromArray($tabelStyle);
				
				$sheet_2_line++;

				$cell++;

			}
			
			$sheet_2->getColumnDimension($column[$column_pos_1])->setWidth(15);
			$sheet_2->getColumnDimension($column[$column_pos_2])->setWidth(15);
			$sheet_2->getColumnDimension($column[$column_pos_3])->setWidth(4);

			$column_pos_1 += 3;
			$column_pos_2 += 3;
			$column_pos_3 += 3;
			$count++;

			if ($count == 5) {
				$column_pos_1 = 0;
				$column_pos_2 = 1;
				$column_pos_3 = 2;
				$sheet_2_line_2 += 19;
				$count = 0;
			}
			
			$line++;

		}
		$line = $line + 3;
	}

	$spreadsheet->setActiveSheetIndex(0);
	$writer = new Xlsx($spreadsheet);
//ex_alert($podr);
	switch ($podr) {
		case 'Алчевск':
			$writer->save('zarplata_'.$podr.'.xlsx');
			break;
		
		case 'Стаханов':
			$writer->save('zarplata_'.$podr.'.xlsx');
			break;
		case 'Петровское':
			$writer->save('zarplata_'.$podr.'.xlsx');
			break;
	}
	//$writer->save('zarplata.xlsx');
/**/
}

function html(){
	GLOBAL $employees,$headers,$classifier,$podr;
	$stop = array("Аванс","Удержания","Штраф","Доп.премия","На руки","Долг");
	$table = "
		<form id=download action=download.php method=post>
			<input type=hidden name=otdel value=".$podr.">
		</form>";	
	foreach ($employees as $division_name => $division_staff) {
		$table .= "
		<table class='table table-bordered table-sm table-striped table-hover'>
		<div class = 'alert alert-info'>".$division_name."</div>";
		$table .= "<thead><tr class=text-center>";
		foreach ($headers as $table_header) {
			if(!in_array($table_header, $stop)){
				$table .= "<th class='align-middle'>$table_header</th>";
			}
		}
		$table .= "</tr></thead>";
		$table .= "<tbody>";
		foreach ($division_staff as $surname => $personal_info) {
			$table .= "<tr class=text-center>";
			foreach ($headers as $array_head) {
				if(!in_array($array_head, $stop)){
					if($array_head == "Заявки" && $personal_info['Заявки'] > 0){
						$table .= "<td><button type='button' class='btn btn-link' data-toggle='modal' data-target='#modal".$personal_info['id']."'>".$personal_info['Заявки']."</button></td>";
						$modal[] = $personal_info;
					}else{
						$table .= "<td>$personal_info[$array_head]</td>";
					}
				}
			}
			$table .= "</tr>";
		}
		$table .= "</tbody>";
	}
	$table .= "</table>";
	if(!empty($modal)){
		foreach ($modal as $modal_info) {
			$table .= "
			<div class='modal' id='modal".$modal_info['id']."'>
				<div class='modal-dialog'>
					<div class='modal-content'>
						<div class='modal-header'>
							<h4 class='modal-title'>Заявки подробно</h4>
							<button type='button' class='close' data-dismiss='modal'>&times;</button>
						</div>
						<div class='modal-body'>
							<table class='table table-bordered'>
								<thead class=text-center>
									<tr>
										<th>Тип заявки</th>
										<th>Количество</th>
										<th>КТУ</th>
									</tr>
								</thead>
							<tbody>";
						foreach ($classifier as $tip_zayavki => $ktu) {
							$table .= "
							<tr>
								<td>$tip_zayavki</td>";
								if(array_key_exists($tip_zayavki, $modal_info)){
									$table .= "<td class=text-center>$modal_info[$tip_zayavki]</td>";
								}else{
									$table .= "<td class=text-center>0</td>";
								}
								$table .= "<td class=text-center>".$classifier[$tip_zayavki]."</td>
							</tr>";
						}
							$table .= "
							<tr>
								<td class='font-weight-bold'>ИТОГО</td>
								<td class=text-center>".$modal_info['Заявки']."</td>
								<td class=text-center>".$modal_info['КТУ Количество']."</td>
							</tr>
						</tbody>
						</table>
					</div>
					<div class='modal-footer'>
						<button type='button' class='btn btn-danger' data-dismiss='modal'>Close</button>
					</div>
					</div>
				</div>
			</div>";
		}
	}	
	echo $table;
}

function setPeople($division){
	$table = "
	<form id=settings>
	<input type=hidden name=type value=staff>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Ф.И.О.</th>
				<th class='align-middle'>Ставка</th>
				<th class='align-middle'>Ставка час</th>
				<th class='align-middle'>Надбавка</th>
			</tr>
		</thead>
		<tbody>";
	$dblite = new SQLite3('ktu.db');
	$res = $dblite->query("SELECT * FROM employees WHERE division = '$division' ORDER BY fio ASC");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$table .= "
		<tr class=text-center>
			<td class='align-middle'>$row[fio]</td>
			<td><input type=number class='form-control text-center' name='$row[id][stavka]' value=$row[stavka]></td>
			<td><input type=number class='form-control text-center' name='$row[id][stavka_chas]' value=$row[stavka_chas]></td>
			<td><input type=number class='form-control text-center' name='$row[id][nadbavka]' value=$row[nadbavka]></td>
		</tr>";
	}
	$dblite->close();
	$table .= "
		</tbody>
		</table>
		
		</form>
	";
	echo $table;
}

function setFondKTU(){
	$table = "
	<form id=settings>
	<input type=hidden name=type value=fondktu>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Отдел</th>
				<th class='align-middle'>Фонд КТУ</th>
			</tr>
		</thead>
		<tbody>";
	$dblite = new SQLite3('ktu.db');
	$res = $dblite->query("SELECT * FROM fond_ktu");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$table .= "
		<tr class=text-center>
			<td class='align-middle'>$row[otdel]</td>
			<td><input type=number class='form-control text-center' name='$row[otdel]' value=$row[ktu]></td>
		</tr>";
	}
	$dblite->close();
	$table .= "
		</tbody>
		</table>
		</form>
	";
	echo $table;
}

function setKTU(){
	$table = "
	<form id=settings>
	<input type=hidden name=type value=ktu>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Тип работ</th>
				<th class='align-middle'>КТУ</th>
			</tr>
		</thead>
		<tbody>";
	$dblite = new SQLite3('ktu.db');
	$res = $dblite->query("SELECT * FROM ktu");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$table .= "
		<tr class=text-center>
			<td class='align-middle'>$row[type_of_work]</td>
			<td><input type=number class='form-control text-center' name='$row[work_id]' value=$row[work_ktu]></td>
		</tr>";
	}
	$dblite->close();
	$table .= "
		</tbody>
		</table>
		</form>
	";
	echo $table;
}

function setChasy(){
	$table = "
	<form id=settings>
	<input type=hidden name=type value=chasy>
	<table class='table table-bordered table-sm table-striped'>
		<thead>
			<tr class=text-center>
				<th class='align-middle'>Часы</th>
				<th class='align-middle'>Оплата</th>
			</tr>
		</thead>
		<tbody>";
	$dblite = new SQLite3('ktu.db');
	$res = $dblite->query("SELECT * FROM chasy");
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$table .= "
		<tr class=text-center>
			<td class='align-middle'>$row[tip]</td>
			<td><input type=number class='form-control text-center' name='$row[tip]' value=$row[stavka]></td>
		</tr>";
	}
	$dblite->close();
	$table .= "
		</tbody>
		</table>
		</form>
	";
	echo $table;
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

		case 'Алчевск_Монтажники':
			setPeople($set);
			break;

        case 'Стажеры':
			setPeople($set);
			break;

		case 'Строители-Алчевск':
			setPeople($set);
			break;
		
		case 'Тех.помощь Алчевск':
			setPeople($set);
			break;
		
		case 'Контакт-центр':
			setPeople($set);
			break;

		case 'M@trix-Стаханов':
			setPeople($set);
			break;

		case 'M@trix-Петровское':
			setPeople($set);
			break;
	}
}

switch ($_POST['nastr']) {
	case 'settings':
		settings($_POST['tip']);
		break;
	
	case 'zarplata':
		main();
		excel();
		html();
		break;
}



?>
