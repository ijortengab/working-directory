<?php

namespace IjorTengab;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Menjadikan CWD (Current Working Directory) sebagai sebuah object.
 */
class WorkingDirectory
{
    const MKDIR_MODE = 0777;
    const MKDIR_RECURSIVE = true;

    protected $working_directory;

    public $log;

    protected $files;

    public function __construct($dir = null, LoggerInterface $log = null)
    {
        // Init log.
        if (null === $log) {
            $this->log = new NullLogger;
        }
        else {
            $this->log = $log;
        }

        if (null !== $dir) {
            // Saat baru dibuat kita tidak perlu autocreate.
            return $this->chDir($dir, false);
        }
        $this->working_directory = getcwd();
    }

    /**
     * Return working directory.
     */
    public function getCwd()
    {
        return $this->working_directory;
    }

    /**
     * Check file (include dir or link) is exists.
     */
    public function file_exists($filename)
    {
        return file_exists($this->getAbsolutePath($filename));
    }

    /**
     * Jika parameter filename merupakan relative path, maka
     * otomatis akan di daftarkan ke register
     */
    public function getAbsolutePath($filename)
    {
        if ($this->isRelativePath($filename)) {
            $filename = $this->working_directory . DIRECTORY_SEPARATOR . $filename;
        }
        return $filename;
    }

    /**
     *
     */
    public function getRelativePath($filename)
    {
        $direktori = $this->working_directory;
        $filename = trim($filename);
        if ($direktori == substr($filename, 0, strlen($direktori))) {
            if ($relative = substr($filename, strlen($direktori))) {
                return ltrim($relative, '\\/');
            }
        }
        return false;
    }

    /**
     * $filename bisa path relative parent menggunakan karakter ".."
     * contoh: $filename = '../file.txt';
     * $filename bisa absolute path asal posisi file berada didalam
     * working direktori.
     * contoh: $this->working_direktori = '/home/ijortengab';
     * jika: $filename = '/home/ijortengab/file.txt'; maka berhasil
     * ter-register
     * jika: $filename = '/home/guest/file.txt'; maka gagal ter-register
     */
    public function addFile($filename)
    {
        // Hanya untuk relative path.
        if ($this->isRelativePath($filename)) {
            $this->register($filename);
        }
        elseif ($relative = $this->getRelativePath($filename)) {
            $this->register($relative);
        }
    }

    /**
     * Ganti working direktori, otomatis mencoba
     * autocreate jika direktori tidak exists.
     * Kegagalan autocreate menyebabkan direktori
     * kembali seperti semula atau diset sesuai cwd-nya PHP.
     *
     * Perbedaan method ini ::chDir() dengan fungsi PHP chdir()
     * yakni, fungsi chdir() otomatis mengubah path menjadi
     * realpath(), semantara method ini tidak.
     */
    public function chDir($dir, $autocreate = true)
    {
        try {
            $_ = DIRECTORY_SEPARATOR;
            $cwd = $this->working_directory;
            if (null === $cwd) {
                $cwd = getcwd();
            }
            $dir = trim($dir);
            $dir = rtrim($dir, '\\/');
            if ($this->isRelativePath($dir)) {
                $dir = $cwd . $_ . $dir;
            }
            if ($autocreate && $this->prepareDirectory($dir, $this->log) === false) {
                throw new \Exception;
            }
            // Finish.
            $this->working_directory = $dir;

            // Move Follower.
            $this->moveRegisteredFiles($cwd, $dir);
        }
        catch (\Exception $e) {
            $message = $e->getMessage();
            if (null === $this->working_directory) {
                $this->working_directory = getcwd();
            }
            $this->log->error('Change Directory failed.', ['cwd' => getcwd()]);
            $this->log->notice('Working Direktori kembali ke semula: {cwd}', ['cwd' => $this->working_directory]);
        }
    }

    public function getRegisteredFiles()
    {
        return $this->files;
    }
    
