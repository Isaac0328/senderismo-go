(function () {
    function updateRange(input) {
        const box = input.closest('.survey-range-control');
        const output = box ? box.querySelector('[data-range-output]') : null;
        if (output) {
            output.textContent = input.value;
        }
    }

    document.querySelectorAll('[data-range-input]').forEach((input) => {
        updateRange(input);
        input.addEventListener('input', () => updateRange(input));
    });
})();
