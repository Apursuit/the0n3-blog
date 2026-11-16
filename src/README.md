# src/ 核心架构

## 架构概览

```
  posts/**/*.md ──→ PostProcessor ──→ posts[]
  pages/**/*.md ──→ PageProcessor ──→ pages[]

  images/ ─┐
  public/ ─┼─→ AssetManager ──→ dist/（静态资源）
  assets/ ─┘
                       │
                       ↓
              ┌─────────────────┐
              │    Generator    │  （编排）
              └────────┬────────┘
                       │
   ┌───────┬───────────┼───────────┬─────────────┐
   ↓       ↓           ↓           ↓             ↓
posts[]  pages[]   tags/cats   search-index   sitemap
   │       │        archives       .json         .xml
   └───────┴───────────┴───────────┴─────────────┘
                       ↓
                  dist/*.html
```

`Generator` 是唯一的编排入口，调用 6 个组件，按 8 步流水线完成构建。模板中的 `.php` 文件由 `Renderer` 统一渲染。

---

## 构建流程（8 步）

| 步骤 | 调用 | 输入 | 输出 |
|------|------|------|------|
| 1. 清空 dist/ | `AssetManager::cleanup()` | — | 空的 `dist/` 目录 |
| 2. 资源映射 | `AssetManager::copy()` | `images/`, `public/`, `assets/` | `dist/images/`, `dist/assets/`, 根目录静态文件 |
| 3. 加载文章 | `PostProcessor::load()` | `posts/**/*.md` | `posts[]`（含 frontMatter，过滤草稿） |
| 4. 加载独立页面 | `PageProcessor::load()` | `pages/**/*.md` | `pages[]`；目录不存在时返回空数组 |
| 5. 校验数据 | `Validator::validate()` | `posts[]` | 通过 / 抛异常（一次列出所有错误） |
| 6. 处理文章数据 | `PostProcessor::prepare()` | `posts[]` | `posts[]`（加 `html` 字段）、`tags[]`、`categories[]`、`archives[]` |
| 7. 生成站点页面 | `PageGenerator::generate()` | 全部数据 | 文章页、独立页、首页分页、标签页、分类页、归档页、404、`sitemap.xml` |
| 8. 搜索索引 | `SearchIndexer::build()` | `posts[]` | `dist/search-index.json` |

步骤之间通过 `posts[]` / `pages[]` 数组传递数据，步骤 3-4 产出原始数据 → 步骤 5 校验 → 步骤 6 补充 HTML 并排序聚合 → 步骤 7-8 消费。

步骤 7 之前会调用 `GitLastmod::init()`：sitemap 的 `lastmod` 取自 git 提交历史，读取失败则中断构建。

---

## 文件职责

### 编排层

**Generator.php**
持有构建流水线 `run()`，按顺序调用各组件的 public 方法，输出每步计时日志。不包含业务逻辑。

### 处理层

**PostProcessor.php**
- `load()`：扫描 Markdown 文件 → `Loader` → `FrontMatter::parse()` → 过滤草稿
- `prepare()`：`Markdown::toHtml()` → 按日期降序排列 → 聚合标签/分类/归档 → 分类与标签按文章数降序排序

**PageProcessor.php**
- `load()`：扫描 `pages/` 下的独立页面（关于、友链等）→ 解析 front matter → 过滤草稿 → permalink 去重
- 目录不存在或路径未配置时直接返回空数组，不阻断构建

**PageGenerator.php**
- `generate()`：渲染所有页面类型（文章、独立页面、首页分页、标签/分类/归档索引及各自的落地页、404），写入 `dist/`
- `generateTaxonomyChildren()`：为每个分类、标签生成独立落地页 `/categories/{名称}/`、`/tags/{名称}/`
- `buildSitemapXml()`：生成 sitemap，只收录文章、独立页面与首页；`lastmod` 取自 git 提交历史

### 基础层

