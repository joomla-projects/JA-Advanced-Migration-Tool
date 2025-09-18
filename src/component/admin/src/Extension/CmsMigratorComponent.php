<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\CmsMigrator\Administrator\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

class CmsMigratorComponent extends MVCComponent implements BootableExtensionInterface
{
    public function boot(ContainerInterface $container)
    {
    }
}
