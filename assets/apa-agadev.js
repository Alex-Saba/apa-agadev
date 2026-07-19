(function () {
    'use strict';

    var modalTrigger = null;

    function openAgreementModal(modal, trigger, focusCloseButton) {
        if (!modal) {
            return;
        }

        modalTrigger = trigger || modalTrigger;
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('acl_shortcode_modal_open');

        if (focusCloseButton) {
            var closeButton = modal.querySelector('.acl_shortcode_agreement_modal_close');
            if (closeButton) {
                closeButton.focus();
            }
        }
    }

    function closeAgreementModal(modal) {
        if (!modal) {
            return;
        }

        var submissionSucceeded = Boolean(modal.querySelector('.acl_shortcode_apa_form--success'));
        var successReturnUrl = modal.getAttribute('data-apa-success-return-url');

        modal.classList.remove('is-open');
        modal.hidden = true;
        document.body.classList.remove('acl_shortcode_modal_open');

        if (modalTrigger) {
            modalTrigger.focus();
            modalTrigger = null;
        }

        // A GET refresh discards the POST-rendered success state and rebuilds a fresh form.
        if (submissionSucceeded && successReturnUrl) {
            window.location.replace(successReturnUrl);
        }
    }

    function fieldSummaryValue(field) {
        var controls = Array.prototype.slice.call(
            field.querySelectorAll('input:not([type="hidden"]), select, textarea')
        );
        var values = [];

        if (1 === controls.length && 'checkbox' === controls[0].type) {
            return controls[0].checked ? 'Oui' : 'Non';
        }

        controls.forEach(function (control) {
            if (('checkbox' === control.type || 'radio' === control.type) && !control.checked) {
                return;
            }

            if ('SELECT' === control.tagName) {
                Array.prototype.forEach.call(control.selectedOptions || [], function (option) {
                    if (option.value) {
                        values.push(option.textContent.trim());
                    }
                });
                return;
            }

            if ('file' === control.type) {
                Array.prototype.forEach.call(control.files || [], function (file) {
                    values.push(file.name);
                });
                return;
            }

            if ('checkbox' === control.type || 'radio' === control.type) {
                var optionLabel = control.closest('label');
                values.push(optionLabel ? optionLabel.textContent.replace('*', '').trim() : control.value);
                return;
            }

            if (String(control.value || '').trim()) {
                values.push(String(control.value).trim());
            }
        });

        return values.length ? values.filter(function (value, index) {
            return values.indexOf(value) === index;
        }).join(', ') : 'Non renseigné';
    }

    function updateFileSummary(input) {
        var dropzone = input.closest('[data-apa-dropzone]');
        var summary = dropzone ? dropzone.querySelector('[data-apa-file-summary]') : null;
        var names = Array.prototype.map.call(input.files || [], function (file) {
            return file.name;
        });

        if (summary) {
            summary.textContent = names.length
                ? names.join(', ')
                : 'ou cliquez pour sélectionner un fichier';
        }
    }

    function prepareFileSubmission(form) {
        form.querySelectorAll('[data-apa-file-manifest-input]').forEach(function (input) {
            input.remove();
        });

        form.querySelectorAll('[data-apa-file-input]').forEach(function (input, index) {
            var pathInput = document.createElement('input');
            var multipleInput = document.createElement('input');

            input.name = 'apa_agadev_document_' + index + '[]';

            pathInput.type = 'hidden';
            pathInput.name = 'apa_agadev_document_path_' + index;
            pathInput.value = input.dataset.apaFilePath || '';
            pathInput.setAttribute('data-apa-file-manifest-input', '');

            multipleInput.type = 'hidden';
            multipleInput.name = 'apa_agadev_document_multiple_' + index;
            multipleInput.value = input.multiple ? '1' : '0';
            multipleInput.setAttribute('data-apa-file-manifest-input', '');

            form.appendChild(pathInput);
            form.appendChild(multipleInput);
        });
    }

    function populateReview(form) {
        var summary = form.querySelector('[data-apa-review-summary]');
        if (!summary) {
            return;
        }

        summary.innerHTML = '';
        var sectionCards = {};
        var dataSteps = form.querySelectorAll('[data-apa-step]:not([data-apa-review-step])');

        dataSteps.forEach(function (step) {
            var sectionIndex = String(step.dataset.apaSectionIndex || 0);
            var sectionCard = sectionCards[sectionIndex];

            if (!sectionCard) {
                sectionCard = document.createElement('article');
                sectionCard.className = 'acl_shortcode_apa_review_section';

                var sectionTitle = document.createElement('h3');
                sectionTitle.textContent = step.querySelector('.acl_shortcode_apa_section_title').textContent.trim();
                sectionCard.appendChild(sectionTitle);
                summary.appendChild(sectionCard);
                sectionCards[sectionIndex] = sectionCard;
            }

            var subsection = document.createElement('section');
            subsection.className = 'acl_shortcode_apa_review_subsection';

            var subsectionTitle = document.createElement('h4');
            subsectionTitle.textContent = step.querySelector('.acl_shortcode_apa_step_heading h2').textContent.trim();
            subsection.appendChild(subsectionTitle);

            var values = document.createElement('dl');
            step.querySelectorAll('.acl_shortcode_apa_field').forEach(function (field) {
                var label = field.querySelector('.acl_shortcode_label, .acl_shortcode_option');
                if (!label) {
                    return;
                }

                var item = document.createElement('div');
                var term = document.createElement('dt');
                var description = document.createElement('dd');
                term.textContent = label.textContent.replace('*', '').trim();
                description.textContent = fieldSummaryValue(field);
                item.appendChild(term);
                item.appendChild(description);
                values.appendChild(item);
            });

            subsection.appendChild(values);
            sectionCard.appendChild(subsection);
        });
    }

    function markSectionError(step, hasError) {
        var form = step.closest('[data-apa-step-form]');
        var sectionIndex = Number(step.dataset.apaSectionIndex || 0);
        var portal = form ? form.closest('[data-apa-portal]') : null;

        step.classList.toggle('has-error', hasError);

        if (portal) {
            var navigationItem = portal.querySelector('[data-apa-section-nav-index="' + sectionIndex + '"]');
            if (navigationItem) {
                navigationItem.classList.toggle('has-error', hasError);
            }
        }

        if (form) {
            form.querySelectorAll('[data-apa-step]').forEach(function (formStep) {
                var segments = formStep.querySelectorAll('.acl_shortcode_apa_section_segment');
                if (segments[sectionIndex]) {
                    segments[sectionIndex].classList.toggle('has-error', hasError);
                }
            });
        }
    }

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

        if (steps[targetIndex].hasAttribute('data-apa-review-step')) {
            populateReview(form);
        }

        var targetSectionIndex = Number(steps[targetIndex].dataset.apaSectionIndex || 0);
        var portal = form.closest('[data-apa-portal]');
        if (portal) {
            portal.querySelectorAll('[data-apa-section-nav-index]').forEach(function (item) {
                var itemIndex = Number(item.dataset.apaSectionNavIndex || 0);
                item.classList.toggle('is-active', itemIndex === targetSectionIndex);
                item.classList.toggle('is-complete', itemIndex < targetSectionIndex);

                if (itemIndex === targetSectionIndex) {
                    item.setAttribute('aria-current', 'step');
                } else {
                    item.removeAttribute('aria-current');
                }
            });
        }

        var heading = steps[targetIndex].querySelector('h2, h3');
        var status = form.querySelector('[data-apa-form-status]');
        if (status && heading) {
            status.textContent = heading.textContent.trim();
        }

        if (focusHeading && heading) {
            var scrollContainer = form.closest('.acl_shortcode_agreement_modal_body');
            if (scrollContainer) {
                scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
            }
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        }
    }

    function validateStep(step) {
        var controls = Array.prototype.slice.call(step.querySelectorAll('input, select, textarea'));

        for (var index = 0; index < controls.length; index++) {
            if (!controls[index].disabled && !controls[index].checkValidity()) {
                markSectionError(step, true);
                controls[index].reportValidity();
                return false;
            }
        }

        markSectionError(step, false);
        return true;
    }

    document.addEventListener('click', function (event) {
        var modalOpenButton = event.target.closest('[data-apa-agreement-modal-open]');

        if (modalOpenButton) {
            var modalId = modalOpenButton.getAttribute('aria-controls');
            openAgreementModal(modalId ? document.getElementById(modalId) : null, modalOpenButton, true);
            return;
        }

        var modalCloseButton = event.target.closest('[data-apa-agreement-modal-close]');

        if (modalCloseButton) {
            closeAgreementModal(modalCloseButton.closest('[data-apa-agreement-modal]'));
            return;
        }

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

    document.addEventListener('keydown', function (event) {
        if ('Escape' !== event.key) {
            return;
        }

        var modal = document.querySelector('[data-apa-agreement-modal].is-open');
        if (modal) {
            closeAgreementModal(modal);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-apa-file-input]')) {
            updateFileSummary(event.target);
        }
    });

    ['dragenter', 'dragover'].forEach(function (eventName) {
        document.addEventListener(eventName, function (event) {
            var dropzone = event.target.closest('[data-apa-dropzone]');
            if (dropzone) {
                dropzone.classList.add('is-dragging');
            }
        });
    });

    ['dragleave', 'drop'].forEach(function (eventName) {
        document.addEventListener(eventName, function (event) {
            var dropzone = event.target.closest('[data-apa-dropzone]');
            if (dropzone) {
                dropzone.classList.remove('is-dragging');
            }
        });
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
        } else {
            prepareFileSubmission(form);
        }
    });

    function initializeStepForms() {
        document.querySelectorAll('[data-apa-step-form]').forEach(function (form) {
            showStep(form, 0, false);
        });

        document.querySelectorAll('[data-apa-modal-initial-open]').forEach(function (modal) {
            openAgreementModal(modal, null, false);
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initializeStepForms);
    } else {
        initializeStepForms();
    }
}());
