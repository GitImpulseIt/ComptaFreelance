(function () {
    const SCROLL_KEY = 'banque_scroll';
    const main = document.querySelector('main');
    if (!main) return;

    const saved = sessionStorage.getItem(SCROLL_KEY);
    sessionStorage.removeItem(SCROLL_KEY);
    if (saved !== null && /\/app\/banque\/[^/?#]+/.test(document.referrer)) {
        main.scrollTop = parseInt(saved, 10) || 0;
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.transaction-row')) {
            sessionStorage.setItem(SCROLL_KEY, String(main.scrollTop));
        }
    }, true);
})();
