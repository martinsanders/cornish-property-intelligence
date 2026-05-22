(function ($) {
    'use strict';

    function normaliseColor(value) {
        const trimmed = String(value || '').trim();

        return /^#[0-9a-f]{3,8}$/i.test(trimmed) ? trimmed : '';
    }

    function pickerPalette() {
        const seen = new Set();
        const palette = [];

        if (!window.cpiAdminDesign || !Array.isArray(window.cpiAdminDesign.palette)) {
            return palette;
        }

        window.cpiAdminDesign.palette.forEach((item) => {
            const color = normaliseColor(item && item.color);
            const key = color.toLowerCase();

            if (!color || seen.has(key)) {
                return;
            }

            seen.add(key);
            palette.push(color);
        });

        return palette;
    }

    $(function () {
        $('.cpi-color-picker').wpColorPicker({
            palettes: pickerPalette(),
        });
    });
}(jQuery));
