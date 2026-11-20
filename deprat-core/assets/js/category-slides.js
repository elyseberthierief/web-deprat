/* DEPRAT — transitions fondues des slides de catégories, par catégorie uniquement. */
(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-deprat-category-slider]').forEach(function (slider) {
            var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-deprat-category-slide]'));
            if (slides.length < 2) { return; }
            var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-deprat-category-dot]'));
            var index = 0, timer = null, paused = false;
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            function show(next) {
                index = (next + slides.length) % slides.length;
                slides.forEach(function (slide, i) {
                    var current = i === index;
                    slide.classList.toggle('is-active', current);
                    slide.setAttribute('aria-hidden', current ? 'false' : 'true');
                    if ('inert' in slide) { slide.inert = !current; }
                });
                dots.forEach(function (dot, i) { dot.setAttribute('aria-pressed', i === index ? 'true' : 'false'); });
            }
            function stop() { if (timer !== null) { clearInterval(timer); timer = null; } }
            function play() {
                stop();
                if (!paused && !reduce && !document.hidden) {
                    timer = setInterval(function () { show(index + 1); }, 6500);
                }
            }
            function advance(delta) { show(index + delta); play(); }
            var prev = slider.querySelector('[data-deprat-category-prev]');
            var next = slider.querySelector('[data-deprat-category-next]');
            if (prev) { prev.addEventListener('click', function () { advance(-1); }); }
            if (next) { next.addEventListener('click', function () { advance(1); }); }
            dots.forEach(function (dot, i) { dot.addEventListener('click', function () { show(i); play(); }); });
            slider.addEventListener('mouseenter', function () { paused = true; stop(); });
            slider.addEventListener('mouseleave', function () { paused = false; play(); });
            slider.addEventListener('focusin', function () { paused = true; stop(); });
            slider.addEventListener('focusout', function (event) { if (!slider.contains(event.relatedTarget)) { paused = false; play(); } });
            document.addEventListener('visibilitychange', play);
            show(0); play();
        });
    });
}());