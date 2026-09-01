// InnovaGo — podgląd statyczny: liczniki, menu mobilne, toast.
(function () {
  function animateCount(el) {
    var target = parseInt(el.getAttribute('data-count'), 10) || 0;
    var duration = 700;
    var start = null;
    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      el.textContent = Math.round(eased * target);
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  window.zpToast = function (message) {
    var el = document.getElementById('zp-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'zp-toast';
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.classList.remove('show'); }, 2600);
  };

  document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.navbar-toggle');
    var links = document.querySelector('.navbar-links');
    if (toggle && links) {
      toggle.addEventListener('click', function () { links.classList.toggle('open'); });
    }

    var counters = document.querySelectorAll('[data-count]');
    if (counters.length) {
      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) { animateCount(entry.target); io.unobserve(entry.target); }
          });
        }, { threshold: 0.4 });
        counters.forEach(function (c) { io.observe(c); });
      } else {
        counters.forEach(animateCount);
      }
    }
  });
})();
