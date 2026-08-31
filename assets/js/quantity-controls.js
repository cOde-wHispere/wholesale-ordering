(function () {
    'use strict';

    function decimalPlaces(value) {
        var stringValue = String(value || '');
        if (stringValue.indexOf('.') === -1) {
            return 0;
        }
        return stringValue.split('.')[1].length;
    }

    function roundToStep(value, step) {
        var decimals = Math.max(decimalPlaces(value), decimalPlaces(step));
        var factor = Math.pow(10, decimals);
        return Math.round(value * factor) / factor;
    }

    function updateQuantity(input, direction) {
        var step = parseFloat(input.getAttribute('step'));
        var min = parseFloat(input.getAttribute('min'));
        var max = parseFloat(input.getAttribute('max'));
        var current = parseFloat(input.value);

        if (!isFinite(step) || step <= 0) {
            step = 1;
        }

        if (!isFinite(current)) {
            current = isFinite(min) ? min : 0;
        }

        var next = current + (direction * step);

        if (isFinite(min)) {
            next = Math.max(min, next);
        }

        if (isFinite(max)) {
            next = Math.min(max, next);
        }

        next = roundToStep(next, step);
        input.value = String(next);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function addControls(container) {
        var input = container.querySelector('.qty');

        if (!input || container.querySelector('.wholesale-quantity-button')) {
            return;
        }

        var decrease = document.createElement('button');
        decrease.type = 'button';
        decrease.className = 'wholesale-quantity-button wholesale-quantity-decrease';
        decrease.setAttribute('aria-label', 'Decrease quantity');
        decrease.textContent = '−';

        var increase = document.createElement('button');
        increase.type = 'button';
        increase.className = 'wholesale-quantity-button wholesale-quantity-increase';
        increase.setAttribute('aria-label', 'Increase quantity');
        increase.textContent = '+';

        decrease.addEventListener('click', function () {
            updateQuantity(input, -1);
        });

        increase.addEventListener('click', function () {
            updateQuantity(input, 1);
        });

        container.insertBefore(decrease, input);
        container.appendChild(increase);
    }

    function init() {
        document.querySelectorAll('.quantity').forEach(addControls);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    if ('MutationObserver' in window) {
        var observer = new MutationObserver(function () {
            init();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})();
