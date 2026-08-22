<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// EXEL
function excel(){

	GLOBAL $employees, $classifier, $headers, $podr, $dateFrom, $dateTo, $created_at;

    // Порядок и состав колонок листа "Ведомость" (A..S)
    // head    - текст шапки
    // key     - ключ массива $employees
    // group   - группа для двухуровневой шапки ("Часы")
    // formula - формула Excel вместо значения
    $columns = array(
        array('head' => 'Ф.И.О',               'key' => 'Ф.И.О'),                                  // A
        array('head' => 'Заявки',              'key' => 'Заявки'),                                 // B
        array('head' => '% КТУ Личный',        'key' => '% КТУ Личный'),                           // C
        array('head' => 'Обычные',             'key' => 'Часы',              'group' => 'Часы'),   // D
        array('head' => 'Сверхурочные',        'key' => 'Часы Сверхурочные', 'group' => 'Часы'),   // E
        array('head' => 'Выходные',            'key' => 'Часы Выходные',     'group' => 'Часы'),   // F
        array('head' => 'Ставка',              'key' => 'Ставка'),                                 // G
        array('head' => 'Ставка час',          'key' => 'Ставка час'),                             // H
        array('head' => 'ЗП Часы',             'key' => 'ЗП Часы'),                                // I
        array('head' => 'ЗП КТУ',              'key' => 'ЗП КТУ'),                                 // J
        array('head' => 'Строительные работы', 'key' => 'Строительные работы'),                    // K
        array('head' => 'Надбавки',            'key' => 'Надбавки'),                               // L
        array('head' => 'Доп.премия',          'key' => 'Доп.премия'),                             // M
        array('head' => 'Отпускные',           'key' => 'Отпускные'),                              // N
        array('head' => 'Больничные',          'key' => 'Больничные'),                             // O
        array('head' => 'Удержания/Штраф',     'key' => 'Удержания'),                              // P
        array('head' => 'Итого',               'key' => 'Итого',   'formula' => '=G{line}+I{line}+J{line}+K{line}+L{line}+M{line}+N{line}+O{line}-P{line}'), // Q
        array('head' => 'Аванс',               'key' => 'Аванс'),                                  // R
        array('head' => 'На руки',             'key' => 'На руки', 'formula' => '=Q{line}-R{line}'), // S
    );

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

    // ширины колонок (для этих колонок autosize отключается)
    // значение указано в количестве символов
    $columnWidth = array(
        '% КТУ Личный'        => 9 ,  // уже + перенос
        'Обычные'             => 10 , // шире
        'Ставка'              => 10 , // уже
        'Ставка час'          => 9 ,  // уже + перенос
        'Строительные работы' => 14,  // уже + перенос
        'Удержания/Штраф'     => 12,  // уже + перенос
    );

    // в этих шапках включаем перенос строк
    $wrapText = array('% КТУ Личный', 'Ставка час', 'Строительные работы', 'Удержания/Штраф');

    // ---- СТРОКА 1: период и дата формирования файла ----
    $periodText = 'Период: с ' . date('Y-m-d', strtotime($dateFrom))
                . ' по '       . date('Y-m-d', strtotime($dateTo))
                . ' от '       . date('Y-m-d', strtotime($created_at));

    $sheet->mergeCells('A1:S1');
    $sheet->setCellValue('A1', $periodText);
    $sheet->getRowDimension(1)->setRowHeight(20);
    $sheet->getStyle('A1')->applyFromArray($styleArray);
    $sheet->getStyle('A1')->getFont()->setBold(true);

	$column = range('A', 'Z');
	$column_pos_1 = 0;
	$column_pos_2 = 1;
	$column_pos_3 = 2;
	$count = 0;
	$line = 2;               // данные начинаются со 2-й строки (1-я - период)
	$sheet_2_line = 1;
	$sheet_2_line_2 = 1;

	foreach ($employees as $division_name => $division_staff) {

		// ---- название подразделения ----
        $sheet->mergeCells('A'.$line.':'.'S'.$line);
		$sheet->setCellValue('A'.$line, $division_name);
		$sheet->getStyle('A'.$line.':'.'S'.$line)->applyFromArray($styleArray);
		$line++;

        // ---- двухуровневая шапка ----
        $h1 = $line;       // строка шапки 1 (группа "Часы")
        $h2 = $line + 1;   // строка шапки 2 (Обычные/Сверхурочные/Выходные)

        $prevGroup = null;
		$cell = 'A';

        foreach ($columns as $col) {
            $group = isset($col['group']) ? $col['group'] : null;

            if ($group !== null && $group !== $prevGroup) {
                // начало группы "Часы": объединяем 3 колонки в шапке 1
                $endCell = chr(ord($cell) + 2);
                $sheet->mergeCells($cell.$h1.':'.$endCell.$h1);
                $sheet->setCellValue($cell.$h1, $group);
                // Применяем границы ко всему диапазону объединения по горизонтали
                $sheet->getStyle($cell.$h1.':'.$endCell.$h1)->applyFromArray($styleArray);
                $sheet->setCellValue($cell.$h2, $col['head']);
                $sheet->getStyle($cell.$h2)->applyFromArray($styleArray);
            } elseif ($group !== null) {
                // продолжение группы
                $sheet->setCellValue($cell.$h2, $col['head']);
                $sheet->getStyle($cell.$h2)->applyFromArray($styleArray);
            } else {
                // обычная колонка: объединяем вертикально 2 строки шапки
                $sheet->mergeCells($cell.$h1.':'.$cell.$h2);
                $sheet->setCellValue($cell.$h1, $col['head']);
                // Применяем границы ко всему диапазону объединения по вертикали
                $sheet->getStyle($cell.$h1.':'.$cell.$h2)->applyFromArray($styleArray);
            }

            // ширина колонки: либо фиксированная, либо autosize
            if (isset($columnWidth[$col['head']])) {
                $sheet->getColumnDimension($cell)->setWidth($columnWidth[$col['head']]);
            } else {
                $sheet->getColumnDimension($cell)->setAutoSize(true);
            }

            // перенос строк в шапке
            if (in_array($col['head'], $wrapText)) {
                $sheet->getStyle($cell.$h1.':'.$cell.$h2)->getAlignment()->setWrapText(true);
            }
            $prevGroup = $group;
            $cell++;
        }
        $sheet->getRowDimension($h1)->setRowHeight(20);
        $sheet->getRowDimension($h2)->setRowHeight(30);

        $line = $h2 + 1;

        // ---- строки сотрудников ----
		foreach ($division_staff as $surname => $personal_info) {

			$cell = 'A';
			$sheet_2_line = $sheet_2_line_2;

            foreach ($columns as $col) {
                if (isset($col['formula'])) {
                    // Итого / На руки - считаем формулой в Excel
                    $sheet
                        ->setCellValue($cell.$line, str_replace('{line}', $line, $col['formula']))
                        ->getStyle($cell.$line)->applyFromArray($styleArray);
                } else {
                    $value = isset($personal_info[$col['key']]) ? $personal_info[$col['key']] : '';
                    $sheet
                        ->setCellValue($cell.$line, $value)
                        ->getStyle($cell.$line)->applyFromArray($styleArray);
                }

                // лист "Табель": имя колонки + ссылка на ячейку "Ведомость"
                $tabHead = ($group = isset($col['group']) ? $col['group'].' ' : '') . $col['head'];
                $sheet_2
                    ->setCellValue($column[$column_pos_1].$sheet_2_line, $tabHead)
                    ->setCellValue($column[$column_pos_2].$sheet_2_line, "='Ведомость'!$cell$line")
                    ->getStyle($column[$column_pos_1].$sheet_2_line.':'.$column[$column_pos_2].$sheet_2_line)->applyFromArray($tabelStyle);
                $sheet_2_line++;
                $cell++;
            }

            //$sheet_2->getColumnDimension($column[$column_pos_1])->setAutoSize(true);
            $sheet_2->getColumnDimension($column[$column_pos_1])->setWidth(21);
			$sheet_2->getColumnDimension($column[$column_pos_2])->setWidth(16);
			$sheet_2->getColumnDimension($column[$column_pos_3])->setWidth(4);

			$column_pos_1 += 3;
			$column_pos_2 += 3;
			$column_pos_3 += 3;
			$count++;

			if ($count == 5) {
				$column_pos_1 = 0;
				$column_pos_2 = 1;
				$column_pos_3 = 2;
				$sheet_2_line_2 += 20;
				$count = 0;
			}

			$line++;

		}
		$line = $line + 3;
	}

	$spreadsheet->setActiveSheetIndex(0);
	$writer = new Xlsx($spreadsheet);
//ex_alert($podr);
	if (in_array($podr, arr_city))
	{
	  $writer->save('zarplata_'.$podr.'.xlsx');
	}
}