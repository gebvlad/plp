<?php
/**
 * Запуск выполнения заданий
 */

namespace Plp;

//Установить путь к логу
ini_set('error_log', __DIR__ . '/error_log.log');

//Подключить все классы (можно сделать автолоадером, но дял тестового задания думаю это не нужно делать)
foreach (glob(__DIR__ . '/classes/*.php') as $filename) {
    require_once $filename;
}

use Plp\Task;

/**
 * Class Application
 *
 * Содержит методы для работы с очередью заданий
 */
class Application
{
    /**
     * Хост БД
     */
    const DB_HOST = '192.168.1.107';

    /**
     * Имя БД
     */
    const DB_NAME = 'plp_base';

    /**
     * Кодировка БД
     */
    const DB_ENCODE = 'utf8';

    /**
     * Пользователь БД
     */
    const DB_LOGIN = 'root';

    /**
     * Пароль БД
     */
    const DB_PASS = '';

    /**
     * Статус задания - в очереди
     */
    const TASK_STATUS_QUEUED = 0;

    /**
     * Статус задания - выполняется
     */
    const TASK_STATUS_RUNNING = 1;

    /**
     * Статус задания - невыполнимое задание
     */
    const TASK_STATUS_FAILED = 2;

    /**
     * Статус задания - выполнено
     */
    const TASK_STATUS_COMPLETE = 3;

    /**
     * Неймспейс для вызова функций обрабатывающих задания
     */
    const TASK_NAMESPACE = 'Plp\\Task\\';

    /**
     * @var \PDO Объект подключения к БД
     */
    private static $db;

    /**
     * Запуск выполнения заданий.
     *
     * @return bool
     *
     * @throws \PDOException
     */
    public static function run()
    {
        echo 'Run... #' . getmypid() . PHP_EOL;

        try {
            //Создаем подключение к БД
            self::$db = self::connect(self::DB_HOST, self::DB_NAME, self::DB_ENCODE, self::DB_LOGIN, self::DB_PASS);

            while (true) {
                $result = self::runTask(self::$db);

                if (!$result) {
                    echo getmypid() . ' | Заданий в очереди нет или выполняется запись в БД' . PHP_EOL;
                    continue;
                }

                echo getmypid() . ' | ' . implode(' | ', $result) . PHP_EOL;
            }
        } catch (\PDOException $e){
            echo getmypid() . ' | '.$e->getMessage() .' | '.$e->getLine(). PHP_EOL;
            echo getmypid() . ' | STOP' . PHP_EOL;
        }

        return true;
    }

    /**
     * Выполнение задания из очереди
     *
     * @param \PDO $db Объект подключения к БД
     *
     * @return array|bool false если заданий в очереди нет,
     *                    иначе - массив описывающий задание - дата и веремя, идентификатор задания, задание, действие,
     *                    результат
     *
     * @throws \PDOException
     */
    private static function runTask($db)
    {
        //Получаем новое задание из очереди
        $arTask = self::getNextTask($db);

        //Если заданий в очереди нет
        if (0 === count($arTask)) {
            return false;
        }

        try {
            $result = call_user_func_array([self::TASK_NAMESPACE . $arTask['task'], $arTask['action']],
                json_decode($arTask['data'], JSON_OBJECT_AS_ARRAY));

            //Записать резултат выполнения задания
            self::setTaskResult(self::$db, $arTask['id'], json_encode($result));
        } catch (Task\UserException $e) {

            $result = ['error' => $e->getMessage()];
            //Откладываем задачу
            self::setTaskDeffered(self::$db, $arTask['id'], json_encode($result));
        } catch (Task\FatalException $e) {

            $result = ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
            //Пометить как невыполнимую
            self::setTaskFailed(self::$db, $arTask['id'], $result);
        }

        return [
            'datetime' => date('Y-m-d H:i:s'),
            'id'       => $arTask['id'],
            'task'     => $arTask['task'],
            'action'   => $arTask['action'],
            'result'   => json_encode($result)
        ];
    }

    /**
     * Подключение к БД
     *
     * @param string $host     Хост БД
     * @param string $database Имя БД
     * @param string $charset  Кодировка БД
     * @param string $user     Логин пользователя БД
     * @param string $pass     Пароль доступа к БД
     *
     * @return \PDO
     *
     * @throws \PDOException
     */
    private static function connect($host, $database, $charset, $user, $pass)
    {
        //Строка подключения к БД
        $dsn = "mysql:host=$host;dbname=$database;charset=$charset";

        //Опции подключения
        $opt = array(
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_PERSISTENT         => true
        );

        return new \PDO($dsn, $user, $pass, $opt);
    }

