<?php
/**
 *
 */

use Plp\Application;

/**
 * Class DBTest
 */
class ApplicationTest extends PHPUnit_Extensions_Database_TestCase
{
    /**
     * Хост БД
     */
    const DB_HOST = '192.168.1.107';

    /**
     * Имя БД
     */
    const DB_NAME = 'plp_base_test';

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
     * @var
     */
    protected $connection;

    /**
     * @var
     */
    protected static $pdo;

    /**
     * Создать подключение к БД
     *
     * @return mixed
     */
    protected function getConnection()
    {
        if (!$this->connection) {
            if (!self::$pdo) {
                self::$pdo = new PDO('mysql:host=' . self::DB_HOST . ';dbname=' . self::DB_NAME . ';charset=' . self::DB_ENCODE,
                    self::DB_LOGIN, self::DB_PASS);
            }
            $this->connection = $this->createDefaultDBConnection(self::$pdo);
        }
        return $this->connection;
    }

    /**
     * Тестовый набор данных для БД
     *
     * @return mixed
     */
    protected function getDataSet()
    {
        return $this->createMySQLXMLDataSet('/home/bitrix/www/PlatformLP/tests/mysql_test_data.xml');
    }

    /**
     * @cover Application::getNextTask
     */
    public function testGetNextTask()
    {
        $object = new Application;
        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        $this->assertTrue(is_array($task) && array_key_exists('task', $task),
            'Задание существует в БД, но не было возвращено');
    }

    /**
     * @cover Application::getNextTask
     */
    public function testGetNextTaskFromEmptyQuery()
    {

        $object = new Application;

        //Выбираем из очереди все три задания
        $this->invokeMethod($object, 'getNextTask', [self::$pdo]);
        $this->invokeMethod($object, 'getNextTask', [self::$pdo]);
        $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Больше заданий нет
        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        $this->assertTrue(0 === count($task), 'Возвращено задание из пустой очереди');
    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     * @cover Application::checkString
     */
    public function testSetTaskResult()
    {
        $object = new Application;

        //Получаем задание из очереди
        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskResult', [self::$pdo, $task['id'], json_encode(['test'])]);

        $this->assertTrue($res, 'Установка корректного результата для существующей задачи не выполнена');
    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     * @cover Application::checkString
     */
    public function testSetTaskResultForIncorrectID()
    {
        $object = new Application;

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskResult', [self::$pdo, PHP_INT_MAX, json_encode(['test'])]);

        $this->assertFalse($res, 'Выполнена установка корректного результата для не существующей задачи');
    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     * @cover Application::checkString
     */
    public function testSetTaskIncorrectResult()
    {
        $object = new Application;

        //Получаем задание из очереди
        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskResult', [self::$pdo, $task['id'], ['test']]);

        $this->assertFalse($res, 'Выполнена установка не корректного результата для существующей задачи');
    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     * @cover Application::checkString
     */
    public function testSetTaskDeffered()
    {
        $object = new Application;

        //Получаем задание из очереди
        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskResult', [self::$pdo, $task['id'], json_encode(['test'])]);

        $this->assertTrue($res,
            'Не выполнен отложенный запуск провалившегося существующего задания с корректным описанием результата');

    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     * @cover Application::checkString
     */
    public function testSetTaskDefferedForIncorrectID()
    {
        $object = new Application;

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskResult', [self::$pdo, PHP_INT_MAX, json_encode(['test'])]);

        $this->assertFalse($res,
            'Выполнен отложенный запуск провалившегося не существующего задания с корректным описанием результата');

    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     * @cover Application::checkString
     */
    public function testSetTaskDefferedIncorrectResult()
    {
        $object = new Application;

        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskResult', [self::$pdo, $task['id'], ['test']]);

        $this->assertFalse($res,
            'Не выполнен отложенный запуск провалившегося существующего задания с не корректным описанием результата');

    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     */
    public function testSetTaskFailed()
    {
        $object = new Application;

        //Получаем задание из очереди
        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskFailed', [self::$pdo, $task['id'], ['test']]);

        $this->assertTrue($res, 'Не выполнена фиксация существующей задачи как невыполнимой');


    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     */
    public function testSetTaskFailedForIncorrectID()
    {
        $object = new Application;

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskFailed', [self::$pdo, PHP_INT_MAX, json_encode(['test'])]);

        $this->assertFalse($res, 'Выполнена фиксация не существующей задачи как невыполнимой');

    }

    /**
     * @cover Application::setTaskResult
     * @cover Application::checkInt
     */
    public function testSetTaskFailedIncorrectResult()
    {
        $object = new Application;

        $task = $this->invokeMethod($object, 'getNextTask', [self::$pdo]);

        //Запись результата выполнения задания
        $res = $this->invokeMethod($object, 'setTaskFailed', [self::$pdo, $task['id'], 'test']);

        $this->assertFalse($res,
            'Не выполнена фиксация существующей задачи как невыполнимой с указанием некорректного описания ошибки');
    }


    /**
     * Вызвать protected/private метод класса.
     *
     * @param object &$object    Объект класса/.
     * @param string $methodName Метод для вызова
     * @param array  $parameters Массив параметров метода
     *
     * @return mixed Результат работы метода
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}