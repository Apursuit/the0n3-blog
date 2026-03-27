# the0n3 blog

一个基于 Markdown 的极简博客系统，专注 Markdown 写作，自动生成静态站点并支持一键部署。

👉 在线演示：https://blog.the0n3.top/

浅色主题：

![](/images/blog-deployment/light.png)

深色主题：

![](/images/blog-deployment/dark.png)


## ✨ 特性

### 界面与体验
- 🌗 支持深浅主题切换
- 🧭 向上滚动自动显示导航栏
- 📖 阅读进度条与目录联动高亮
- 🧩 文章页可选目录（基于 h2/h3 自动生成）
- 🖼️ 图片增强（懒加载 + 点击放大）

### 写作与内容
- 📝 使用带 YAML Front Matter 的 Markdown 作为文章源
- 🏷️ 自动生成首页、标签页、分类页、归档页、文章详情页
- 💡 Callout 语法支持（[!NOTE] / [!TIP] 等）

### 构建与部署
- 🚀 GitHub Actions 自动构建并部署到 gh-pages 分支
- 📦 构建过程写入 logs/build.log
- 🗺️ 自动生成 dist/sitemap.xml（基于 config/site.php）

### 其他
- 💬 Giscus 评论系统支持（需自行配置 repo 等参数）
- 🎨 Prism 代码高亮


## 🚀快速上手

### 环境要求

- PHP ≥ 7.4
- Composer
- git

打开 PowerShell（建议以普通用户运行），执行：

过程中会提示是否修改执行策略，输入 Y 确认。

```powershell
Set-ExecutionPolicy RemoteSigned -Scope CurrentUser
irm get.scoop.sh | iex
```

安装 PHP 和 Composer

```powershell
scoop install php composer git
```

![](/images/blog-deployment/0.png)

安装完成后，验证是否成功：

```powershell
php -v
composer -V
git -v
```

![](/images/blog-deployment/01.png)

### 克隆项目到本地：

```bash
git clone https://hk.gh-proxy.org/https://github.com/Apursuit/the0n3-blog-template.git
cd the0n3-blog-template
```


### 安装依赖

composer 依赖安装：

```bash
composer install
```

### 开始写作

运行 main.php 生成新文章模板：

```bash
php main.php new "文章标题"
```

手动在 posts/ 下会创建一个新的 Markdown 文件，把生成的模板内容复制进去

**注意，--- 分隔符必须顶格、并且必须出现在文件开头**

### 构建博客

```bash
php main.php build
```

构建流程:

1. 清空 dist/
2. 资源映射
3. 解析 Markdown
4. 校验数据
5. 构建数据索引
6. 渲染页面

最终生成所有静态文件到 dist/ 目录，博客即完成构建，在 dist 目录下开启web服务即可预览。

```bash
php -S localhost:8000 -t dist/
```

如果部署在 github pages，把 dist/ 目录下的所有文件推送到 gh-pages 分支即可。


### 自动构建

功能说明：本地在 posts 目录文章书写完成后，提交到 GitHub 仓库，GitHub Action 会自动触发构建流程，构建完成后会将 dist 目录下的静态文件推送到 gh-pages 分支，实现自动部署。

需要给项目开启 action 的读写权限

Settings → Actions → 常规（General）下滑找到 Workflow permissions，选择 Allow GitHub Actions to read and write permissions

![](/images/blog-deployment/14.png)


## 目录结构

```plaintext
.
├─ assets/      前端资源（CSS / JS / Prism）
├─ config/      站点配置
├─ logs/        构建日志
├─ posts/       Markdown 文章（支持子目录）
├─ public/      静态资源（原样拷贝到 dist/）
├─ images/      图片资源（原样拷贝到 dist/images/）
├─ src/         核心 PHP 逻辑
├─ templates/   页面模板
├─ dist/        构建输出（生成后出现）
└─ main.php     CLI 入口
```


## 文章格式

每篇文章为 Markdown 文件，需包含 YAML Front Matter，例如：

