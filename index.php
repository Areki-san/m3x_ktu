<?php
    include 'conf.php';

    $smarty -> assign('arr_city', arr_city);
    $smarty -> display('index.tpl'); //публикуем шаблон



    //echo $site_name;
    //print_r($arr_city);

    //foreach ($arr_city as $item)
    //    echo $item;

    //phpinfo();
?>
