/* Site public : apparitions au défilement, halo du héros, mot et scène synchronisés. */
(function () {
    'use strict';

    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var nodes = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));

    if (reduce || !('IntersectionObserver' in window)) {
        nodes.forEach(function (node) {
            node.classList.add('is-in');
        });
    } else {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
        nodes.forEach(function (node) {
            observer.observe(node);
        });
    }

    var hero = document.querySelector('[data-hero]');
    var blob = document.querySelector('[data-blob]');
    if (hero && blob && !reduce && window.matchMedia('(pointer: fine)').matches) {
        var frame = 0;
        hero.addEventListener('pointermove', function (event) {
            if (frame) {
                return;
            }
            frame = window.requestAnimationFrame(function () {
                var box = hero.getBoundingClientRect();
                var x = event.clientX - box.left - 210;
                var y = event.clientY - box.top - 210;
                blob.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
                frame = 0;
            });
        });
    }

    /* Mot du titre et photo de la scène changent ensemble (4 services). */
    var rot = document.querySelector('[data-hx-rot]');
    var stage = document.querySelector('[data-hx-stage]');
    if (rot && stage) {
        var list = rot.querySelector('.hx-rot-list');
        var scenes = Array.prototype.slice.call(stage.querySelectorAll('[data-hx-scene]'));
        var count = scenes.length;
        var index = 0;
        var holdMs = 2500;
        var timer = 0;

        function apply(sceneIndex, listIndex, instant) {
            if (!list || count < 1) {
                return;
            }
            index = sceneIndex;
            if (instant) {
                list.style.transition = 'none';
            }
            list.style.transform = 'translateY(' + (-listIndex * 1.02) + 'em)';
            scenes.forEach(function (scene, i) {
                scene.classList.toggle('is-on', i === sceneIndex);
            });
            if (instant) {
                list.offsetHeight;
                list.style.transition = '';
            }
        }

        apply(0, 0, true);

        if (!reduce) {
            function tick() {
                if (index >= count - 1) {
                    apply(0, count, false);
                    window.setTimeout(function () {
                        apply(0, 0, true);
                        timer = window.setTimeout(tick, holdMs);
                    }, 560);
                    return;
                }
                apply(index + 1, index + 1, false);
                timer = window.setTimeout(tick, holdMs);
            }
            timer = window.setTimeout(tick, holdMs);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    window.clearTimeout(timer);
                } else {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(tick, holdMs);
                }
            });
        }
    }
}());
