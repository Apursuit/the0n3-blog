(function () {
  // 尊重 DNT / GPC：用户明确表示不要被追踪，则完全不上报
  if (
    navigator.doNotTrack === '1' ||
    window.doNotTrack === '1' ||
    navigator.globalPrivacyControl === true
  ) {
    return;
  }

  var ENDPOINT = 'https://blog-stats.the0n3.top/api/pv';
  var COOKIE = 'bs_vid';
  var THIRTY_DAYS = 2592000; // 30 天

  function getCookie(name) {
    var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
    return m ? decodeURIComponent(m[1]) : '';
  }

  // 读/生成访客 ID（第一方 Cookie，30 天，每次访问滚动刷新）
  var vid = getCookie(COOKIE);
  if (!vid) {
    vid = 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
  }
  document.cookie = COOKIE + '=' + encodeURIComponent(vid) +
    '; max-age=' + THIRTY_DAYS + '; path=/; SameSite=Lax';

  // 取来源的「域名」，完整 referer 不离开浏览器
  var refHost = '';
  if (document.referrer) {
    try {
      refHost = new URL(document.referrer).hostname;
    } catch (e) {
      refHost = '';
    }
  }

  // 收集数据，统计热门文章
  var data = {
    vid: vid,
    path: location.pathname + location.search,
    title: document.title,
    refHost: refHost,
    lang: navigator.language || ''
  };

  // 上传数据
  var payload = new Blob([JSON.stringify(data)], { type: 'text/plain;charset=UTF-8' });

  if (navigator.sendBeacon) {
    navigator.sendBeacon(ENDPOINT, payload);
  } else if (window.fetch) {
    fetch(ENDPOINT, { method: 'POST', body: payload, keepalive: true, mode: 'cors' });
  }
})();
