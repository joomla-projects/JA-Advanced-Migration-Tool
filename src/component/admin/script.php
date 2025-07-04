<?php

/**
 * @package		Joomla.Administrator
 * @subpackage	com_cmsmigrator
 * @copyright
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

/**
 * Installation script
 *
 * @since 1.0.0
 */
class Com_CmsMigratorInstallerScript
{
    /**
     * Minimum Joomla version to check
     *
     * @var     string
     * @since   1.0.0
     */
    private $minimumJoomlaVersion = '4.0';

    /**
     * Minimum PHP version to check
     *
     * @var     string
     * @since   1.0.0
     */
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;

    /**
     * Method to install the extension
     *
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function install($parent): bool
    {
        try {
            // Create media folder
            $this->createMediaFolder();

            // Execute SQL installation file
            $db = Factory::getDbo();
            $sqlFile = JPATH_ADMINISTRATOR . '/components/com_cmsmigrator/sql/install.mysql.utf8.sql';
            
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                if ($sql) {
                    // Split SQL file into individual queries
                    $queries = $db->splitSql($sql);
                    
                    foreach ($queries as $query) {
                        $query = trim($query);
                        if ($query) {
                            $db->setQuery($query);
                            try {
                                $db->execute();
                            } catch (\Exception $e) {
                                Log::add('Error executing SQL query: ' . $e->getMessage(), Log::ERROR, 'com_cmsmigrator');
                                throw $e;
                            }
                        }
                    }
                }
            } else {
                throw new \RuntimeException('SQL installation file not found: ' . $sqlFile);
            }

            return true;
        } catch (\Exception $e) {
            Log::add('Error during installation: ' . $e->getMessage(), Log::ERROR, 'com_cmsmigrator');
            return false;
        }
    }

    /**
     * Method to uninstall the extension
     *
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function uninstall($parent): bool
    {
        return true;
    }

    /**
     * Method to update the extension
     *
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function update($parent): bool
    {
        return true;
    }

    /**
     * Function called before extension installation/update/removal procedure commences
     *
     * @param   string            $type    The type of change (install, update or discover_install, not uninstall)
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function preflight($type, $parent): bool
    {
        // Check for minimum PHP version
        if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            Log::add(
                Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                Log::WARNING,
                'jerror'
            );

            return false;
        }

        // Check for minimum Joomla version
        if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            Log::add(
                Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                Log::WARNING,
                'jerror'
            );

            return false;
        }

        return true;
    }

    /**
     * Function called after extension installation/update/removal procedure commences
     *
     * @param   string            $type    The type of change (install, update or discover_install, not uninstall)
     * @param   InstallerAdapter  $parent  The class calling this method
     *
     * @return  boolean  True on success
     */
    public function postflight($type, $parent)
    {
        return true;
    }

    /**
     * Create the media folder for storing imported files
     *
     * @return  void
     */
    private function createMediaFolder(): void
    {
        $mediaPath = JPATH_ROOT . '/media/com_cmsmigrator';
        $importsPath = $mediaPath . '/imports';
        $imagesPath = $mediaPath . '/images';

        if (!file_exists($mediaPath)) {
            mkdir($mediaPath, 0755, true);
        }

        if (!file_exists($importsPath)) {
            mkdir($importsPath, 0755, true);
        }

        if (!file_exists($imagesPath)) {
            mkdir($imagesPath, 0755, true);
        }
    }
}
