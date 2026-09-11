#!/usr/bin/env bash

set -e

# ===== 基本配置 =====
TOOL_NAME="linpeas.sh"
OFFICIAL_URL="https://github.com/peass-ng/PEASS-ng/releases/latest/download/linpeas.sh"

MIRRORS=(
  "https://gh-proxy.com/https://github.com/peass-ng/PEASS-ng/releases/latest/download/linpeas.sh"
  "https://gh.llkk.cc/https://github.com/peass-ng/PEASS-ng/releases/latest/download/linpeas.sh"
  "https://cdn.akaere.online/https://github.com/peass-ng/PEASS-ng/releases/latest/download/linpeas.sh"
)

# ===== 颜色 =====
GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
NC="\033[0m"

# ===== 检测下载工具 =====
detect_downloader() {
    if command -v curl >/dev/null 2>&1; then
        DL="curl"
    elif command -v wget >/dev/null 2>&1; then
        DL="wget"
    elif command -v busybox >/dev/null 2>&1; then
        DL="busybox"
    else
        echo -e "${RED}[-] 未检测到 curl / wget / busybox，无法下载${NC}"
        exit 1
    fi
}

# ===== 下载函数 =====
download() {
    url="$1"
    outfile="$2"

    if [ "$DL" = "curl" ]; then
        curl -fL --connect-timeout 5 --max-time 20 -o "$outfile" "$url"
    elif [ "$DL" = "wget" ]; then
        wget --timeout=10 --tries=1 -O "$outfile" "$url"
    else
        busybox wget -O "$outfile" "$url"
    fi
}

# ===== 简单校验（防止下载到 HTML / 错误页）=====
basic_check() {
    file="$1"

    # 检查 shebang
    head -n 1 "$file" | grep -qE "^#!" || return 1

    # 检查文件大小（避免下载到错误页面）
    size=$(stat -c%s "$file" 2>/dev/null || stat -f%z "$file")
    [ "$size" -lt 50000 ] && return 1

    return 0
}

# ===== 主流程 =====
main() {
    detect_downloader

    echo -e "${GREEN}[+] 正在尝试官方源下载${NC}"
    echo -e "${GREEN}[+] $OFFICIAL_URL${NC}"

    if download "$OFFICIAL_URL" "$TOOL_NAME"; then
        if basic_check "$TOOL_NAME"; then
            echo -e "${GREEN}[+] 官方源下载成功${NC}"
            chmod +x "$TOOL_NAME"
            echo -e "${GREEN}[+] 来源：官方（可信）${NC}"
            return
        else
            echo -e "${RED}[-] 官方源文件校验失败${NC}"
            rm -f "$TOOL_NAME"
        fi
    else
        echo -e "${RED}[-] 官方源下载失败${NC}"
    fi

    # ===== 询问用户是否使用代理 =====
    echo -e "${YELLOW}[!] 官方源不可用${NC}"
    echo -e "${YELLOW}[!] 即将使用第三方代理下载（存在风险）${NC}"
    read -p "[?] 是否继续使用代理下载？(y/N): " choice

    if [[ ! "$choice" =~ ^[Yy]$ ]]; then
        echo -e "${RED}[-] 用户取消操作${NC}"
        exit 1
    fi

    # ===== 使用代理 =====
    for url in "${MIRRORS[@]}"; do
        echo -e "${YELLOW}[!] 尝试代理源：${NC}$url"

        if download "$url" "$TOOL_NAME"; then
            if basic_check "$TOOL_NAME"; then
                echo -e "${YELLOW}[+] 代理源下载成功${NC}"
                echo -e "${YELLOW}[!] 来源：第三方代理（请自行确认安全性）${NC}"
                chmod +x "$TOOL_NAME"
                return
            else
                echo -e "${RED}[-] 代理源文件校验失败${NC}"
                rm -f "$TOOL_NAME"
            fi
        else
            echo -e "${RED}[-] 下载失败：$url${NC}"
        fi
    done

    echo -e "${RED}[-] 所有代理源均下载失败${NC}"
    exit 1
}

main