(function($) {
    'use strict';

    var config = window.prayerPopFormConfig || null;
    if (!config) {
        return;
    }

    function setStartTime($form) {
        var $field = $form.find('input[name="prayer_pop_start_time"]');
        if ($field.length) {
            $field.val(Math.floor(Date.now() / 1000));
        }
    }

    function formatTimeUnit(value, unit, units) {
        var key = unit + '_' + (value === 1 ? 'singular' : 'plural');
        return value + ' ' + units[key];
    }

    function formatTimeAgo(seconds) {
        if (!isFinite(seconds) || seconds < 0) {
            seconds = 0;
        }
        seconds = Math.floor(seconds);

        var units = config.timeUnits;

        if (seconds < 60) {
            return formatTimeUnit(seconds, 'second', units);
        } else if (seconds < 3600) {
            return formatTimeUnit(Math.floor(seconds / 60), 'minute', units);
        } else if (seconds < 86400) {
            return formatTimeUnit(Math.floor(seconds / 3600), 'hour', units);
        }

        return formatTimeUnit(Math.floor(seconds / 86400), 'day', units);
    }

    function isLastSubmissionEnabled() {
        if (typeof config.enableLastSubmissionTime !== 'undefined') {
            return !!config.enableLastSubmissionTime;
        }
        if (typeof config.showLastSubmission !== 'undefined') {
            return !!config.showLastSubmission;
        }
        return true;
    }

    function normalizeUnixTimestamp(rawTimestamp) {
        var timestamp = 0;
        var now = Math.floor(Date.now() / 1000);

        if (typeof rawTimestamp === 'number' && isFinite(rawTimestamp)) {
            timestamp = rawTimestamp;
        } else if (typeof rawTimestamp === 'string') {
            var trimmed = $.trim(rawTimestamp);
            if (!trimmed) {
                return 0;
            }

            if (/^-?\d+(\.\d+)?$/.test(trimmed)) {
                timestamp = parseFloat(trimmed);
            } else {
                var parsedMillis = Date.parse(trimmed);
                if (!isNaN(parsedMillis)) {
                    timestamp = parsedMillis / 1000;
                }
            }
        }

        if (!isFinite(timestamp)) {
            return 0;
        }

        timestamp = Math.floor(timestamp);

        // Convert milliseconds to seconds.
        if (timestamp > 9999999999) {
            timestamp = Math.floor(timestamp / 1000);
        }

        // Reject invalid/ancient/far-future values.
        if (timestamp < 946684800 || timestamp > (now + 86400)) {
            return 0;
        }

        return timestamp;
    }

    var formDraftStoragePrefix = 'prayer_pop_form_draft:' + window.location.pathname + ':';

    function getFormType($root) {
        var type = ($root && $root.length) ? String($root.data('type') || '') : '';
        if (!type) {
            type = String($root.find('input[name="prayer_pop_type"]').val() || '');
        }
        return type;
    }

    function getDraftKey($root) {
        var type = getFormType($root);
        if (!type) {
            return null;
        }
        return formDraftStoragePrefix + type;
    }

    function hasMeaningfulDraft(draft) {
        if (!draft || typeof draft !== 'object') {
            return false;
        }

        var message = typeof draft.message === 'string' ? draft.message.trim() : '';
        var name = typeof draft.name === 'string' ? draft.name.trim() : '';
        var isPublic = !!draft.isPublic;
        var isReadyToShare = !!draft.isReadyToShare;

        return !!(message || name || isPublic || isReadyToShare);
    }

    function saveDraft($root) {
        var key = getDraftKey($root);
        if (!key || !window.sessionStorage) {
            return;
        }

        var draft = {
            message: ($root.find('textarea[name="prayer_pop_message"]').val() || ''),
            name: ($root.find('input[name="prayer_pop_name"]').val() || ''),
            isPublic: $root.find('input[name="prayer_pop_public"]').is(':checked'),
            isReadyToShare: $root.find('input[name="prayer_pop_ready_to_share"]').is(':checked')
        };

        try {
            if (hasMeaningfulDraft(draft)) {
                window.sessionStorage.setItem(key, JSON.stringify(draft));
            } else {
                window.sessionStorage.removeItem(key);
            }
        } catch (e) {
            // Ignore storage errors.
        }
    }

    function clearDraft($root) {
        var key = getDraftKey($root);
        if (!key || !window.sessionStorage) {
            return;
        }

        try {
            window.sessionStorage.removeItem(key);
        } catch (e) {
            // Ignore storage errors.
        }
    }

    function restoreDraft($root) {
        var key = getDraftKey($root);
        if (!key || !window.sessionStorage) {
            return;
        }

        try {
            var raw = window.sessionStorage.getItem(key);
            if (!raw) {
                return;
            }

            var draft = JSON.parse(raw);
            if (!hasMeaningfulDraft(draft)) {
                return;
            }

            $root.find('textarea[name="prayer_pop_message"]').val(typeof draft.message === 'string' ? draft.message : '');
            $root.find('input[name="prayer_pop_name"]').val(typeof draft.name === 'string' ? draft.name : '');
            $root.find('input[name="prayer_pop_public"]').prop('checked', !!draft.isPublic);
            $root.find('input[name="prayer_pop_ready_to_share"]').prop('checked', !!draft.isReadyToShare);
        } catch (e) {
            // Ignore malformed JSON or storage errors.
        }
    }

    function updateLastTime($root) {
        if (!isLastSubmissionEnabled()) {
            $root.find('.prayer-pop-form__last-time').hide();
            return;
        }

        var type = $root.data('type');
        var lastTimeData = config.lastTimes[type];
        var $container = $root.find('.prayer-pop-form__last-time');
        var $text = $root.find('.prayer-pop-form__last-time-text');

        var timestamp = lastTimeData ? normalizeUnixTimestamp(lastTimeData.timestamp) : 0;
        if (!timestamp) {
            $container.hide();
            return;
        }

        var utcNow = Math.floor(Date.now() / 1000);
        var diff = utcNow - timestamp;
        if (!isFinite(diff) || diff < 0) {
            diff = 0;
        }
        var template = (lastTimeData && typeof lastTimeData.message === 'string') ? lastTimeData.message : '';
        if (!template) {
            $container.hide();
            return;
        }

        var timeAgo = formatTimeAgo(diff);
        var message = template.replace('{time_ago}', timeAgo);

        $text.text(message);
        $container.css({
            display: 'flex',
            visibility: 'visible',
            opacity: '1'
        }).show();
    }

    function resetForm($root) {
        var $form = $root.find('.prayer-pop-form__form');

        $form[0].reset();
        setStartTime($form);
        clearDraft($root);

        $root.find('.prayer-pop-form__success').hide().empty();
        $root.find('.prayer-pop-form__error').hide().empty();
        $root.find('.prayer-pop-form__header').show();
        $root.find('.prayer-pop-form__last-time').show();
        $form.show();
    }

    function handleFormSubmit($root, $form) {
        var $submitButton = $form.find('.prayer-pop-form__submit');
        var $nameInput = $form.find('input[name="prayer_pop_name"]');
        var type = $root.data('type');
        var originalButtonText = $submitButton.data('original-label') || $.trim($submitButton.text());
        var submittingText = (config.messages && config.messages.submitting)
            ? config.messages.submitting
            : 'Sending...';

        if (!$submitButton.data('original-label')) {
            $submitButton.data('original-label', originalButtonText);
        }

        if ($nameInput.val().trim() === '' && !$nameInput.prop('required')) {
            $nameInput.val(config.anonymousName);
        }

        $submitButton.prop('disabled', true);
        $submitButton.text(submittingText);

        $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            data: {
                action: 'prayer_pop_submit',
                nonce: config.nonce,
                data: $form.serialize()
            },
            success: function(response) {
                if (response.success) {
                    clearDraft($root);
                    if (config.lastTimes[type] && response.data && response.data.timestamp) {
                        config.lastTimes[type].timestamp = normalizeUnixTimestamp(response.data.timestamp);
                        config.lastTimes[type].message = response.data.message_template || config.lastTimes[type].message;
                        updateLastTime($root);
                    }

                    $form.hide();
                    $root.find('.prayer-pop-form__error').hide();
                    $root.find('.prayer-pop-form__header').hide();
                    $root.find('.prayer-pop-form__last-time').hide();

                    var successMessage = response.data && response.data.message ? response.data.message : config.messages.success;
                    var $success = $root.find('.prayer-pop-form__success');
                    $success.text(successMessage).show();

                    if ($root.find('.prayer-pop-form__new-request').length === 0) {
                        $success.after(
                            '<button class="prayer-pop-form__new-request prayer-pop-form__submit" type="button">' +
                            config.messages.newRequest +
                            '</button>'
                        );
                    }
                } else {
                    var errorMessage = (response && typeof response.data === 'string' && response.data.trim() !== '')
                        ? response.data
                        : config.messages.error;
                    $root.find('.prayer-pop-form__error').text(errorMessage).show();
                }

            },
            error: function() {
                $root.find('.prayer-pop-form__error').text(config.messages.error).show();
            },
            complete: function() {
                $submitButton.prop('disabled', false);
                $submitButton.text(originalButtonText);
            }
        });
    }

    $('.prayer-pop-form').each(function() {
        var $root = $(this);
        var $form = $root.find('.prayer-pop-form__form');

        setStartTime($form);
        restoreDraft($root);
        updateLastTime($root);

        var interval = setInterval(function() {
            updateLastTime($root);
        }, 60000);

        $root.data('lastTimeInterval', interval);

        $form.on('submit', function(e) {
            e.preventDefault();
            handleFormSubmit($root, $form);
        });

        $root.on('click', '.prayer-pop-form__new-request', function() {
            $(this).remove();
            resetForm($root);
        });

        $form.on('input change', 'textarea[name="prayer_pop_message"], input[name="prayer_pop_name"], input[name="prayer_pop_public"], input[name="prayer_pop_ready_to_share"]', function() {
            saveDraft($root);
        });
    });
})(jQuery);
