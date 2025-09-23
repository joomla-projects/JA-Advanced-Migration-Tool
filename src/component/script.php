<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_cmsmigrator
 * @copyright   Copyright (C) 2025 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
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
        // Only install bundled extensions during install or update
        if ($type === 'install' || $type === 'update') {
            $this->installBundledExtensions($parent);
        }
        
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

    /**
     * Install bundled extensions that come with the component
     *
     * @param   InstallerAdapter  $parent  The installer adapter
     *
     * @return  void
     */
    private function installBundledExtensions($parent): void
    {
        // Get the source path of the component package
        $sourcePath = $parent->getParent()->getPath('source');
        
        // Try different possible paths for the module
        $possiblePaths = [
            $sourcePath . '/modules/mod_migrationnotice',
            $sourcePath . '/../modules/mod_migrationnotice',
            dirname($sourcePath) . '/modules/mod_migrationnotice'
        ];
        
        $moduleSourcePath = null;
        foreach ($possiblePaths as $path) {
            Log::add('Checking path: ' . $path, Log::INFO, 'com_cmsmigrator');
            if (file_exists($path) && file_exists($path . '/mod_migrationnotice.xml')) {
                $moduleSourcePath = $path;
                Log::add('Found module at: ' . $path, Log::INFO, 'com_cmsmigrator');
                break;
            }
        }
        
        if ($moduleSourcePath) {
            try {
                $moduleInstaller = new Installer();
                $result = $moduleInstaller->install($moduleSourcePath);
                
                if ($result) {
                    Log::add('Successfully installed bundled module: mod_migrationnotice', Log::INFO, 'com_cmsmigrator');
                } else {
                    $errors = $moduleInstaller->getErrors();
                    Log::add('Failed to install bundled module: mod_migrationnotice. Errors: ' . implode('; ', $errors), Log::WARNING, 'com_cmsmigrator');
                }
            } catch (\Exception $e) {
                Log::add('Exception during module installation: ' . $e->getMessage(), Log::ERROR, 'com_cmsmigrator');
            }
        } else {
            Log::add('Bundled module not found in any of the expected locations', Log::WARNING, 'com_cmsmigrator');
            Log::add('Available files in source: ' . print_r(scandir($sourcePath), true), Log::INFO, 'com_cmsmigrator');
        }
    }
}
