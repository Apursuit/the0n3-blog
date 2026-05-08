# 🚀 Roadmap

## Completed

- [x] 评论系统（Giscus）
- [x] 全文搜索（Fuse.js 客户端搜索，搜索索引自动生成）
- [x] SEO 优化（Open Graph / Twitter Card / 动态 canonical / meta description）
- [x] 架构重构（Generator 拆分为 PostProcessor / SearchIndexer / PageGenerator / AssetManager）
- [x] 构建容错（单篇文章解析失败跳过而非中断全站构建，CI 同步通知）
- [x] 幽灵日期修复（FrontMatter 解析阶段校验日期有效性）
- [x] 搜索索引延迟加载（940KB 改为首次输入/聚焦时按需 fetch）
- [x] CI 容错（deploy.yml continue-on-error + 解析错误检查步骤）
- [x] 功能目录化（assets/features/ 自动扫描替代平铺 CSS/JS）

---

## P0：健壮性

- [ ] Step 4 Validator 校验失败不崩溃全站构建（try/catch 包裹，记录错误并退出码非零）
- [ ] Step 5-6-7 外覆 try/catch，单篇文章/页面渲染失败不影响其余
- [ ] AssetManager::copy() 文件拷贝失败记录警告而非静默

---

## P1：体验完整度

- [ ] 配置外部化：`posts_per_page`、`timezone`、`lang`、`pin_limit` 移入 config/site.php
- [ ] RSS / Atom feed 生成
- [ ] 图片构建时优化（最大宽度限制、WebP 转换、`<picture>` 标签降级）
- [ ] 关于页面（config/site.php 配置项 + layout 导航渲染）
- [ ] 文章页 JSON-LD 结构化数据（Article schema，Google 富摘要）

---

## P1：工程化

- [ ] 增量构建（文件 hash 比对，仅编译变更的 .md 和静态资源）
- [ ] 测试基础设施（phpunit + phpstan，最少覆盖 FrontMatter / Validator / Markdown callout）
- [ ] CSS 模块化（site.css 拆为 variables / layout / components / dark-theme）
- [ ] PR CI 检查（pull_request 触发 build-only，防止合并即爆炸）

---

## Backlog

- [ ] Git LFS 托管 images/（200MB+ 不宜全量 Git 跟踪）
- [ ] Parsedown v2 升级（原生脚注、表格、任务列表）
- [ ] 短代码系统（视频嵌入、图集等 Markdown 扩展）
- [ ] 评论系统体验优化（回复通知、身份认证提示）