    /**
     *
     */
    protected function moveRegisteredFiles($old, $new)
    {
        $files = $this->files;
        if (!empty($files)) {
            $move_files = [];
            foreach ($files as $file) {
                if (file_exists($old . DIRECTORY_SEPARATOR . $file)) {
                    $move_files[] = $file;
                }
            }
            if (!empty($move_files)) {
                $this->movingFiles($old, $new, $move_files);
            }
        }
    }

    /**
     *
     */
    protected function register($filename)
    {
        $this->files[] = $filename;
    }

    /**
     * Memindahkan keseluruhan files dari direktori lama ke direktori baru.
     * parameter $files adalah array dengan path relative.
     */
    public static function movingFiles($old, $new, $files, LoggerInterface $log = null)
    {
        $moved = [];
        foreach ($files as $file) {
            $oldpath = $old . DIRECTORY_SEPARATOR . $file;
            $newpath = $new . DIRECTORY_SEPARATOR . $file;
            if (self::prepareDirectory(dirname($newpath), $log) && self::rename($oldpath, $newpath)) {
                $moved[] = $file;
            }
        }
        $count_files = count($files);
        $count_moved = count($moved);
        $count_diff = abs($count_files - $count_moved);
        if ($count_diff != 0) {
            $diff = array_diff($files, $moved);
            $context = [
                'count' => $count_diff,
                'files' => implode(', ', $diff),
            ];
            null === $log or $log->error('Sebanyak {count} file gagal dipindahkan: {files}', $context);
        }
        $context = ['count' => $count_moved];
        null === $log or $log->notice('Berhasil memindahkan {count} file.', $context);
    }

    /**
     * Support log for rename().
     */
    public static function rename($old, $new, LoggerInterface $log = null)
    {
        $result = @rename($old, $new);
        if ($result) {
            null === $log or $log->notice('Success moving file from {old} to {new}', ['old' => $old, 'new' => $new]);
        }
        else {
            null === $log or $log->error('Failed moving file from {old} to {new}', ['old' => $old, 'new' => $new]);
        }
        return $result;
    }

    /**
     * Membuat direktori dan pastikan dapat ditulis.
     * return boolean.
     */
    public static function prepareDirectory($dir, LoggerInterface $log = null)
    {
        try {
            // Contoh direktori yang tidak dapat ditulis pada windows 7
            // adalah "C:\System Volume Information"
            if (is_dir($dir)) {
                if (is_writable($dir)) {
                    return true;
                }
                else {
                    throw new \Exception('Direktori tidak dapat ditulis.');
                }
            }
            if (file_exists($dir)) {
                $something = 'something';
                if (is_link($dir)) {
                    $something = 'link';
                }
                elseif (is_file($dir)) {
                    $something = 'file';
                }
                $error = "A $something has same name and exists.";
                throw new \Exception($error);
            }
            // Contoh direktori yang berhasil lolos sampai baris ini
            // pada windows 7 adalah direktori C:\Users\{User}\PrintHood
            if (@mkdir($dir, self::MKDIR_MODE, self::MKDIR_RECURSIVE) === false) {
                throw new \Exception('Create directory failed.');
            }
            null === $log or $log->notice('Direktori berhasil dibuat: {dir}.', ['dir' => $dir]);

            // Wajib return true;
            return true;
        }
        catch (\Exception $e) {
            $context = [
                'directory' => $dir,
                'message' => $e->getMessage(),
            ];
            null === $log or $log->error('Gagal menyiapkan direktori "{directory}": {message}', $context);
            return false;
        }
    }

    /**
     *
     */
    public static function isRelativePath($path)
    {
        $path = trim($path);
        if (dirname($path) == '.') {
            return true;
        }
        if (in_array(substr($path, 0, 1), ['/', '\\'])) {
            return false;
        }
        if (substr(PHP_OS, 0, 3) == 'WIN' && preg_match('/^[a-zA-Z]\:/', $path)) {
            return false;
        }
        return true;
    }
}
