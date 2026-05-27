<?php

namespace App;

class Generator
{
    private array $config;
    private Renderer $renderer;
    private Validator $validator;
    private AssetManager $assetManager;
    private PostProcessor $postProcessor;
    private PageProcessor $pageProcessor;
    private PageGenerator $pageGenerator;
    private SearchIndexer $searchIndexer;

    private array $posts = [];
    private array $pages = [];
    private array $tags = [];
    private array $categories = [];
    private array $archives = [];
    private array $publicPaths = [];
    private array $systemPages = [];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->renderer = new Renderer($config['templates_path']);
        $this->publicPaths = $this->collectPublicPaths();
        $this->systemPages = ['/tags', '/categories', '/archives'];

        $this->validator = new Validator($this->publicPaths, $this->systemPages);
        $this->assetManager = new AssetManager($config);
        $this->postProcessor = new PostProcessor($config);
        $this->pageProcessor = new PageProcessor($config, $this->publicPaths, $this->systemPages);
        $this->pageGenerator = new PageGenerator($this->renderer, $config);
        $this->searchIndexer = new SearchIndexer();
    }

    public function run(): int
    {
        $startTime = microtime(true);

        Utils::log('开始构建站点');

        $this->timeStep('1. 清理输出目录：dist/', '1. 输出目录清理完成', function () {
            $this->assetManager->cleanup();
        });
        $this->timeStep('2. 复制静态资源：assets/, public/, images/', '2. 静态资源复制完成', function () {
            $this->assetManager->copy();
        });
        $this->timeStep('3. 加载文章：posts/', '3. 文章加载完成', function () {
            $this->posts = $this->postProcessor->load();
        });
        $this->timeStep('4. 加载独立页面：pages/', '4. 独立页面加载完成', function () {
            $this->pages = $this->pageProcessor->load();
        });
        $this->timeStep('5. 校验文章：必填字段、permalink、路径冲突', '5. 文章校验完成', function () {
            $pagePermalinks = array_map(function ($page) {
                return rtrim($page['frontMatter']['permalink'] ?? '', '/');
            }, $this->pages);
            $systemPages = array_values(array_unique(array_merge($this->systemPages, $pagePermalinks)));
            $this->validator = new Validator($this->publicPaths, $systemPages);
            $this->posts = $this->validator->validate($this->posts);
        });
        $this->timeStep('6. 处理文章数据：Markdown、标签、分类、归档', '6. 文章数据处理完成', function () {
            $result = $this->postProcessor->prepare($this->posts);
            $this->posts = $result['posts'];
            $this->tags = $result['tags'];
            $this->categories = $result['categories'];
            $this->archives = $result['archives'];
        });
        $this->timeStep('7. 生成站点页面：HTML、分页、sitemap', '7. 站点页面生成完成', function () {
            $this->pageGenerator->generate($this->posts, $this->pages, $this->tags, $this->categories, $this->archives);
        });
        $this->timeStep('8. 生成搜索索引：search-index.json', '8. 搜索索引生成完成', function () {
            $this->searchIndexer->build($this->posts, $this->config['dist_path']);
        });

        $buildTime = round(microtime(true) - $startTime, 2);

        $this->logBuildSummary($buildTime);

        $exitCode = 0;
        if ($this->postProcessor->hasParseErrors()) {
            $exitCode = 1;
            foreach ($this->postProcessor->getParseErrors() as $err) {
                Utils::log("  - {$err['file']}: {$err['error']}", 'warning');
            }
        }

        if ($this->pageProcessor->hasErrors()) {
            $exitCode = 1;
            foreach ($this->pageProcessor->getErrors() as $err) {
                Utils::log("  - {$err}", 'warning');
            }
        }

        if ($this->validator->hasErrors()) {
            $exitCode = 1;
            foreach ($this->validator->getErrors() as $err) {
                Utils::log("  - {$err}", 'warning');
            }
        }

        return $exitCode;
    }

    private function collectPublicPaths(): array
    {
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

        return $publicPaths;
    }

    private function timeStep(string $startLabel, string $doneLabel, callable $task): void
    {
        Utils::log($startLabel);
        $start = microtime(true);
        $task();
        $elapsed = number_format(microtime(true) - $start, 2);
        Utils::log("{$doneLabel}，用时 {$elapsed} 秒");
    }

    private function logBuildSummary(float $buildTime): void
    {
        $elapsed = number_format($buildTime, 2);

        Utils::log("构建完成，用时 {$elapsed} 秒");
        Utils::log(
            '构建结果：文章 ' . count($this->posts) . ' 篇，' .
            '独立页面 ' . count($this->pages) . ' 个，' .
            '标签 ' . count($this->tags) . ' 个，' .
            '分类 ' . count($this->categories) . ' 个'
        );
        Utils::log('输出目录：dist/');
        Utils::log('本地预览：php -S localhost:8000 -t dist/');
    }
}
