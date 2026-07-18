(function () {
    'use strict';

    function showStep(form, index, focusHeading) {
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-apa-step]'));

        if (!steps.length) {
            return;
        }

        var targetIndex = Math.max(0, Math.min(index, steps.length - 1));
        steps.forEach(function (step, stepIndex) {
            step.hidden = stepIndex !== targetIndex;
        });
        form.dataset.apaCurrentStep = String(targetIndex);

        var portal = form.closest('[data-apa-portal]');
        if (portal) {
            portal.querySelectorAll('[data-apa-step-nav-index]').forEach(function (item) {
                var itemIndex = Number(item.dataset.apaStepNavIndex || 0);
                item.classList.toggle('is-active', itemIndex === targetIndex);
                item.classList.toggle('is-complete', itemIndex < targetIndex);

                if (itemIndex === targetIndex) {
                    item.setAttribute('aria-current', 'step');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
        }

        var heading = steps[targetIndex].querySelector('h2, h3');
        if (focusHeading && heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        }
    }

    function validateStep(step) {
        var controls = Array.prototype.slice.call(step.querySelectorAll('input, select, textarea'));

        for (var index = 0; index < controls.length; index++) {
            if (!controls[index].disabled && !controls[index].checkValidity()) {
                controls[index].reportValidity();
                return false;
            }
        }

        return true;
    }

    document.addEventListener('click', function (event) {
        var nextButton = event.target.closest('[data-apa-step-next]');

        if (nextButton) {
            var nextForm = nextButton.closest('[data-apa-step-form]');
            var currentStep = nextButton.closest('[data-apa-step]');

            if (nextForm && currentStep && validateStep(currentStep)) {
                showStep(nextForm, Number(currentStep.dataset.apaStepIndex || 0) + 1, true);
            }
            return;
        }

        var previousButton = event.target.closest('[data-apa-step-previous]');

        if (previousButton) {
            var previousForm = previousButton.closest('[data-apa-step-form]');
            var previousStep = previousButton.closest('[data-apa-step]');

            if (previousForm && previousStep) {
                showStep(previousForm, Number(previousStep.dataset.apaStepIndex || 0) - 1, true);
            }
            return;
        }

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

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('[data-apa-step-form]');

        if (!form) {
            return;
        }

        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-apa-step]'));
        var currentIndex = Number(form.dataset.apaCurrentStep || 0);

        // Pressing Enter behaves like “Continuer” until the final step.
        if (currentIndex < steps.length - 1) {
            event.preventDefault();
            if (validateStep(steps[currentIndex])) {
                showStep(form, currentIndex + 1, true);
            }
        }
    });

    function initializeStepForms() {
        document.querySelectorAll('[data-apa-step-form]').forEach(function (form) {
            showStep(form, 0, false);
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initializeStepForms);
    } else {
        initializeStepForms();
    }
}());
