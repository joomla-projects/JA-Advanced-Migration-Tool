<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  migration.wordpress
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Migration\Wordpress\Extension\Wordpress;

return new class () implements ServiceProviderInterface
{
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {

                $config = (array) PluginHelper::getPlugin('migration', 'wordpress');
                $subject = $container->get(DispatcherInterface::class);
                $app = Factory::getApplication();

                $plugin = new Wordpress($subject, $config);
                $plugin->setApplication($app);

                return $plugin;
            }
        );
    }
};
