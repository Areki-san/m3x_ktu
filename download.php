<?php

function file_force_download($file) {
  if (file_exists($file)) {
    // сбрасываем буфер вывода PHP, чтобы избежать переполнения памяти выделенной под скрипт
    // если этого не сделать файл будет читаться в память полностью!
    if (ob_get_level()) {
      ob_end_clean();
    }
    // заставляем браузер показать окно сохранения файла
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename=' . $file);
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    // читаем файл и отправляем его пользователю
    readfile($file);
    exit;
  }
}

switch ($_POST['otdel']) {
  case 'Алчевск':
    file_force_download("zarplata_".$_POST['otdel'].".xlsx");
    break;
  
  case 'Стаханов':
    file_force_download("zarplata_".$_POST['otdel'].".xlsx");
    break;
  case 'Петровское':
    file_force_download("zarplata_".$_POST['otdel'].".xlsx");
    break;
}
//file_force_download("zarplata.xlsx");

?>