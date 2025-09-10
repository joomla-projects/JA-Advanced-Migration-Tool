<?php
// check_syntax.php
// Recursively checks PHP syntax for all .php files in src and tests

$directories = ['src', 'tests'];
$exitCode = 0;
$errors = [];
foreach ($directories as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            $output = [];
            $result = 0;
            exec("php -l " . escapeshellarg($path), $output, $result);
            if ($result !== 0) {
                $errors[] = implode("\n", $output);
                $exitCode = $result;
            }
        }
    }
}
if (count($errors) > 0) {
    echo "Syntax errors found:\n";
    foreach ($errors as $error) {
        echo $error . "\n";
    }
} else {
    echo "No syntax errors found in the directory.\n";
}
exit($exitCode);
