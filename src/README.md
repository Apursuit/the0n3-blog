# src/ 核心架构

## 架构概览

```
                         ┌──────────────────┐
  posts/*.md  ─────────→│  PostProcessor   │──→ posts[]
                         └────────┬─────────┘
                                  │
  images/  ───┐                   │
  public/  ───┼─→ AssetManager    │
  assets/  ───┘                   │
                                  ↓
                         ┌──────────────────┐
                         │    Generator     │  (编排)
                         └────────┬─────────┘
                                  │
               ┌──────────────────┼──────────────────┐
               ↓                  ↓                  ↓
        PageGenerator        Validator        SearchIndexer
               │
               ↓
        dist/*.html
        dist/sitemap.xml
        dist/search-index.json
```

`Generator` 是唯一的编排入口，调用 6 个组件，按 7 步流水线完成构建。模板 7 个 `.php` 文件由 `Renderer` 统一渲染。

---

## 构建流程（7 步）

| 步骤 | 调用 | 输入 | 输出 |
|------|------|------|------|
| 1. 清空 dist/ | `AssetManager::cleanup()` | — | 空的 `dist/` 目录 |
| 2. 资源映射 | `AssetManager::copy()` | `images/`, `public/`, `assets/` | `dist/images/`, `dist/assets/`, 根目录静态文件 |
| 3. 解析 Markdown | `PostProcessor::load()` | `posts/**/*.md` | `posts[]`（含 frontMatter，过滤草稿） |
| 4. 校验数据 | `Validator::validate()` | `posts[]` | 通过 / 抛异常（一次列出所有错误） |
| 5. 构建索引 | `PostProcessor::prepare()` | `posts[]` | `posts[]`（加 `html` 字段）、`tags[]`、`categories[]`、`archives[]` |
| 6. 渲染页面 | `PageGenerator::generate()` | 全部数据 | 文章页、首页分页、标签页、分类页、归档页、404、sitemap.xml |
| 7. 搜索索引 | `SearchIndexer::build()` | `posts[]` | `dist/search-index.json` |

步骤之间通过 `posts[]` 数组传递数据，步骤 3 产出原始文章 → 步骤 4 校验 → 步骤 5 补充 HTML 并排序聚合 → 步骤 6-7 消费。

---

## 文件职责

### 编排层

**Generator.php**
持有构建流水线 `run()`，按顺序调用各组件的 public 方法，输出每步计时日志。不包含业务逻辑。

### 处理层

**PostProcessor.php**
- `load()`：扫描 Markdown 文件 → `Loader` → `FrontMatter::parse()` → 过滤草稿
- `prepare()`：`Markdown::toHtml()` → 按日期降序排列 → 聚合标签/分类/归档

**PageGenerator.php**
- `generate()`：渲染所有页面类型（文章、首页分页、标签、分类、归档、404），写入 `dist/`
- 内置 `buildSitemapXml()` 生成 sitemap

### 基础层

| 文件 | 职责 | 核心方法 |
|------|------|----------|
| `Loader.php` | 递归扫描 `posts/` 下的 `.md` 文件 | `loadPosts(): array` |
| `FrontMatter.php` | 解析 YAML front matter，补默认值 | `parse(string $content, string $sourcePath): array` |
| `Markdown.php` | Markdown → HTML，`[!NOTE]` 等 callout 转换为 `<div class="callout">` | `toHtml(string): string` |
| `Renderer.php` | PHP 模板渲染（`ob_start` + `include`） | `render(string $template, array $data): string` |
| `Validator.php` | **批量**校验必填字段、类型、permalink 格式/唯一性/冲突，所有错误一次性报出 | `validate(array $posts): void` |
| `SearchIndexer.php` | 从 HTML 提取 h1-h3 标题，构建搜索 JSON | `build(array $posts, string $distPath): void` |

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

**模板** 在 `templates/` 目录，共 7 个文件：
`layout.php`（全局布局）、`post.php`、`index.php`（首页分页）、`tags.php`、`categories.php`、`archive.php`、`404.php`

模板内通过数组访问数据：`$post['frontMatter']['title']`、`$post['html']` 等。模板改动不会影响 `src/` 下的逻辑。
