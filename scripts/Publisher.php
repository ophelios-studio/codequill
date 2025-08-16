<?php namespace Scripts;

use Composer\Script\Event;

class Publisher
{
    public static function publish(Event $event): void
    {
        $root = dirname(__DIR__);
        $opheliosVendor = $root . '/vendor/ophelios';

        // Define the source folders to search within the library
        $sourceDirectories = [
            '/backpack/locale/en_CA/pulsar',
            '/backpack/locale/fr_CA/pulsar',
            '/backpack/public/assets/pulsar',
            '/backpack/public/stylesheets/pulsar',
            '/backpack/public/javascripts/pulsar',
            '/backpack/app/Controllers/pulsar',
            '/backpack/app/Models/pulsar',
            '/backpack/app/Views/pulsar',
            '/backpack/sql/pulsar',
        ];

        // Target directories in the project
        $targetDirectories = [
            '/locale/en_CA/pulsar',
            '/locale/fr_CA/pulsar',
            '/public/assets/pulsar',
            '/public/stylesheets/pulsar',
            '/public/javascripts/pulsar',
            '/app/Controllers/pulsar',
            '/app/Models/pulsar',
            '/app/Views/pulsar',
            '/sql/pulsar',
        ];

        $directories = scandir($opheliosVendor);
        foreach ($directories as $directory) {
            if ($directory === '.' || $directory === '..') {
                continue;
            }
            $fullPath = $opheliosVendor . DIRECTORY_SEPARATOR . $directory;
            // Check if it's a directory and starts with 'pulsar-'
            if (is_dir($fullPath) && str_starts_with($directory, 'pulsar-')) {
                echo "Found matching directory: $fullPath" . PHP_EOL;
                $event->getIO()->write("<info>Found Pulsar vendor to publish ($fullPath)</info>");

                foreach ($sourceDirectories as $key => $sourceBase) {
                    $source = $fullPath . $sourceBase;
                    $target = $root . $targetDirectories[$key];
                    if (is_dir($source)) {
                        // Recursively copy the source folder to the target
                        self::recursiveCopy($source, $target);
                        $event->getIO()->write("<info>Copied from $source to $target</info>");
                    } else {
                        $event->getIO()->write("<comment>Source directory $source does not exist. Skipping.</comment>");
                    }
                }
            }
        }
    }

    private static function recursiveCopy($src, $dst): void
    {
        // Create destination directory if it doesn't exist
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        // Loop through every file/folder in the source directory
        foreach (scandir($src) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                // If it's a subdirectory, recursively copy it
                self::recursiveCopy($srcPath, $dstPath);
            } else {
                // Otherwise, copy the file
                copy($srcPath, $dstPath);
            }
        }
    }
}
