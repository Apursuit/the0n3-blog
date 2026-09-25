const toolsData = {
    'installation-tools': [
        { name: 'Google Chrome', desc: '浏览器', link: 'https://www.google.com/chrome/', linkText: '官网' },
        { name: 'Firefox', desc: '浏览器', link: 'https://www.firefox.com/', linkText: '官网' },
        { name: 'Watt Toolkit', desc: 'Github加速', link: 'https://steampp.net/', linkText: '官网' },
        { name: 'Linux Mirrors', desc: 'Linux 换源加速', link: 'https://github.com/SuperManito/LinuxMirrors', linkText: 'GitHub' },
        { name: 'gh-proxy', desc: 'GitHub 代理加速', link: 'https://gh-proxy.com/', linkText: '官网' },
        { name: 'PowerToys', desc: '微软官方 Windows 增强工具集', link: 'https://github.com/microsoft/PowerToys', linkText: 'GitHub' },
        { name: 'Clash for Linux Install', desc: 'clash for linux安装脚本', link: 'https://github.com/nelvko/clash-for-linux-install', linkText: 'GitHub' },
        { name: 'Flclash', desc: '代理工具', link: 'https://github.com/chen08209/FlClash', linkText: 'GitHub' },
        { name: 'Localsend', desc: '跨平台文件传输工具', link: 'https://localsend.org/zh-CN/download', linkText: '官网' },
        { name: 'VMware', desc: '虚拟机', link: 'https://github.com/201853910/VMwareWorkstation', linkText: 'GitHub直链下载' },
        { name: 'VirtualBox', desc: '虚拟机', link: 'https://www.virtualbox.org/', linkText: '官网' },
        { name: 'Tabby', desc: '跨平台终端模拟器', link: 'https://tabby.sh/', linkText: '官网' },
        { name: 'Dawn Launcher', desc: 'Windows 快捷启动工具', link: 'https://dawnlauncher.com/', linkText: '官网' },
        { name: 'ServBay', desc: '开发环境快速配置', link: 'https://www.servbay.com/zh-CN', linkText: '官网' },
        { name: 'Geek Uninstall', desc: '程序卸载', link: 'https://geekuninstaller.com/download', linkText: '下载' },
        { name: 'Snipaste', desc: '截图贴图', link: 'https://zh.snipaste.com/', linkText: '官网' },
        { name: 'Termius', desc: '跨平台 SSH 客户端', link: 'https://termius.com/', linkText: '官网' },
        { name: 'uTools', desc: '效率工具箱', link: 'https://www.u-tools.cn/', linkText: '官网' },
        { name: 'Ventoy', desc: '启动U盘制作', link: 'https://www.ventoy.net/cn/doc_start.html', linkText: '文档' },
        { name: 'MobaXterm-Chinese', desc: 'MobaXterm汉化版', link: 'https://github.com/RipplePiam/MobaXterm-Chinese-Simplified', linkText: 'GitHub' },
        { name: 'Windows Terminal', desc: '微软终端工具', link: 'https://github.com/microsoft/terminal', linkText: 'GitHub' },
        { name: 'Office-Tool', desc: 'Office 安装/部署管理', link: 'https://github.com/YerongAI/Office-Tool', linkText: 'GitHub' },
        { name: 'Docker 清华镜像', desc: 'Docker 中国加速源', link: 'https://mirrors.tuna.tsinghua.edu.cn/help/docker-ce/', linkText: '镜像站' },
        { name: 'Docker 安装脚本', desc: '官方推荐一键安装', link: 'https://github.com/docker/docker-install', linkText: 'GitHub' },
        { name: 'Nali', desc: 'IP归属地离线工具', link: 'https://github.com/zu1k/nali', linkText: 'GitHub' },
        { name: 'phpstudy', desc: '本地 PHP 测试环境', link: 'https://www.xp.cn/', linkText: '官网' },
        { name: 'D盾', desc: 'PHP Webshell 检测工具', link: 'https://www.d99net.net/', linkText: '官网' },
        { name: 'Hawkeye', desc: 'Windows 日志分析工具', link: 'https://github.com/mir1ce/Hawkeye', linkText: 'GitHub' },
        { name: 'fail2ban', desc: '基于日志的自动防护工具', link: 'https://github.com/fail2ban/fail2ban', linkText: 'Github' },
        { name: 'IDA', desc: '逆向工程工具', link: '', linkText: '官网' },
        { name: 'CyberChef', desc: '编码解码', link: 'https://github.com/gchq/CyberChef', linkText: 'GitHub' },
        { name: 'Cmder', desc: 'Windows 终端', link: 'https://github.com/cmderdev/cmder', linkText: 'GitHub' },
        { name: 'ZyperWinOptimize', desc: 'Zyper Windows 优化工具', link: 'https://github.com/ZyperWave/ZyperWinOptimize', linkText: 'GitHub' },
        { name: 'Notepad3', desc: '轻量记事本', link: 'https://github.com/rizonesoft/Notepad3/', linkText: 'GitHub' },
        { name: 'Sublime Text', desc: '代码编辑器', link: 'https://www.sublimetext.com/', linkText: '官网' },
        { name: 'Notepad--', desc: '轻量记事本', link: 'https://github.com/cxasm/notepad--', linkText: 'GitHub' },
        { name: 'Typora', desc: 'Markdown 编辑器', link: 'https://typora.io/', linkText: '官网' },
        { name: 'VSCode', desc: '代码编辑器', link: 'https://code.visualstudio.com/', linkText: '官网' },
        { name: 'OBS Studio', desc: '屏幕录制/直播软件', link: 'https://obsproject.com/', linkText: '官网' }

    ],
    'pentest-tools': [
        { name: 'Stream++', desc: '游戏/学术加速器', link: 'https://steampp.net/', linkText: '官网' },
        { name: 'gh-proxy', desc: 'GitHub 代理加速', link: 'https://gh-proxy.com/', linkText: '官网' },
        { name: 'Static Binaries', desc: '静态二进制文件集合85 stars', link: 'https://github.com/pkgforge-dev/Static-Binaries', linkText: 'GitHub' },
        { name: 'Static Binaries', desc: '静态二进制文件集合 3.6k stars', link: 'https://github.com/andrew-d/static-binaries', linkText: 'GitHub' },
        { name: 'BurpSuite v2026.1', desc: 'Web 安全测试/抓包工具', link: '', linkText: '官网' },
        { name: 'Yakit', desc: '抓包漏洞扫描', link: 'https://www.yaklang.com/', linkText: '官网' }, 
        { name: 'Kali Linux', desc: '渗透测试系统', link: 'https://www.kali.org/get-kali/#kali-platforms', linkText: '官网' },
        { name: 'PEASS-ng', desc: '权限提升工具', link: 'https://github.com/peass-ng/PEASS-ng', linkText: 'GitHub' },
        { name: 'Nmap', desc: '扫描器', link: 'https://github.com/nmap/nmap', linkText: 'GitHub' },
        { name: 'Metasploit Framework', desc: '渗透测试框架', link: 'https://www.metasploit.com/', linkText: '官网'},
        { name: 'CobaltStrike', desc: '商业红队对抗框架', link: '', linkText: '官网'},
        { name: 'hackbar', desc: '发包扩展', link: 'https://chromewebstore.google.com/detail/ginpbkfigcoaokgflihfhhmglmbchinc?utm_source=item-share-cb', linkText: 'Chrome' }, 
        { name: 'dirsearch', desc: '目录扫描器', link: 'https://github.com/maurosoria/dirsearch', linkText: 'GitHub' },
        { name: 'git-dumper', desc: 'Git 仓库泄露扫描工具', link: 'https://github.com/arthaud/git-dumper', linkText: 'GitHub' },
        { name: 'dumpall', desc: '信息收集工具', link: 'https://github.com/0xHJK/dumpall', linkText: 'GitHub' },
        { name: 'sqlmap', desc: '自动化 SQL 注入', link: 'https://github.com/sqlmapproject/sqlmap', linkText: 'GitHub' },
        { name: 'CN_Sqlmap', desc: 'sqlmap 汉化', link: 'https://github.com/BugFor-Pings/CN_Sqlmap', linkText: 'GitHub' },
        { name: 'TscanPlus', desc: '无影(TscanPlus)', link: 'https://github.com/TideSec/TscanPlus', linkText: 'GitHub' }, 
        { name: 'fscan', desc: '内网扫描器', link: 'https://github.com/shadow1ng/fscan', linkText: 'GitHub' },
        { name: 'Tscan', desc: '无影(Tscan)', link: 'https://github.com/TideSec/TscanPlus', linkText: 'GitHub' }, 
        { name: '蚁剑', desc: 'webshell客户端', link: 'https://github.com/AntSwordProject/antSword', linkText: 'GitHub' },
        { name: 'Goby', desc: '网络空间测绘工具', link: 'https://gobysec.net/', linkText: '官网' },
        { name: 'ThinkphpGUI', desc: 'ThinkPHP 漏洞利用工具', link: 'https://github.com/Lotus6/ThinkphpGUI', linkText: 'GitHub' }, 
        { name: 'Thinkphp_gui_tools', desc: 'thinkphp综合利用工具', link: 'https://github.com/bewhale/thinkphp_gui_tools', linkText: 'Github' }, 
        { name: 'Proxifier', desc: '代理工具', link: 'https://www.proxifier.com/', linkText: '官网' }, 
        { name: 'suo5', desc: 'HTTP 流量代理', link: 'https://github.com/zema1/suo5', linkText: 'GitHub' },
        { name: 'php_filter_chain_generator', desc: 'PHP 过滤链生成器', link: 'https://github.com/synacktiv/php_filter_chain_generator', linkText: 'GitHub' }
    ]
};
function renderTools(data, elementId) {
const list = document.getElementById(elementId);
list.innerHTML = data.map(item => `
    <li class="tool-item" data-link="${item.link}">
        <div class="tool-info">
            <div class="tool-name">${item.name}</div>
            <div class="tool-description">${item.desc}</div>
        </div>
        <div class="tool-link">
            <a href="${item.link}" target="_blank">${item.linkText}</a>
        </div>
    </li>
`).join('');
}

function handleItemClick(e) {
const li = e.target.closest('li');
if (li && e.target.tagName !== 'A') {
    window.open(li.dataset.link, '_blank');
}
}

function generateTOC() {
const tocList = document.querySelector('#toc ul');
const headings = document.querySelectorAll('.container h2');

headings.forEach((h2, i) => {
    h2.id ||= 'section-' + i;
    const li = document.createElement('li');
    li.innerHTML = `<a href="#${h2.id}">${h2.textContent}</a>`;
    tocList.appendChild(li);
});
}

document.addEventListener('DOMContentLoaded', () => {
renderTools(toolsData['installation-tools'], 'installation-tools-list');
renderTools(toolsData['pentest-tools'], 'pentest-tools-list');

document.getElementById('installation-tools-list').addEventListener('click', handleItemClick);
document.getElementById('pentest-tools-list').addEventListener('click', handleItemClick);

generateTOC();
});