    /**
     * Получить задание, поставленное в очередь и изменить статус задания на «Выполняется»
     *
     * @param \PDO $db Объект подключения к БД
     *
     * @return array Массив описывающий задание
     */
    private static function getNextTask($db)
    {
        //Начинаем транзацию
        $db->beginTransaction();

        //Получить задачу из очереди
        $sql =
            'SELECT id, task, action, data
             FROM task
             WHERE `retries` < 3 AND `finished` IS NULL AND (`deffer` <= NOW() OR `deffer` IS NULL) AND `status` = ' . self::TASK_STATUS_QUEUED . '
             ORDER BY `id`
             LIMIT 1;';

        try {
            //Выполняем щапрос
            $arResult = $db->query($sql)->fetch();

            //Если ничего не выбрано
            if (false === $arResult) {
                $db->rollBack();

                return [];
            }

            //Меняем статус у задачи
            $sql = 'UPDATE task
                SET
                `status` = ' . self::TASK_STATUS_RUNNING . '
                WHERE `id`=' . $arResult['id'] . ';';

            //Выполняем щапрос
            $result = $db->query($sql);

            //Если статус не получилось изменить
            if (0 === $result->rowCount()) {
                $db->rollBack();

                return [];
            }

            $db->commit();

            return $arResult;

        } catch (\PDOException $e) {
            $db->rollBack();

            return [];
        }
    }

    /**
     * Запись результата корректного выполнения задания.
     *
     * Записать результат выполнения задания в БД, установить дату выполнения задания, установка статуса задания.
     *
     * @param \PDO    $db     Объект подключения к БД
     * @param integer $id     Идентификатор задания
     * @param string  $result Результат выполнения задания закодированый в JSON
     *
     * @return bool
     *
     * @throws \PDOException
     */
    private static function setTaskResult($db, $id, $result)
    {
        if(!self::checkInt($id)){
            return false;
        }

        if(!self::checkString($result)){
            return false;
        }

        while(true) {
            $db->beginTransaction();

            //Обновление данных по заданию
            $sql = 'UPDATE task
                SET
                `result` = ' . $db->quote($result) . ',
                `finished` = NOW(),
                `status` = ' . self::TASK_STATUS_COMPLETE . '
                WHERE `id`=' . $id . ';';

            try {
                //Выполнение запроса
                $result = $db->query($sql);

                //Если обновление задания не было выполнено
                if (0 === $result->rowCount()) {
                    $db->rollBack();

                    return false;
                }

                $db->commit();

                break;

            } catch (\PDOException $e) {
                $db->rollBack();

                throw $e;
            }
        }

        return true;
    }

    /**
     * Отложить выполнение задания
     *
     * Записать ошибку выполнения задания в БД, установить дату следующего выполнения задания, установка статуса
     * задания, увеличить счетчик попыток выполнения задания.
     *
     * @param \PDO    $db     Объект подключения к БД
     * @param integer $id     Идентификатор задания
     * @param string  $result Результат выполнения задания закодированый в JSON
     *
     * @return bool
     *
     * @throws \PDOException
     */
    private static function setTaskDeffered($db, $id, $result)
    {
        if(!self::checkInt($id)){
            return false;
        }

        if(!self::checkString($result)){
            return false;
        }
        while(true) {
            $db->beginTransaction();

            //Обновление данных по заданию
            $sql = 'UPDATE task
                SET
                `result` = ' . $db->quote($result) . ',
                `status` = ' . self::TASK_STATUS_QUEUED . ',
                `deffer` = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                `retries` = `retries` + 1
                WHERE `id`=' . $id . ';';

            try {
                //Выполнение запроса
                $result = $db->query($sql);

                //Если обновление задания не было выполнено
                if (0 === $result->rowCount()) {
                    $db->rollBack();

                    return false;
                }

                $db->commit();

                break;

            } catch (\PDOException $e) {
                $db->rollBack();

                throw $e;
            }
        }
        return true;
    }

    /**
     * Отметить задание невыполнимым
     *
     * Записать ошибку выполнения задания в лог, установка статуса задания.
     *
     * @param \PDO    $db      Объект подключения к БД
     * @param integer $id      Идентификатор задания
     * @param array   $arError Массив отписывающий ошибку и трейс ошибки.
     *
     * @return bool
     *
     * @throws \PDOException
     */
    private static function setTaskFailed($db, $id, $arError)
    {
        if(!self::checkInt($id)){
            return false;
        }

        if(!is_array($arError)){
            return false;
        }

        while(true){
            $db->beginTransaction();

            //Изменение статуса задания
            $sql = 'UPDATE task
                    SET
                    `status` = ' . self::TASK_STATUS_FAILED . '
                    WHERE `id`=' . $id . ';';

            try {
                //Выполнение запроса
                $result = $db->query($sql);

                //Если обновление статус задания не было выполнено
                if (0 === $result->rowCount()) {
                    $db->rollBack();

                    return false;
                }

                $db->commit();

                break;
            } catch (\PDOException $e) {
                $db->rollBack();

                throw $e;
            }
        }

        //Пишем ошибку и трейс  в лог
        error_log(implode(', ', $arError), 0);

        return true;
    }

    /**
     * Проверка корректности числа типа int
     *
     * @param int $var Проверяемое число
     *
     * @return bool
     */
    private static function checkInt($var)
    {
        return !(filter_var($var, FILTER_VALIDATE_INT) === false);
    }

    /**
     * Проверка корректности строки
     *
     * @param string $var     Проверяемая строка
     *
     * @return bool
     */
    private static function checkString($var)
    {
        return  is_string($var) && trim($var) !== '';
    }
}



