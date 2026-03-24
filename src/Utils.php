<?php

namespace App;

class Utils
{
    private static ?string $logFile = null;
    private static bool $logToConsole = true;

    /**
    * 初始化日志（支持按天清理与单次构建分隔）。
     */
    public static function initLogging(
        string $logsDir,
        string $fileName = 'build.log',
        bool $append = true,
        bool $logToConsole = true,
        int $retentionDays = 14
    ): void {
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        $path = rtrim($logsDir, '/\\') . DIRECTORY_SEPARATOR . $fileName;
        self::$logFile = $path;
        self::$logToConsole = $logToConsole;

        if (!$append) {
            file_put_contents($path, '');
        }

        if ($retentionDays > 0 && file_exists($path)) {
            $maxAgeSeconds = $retentionDays * 86400;
            $ageSeconds = time() - filemtime($path);
            if ($ageSeconds >= $maxAgeSeconds) {
                file_put_contents($path, '');
            }
        }

        $separator = str_repeat('=', 28);
        $timestamp = date('Y-m-d H:i:s');
        $line = $separator . ' Build start ' . $timestamp . ' ' . $separator . PHP_EOL;
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
    /**
        * 生成用于短链接的 6 位短 ID。
     *
     * @return string
     */
    public static function generateShortId(): string
    {
        return substr(md5(uniqid('', true)), 0, 6);
    }

    /**
        * 统一 Front Matter 中日期输入的格式。
     *
     * Supports:
     * - int (unix timestamp)
     * - DateTimeInterface
     * - string (e.g. 2026-03-21)
     */
    public static function formatDate(mixed $value, string $format = 'Y-m-d'): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        if (is_int($value)) {
            return date($format, $value);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return '';
            }

            if (ctype_digit($trimmed)) {
                return date($format, (int) $trimmed);
            }

            $timestamp = strtotime($trimmed);
            if ($timestamp !== false) {
                return date($format, $timestamp);
            }

            return $trimmed;
        }

        return '';
    }

    /**
        * 递归复制目录到目标位置。
     *
     * @param string $source
     * @param string $destination
     */
    public static function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath);
                }
            } else {
                copy($item, $destPath);
            }
        }
    }

    /**
        * 递归删除目录及其内容。
     *
     * @param string $dirPath
     */
    public static function deleteDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDirectory($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    /**
        * 记录日志到文件与控制台，支持等级。
     *
     * @param string $message
     * @param string $level
     */
    public static function log(string $message, string $level = 'info'): void
    {
        $colors = [
            'info'    => "\033[0;32m", // Green
            'warning' => "\033[1;33m", // Yellow
            'error'   => "\033[0;31m", // Red
            'reset'   => "\033[0m"
        ];

        $levelLabels = [
            'info' => '信息',
            'warning' => '警告',
            'error' => '错误',
        ];

        if (self::$logFile) {
            $timestamp = date('Y-m-d H:i:s');
            $line = "[{$timestamp}] [" . strtoupper($level) . "] {$message}" . PHP_EOL;
            file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
        }

        if (self::$logToConsole) {
            $color = $colors[$level] ?? $colors['info'];
            $timestamp = date('Y-m-d H:i:s');
            $label = $levelLabels[$level] ?? ucfirst($level);
            echo $color . "[{$timestamp}] [{$label}] " . $message . $colors['reset'] . PHP_EOL;
        }
    }
}
