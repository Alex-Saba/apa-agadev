(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-apa-add-row]');

        if (addButton) {
            var repeater = addButton.closest('[data-apa-repeater]');
            var rows = repeater ? repeater.querySelector('[data-apa-repeater-rows]') : null;
            var template = repeater ? repeater.querySelector('[data-apa-repeater-template]') : null;

            if (!rows || !template) {
                return;
            }

            var indexes = Array.prototype.map.call(
                rows.querySelectorAll('[data-apa-row-index]'),
                function (row) { return Number(row.dataset.apaRowIndex) || 0; }
            );
            var index = indexes.length ? Math.max.apply(null, indexes) + 1 : 0;
            rows.insertAdjacentHTML('beforeend', template.innerHTML.split('__INDEX__').join(String(index)));

            // Template controls share a static PHP-generated id; make each
            // inserted row's label associations unique in the document.
            var insertedRow = rows.lastElementChild;
            insertedRow.querySelectorAll('[id]').forEach(function (input) {
                var oldId = input.id;
                var newId = oldId + '-' + index;
                input.id = newId;
                insertedRow.querySelectorAll('label[for="' + oldId + '"]').forEach(function (label) {
                    label.setAttribute('for', newId);
                });
            });
            return;
        }

        var removeButton = event.target.closest('[data-apa-remove-row]');

        if (removeButton) {
            var row = removeButton.closest('[data-apa-repeater-row]');
            var rowContainer = row ? row.parentElement : null;

            // Keep one empty row so required repeaters remain directly usable.
            if (row && rowContainer && rowContainer.children.length > 1) {
                row.remove();
            } else if (row) {
                row.querySelectorAll('input, textarea, select').forEach(function (input) {
                    if ('checkbox' === input.type || 'radio' === input.type) {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
            }
        }
    });
}());
