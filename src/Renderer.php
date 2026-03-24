<?php

namespace App;

class Renderer
{
    private $templatesPath;

    // 管理模板目录路径。
    public function __construct(string $templatesPath)
    {
        $this->templatesPath = $templatesPath;
    }

    /**
        * 渲染模板文件并返回 HTML 字符串。
     * @param string $template
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function render(string $template, array $data = []): string
    {
        $templateFile = $this->templatesPath . '/' . $template . '.php';
        if (!file_exists($templateFile)) {
            throw new \Exception("Template '{$templateFile}' not found.");
        }

        extract($data);

        ob_start();
        include $templateFile;
        return ob_get_clean();
    }
}
