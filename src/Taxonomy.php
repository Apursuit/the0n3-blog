<?php

namespace App;

/**
 * 分类 / 标签的路径工具。
 *
 * 物理目录名保留原始名称（中文原样落盘），URL 只做一次 percent-encoding。
 * 二者是同一个名字的两种表示，全站必须经由本类转换：任何一处漏编码会 404，
 * 任何一处重复编码会把 %E5 变成 %25E5，同样 404。
 */
class Taxonomy
{
    /**
     * 文件系统保留字符。
     * "/" 最危险：会被当成目录分隔符，建成两级目录而不是一个。
     * 其余为 Windows 保留字符，出现即无法创建目录。
     */
    private const ILLEGAL_CHARS = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];

    /**
     * 物理目录名：保留原始名称，只做校验不做归一化。
     * 校验失败直接抛异常中止构建，避免生成一个静默错误或不可访问的页面。
     *
     * @param string $name 原始分类 / 标签名
     * @param string $kind 用于报错时指明类型
     * @return string
     * @throws \Exception
     */
    public static function dirName(string $name, string $kind = '条目'): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw new \Exception("{$kind}名称不能为空");
        }

        if ($trimmed === '.' || $trimmed === '..') {
            throw new \Exception("{$kind}名称不能是「{$trimmed}」，会被解析成上级目录");
        }

        foreach (self::ILLEGAL_CHARS as $char) {
            if (strpos($trimmed, $char) !== false) {
                throw new \Exception("{$kind}「{$name}」含文件系统非法字符「{$char}」，无法生成目录");
            }
        }

        return $trimmed;
    }

    /**
     * URL 路径段：对原始名称做一次 rawurlencode。
     *
     * 必须用 rawurlencode —— urlencode 会把空格转成 "+"，在路径里会被当成字面量，
     * 导致带空格的标签（如 "proc 文件上传"）点进去 404。
     *
     * @param string $name
     * @return string
     * @throws \Exception
     */
    public static function urlPath(string $name): string
    {
        return rawurlencode(self::dirName($name));
    }

    /**
     * 大小写冲突检测。
     *
     * 构建机是 Linux（大小写敏感），本地预览是 Windows（NTFS 不敏感）。
     * SUID 与 suid 在线上是两个独立目录，在本地会写进同一个并静默覆盖。
     * 这里只报告不自动改名，是否合并由作者决定。
     *
     * @param array $names 原始名称列表
     * @return array 形如 [['SUID', 'suid'], ...]
     */
    public static function findCaseConflicts(array $names): array
    {
        $groups = [];
        foreach ($names as $name) {
            $groups[strtolower((string) $name)][] = (string) $name;
        }

        $conflicts = [];
        foreach ($groups as $group) {
            if (count($group) > 1) {
                $conflicts[] = $group;
            }
        }

        return $conflicts;
    }

    /**
     * 标签权重分档：按文章数映射到四档字号。
     *
     * 阈值 15 / 10 / 5 把当前 39 个标签分成 2 / 1 / 7 / 29：
     * 字号只用于区分「主要标签」与「长尾」，3 篇与 4 篇的差异不值得用字号表达。
     * 四档字号比控制在 1.7 倍以内（1.5 / 0.92），避免行高失控与长尾不可读。
     *
     * @param int $count 该标签下的文章数
     * @return string xl | l | m | s
     */
    public static function weightTier(int $count): string
    {
        if ($count >= 15) {
            return 'xl';
        }

        if ($count >= 10) {
            return 'l';
        }

        if ($count >= 5) {
            return 'm';
        }

        return 's';
    }
}
