<?php

namespace App;

/**
 * 从 git 提交历史读取源文件最后一次变更的时间。
 *
 * 仅用于 sitemap 的 <lastmod>；front matter 的 date 不受影响，
 * 仍负责文章排序、页面显示与归档分组。
 */
final class GitLastmod
{
    /** @var array<string,int>|null 仓库相对路径 => Unix 时间戳 */
    private static ?array $map = null;

    /** 映射是否可用（git 可执行且至少读到一条记录） */
    private static bool $ready = false;

    /**
     * 载入 git 历史映射，进程内只执行一次。
     *
     * @return bool 失败时返回 false，调用方应中断构建
     */
    public static function init(): bool
    {
        if (self::$map !== null) {
            return self::$ready;
        }
        self::$map = [];

        exec(
            'git -C ' . escapeshellarg(self::repoRoot())
            . ' -c core.quotepath=false log'
            . ' --pretty=format:__C__%ct --name-only -- posts/ pages/',
            $lines,
            $code
        );

        // 退出码非 0 表示 git 不可用、不在仓库内或仓库损坏
        if ($code !== 0) {
            return false;
        }

        $current = null;
        foreach ($lines as $line) {
            if (str_starts_with($line, '__C__')) {
                $current = (int) substr($line, 5);
                continue;
            }
            $line = trim($line);
            // git log 按提交时间倒序输出，首次遇到的文件即为最新一次变更
            if ($line !== '' && $current !== null && !isset(self::$map[$line])) {
                self::$map[$line] = $current;
            }
        }

        self::$ready = self::$map !== [];
        return self::$ready;
    }

    /**
     * 按源文件绝对路径查询最后提交时间。
     *
     * @return int|null Unix 时间戳；未提交或未命中返回 null
     */
    public static function resolve(string $sourcePath): ?int
    {
        if (!self::$ready) {
            return null;
        }
        $relative = self::toRelative($sourcePath);
        return $relative !== null ? (self::$map[$relative] ?? null) : null;
    }

    /**
     * 把绝对路径归一化为仓库相对路径，截断到 posts/ 或 pages/ 起始处。
     */
    private static function toRelative(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        // 取最后一次出现的位置，避免上级目录恰好也叫 posts/pages 时截错
        $pos = strrpos($path, '/posts/');
        if ($pos === false) {
            $pos = strrpos($path, '/pages/');
        }

        return $pos === false ? null : substr($path, $pos + 1);
    }

    private static function repoRoot(): string
    {
        return dirname(__DIR__);
    }
}
