(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var selectButton = event.target.closest('[data-apa-agadev-media-select]');
        var removeButton = event.target.closest('[data-apa-agadev-media-remove]');

        if (!selectButton && !removeButton) {
            return;
        }

        var field = event.target.closest('[data-apa-agadev-media-field]');
        if (!field) {
            return;
        }

        event.preventDefault();

        var input = field.querySelector('[data-apa-agadev-media-input]');
        var preview = field.querySelector('[data-apa-agadev-media-preview]');

        if (removeButton) {
            input.value = '';
            preview.replaceChildren();
            removeButton.classList.add('is-hidden');
            field.querySelector('[data-apa-agadev-media-select]').textContent = apaAgadevLotMedia.selectButton;
            return;
        }

        var frame = wp.media({
            title: apaAgadevLotMedia.frameTitle,
            button: { text: apaAgadevLotMedia.frameButton },
            library: { type: 'image' },
            multiple: false
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var source = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;
            var image = document.createElement('img');

            image.src = source;
            image.alt = attachment.alt || '';
            image.className = 'apa-agadev-media-preview-image';
            preview.replaceChildren(image);
            input.value = String(attachment.id);
            removeButton.classList.remove('is-hidden');
            selectButton.textContent = apaAgadevLotMedia.replaceButton;
        });

        frame.open();
    });
}());
