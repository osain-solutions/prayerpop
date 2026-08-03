(function () {
    'use strict';

    var panel = document.getElementById('ppfc-panel');
    if (!panel) return;

    var cfg = window.PrayerPopChat || {
        root: panel.dataset.restRoot,
        i18n: {
            error: panel.dataset.errorMessage
        }
    };

    if (!cfg.root) return;

    var current = null;
    var lastMessageId = 0;
    var pollTimer = null;
    var lastFocusedElement = null;
    var panelOpening = false;
    var panelClosing = false;
    var classicPanelHeight = 0;

    function panelIsOpen() {
        return !panel.hidden && panel.getAttribute('aria-hidden') !== 'true';
    }

    function focusableElements() {
        return Array.prototype.slice.call(
            panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
        ).filter(function (element) {
            return !element.hidden && element.getAttribute('aria-hidden') !== 'true' && !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
        });
    }

    function focusPanel() {
        var focusable = focusableElements();
        var target = focusable[0] || panel;
        if (!panel.hasAttribute('tabindex')) panel.setAttribute('tabindex', '-1');
        window.setTimeout(function () {
            if (panelIsOpen() && target && typeof target.focus === 'function') target.focus();
        }, 0);
    }

    function setOnboardingStep(step) {
        var allowed = ['name', 'email', 'message'];
        var form = panel.querySelector('.ppfc-start-form');
        if (allowed.indexOf(step) === -1) step = 'name';
        form.querySelectorAll('.ppfc-onboarding-step').forEach(function (section) {
            var active = section.dataset.step === step;
            section.hidden = !active;
            section.classList.toggle('is-active', active);
        });
        if (step === 'email') {
            form.querySelector('.ppfc-visitor-name-preview').textContent = form.elements.name.value.trim();
        }
        var target = step === 'name' ? form.elements.name : (step === 'email' ? form.elements.email : form.elements.message);
        if (target) window.setTimeout(function () { target.focus(); }, 30);
    }

    function request(path, options) {
        options = options || {};
        options.credentials = 'same-origin';
        options.headers = Object.assign({'Content-Type': 'application/json'}, options.headers || {});
        return fetch(cfg.root + path, options).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) throw payload;
                return payload;
            });
        });
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character];
        });
    }

    function formatMessageTime(value) {
        if (!value) return '';
        var stringValue = String(value);
        var normalized = stringValue.replace(' ', 'T') + (stringValue.indexOf('Z') === -1 ? 'Z' : '');
        var date = new Date(normalized);
        if (isNaN(date.getTime())) return '';
        var seconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000));
        if (seconds < 60) return cfg.i18n.justNow;
        if (seconds < 3600) return cfg.i18n.minutes.replace('{count}', Math.max(1, Math.floor(seconds / 60)));
        if (seconds < 86400) return cfg.i18n.hours.replace('{count}', Math.floor(seconds / 3600));
        return date.toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
    }

    function showError(error) {
        var box = panel.querySelector('.ppfc-error');
        box.textContent = error && error.message ? error.message : cfg.i18n.error;
        box.hidden = false;
    }

    function renderMessages(messages, append) {
        var box = panel.querySelector('.ppfc-messages');
        if (!append) {
            box.querySelectorAll('.ppfc-message').forEach(function (message) { message.remove(); });
            lastMessageId = 0;
        }
        (messages || []).forEach(function (message) {
            if (box.querySelector('[data-message-id="' + Number(message.id) + '"]')) return;
            var item = document.createElement('div');
            item.className = 'ppfc-message ppfc-message-' + (message.sender_type === 'admin' ? 'admin' : 'visitor');
            item.dataset.messageId = message.id;
            item.innerHTML = '<div>' + escapeHtml(message.message).replace(/\n/g, '<br>') + '</div><time datetime="' + escapeHtml(message.created_at) + '">' + escapeHtml(formatMessageTime(message.created_at)) + '</time>';
            box.appendChild(item);
            lastMessageId = Math.max(lastMessageId, Number(message.id));
        });
        box.scrollTop = box.scrollHeight;
    }

    function applyConversation(payload) {
        current = payload.conversation || null;
        var start = panel.querySelector('.ppfc-start');
        var conversation = panel.querySelector('.ppfc-conversation');
        if (!current) {
            start.hidden = false;
            conversation.hidden = true;
            setOnboardingStep('name');
            return;
        }
        start.hidden = true;
        conversation.hidden = false;
        var closed = current.status === 'closed';
        conversation.querySelector('.ppfc-closed').hidden = !closed;
        conversation.querySelector('.ppfc-composer').hidden = closed;
        if (payload.messages) renderMessages(payload.messages, false);
        if (current.visitor_unread) {
            request('read', {method: 'POST', body: JSON.stringify({conversation_id: current.id})}).catch(function () {});
        }
        schedulePoll();
    }

    function loadConversation() {
        return request('conversation').then(function (payload) {
            if (!payload.conversation) return applyConversation(payload);
            return request('messages?conversation_id=' + Number(payload.conversation.id)).then(applyConversation);
        }).catch(showError);
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        if (!current || current.status !== 'open' || panel.hidden) return;
        pollTimer = setTimeout(function () {
            request('messages?conversation_id=' + Number(current.id) + '&after_id=' + lastMessageId).then(function (payload) {
                current = payload.conversation;
                renderMessages(payload.messages || [], true);
                var closed = current.status === 'closed';
                panel.querySelector('.ppfc-closed').hidden = !closed;
                panel.querySelector('.ppfc-composer').hidden = closed;
            }).catch(function () {}).finally(schedulePoll);
        }, 5000);
    }

    function openPanel(options) {
        if (panelIsOpen() || panelOpening) return;
        options = options || {};
        panelOpening = true;
        lastFocusedElement = document.activeElement;
        loadConversation().finally(function () {
            if (typeof options.beforeReveal === 'function') options.beforeReveal();

            panel.classList.toggle('ppfc-route-transition', !!options.transitionFromHeight);
            panel.style.removeProperty('height');
            panel.hidden = false;
            panel.setAttribute('aria-hidden', 'false');

            if (!options.transitionFromHeight && window.PrayerPopMotion) {
                window.PrayerPopMotion.enter(panel);
            }

            if (options.transitionFromHeight) {
                var targetHeight = panel.getBoundingClientRect().height;
                var sourceHeight = Math.max(0, options.transitionFromHeight);
                var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                panel.style.height = targetHeight + 'px';

                if (!reduceMotion && typeof panel.animate === 'function' && Math.abs(targetHeight - sourceHeight) > 1) {
                    panel.animate(
                        [
                            {height: sourceHeight + 'px'},
                            {height: targetHeight + 'px'}
                        ],
                        {
                            duration: 220,
                            easing: 'cubic-bezier(.2, .8, .2, 1)'
                        }
                    );
                } else if (!reduceMotion) {
                    panel.style.height = sourceHeight + 'px';
                    panel.offsetHeight;
                    window.requestAnimationFrame(function () {
                        panel.style.height = targetHeight + 'px';
                    });
                }
            }

            var bubble = document.getElementById('prayer-pop-bubble');
            if (bubble) bubble.setAttribute('aria-expanded', 'true');
            document.body.classList.add('ppfc-open');
            panelOpening = false;
            schedulePoll();
            focusPanel();
        });
    }

    function closePanel(options) {
        if (!panelIsOpen() || panelClosing) return;
        options = options && options.immediate ? options : {};
        panelClosing = true;
        var finish = function () {
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
            panel.classList.remove('ppfc-route-transition');
            panel.style.removeProperty('height');
            var bubble = document.getElementById('prayer-pop-bubble');
            if (bubble) bubble.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('ppfc-open');
            clearTimeout(pollTimer);
            var previousFocusIsVisible = lastFocusedElement
                && lastFocusedElement !== document.body
                && document.contains(lastFocusedElement)
                && !!(lastFocusedElement.offsetWidth || lastFocusedElement.offsetHeight || lastFocusedElement.getClientRects().length);
            var focusTarget = previousFocusIsVisible ? lastFocusedElement : bubble;
            lastFocusedElement = null;
            panelClosing = false;
            if (focusTarget && typeof focusTarget.focus === 'function') focusTarget.focus();
        };

        if (options.immediate || !window.PrayerPopMotion) finish();
        else window.PrayerPopMotion.exit(panel, finish);
    }

    function restoreClassicPopup(previousHeight) {
        closePanel({immediate: true});
        var options = document.getElementById('prayer-pop-initial-options');
        var form = document.getElementById('prayer-pop-form-wrapper');
        var modal = document.getElementById('prayer-pop-modal');
        var container = document.getElementById('prayer-pop-form-container');
        var bubble = document.getElementById('prayer-pop-bubble');
        if (options) options.style.display = '';
        var intro = document.getElementById('prayer-pop-popup-intro');
        if (intro) intro.style.display = '';
        if (form) form.style.display = 'none';
		if (container) container.classList.remove('none', 'fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in');
        if (modal) {
            modal.style.display = 'block';
            modal.setAttribute('aria-hidden', 'false');
        }
        if (bubble) bubble.setAttribute('aria-expanded', 'true');

        if (container && previousHeight > 0) {
            var targetHeight = classicPanelHeight || container.getBoundingClientRect().height;
            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            container.style.height = targetHeight + 'px';

            if (!reduceMotion && typeof container.animate === 'function' && Math.abs(previousHeight - targetHeight) > 1) {
                var animation = container.animate(
                    [
                        {height: previousHeight + 'px'},
                        {height: targetHeight + 'px'}
                    ],
                    {
                        duration: 220,
                        easing: 'cubic-bezier(.2, .8, .2, 1)'
                    }
                );
                animation.finished.catch(function () {}).finally(function () {
                    container.style.removeProperty('height');
                });
            } else {
                container.style.removeProperty('height');
            }
        }

        window.setTimeout(function () {
            var target = options && options.querySelector('button:not([hidden])');
            if (!target) target = container;
            if (target && typeof target.focus === 'function') target.focus();
        }, 0);
    }

    function returnToClassicPopup() {
        var currentHeight = panel.getBoundingClientRect().height;
        restoreClassicPopup(currentHeight);
    }

    function openFromClassicPopup(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var modal = document.getElementById('prayer-pop-modal');
        var bubble = document.getElementById('prayer-pop-bubble');
        var intro = document.getElementById('prayer-pop-popup-intro');
        var options = document.getElementById('prayer-pop-initial-options');
        var form = document.getElementById('prayer-pop-form-wrapper');
        var container = document.getElementById('prayer-pop-form-container');
		panel.classList.remove('fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in');
        var sourceHeight = container ? container.getBoundingClientRect().height : 0;
        classicPanelHeight = sourceHeight;

        openPanel({
            transitionFromHeight: sourceHeight,
            beforeReveal: function () {
                if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                }
                if (intro) intro.style.display = '';
                if (options) options.style.display = '';
                if (form) form.style.display = 'none';
            }
        });
    }

    panel.querySelector('.ppfc-back').addEventListener('click', returnToClassicPopup);
    panel.querySelector('.ppfc-close').addEventListener('click', closePanel);

    document.querySelectorAll('.ppm-classic-chat-launch').forEach(function (button) {
        button.addEventListener('click', openFromClassicPopup, true);
    });

    var bubble = document.getElementById('prayer-pop-bubble');
    if (bubble) {
        bubble.addEventListener('click', function (event) {
            if (!panelIsOpen()) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            closePanel();
        }, true);
    }

    document.addEventListener('click', function (event) {
        if (panelIsOpen() && !panel.contains(event.target) && (!bubble || !bubble.contains(event.target))) {
            closePanel();
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (!panelIsOpen()) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closePanel();
            return;
        }
        if (event.key !== 'Tab') return;
        var focusable = focusableElements();
        if (!focusable.length) {
            event.preventDefault();
            panel.focus();
            return;
        }
        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        if (event.shiftKey && (document.activeElement === first || !panel.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !panel.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    }, true);

    panel.querySelector('.ppfc-start-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var button = form.querySelector('button[type="submit"]');
		if (!form.elements.name.value.trim()) {
			setOnboardingStep('name');
			form.elements.name.setAttribute('aria-invalid', 'true');
			return;
		}
		if (form.elements.email.value && !form.elements.email.checkValidity()) {
			setOnboardingStep('email');
			form.elements.email.setAttribute('aria-invalid', 'true');
			form.elements.email.reportValidity();
			return;
		}
		if (!form.elements.message.value.trim()) {
			setOnboardingStep('message');
			return;
		}
        button.disabled = true;
        panel.querySelector('.ppfc-error').hidden = true;
        request('conversations', {method: 'POST', body: JSON.stringify({
            name: form.elements.name.value,
            email: form.elements.email.value,
            message: form.elements.message.value,
            website: form.elements.website.value,
            started_at: form.elements.started_at.value
        })}).then(function (payload) {
            form.reset();
			form.elements.started_at.value = Math.floor(Date.now() / 1000);
            applyConversation(payload);
        }).catch(showError).finally(function () { button.disabled = false; });
    });

	panel.querySelectorAll('.ppfc-step-next').forEach(function (button) {
		button.addEventListener('click', function () {
			var form = panel.querySelector('.ppfc-start-form');
			var next = button.dataset.next;
			if (next === 'email' && !form.elements.name.value.trim()) {
				form.elements.name.setAttribute('aria-invalid', 'true');
				form.elements.name.focus();
				return;
			}
			form.elements.name.removeAttribute('aria-invalid');
			if (next === 'message' && form.elements.email.value && !form.elements.email.checkValidity()) {
				form.elements.email.setAttribute('aria-invalid', 'true');
				form.elements.email.reportValidity();
				return;
			}
			form.elements.email.removeAttribute('aria-invalid');
			setOnboardingStep(next);
		});
	});

	panel.querySelectorAll('.ppfc-step-back').forEach(function (button) {
		button.addEventListener('click', function () { setOnboardingStep(button.dataset.back); });
	});

	panel.querySelector('.ppfc-step-skip').addEventListener('click', function () {
		panel.querySelector('.ppfc-start-form').elements.email.value = '';
		setOnboardingStep('message');
	});

	panel.querySelector('.ppfc-start-form').elements.name.addEventListener('keydown', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
			panel.querySelector('.ppfc-step-next[data-next="email"]').click();
		}
	});

	panel.querySelector('.ppfc-start-form').elements.email.addEventListener('keydown', function (event) {
		if (event.key === 'Enter') {
			event.preventDefault();
			panel.querySelector('.ppfc-step-next[data-next="message"]').click();
		}
	});

    panel.querySelector('.ppfc-composer').addEventListener('submit', function (event) {
        event.preventDefault();
        if (!current) return;
        var form = event.currentTarget;
        var textarea = form.querySelector('textarea');
        var button = form.querySelector('button');
        if (!textarea.value.trim()) return;
        button.disabled = true;
        request('messages', {method: 'POST', body: JSON.stringify({conversation_id: current.id, message: textarea.value})}).then(function (payload) {
            textarea.value = '';
            applyConversation(payload);
        }).catch(showError).finally(function () { button.disabled = false; });
    });

    panel.querySelector('.ppfc-closed button').addEventListener('click', function () {
        current = null;
		var startForm = panel.querySelector('.ppfc-start-form');
		startForm.reset();
		startForm.elements.started_at.value = Math.floor(Date.now() / 1000);
        applyConversation({conversation: null});
    });

    if (new URLSearchParams(window.location.search).get('prayerpop_chat') === 'open') openPanel();
}());
