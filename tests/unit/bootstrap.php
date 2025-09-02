<?php
// Autoload dependencies
require __DIR__ . '/../../vendor/autoload.php';

// Load mock classes first
require_once __DIR__ . '/Mocks/PsrMocks.php';
require_once __DIR__ . '/Mocks/JoomlaExtensionMocks.php';
require_once __DIR__ . '/Mocks/JoomlaControllerMocks.php';
require_once __DIR__ . '/Mocks/JoomlaModelMocks.php';
require_once __DIR__ . '/Mocks/JoomlaTableMocks.php';
require_once __DIR__ . '/Mocks/JoomlaMiscMocks.php';

// Define necessary constants for testing
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', realpath(__DIR__ . '/../../'));
}

if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', JPATH_BASE);
}

if (!defined('JPATH_SITE')) {
    define('JPATH_SITE', JPATH_BASE);
}

if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
    define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_BASE . '/src/component/admin');
}

if (!defined('UPLOAD_ERR_OK')) {
    define('UPLOAD_ERR_OK', 0);
}

if (!defined('UPLOAD_ERR_NO_FILE')) {
    define('UPLOAD_ERR_NO_FILE', 4);
}

// Set up autoloader for component classes
spl_autoload_register(function ($class) {
    $prefix = 'Binary\\Component\\CmsMigrator\\Administrator\\';
    $baseDir = __DIR__ . '/../../src/component/admin/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Set up autoloader for test helper classes
spl_autoload_register(function ($class) {
    $prefix = 'Binary\\Component\\CmsMigrator\\Tests\\';
    $baseDir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Optionally mock Joomla framework (from joomla/test) if available
if (class_exists('\Joomla\Test\Stubs\StubsLoader')) {
    \Joomla\Test\Stubs\StubsLoader::register();
}