| 文件 | 职责 | 核心方法 |
|------|------|----------|
| `Loader.php` | 递归扫描指定目录下的 `.md` 文件 | `loadPosts(): array` |
| `FrontMatter.php` | 解析 YAML front matter，补默认值 | `parse(string $content, string $sourcePath): array` |
| `Markdown.php` | Markdown → HTML，`[!NOTE]` 等标记转换为 GitHub 原版 alert 结构（`markdown-alert` + 图标 + 英文词，无底色） | `toHtml(string, ?array $imageBases): string` |
| `Renderer.php` | PHP 模板渲染（`ob_start` + `include`） | `render(string $template, array $data): string` |
| `Validator.php` | **批量**校验必填字段、类型、permalink 格式/唯一性/冲突，所有错误一次性报出 | `validate(array $posts): void` |
| `SearchIndexer.php` | 从 HTML 提取 h1-h3 标题，构建搜索 JSON | `build(array $posts, string $distPath): void` |
| `GitLastmod.php` | 一次性读取 `git log` 建立「文件 → 最后提交时间」映射，供 sitemap 的 `lastmod` 使用 | `init(): bool`、`resolve(string): ?int` |
| `Taxonomy.php` | 分类/标签目录名与 URL 的映射（含 percent-encoding 与大小写冲突检测） | `urlPath()`、`dirName()`、`findCaseConflicts()` |

### 工具层

| 文件 | 职责 |
|------|------|
| `Utils.php` | 日志（`log`）、目录复制/删除、日期格式化（`formatDate`）、日期转时间戳（`dateToTimestamp`） |
| `AssetManager.php` | 清空 `dist/`、复制 `assets/` `public/` `images/` 到 dist |

---

## 关键数据结构

所有数据以数组形式在组件间传递，未使用对象封装。

### posts[] 各阶段结构

**步骤 3 后（`PostProcessor::load`）**
```php
[
    'sourcePath'  => 'posts/01.network/some-post.md',
    'rawContent'  => "---\ntitle: ...\n---\n正文 Markdown",
    'frontMatter' => [
        'title'      => 'string',
        'date'       => 'string|int|DateTime',  // 多种格式会被 Utils::dateToTimestamp 统一处理
        'permalink'  => '/posts/some-slug/',     // 必须以 / 开头 / 结尾
        'tags'       => ['a', 'b'],
        'categories' => ['x'],                   // 字符串会自动包装为数组
        'pin'        => 0,                       // 0-3，首页置顶优先级
        'draft'      => false,                   // true 的文章在 load() 阶段被过滤
        'sidebar'    => true,
    ],
    'content'     => 'Markdown 正文部分',
]
```

**步骤 5 后（`PostProcessor::prepare`）**

上述结构增加 `'html'` 字段：
```php
    'html' => '<p>渲染后的 HTML 字符串</p>',
```

### 聚合数据返回格式

`PostProcessor::prepare()` 返回：
```php
[
    'posts'      => posts[],        // 已含 html 字段，按日期降序
    'tags'       => [               // key 为标签名，value 为该标签下的 posts[]
        '标签A' => [post, post, ...],
        '标签B' => [post, ...],
    ],
    'categories' => [               // 结构同上
        '分类X' => [post, ...],
    ],
    'archives'   => [               // key 为年份，内部按日期降序
        2026 => [post, post, ...],
        2025 => [post, ...],
    ],
]
```

### 校验错误格式

`Validator` 在发现问题时逐一追加到 `errors[]`，最后统一抛出：
```
发现 N 个错误:
posts/a.md: 缺少 title
posts/b.md: tags 必须为数组
重复 permalink '/posts/x/': posts/c.md, posts/d.md
posts/e.md: permalink '/tags/' 与系统页面 '/tags' 冲突
posts/f.md: permalink '/CNAME/' 与 public 资源 '/CNAME' 冲突
```

---

## 入口与配置

**CLI 入口** `main.php`：
```bash
php main.php new "文章标题"    # 生成新文章模板
php main.php build             # 运行构建
```

**构建配置** 在 `main.php` 的 `$config` 中指定各项路径，站点元信息在 `config/site.php`。

**模板** 在 `templates/` 目录，共 9 个文件：
`layout.php`（全局布局）、`post.php`、`page.php`（独立页面）、`index.php`（首页分页）、`tags.php`、`categories.php`、`taxonomy.php`（分类/标签落地页）、`archive.php`、`404.php`

模板内通过数组访问数据：`$post['frontMatter']['title']`、`$post['html']` 等。模板改动不会影响 `src/` 下的逻辑。
