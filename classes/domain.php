<?php
/**
 *
 */
namespace Plp\Task;

use Plp\Task;

/**
 * Class Domain
 *
 * @package Plp\Task
 */
class Domain
{
    /**
     * @param $arParams
     *
     * @return array
     * @throws FatalException
     * @throws UserException
     */
    public static function addzone($arParams)
    {
        switch (mt_rand(1, 10)) {
            case 1:
            case 2:
                throw new FatalException('FatalException: ' . __CLASS__ . ':' . __FUNCTION__);
                break;
            case 3:
            case 4:
                throw new UserException('UserException: ' . __CLASS__ . ':' . __FUNCTION__);
                break;
        };

        sleep(mt_rand(0, 5));
        
        return ['result' => md5(mt_rand())];
    }
}