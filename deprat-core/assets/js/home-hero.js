/* DEPRAT : diaporama accessible, avec défilement automatique et navigation manuelle. */
(function () {
    'use strict';
    function init(slider) {
        var slides = Array.prototype.slice.call(slider.querySelectorAll('[data-deprat-home-slide]'));
        if (slides.length < 2) { return; }
        var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-deprat-home-dot]'));
        var previous = slider.querySelector('[data-deprat-home-prev]');
        var next = slider.querySelector('[data-deprat-home-next]');
        var current = 0;
        var timer = null;
        var isPaused = false;
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function show(index) {
            current = (index + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                slide.hidden = i !== current;
            });
            dots.forEach(function (dot, i) {
                dot.setAttribute('aria-pressed', i === current ? 'true' : 'false');
            });
        }
        function stop() {
            if (timer !== null) { window.clearInterval(timer); timer = null; }
        }
        function start() {
            stop();
            if (!isPaused && !reducedMotion && !document.hidden) {
                timer = window.setInterval(function () { show(current + 1); }, 7000);
            }
        }
        function move(delta) {
            show(current + delta);
            start();
        }
        previous.addEventListener('click', function () { move(-1); });
        next.addEventListener('click', function () { move(1); });
        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { show(i); start(); });
        });
        slider.addEventListener('mouseenter', function () { isPaused = true; stop(); });
        slider.addEventListener('mouseleave', function () { isPaused = false; start(); });
        slider.addEventListener('focusin', function () { isPaused = true; stop(); });
        slider.addEventListener('focusout', function (event) {
            if (!slider.contains(event.relatedTarget)) { isPaused = false; start(); }
        });
        slider.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                event.preventDefault();
                move(event.key === 'ArrowLeft' ? -1 : 1);
            }
        });
        document.addEventListener('visibilitychange', start);
        var touchX = null;
        slider.addEventListener('touchstart', function (event) {
            if (event.touches.length === 1) { touchX = event.touches[0].clientX; }
        }, { passive: true });
        slider.addEventListener('touchend', function (event) {
            if (touchX === null || !event.changedTouches.length) { return; }
            var difference = event.changedTouches[0].clientX - touchX;
            touchX = null;
            if (Math.abs(difference) > 55) { move(difference < 0 ? 1 : -1); }
        }, { passive: true });
        show(0);
        start();
    }
    /** Faire défiler horizontalement les catégories, sans modifier la rotation des slides. */
    function initCategories(nav) {
        var track = nav.querySelector('[data-deprat-category-track]');
        var previous = nav.querySelector('[data-deprat-category-prev]');
        var next = nav.querySelector('[data-deprat-category-next]');
        if (!track || !previous || !next) { return; }

        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        function update() {
            var max = track.scrollWidth - track.clientWidth;
            previous.disabled = track.scrollLeft <= 2 || max <= 2;
            next.disabled = track.scrollLeft >= max - 2 || max <= 2;
        }
        function move(direction) {
            var distance = Math.max(145, Math.floor(track.clientWidth * .78));
            track.scrollBy({left: distance * direction, behavior: reducedMotion ? 'auto' : 'smooth'});
            window.setTimeout(update, reducedMotion ? 0 : 500);
        }
        previous.addEventListener('click', function () { move(-1); });
        next.addEventListener('click', function () { move(1); });
        track.addEventListener('scroll', update, {passive: true});
        window.addEventListener('resize', update, {passive: true});
        update();
    }
    function ready() {
        document.querySelectorAll('[data-deprat-home-slider]').forEach(init);
        document.querySelectorAll('[data-deprat-home-categories]').forEach(initCategories);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', ready); }
    else { ready(); }
}());
