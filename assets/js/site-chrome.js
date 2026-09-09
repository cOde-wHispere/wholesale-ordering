(function () {
    'use strict';

    function initWholesaleOrderingNavigation() {
        var toggle = document.querySelector('.wholesale-ordering-mobile-toggle');
        var navigation = document.getElementById('wholesale-ordering-primary-navigation');
        if (!toggle || !navigation) { return; }

        function setNavigationState(open) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navigation.classList.toggle('is-open', open);
            document.body.classList.toggle('wholesale-ordering-menu-open', open);
            var label = toggle.querySelector('.screen-reader-text');
            if (label) { label.textContent = open ? 'Close navigation' : 'Open navigation'; }
        }

        toggle.addEventListener('click', function () {
            setNavigationState(toggle.getAttribute('aria-expanded') !== 'true');
        });
        navigation.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', function () { setNavigationState(false); }); });
        document.addEventListener('keydown', function (event) {
            if ('Escape' === event.key && toggle.getAttribute('aria-expanded') === 'true') { setNavigationState(false); toggle.focus(); }
        });
        window.addEventListener('resize', function () { if (window.innerWidth >= 900) { setNavigationState(false); } });
    }

    if ('loading' === document.readyState) { document.addEventListener('DOMContentLoaded', initWholesaleOrderingNavigation); } else { initWholesaleOrderingNavigation(); }
}());
