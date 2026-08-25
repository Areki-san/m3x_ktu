<!DOCTYPE html>
<html lang="ru">
<head>
    <title>{$site_name}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg" href="http://stats.m3x.org/icon.png">
    <!-- Bootstrap 4.6.2 (последняя стабильная в ветке 4) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js"></script>
    <script src="ajax.js"></script>
</head>
<!--{$site_ver}-->
<body>
    <nav class="navbar navbar-expand-md bg-dark navbar-dark">
        <a class="navbar-brand" href="">
            <img src="http://stats.m3x.org/images/logo_Resize_M3x.png" alt="Logo" style="width:90px;">
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#collapsibleNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="collapsibleNavbar">
            <ul class="navbar-nav mr-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Главная</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="archive.php">Архив расчётов</a>
                </li>
                <li>
                    <a class="nav-link" href="settings.php">Настройки оплаты</a>
                </li>
            </ul>
            <!-- end template navbar -->