```yaml
---
title: Hello World
date: 2026-03-20
permalink: /posts/hello-world/
tags:
    - 示例
categories:
    - 示例
pin: 0
draft: false
sidebar: true
---
```
文章文件放在 posts/ 下即可（可分子目录）。

### Front Matter 字段说明

必需字段（缺失会报错）：

- title：文章标题
- date：日期（支持 YYYY-MM-DD 或可被解析的字符串）
- permalink：文章永久链接（例如 /posts/hello-world/）

可选字段（未填写会自动补默认值）：

- tags：标签数组，默认 []
- categories：分类数组，默认 []（支持单个字符串）
- pin：置顶优先级，0-3，数字越小优先级越高，默认 0
- draft：是否草稿，默认 false（true 时构建会跳过）
- sidebar：是否显示文章目录侧边栏，默认 true

## 配置说明

- config/site.php：站点标题、作者、站点地址等
    - 其中 url 会用于生成 sitemap.xml 的站点根地址（例如 https://example.com）

### Giscus 评论系统

默认关闭。如需启用，请在 config/site.php 的 giscus 配置中填入你自己的参数（repo / repo_id / category / category_id）。

重要：不要直接使用他人的配置，否则评论会写入对方仓库。

注意：启用 Giscus 评论系统需要在 GitHub 仓库创建 Discussions，具体步骤参考这篇文章 https://www.lixueduan.com/posts/blog/02-add-giscus-comment/

## 备注

- 主题与排版变量在 assets/css/site.css 中
- 目录由前端脚本根据 h2/h3 生成
- 阅读进度条：assets/js/readingProgress.js + assets/css/reading-progress.css
- 图片增强：assets/js/imageEnhance.js + assets/css/image-enhance.css
- 导航栏自动显示：assets/js/navReveal.js + assets/css/nav-reveal.css
- Callout 支持由后处理完成（src/Markdown.php）
- 构建日志写入 logs/build.log（默认追加写入；超过 14 天会自动清空重写；每次构建会写入分隔线）
- main.php 固定时区为 Asia/Shanghai，保证日志与日期输出为北京时间
- permalink 会做冲突校验：不能与系统页（/tags/、/categories/、/archives/）或 public/images 下已有文件路径冲突

## 性能测试

在一台 Windows 设备上，构建一个包含 182 篇文章、约 200MB 图片资源的博客，完整构建时间约为 2 秒。

主要耗时集中在静态资源复制（`assets/`、`public/`、`images/` → `dist/`），Markdown 解析与页面渲染开销较低。

```bash
PS： php .\main.php build
[2026-03-24 15:12:18] [信息] 开始构建...
[2026-03-24 15:12:18] [信息] 清理 dist 目录...
[2026-03-24 15:12:18] [信息] 1. 清空 dist/ 用时 0.516 秒。
[2026-03-24 15:12:18] [信息] 复制静态资源...
[2026-03-24 15:12:19] [信息] 2. 资源映射 用时 1.123 秒。
[2026-03-24 15:12:19] [信息] 加载并处理文章...
[2026-03-24 15:12:19] [信息] 3. 解析 Markdown 用时 0.039 秒。
[2026-03-24 15:12:19] [信息] 校验文章数据...
[2026-03-24 15:12:19] [信息] 4. 校验数据 用时 0.003 秒。
[2026-03-24 15:12:19] [信息] 准备数据...
[2026-03-24 15:12:19] [信息] 5. 构建数据索引 用时 0.153 秒。
[2026-03-24 15:12:19] [信息] 生成页面...
[2026-03-24 15:12:20] [信息] 6. 渲染页面 用时 0.183 秒。
[2026-03-24 15:12:20] [信息] 构建完成，用时 2.02 秒。
[2026-03-24 15:12:20] [信息]  - 生成文章数：182
[2026-03-24 15:12:20] [信息]  - 标签数：142
[2026-03-24 15:12:20] [信息]  - 分类数：17
```


## 计划中的功能

- Logo、favicon、社交链接、关于页等站点细节
- 增量构建
- 全文搜索