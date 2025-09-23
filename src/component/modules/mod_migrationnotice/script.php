<?php
/**
 * @package     Migration Notice Module
 * @subpackage  mod_migrationnotice
 * @copyright   Copyright (C) 2025 Joomla Academy. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Installer script for mod_wpmigrationnotice
 *
 * Creates/updates language override files for the three login messages
 * and leaves them in place on uninstall.
 */
class mod_migrationnoticeInstallerScript
{
    /**
     * Called on install
     */
    public function install($parent)
    {
        $this->createOverrides();
    }

    /**
     * Called on update
     */
    public function update($parent)
    {
        $this->createOverrides();
    }

    /**
     * Uninstall: intentionally leave overrides in place (permanent)
     */
    public function uninstall($parent)
    {
        Factory::getApplication()->enqueueMessage('Module uninstalled. Language overrides remain in place.', 'message');
    }

    /**
     * Create or update overrides for site and administrator (en-GB)
     */
    private function createOverrides()
    {
        $langTag = 'en-GB';

        // The custom message you want shown instead of the default login error
        $message = "Your site has been migrated from WordPress. For security, existing WordPress passwords cannot be used in Joomla. Please reset your password using the 'Forgot Password' link.";

        // Constants to override
        $constants = [
            'JLIB_LOGIN_AUTHENTICATE',
            'JGLOBAL_AUTH_INVALID_PASS',
        ];

        // Write overrides for both site and admin
        $targets = [
            'site' => JPATH_ROOT . '/language/overrides',
            'administrator' => JPATH_ROOT . '/administrator/language/overrides'
        ];

        foreach ($targets as $scope => $dir) {
            $result = $this->writeOverrideFile($dir, $langTag, $constants, $message);
            if ($result !== true) {
                Factory::getApplication()->enqueueMessage('Failed to create/update overrides in ' . $scope . ' path: ' . $dir . '. ' . $result, 'warning');
            } else {
                Factory::getApplication()->enqueueMessage('Language overrides created/updated for ' . $scope . ' (' . $langTag . ').', 'message');
            }
        }

        // Best-effort clear cache
        try {
            $cache = Factory::getContainer()->get('cache');
            if ($cache) {
                $cache->clean();
            }
        } catch (Throwable $e) {
            // Not critical
        }
    }

    /**
     * Write or update the override file for a specific directory + language
     *
     * @param string $overrideDir Directory path (without trailing slash)
     * @param string $langTag     Language tag like en-GB
     * @param array  $constants   List of language constants to set
     * @param string $message     Message string to set
     *
     * @return true|string  True on success or error message string
     */
    private function writeOverrideFile($overrideDir, $langTag, array $constants, $message)
    {
        // Ensure directory exists
        if (!is_dir($overrideDir)) {
            if (!mkdir($overrideDir, 0755, true) && !is_dir($overrideDir)) {
                return 'Cannot create directory: ' . $overrideDir;
            }
        }

        $overrideFile = rtrim($overrideDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $langTag . '.override.ini';

        // Read existing content (if any)
        $contents = '';
        if (is_file($overrideFile)) {
            $contents = @file_get_contents($overrideFile);
            if ($contents === false) {
                return 'Cannot read existing override file: ' . $overrideFile;
            }
        }

        // Build/modify contents: for each constant, either replace existing line or append
        $newContents = $contents;

        foreach ($constants as $const) {
            // Prepare safe message - escape double quotes
            $safeMessage = str_replace('"', '\"', $message);
            $line = $const . '="' . $safeMessage . '"';

            // Pattern: start of line, optional whitespace, constant, = "...", end of line (multiline)
            $pattern = '/^' . preg_quote($const, '/') . '\s*=\s*".*?"\s*$/m';

            if (preg_match($pattern, $newContents)) {
                $newContents = preg_replace($pattern, $line, $newContents);
            } else {
                // append; ensure there's exactly one trailing newline before append
                $newContents = rtrim($newContents) . PHP_EOL . $line . PHP_EOL;
            }
        }

        // Write file atomically
        $tmpFile = $overrideFile . '.tmp';
        $written = @file_put_contents($tmpFile, $newContents, LOCK_EX);
        if ($written === false) {
            // fallback: try direct write (some hosts block temp file creation)
            if (@file_put_contents($overrideFile, $newContents, LOCK_EX) === false) {
                @unlink($tmpFile);
                return 'Failed to write override file: ' . $overrideFile;
            } else {
                @chmod($overrideFile, 0644);
                return true;
            }
        }

        // replace original
        if (!@rename($tmpFile, $overrideFile)) {
            // attempt copy+unlink if rename fails
            if (!@copy($tmpFile, $overrideFile) || !@unlink($tmpFile)) {
                @unlink($tmpFile);
                return 'Failed to move temporary override file into place: ' . $overrideFile;
            }
        }

        @chmod($overrideFile, 0644);

        return true;
    }
}
