<?php

namespace Joomla\CMS;

class Factory
{
    public static $application;

    public static function getApplication()
    {
        return self::$application;
    }
}
