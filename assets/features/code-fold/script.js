(function () {
    var THRESHOLD = 400;

    function getVisibleBackground(el) {
        var bg = getComputedStyle(el).backgroundColor;
        if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return bg;
        var parent = el.parentElement;
        while (parent) {
            var pbg = getComputedStyle(parent).backgroundColor;
            if (pbg && pbg !== 'rgba(0, 0, 0, 0)' && pbg !== 'transparent') return pbg;
            parent = parent.parentElement;
        }
        return '#ffffff';
    }

    function wrapPre(pre) {
        var wrapper = document.createElement('div');
        wrapper.className = 'code-block-wrapper';

        pre.parentNode.insertBefore(wrapper, pre);
        wrapper.appendChild(pre);
        pre.classList.add('code-folded');

        var overlay = document.createElement('div');
        overlay.className = 'code-fold-overlay';
        overlay.style.background = 'linear-gradient(to bottom, transparent, ' + getVisibleBackground(pre) + ')';
        wrapper.appendChild(overlay);

        var btn = document.createElement('button');
        btn.className = 'code-fold-btn';
        btn.type = 'button';
        btn.textContent = '展开全部代码';
        wrapper.appendChild(btn);

        btn.addEventListener('click', function () {
            pre.classList.remove('code-folded');
            overlay.remove();
            btn.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var pres = document.querySelectorAll('pre');
        for (var i = 0; i < pres.length; i++) {
            if (pres[i].scrollHeight > THRESHOLD) {
                wrapPre(pres[i]);
            }
        }
    });
})();
