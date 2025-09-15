<?php

// script.php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Installer script for the WordPress Migration Plugin.
 *
 * @since  1.0.0
 */
class PlgMigrationWordpressInstallerScript
{
    /**
     * Runs after an install or update.
     *
     * @param   string                                   $type   'install', 'update' or 'discover_install'.
     * @param   \Joomla\CMS\Installer\Adapter\Installer  $parent The parent installer adapter.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function postflight($type, $parent)
    {
        // Only act on fresh installs or updates.
        if (in_array($type, ['install', 'update'], true)) {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type')    . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder')  . ' = ' . $db->quote('migration'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('wordpress'));

            $db->setQuery($query);
            $db->execute();
        }
    }
}
