<?php
require '../class/User.php'; // Подключите ваш класс
// После создания PDO, создайте экземпляр User и выполните logout
$user = new User($pdo);
$user->logout(); // Вызов метода logout

// Перенаправление на главную страницу или страницу входа
$referer = $_SERVER['HTTP_REFERER'];
header('Location: '.$referer); // или login.php
exit();