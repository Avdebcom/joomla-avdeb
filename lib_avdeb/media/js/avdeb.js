/*!
 * AVDEB Products for Joomla — carousel arrows and ←/→ keys over the native scroll-snap track.
 * The only script the embeds use: no network, no cookies. Without it the carousel still scrolls.
 */
(() => {
  'use strict';

  const setup = (root) => {
    if (root.dataset.avdebReady) return;
    root.dataset.avdebReady = '1';
    const track = root.querySelector('[data-avdeb-track]');
    const prev = root.querySelector('[data-avdeb-prev]');
    const next = root.querySelector('[data-avdeb-next]');
    if (!track || !prev || !next) return;

    const rtl = getComputedStyle(track).direction === 'rtl';
    const smooth = !matchMedia('(prefers-reduced-motion: reduce)').matches;
    const update = () => {
      const max = track.scrollWidth - track.clientWidth - 2;
      const x = Math.abs(track.scrollLeft);
      prev.disabled = x <= 2;
      next.disabled = x >= max;
      const overflow = max > 0;
      prev.hidden = !overflow;
      next.hidden = !overflow;
      root.toggleAttribute('data-avdeb-overflow', overflow);
    };
    const go = (dir) => track.scrollBy({
      left: dir * (rtl ? -1 : 1) * track.clientWidth * 0.9,
      behavior: smooth ? 'smooth' : 'auto',
    });

    prev.addEventListener('click', () => go(-1));
    next.addEventListener('click', () => go(1));
    track.addEventListener('scroll', update, { passive: true });
    track.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        e.preventDefault();
        go((e.key === 'ArrowRight') !== rtl ? 1 : -1);
      }
    });
    if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
    update();
  };

  const run = () => document.querySelectorAll('[data-avdeb-carousel]').forEach(setup);

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
