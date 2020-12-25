<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Vector;

class IO implements ILib
{
    public function register(Env $env): void
    {
        $env->set('DIRSEP', \DIRECTORY_SEPARATOR);
        $env->set('HOME', $_SERVER['HOME'] . \DIRECTORY_SEPARATOR);

        if (php_sapi_name() == 'cli') {
            // Define standard I/O streams
            $env->set('STDIN', STDIN);
            $env->set('STDOUT', STDOUT);
            $env->set('STDERR', STDERR);
        }

        $env->set('wd', new CoreFunc('wd', 'Get the current working directory.', 0, 0,
            fn () => getcwd() . \DIRECTORY_SEPARATOR
        ));

        $env->set('chdir', new CoreFunc('chdir', 'Change working directory.', 1, 1,
            fn (string $dir) => chdir($dir)
        ));

        $env->set('file?', new CoreFunc('file?', 'Check if file (or directory) exists.', 1, 1,
            fn (string $filename) => file_exists($filename)
        ));

        $env->set('dir?', new CoreFunc('dir?', 'Check if directory exists and is not a file.', 1, 1,
            fn (string $dir) => is_dir($dir)
        ));

        $env->set('tty?', new CoreFunc('tty?', 'Return true if the given file descriptor is a TTY.', 1, 1,
            fn ($stream) => stream_isatty($stream)
        ));

        $env->set('fsize', new CoreFunc('fsize', 'Return the size of a file.', 1, 1,
            fn (string $file) => @filesize($file)
        ));

        $env->set('ftime', new CoreFunc('ftime', 'Return the last modification time of a file.', 1, 1,
            fn (string $file) => @filemtime($file)
        ));

        $env->set('ftouch', new CoreFunc('ftouch', 'Set the modification time of a file to the given value.', 1, 2,
            fn (string $file, ?int $time = null) => @touch($file, $time !== null ? $time : time())
        ));

        $env->set('fperms', new CoreFunc('fperms', 'Get the permissions of a file. Permission bits are 1=execute, 2=write, 4=read.', 1, 1,
            function (string $file) {
                $perms = @fileperms($file);

                if ($perms === false) {
                    return null;
                } else {
                    return substr(sprintf('%o', $perms), -4);
                }
            }
        ));

        $env->set('fmod', new CoreFunc('fmod', 'Set the permissions of a file. Permission bits are 1=execute, 2=write, 4=read.', 2, 2,
            fn (string $file, string $perms) => @chmod($file, octdec($perms))
        ));

        $env->set('fown', new CoreFunc('fown', 'Get the owner of a file. Give second argument as true to get the user ID.', 1, 2,
            function (string $file, bool $rawId = false) {
                $id = @fileowner($file);

                if ($id === false) {
                    return null;
                } elseif ($rawId) {
                    return $id;
                } else {
                    $info = posix_getpwuid($id);
                    return $info['name'];
                }
            }
        ));

        $env->set('fgrp', new CoreFunc('fgrp', 'Get the group of a file. Give second argument as true to get the group ID.', 1, 2,
            function (string $file, bool $rawId = false) {
                $id = @filegroup($file);

                if ($id === false) {
                    return null;
                } elseif ($rawId) {
                    return $id;
                } else {
                    $info = posix_getgrgid($id);
                    return $info['name'];
                }
            }
        ));

        $env->set('fcache', new CoreFunc('fcache', 'Clear the cache of file information.', 0, 0,
            fn () => clearstatcache()
        ));

        $env->set('fdel', new CoreFunc('fdel', 'Delete a file.', 1, 1,
            fn (string $file) => @unlink($file)
        ));

        // Simple read/write using file_get_contents and file_put_contents

        $env->set('fget', new CoreFunc('fget', 'Read contents of a file.', 1, 1,
            function (string $filename) {
                return @file_get_contents($filename);
            }
        ));

        $env->set('fput', new CoreFunc('fput', 'Write string (second argument) to file (first argument). Give true as optional third argument to append instead of overwrite.', 2, 3,
            function (string $filename, $data, $append = false) {
                $flags = 0;
                if ($append) {
                    $flags = \FILE_APPEND;
                }

                $result = @file_put_contents($filename, $data, $flags);

                return $result !== false;
            }
        ));

        // Functions for working with file descriptors

        $env->set('fopen', new CoreFunc('fopen', 'Open a file for reading or writing. Give mode as second argument.', 2, 2,
            fn ($file, $mode) => @fopen($file, $mode)
        ));

        $env->set('fclose', new CoreFunc('fclose', 'Close a file resource.', 1, 1,
            fn ($handle) => @fclose($handle)
        ));

        $env->set('fread', new CoreFunc('fread', 'Read from a file resource. Give length in bytes as second argument.', 2, 2,
            fn ($handle, $length) => @fread($handle, $length)
        ));

        $env->set('fwrite', new CoreFunc('fwrite', 'Write to a file resource.', 2, 2,
            fn ($handle, $data) => @fwrite($handle, $data)
        ));

        $env->set('fflush', new CoreFunc('fflush', 'Persist buffered writes to disk for a file resource.', 1, 1,
            fn ($handle) => @fflush($handle)
        ));

        $env->set('feof?', new CoreFunc('feof?', 'Return true if end of file has been reached for a file resource.', 1, 1,
            fn ($handle) => @feof($handle)
        ));

        // Functions for listing files

        $env->set('glob', new CoreFunc('glob', 'Search for files and directories that match a pattern.', 1, 1,
            function (string $pattern) {
                $result = @glob($pattern);

                if ($result === false) {
                    return null;
                } else {
                    return new Vector($result);
                }
            }
        ));

        $env->set('read-dir', new CoreFunc('read-dir', 'Read the contents of a directory.', 1, 1,
            function (string $dir) {
                $result = @scandir($dir);

                if ($result === false) {
                    return null;
                } else {
                    return new Vector($result);
                }
            }
        ));

        // Readline support
        if (extension_loaded('readline')) {
            $env->set('readline', new CoreFunc('readline', 'Read line of user input.', 0, 1,
                fn ($prompt = null) => readline($prompt)
            ));

            $env->set('readline-add', new CoreFunc('readline-add', 'Add new line of input to history.', 1, 1,
                fn (string $line) => readline_add_history($line)
            ));

            $env->set('readline-load', new CoreFunc('readline-load', 'Load the history for readline from a file.', 1, 1,
                fn (string $file) => readline_read_history($file)
            ));

            $env->set('readline-save', new CoreFunc('readline-save', 'Save the readline history into a file.', 1, 1,
                fn (string $file) => readline_write_history($file)
            ));
        }
    }
}
