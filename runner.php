<?php
//Скрипт не ограничиваем по времени работы
set_time_limit(0);

//namespace Plp;
require_once __DIR__.'/application.php';
/**
 * Запуск выполнения заданий
 */
use Plp\Application;

//Запускаем обработуку заданий
Application::run();