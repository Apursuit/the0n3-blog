import sys
import json
import time
from pathlib import Path
from urllib.parse import urlsplit
from datetime import datetime
from xml.etree import ElementTree as ET

try:
    import requests
except ImportError:
    print("错误: 缺少 'requests' 库。请在 Workflow 中执行 'pip install requests'。")
    sys.exit(1)

# --- 配置 ---
# IndexNow 密钥（占位符，请替换为你自己的 key）
KEY = "f14b1d89a54048df87e141e665e6af6e"

# IndexNow 提交端点
INDEXNOW_API = "https://api.indexnow.org/IndexNow"

# 本地构建产物中的 sitemap（作为“当前”状态；相对仓库根目录）
# 脚本位于 <root>/.github/deploy/indexnow.py，向上三级即仓库根目录
REPO_ROOT = Path(__file__).resolve().parents[2]
LOCAL_SITEMAP = REPO_ROOT / "dist" / "sitemap.xml"

# 线上 sitemap 的路径（相对站点根），作为“上一次”状态
REMOTE_SITEMAP_PATH = "/sitemap.xml"

# IndexNow 单次请求 URL 上限
MAX_URLS_PER_REQUEST = 10000

# 网络请求重试
MAX_RETRIES = 3
RETRY_DELAY = 3


def log(message):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    sys.stdout.write(f"[{timestamp}] {message}\n")
    sys.stdout.flush()


def extract_locs(xml_text):
    """从 sitemap XML 中提取所有 <loc> URL（忽略命名空间）。"""
    locs = set()
    root = ET.fromstring(xml_text)
    for el in root.iter():
        tag = el.tag.split('}')[-1]
        if tag == 'loc' and el.text and el.text.strip():
            locs.add(el.text.strip())
    return locs


def load_current_urls():
    """读取本地构建产物中的 sitemap，返回当前站点全部 URL。"""
    if not LOCAL_SITEMAP.is_file():
        log(f"错误: 未找到本地 sitemap {LOCAL_SITEMAP}。构建可能失败或未生成，跳过推送。")
        return set()
    try:
        xml_text = LOCAL_SITEMAP.read_text(encoding='utf-8')
        urls = extract_locs(xml_text)
        log(f"本地 sitemap 解析完成，共 {len(urls)} 条 URL。")
        return urls
    except Exception as e:
        log(f"错误: 解析本地 sitemap 失败: {e}。跳过推送。")
        return set()


def load_previous_urls(remote_sitemap_url):
    """下载线上 sitemap，返回上一次部署时的 URL 集合；失败则返回空集（触发全量）。"""
    log(f"尝试获取线上 sitemap: {remote_sitemap_url}")
    try:
        resp = requests.get(remote_sitemap_url, timeout=10)
        if resp.status_code == 200:
            urls = extract_locs(resp.text)
            log(f"线上 sitemap 解析完成，共 {len(urls)} 条历史 URL。")
            return urls
        log(f"警告: 无法获取线上 sitemap (HTTP {resp.status_code})，将执行全量推送。")
    except requests.exceptions.RequestException as e:
        log(f"警告: 访问线上 sitemap 失败 ({e})，将执行全量推送。")
    except ET.ParseError as e:
        log(f"警告: 线上 sitemap 解析失败 ({e})，将执行全量推送。")
    return set()


def submit_batch(urls, host, key_location):
    """提交一批 URL 到 IndexNow，带重试。返回是否成功。"""
    headers = {'Content-Type': 'application/json; charset=utf-8'}
    data = {
        "host": host,
        "key": KEY,
        "keyLocation": key_location,
        "urlList": urls,
    }
    for attempt in range(1, MAX_RETRIES + 1):
        try:
            resp = requests.post(INDEXNOW_API, headers=headers,
                                 data=json.dumps(data), timeout=15)
            if resp.status_code in (200, 202):
                log(f"✅ 成功提交 {len(urls)} 条 URL (HTTP {resp.status_code})。")
                return True
            log(f"❌ 提交失败 (HTTP {resp.status_code}): {resp.text[:200]}")
        except requests.exceptions.RequestException as e:
            log(f"❌ 提交时发生网络错误 (第 {attempt}/{MAX_RETRIES} 次): {e}")
        if attempt < MAX_RETRIES:
            time.sleep(RETRY_DELAY)
    log("❌ 已达最大重试次数，放弃本批提交。")
    return False


def submit_to_indexnow(urls, base_url, host):
    """分批提交 URL 到 IndexNow。"""
    key_location = f"{base_url}/{KEY}.txt"
    url_list = sorted(urls)
    total = len(url_list)
    all_ok = True
    for start in range(0, total, MAX_URLS_PER_REQUEST):
        batch = url_list[start:start + MAX_URLS_PER_REQUEST]
        if not submit_batch(batch, host, key_location):
            all_ok = False
    return all_ok


def main():
    log("--- IndexNow 增量推送脚本启动 ---")

    if KEY == "REPLACE_WITH_YOUR_INDEXNOW_KEY" or not KEY:
        log("警告: IndexNow key 仍为占位符，未配置。跳过提交。")
        return 0

    # 1. 当前状态（本地构建产物）
    current_urls = load_current_urls()
    if not current_urls:
        log("当前 URL 集为空，终止（不做任何提交，避免误操作）。")
        return 0

    # 2. 从 sitemap 推导站点根地址（无需单独配置域名）
    sample = next(iter(current_urls))
    parts = urlsplit(sample)
    base_url = f"{parts.scheme}://{parts.netloc}"
    host = parts.netloc
    remote_sitemap_url = f"{base_url}{REMOTE_SITEMAP_PATH}"

    # 3. 上一次状态（线上 sitemap）
    previous_urls = load_previous_urls(remote_sitemap_url)

    if not previous_urls:
        log("❗ 未获取到历史状态，将执行全量推送。")

    # 4. 计算差异
    added = current_urls - previous_urls
    deleted = previous_urls - current_urls

    log(f"当前 URL: {len(current_urls)} 条，历史 URL: {len(previous_urls)} 条。")
    log(f"新增: {len(added)} 条，删除: {len(deleted)} 条。")

    # 5. 提交新增 URL
    if added:
        log("--- 提交新增 URL 到 IndexNow ---")
        for url in sorted(added):
            log(f"  + {url}")
        submit_to_indexnow(added, base_url, host)
    else:
        log("无新增 URL，跳过提交。")

    # 6. 删除项仅记录，不提交
    if deleted:
        log("--- 已删除的 URL（仅记录）---")
        for url in sorted(deleted):
            log(f"  - {url}")

    log("--- 脚本运行结束 ---")
    return 0


if __name__ == "__main__":
    sys.exit(main())
