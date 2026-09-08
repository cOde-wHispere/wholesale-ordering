document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.querySelector(
        '.wholesale-ordering-mobile-toggle'
    );

    const navigation = document.getElementById(
        'wholesale-ordering-primary-navigation'
    );

    if (!toggle || !navigation) {
        return;
    }

    const setNavigationState = function (open) {
        toggle.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );

        navigation.classList.toggle(
            'is-open',
            open
        );

        document.body.classList.toggle(
            'wholesale-ordering-menu-open',
            open
        );

        const label = toggle.querySelector(
            '.screen-reader-text'
        );

        if (label) {
            label.textContent = open
                ? 'Close navigation'
                : 'Open navigation';
        }
    };

    toggle.addEventListener('click', function () {
        const isOpen =
            toggle.getAttribute('aria-expanded') === 'true';

        setNavigationState(!isOpen);
    });

    navigation.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            setNavigationState(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if ('Escape' !== event.key) {
            return;
        }

        if (
            toggle.getAttribute('aria-expanded') === 'true'
        ) {
            setNavigationState(false);
            toggle.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth >= 900) {
            setNavigationState(false);
        }
    });
});