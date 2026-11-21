# 插件说明

## 新增插件

`assets/features/` 用于实现前台插件功能，以原生 CSS、JS 改变页面表现，不参与 PHP 业务逻辑。新增功能时，在本目录下新建一个以功能命名的子目录，放入 `style.css` 与 `script.js`。目录名建议使用小写与连字符，例如 code-fold。

两个文件均为可选，存在即注入，缺失即跳过。构建生成 HTML 时会扫描 `assets/features/新增功能/` 下的样式与脚本，不需要修改任何配置文件。

例如以下结构：

```plaintext
assets/
    │──features（插件目录）
    │   ├── code-fold（新增功能）
    │   │   ├── script.js
    │   │   └── style.css
    │   └── README.md
    │
    │──js（系统级基础功能）
    │   └──site.js
    │   └──ThemeSwitch.js
    │   └──xxx.js
    └──css（系统级基础样式）
        └──site.css
        └──markdown.css
        └──xxx.css
```

加载顺序

- 按目录名字母序注入，后加载的样式覆盖先加载的
- 插件样式位于上级目录的 `site.css` 之后，因此插件样式可覆盖站点样式。
- 注入结果由所有页面共用，包括首页、归档、标签与 404。


## 停用插件

将文件改名，例如 script.js 改为 script.js.off。扫描按精确文件名匹配，改名即不再注入，恢复时改回原名。

