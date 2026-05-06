<?php

namespace App;

class Generator
{
    private $config;
    private $renderer;
    private $validator;
    private $assetManager;
    private $postProcessor;
    private $pageGenerator;
    private $searchIndexer;

    private $posts = [];
    private $tags = [];
    private $categories = [];
    private $archives = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->renderer = new Renderer($config['templates_path']);

        $publicPaths = [];
        if (is_dir($this->config['public_path'])) {
            $publicDirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->config['public_path'], \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($publicDirIterator as $file) {
                if ($file->isFile()) {
                    $publicPaths[] = '/' . str_replace('\\', '/', $publicDirIterator->getSubPathName());
                }
            }
        }

        if (is_dir($this->config['images_path'])) {
            $imagesDirIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->config['images_path'], \RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($imagesDirIterator as $file) {
                if ($file->isFile()) {
                    $publicPaths[] = '/images/' . str_replace('\\', '/', $imagesDirIterator->getSubPathName());
                }
            }
        }

        $systemPages = ['/tags', '/categories', '/archives'];
        $this->validator = new Validator($publicPaths, $systemPages);
        $this->assetManager = new AssetManager($config);
        $this->postProcessor = new PostProcessor($config);
        $this->pageGenerator = new PageGenerator($this->renderer, $config);
        $this->searchIndexer = new SearchIndexer();
    }

    public function run(): int
    {
        $startTime = microtime(true);

        Utils::log("开始构建...");

        $this->timeStep('1. 清空 dist/', function () {
            $this->assetManager->cleanup();
        });
        $this->timeStep('2. 资源映射', function () {
            $this->assetManager->copy();
        });
        $this->timeStep('3. 解析 Markdown', function () {
            $this->posts = $this->postProcessor->load();
        });
        $this->timeStep('4. 校验数据', function () {
            $this->validator->validate($this->posts);
        });
        $this->timeStep('5. 构建数据索引', function () {
            $result = $this->postProcessor->prepare($this->posts);
            $this->posts = $result['posts'];
            $this->tags = $result['tags'];
            $this->categories = $result['categories'];
            $this->archives = $result['archives'];
        });
        $this->timeStep('6. 渲染页面', function () {
            $this->pageGenerator->generate($this->posts, $this->tags, $this->categories, $this->archives);
        });
        $this->timeStep('7. 生成搜索索引', function () {
            $this->searchIndexer->build($this->posts, $this->config['dist_path']);
        });

        $endTime = microtime(true);
        $buildTime = round($endTime - $startTime, 2);

        Utils::log("构建完成，用时 {$buildTime} 秒。");
        Utils::log(" - 生成文章数：" . count($this->posts));
        Utils::log(" - 标签数：" . count($this->tags));
        Utils::log(" - 分类数：" . count($this->categories));

        $exitCode = 0;
        if ($this->postProcessor->hasParseErrors()) {
            $exitCode = 1;
            $parseErrors = $this->postProcessor->getParseErrors();
            Utils::log("警告：" . count($parseErrors) . " 篇文章解析失败已被跳过，请修复：", 'warning');
            foreach ($parseErrors as $err) {
                Utils::log("  - {$err['file']}: {$err['error']}", 'warning');
            }
        }

        return $exitCode;
    }

    private function timeStep(string $label, callable $task): void
    {
        $start = microtime(true);
        $task();
        $elapsed = round(microtime(true) - $start, 3);
        Utils::log("{$label} 用时 {$elapsed} 秒。");
    }
}
