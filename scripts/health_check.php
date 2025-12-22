<?php
// scripts/health_check.php

$rootDir = dirname(__DIR__);
$report = [];
$errors = 0;
$warnings = 0;

function addReport($type, $message, $file = null) {
    global $report, $errors, $warnings;
    $entry = strtoupper($type) . ": " . $message;
    if ($file) {
        $entry .= " in " . str_replace(dirname(__DIR__) . DIRECTORY_SEPARATOR, '', $file);
    }
    $report[] = $entry;
    if ($type === 'Error') $errors++;
    if ($type === 'Warning') $warnings++;
}

function checkPhpSyntax($file) {
    $output = [];
    $returnVar = 0;
    // Use -l for syntax check
    $phpPath = "C:\\xampp\\php\\php.exe";
    exec("\"$phpPath\" -l \"$file\" 2>&1", $output, $returnVar);
    if ($returnVar !== 0) {
        // Filter out the standard "Errors parsing..." line to get the actual error
        $msg = implode(" ", array_filter($output, function($line) {
            return strpos($line, 'No syntax errors') === false;
        }));
        addReport('Error', "PHP Syntax Error: " . trim($msg), $file);
    }
}

function checkJsonValidity($file) {
    $content = file_get_contents($file);
    if (trim($content) === '') {
        addReport('Warning', "Empty JSON file", $file);
        return;
    }
    json_decode($content);
    if (json_last_error() !== JSON_ERROR_NONE) {
        addReport('Error', "Invalid JSON: " . json_last_error_msg(), $file);
    }
}

function scanDirectory($dir) {
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($item === '.git' || $item === '.vscode' || $item === 'vendor' || $item === 'node_modules') continue; // Skip large/irrelevant folders

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            scanDirectory($path);
        } else {
            // File checks
            if (!is_readable($path)) {
                addReport('Error', "File not readable", $path);
                continue;
            }

            if (filesize($path) === 0) {
                addReport('Warning', "File is empty", $path);
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($ext === 'php') {
                checkPhpSyntax($path);
            } elseif ($ext === 'json') {
                checkJsonValidity($path);
            }
        }
    }
}

echo "Starting Deep Health Check on $rootDir...\n";
scanDirectory($rootDir);

echo "\n--- Health Check Report ---\n";
if (empty($report)) {
    echo "No issues found. System looks healthy.\n";
} else {
    foreach ($report as $line) {
        echo $line . "\n";
    }
}

echo "\nSummary: $errors Errors, $warnings Warnings found.\n";
?>
