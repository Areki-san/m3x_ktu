<?php
    include 'conf.php';

    $smarty -> assign('arr_setings_division', arr_setings_division);
    $smarty -> display('settings.tpl'); //публикуем шаблон

?>
