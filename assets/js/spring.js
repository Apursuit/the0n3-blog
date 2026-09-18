/* Spring.js
   手写 rAF 阻尼弹簧（Apple Design §3/§4/§5）。
   参数：damping（1.0 = 临界阻尼无过冲，<1.0 = 带回弹）、
         response（到达目标的速度，秒）。
   特性：始终从当前屏上值续跑、可随时打断重定向、天然速度连续。
   暴露 window.Spring.createSpring 与 window.Spring.prefersReducedMotion。
*/
(function (global) {
    'use strict';

    function prefersReducedMotion() {
        return (
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        );
    }

    function createSpring(options) {
        var from = typeof options.from === 'number' ? options.from : 0;
        var to = typeof options.to === 'number' ? options.to : 1;
        var damping = typeof options.damping === 'number' ? options.damping : 1;
        var response = typeof options.response === 'number' ? options.response : 0.35;
        var velocity = typeof options.velocity === 'number' ? options.velocity : 0;
        var onUpdate = options.onUpdate || function () {};
        var onComplete = options.onComplete || function () {};

        // 将 damping + response 映射为质量=1 的简谐振荡器参数
        var omega = (2 * Math.PI) / response;
        var stiffness = omega * omega;
        var friction = 2 * damping * omega;

        var value = from;
        var v = velocity;
        var rafId = null;
        var lastTime = null;

        function tick(now) {
            if (lastTime === null) {
                lastTime = now;
            }
            var dt = Math.min((now - lastTime) / 1000, 1 / 60);
            lastTime = now;

            var force = -stiffness * (value - to) - friction * v;
            v += force * dt;
            value += v * dt;

            onUpdate(value);

            if (Math.abs(to - value) < 0.01 && Math.abs(v) < 0.05) {
                onUpdate(to);
                stop();
                onComplete();
                return;
            }
            rafId = global.requestAnimationFrame(tick);
        }

        function start() {
            stop();
            lastTime = null;
            rafId = global.requestAnimationFrame(tick);
        }

        function stop() {
            if (rafId !== null) {
                global.cancelAnimationFrame(rafId);
                rafId = null;
            }
        }

        return { start: start, stop: stop };
    }

    global.Spring = {
        createSpring: createSpring,
        prefersReducedMotion: prefersReducedMotion,
    };
})(window);
