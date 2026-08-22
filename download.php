<?php
require 'conf.php';

function file_force_download($file) {
    if (file_exists($file)) {
        // Сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти
        if (ob_get_level()) {
            ob_end_clean();
        }
        // Заставляем браузер показать окно сохранения файла
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        // Читаем файл и отправляем его пользователю
        readfile($file);
        exit;
    }
    return false;
}

// Проверяем, что передан корректный отдел
if (isset($_POST['otdel']) && in_array($_POST['otdel'], arr_city)) {
    $filename = "zarplata_" . $_POST['otdel'] . ".xlsx";
    
    // Файл уже был сгенерирован api.php (case 'generate_excel' или 'zarplata') 
    // непосредственно перед вызовом этого скрипта.
    // Нам не нужно искать JSON, достаточно просто отдать готовый XLSX.
    if (file_force_download($filename)) {
        exit;
    }
    
    // Если по какой-то причине файл не был создан
    http_response_code(404);
    echo "Файл не найден: " . $filename;
} else {
    http_response_code(400);
    echo "Некорректный запрос";
}