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
    var backgroundStatusTimer = null;
    var bubble = null;
    var conversationHydration = null;
    var lastFocusedElement = null;
    var panelOpening = false;
    var panelClosing = false;
    var classicRouteTransition = null;

    function panelIsOpen() {
        return !panel.hidden && panel.getAttribute('aria-hidden') !== 'true';
    }

    function syncBubbleUnread() {
        if (!bubble) return;
        var badge = bubble.querySelector('.prayer-pop-chat-unread-badge');
        if (!badge) return;
        var unread = current ? Number(current.visitor_unread || 0) : 0;
        badge.textContent = unread > 99 ? '99+' : (unread || '');
        badge.hidden = !(unread > 0 && !panelIsOpen() && bubble.getAttribute('aria-expanded') !== 'true');
    }

    function markCurrentRead() {
        if (!current || !current.visitor_unread) return;
        var conversationId = Number(current.id);
        request('read', {method: 'POST', body: JSON.stringify({conversation_id: conversationId})}).then(function () {
            if (current && Number(current.id) === conversationId) {
                current.visitor_unread = 0;
                syncBubbleUnread();
            }
        }).catch(function () {});
    }

    // Poll only returning visitors, while their Chat panel is closed, so new
    // team replies can be shown on the launcher without background traffic for
    // visitors who have never started a conversation.
    function scheduleBackgroundStatus() {
        clearTimeout(backgroundStatusTimer);
        if (!bubble || !current || panelIsOpen()) return;
        backgroundStatusTimer = window.setTimeout(function () {
            if (document.hidden) {
                scheduleBackgroundStatus();
                return;
            }
            request('conversation').then(function (payload) {
                current = payload.conversation || null;
                syncBubbleUnread();
            }).catch(function () {}).finally(scheduleBackgroundStatus);
        }, 15000);
    }

    function classicPanelHost() {
        return panel.classList.contains('ppfc-classic-chat-embedded') ? document.getElementById('prayer-pop-form-container') : null;
    }

    function mountClassicPanel() {
        var host = document.getElementById('prayer-pop-form-container');
        if (!host) return;

        host.classList.add('ppfc-classic-chat-host');
        panel.classList.add('ppfc-classic-chat-embedded');
        panel.setAttribute('role', 'region');
        panel.removeAttribute('aria-modal');
        if (panel.parentNode !== host) host.appendChild(panel);
    }

    /*
     * Classic Popup and Chat are two views of the same surface.  This helper
     * is deliberately the only owner of that surface's temporary dimensions:
     * lock the current height, make the view change, measure the new height,
     * then animate to it on the following frame.  Keeping those four phases
     * together avoids a second script or a late network response replacing the
     * height half way through a route change.
     */
    function transitionClassicHost(host, updateView, complete) {
        if (!host) {
            if (typeof updateView === 'function') updateView();
            if (typeof complete === 'function') complete();
            return;
        }

        if (classicRouteTransition) classicRouteTransition();

        var sourceHeight = Math.max(0, host.getBoundingClientRect().height);
        var originalHeight = host.style.height;
        var originalMaxHeight = host.style.maxHeight;
        var originalOverflow = host.style.overflow;
        var originalTransition = host.style.transition;
        var frame = 0;
        var timer = 0;
        var finished = false;

        var finish = function () {
            if (finished) return;
            finished = true;
            if (frame) window.cancelAnimationFrame(frame);
            window.clearTimeout(timer);
            host.removeEventListener('transitionend', onTransitionEnd);
            host.style.height = originalHeight;
            host.style.maxHeight = originalMaxHeight;
            host.style.overflow = originalOverflow;
            host.style.transition = originalTransition;
            if (classicRouteTransition === finish) classicRouteTransition = null;
            if (typeof complete === 'function') complete();
        };

        var onTransitionEnd = function (event) {
            if (event.target === host && event.propertyName === 'height') finish();
        };

        classicRouteTransition = finish;
        host.style.overflow = 'hidden';
        host.style.maxHeight = sourceHeight + 'px';
        host.style.height = sourceHeight + 'px';

        if (typeof updateView === 'function') updateView();

        // The active Chat class supplies its real target height. Temporarily
        // release our source-height lock solely to read that final layout.
        host.style.height = '';
        host.style.maxHeight = '';
        var targetHeight = Math.max(0, host.getBoundingClientRect().height);
        host.style.height = sourceHeight + 'px';
        host.style.maxHeight = Math.max(sourceHeight, targetHeight) + 'px';

        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reducedMotion || Math.abs(targetHeight - sourceHeight) <= 1) {
            finish();
            return;
        }

        host.style.transition = 'height 220ms cubic-bezier(.2, .8, .2, 1)';
        host.addEventListener('transitionend', onTransitionEnd);
        // Force the browser to commit the source height before changing it.
        void host.offsetHeight;
        frame = window.requestAnimationFrame(function () {
            frame = 0;
            if (finished) return;
            host.style.height = targetHeight + 'px';
        });
        timer = window.setTimeout(finish, 300);
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
        // Chat data is live state. Do not permit an intermediary or browser cache
        // to satisfy a poll with an older response.
        options.cache = 'no-store';
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

    function teamMessageAvatar() {
        var avatar = panel.querySelector('.ppfc-thread-welcome .ppfc-agent-avatar, .ppfc-agent-welcome .ppfc-agent-avatar');
        if (avatar && avatar.tagName === 'IMG' && avatar.getAttribute('src')) {
            return '<img class="ppfc-agent-avatar ppfc-message-team-avatar" src="' + escapeHtml(avatar.getAttribute('src')) + '" alt="">';
        }
        return '<span class="ppfc-agent-avatar ppfc-message-team-avatar dashicons dashicons-groups" aria-hidden="true"></span>';
    }

    function teamMessageName() {
        var title = panel.querySelector('#ppfc-title');
        return title ? title.textContent.trim() : '';
    }

    function messageMinute(value) {
        var normalized = String(value || '').replace(' ', 'T');
        var date = new Date(normalized + (normalized.indexOf('Z') === -1 ? 'Z' : ''));
        return isNaN(date.getTime()) ? String(value || '').slice(0, 16) : String(Math.floor(date.getTime() / 60000));
    }

    function isNearMessageBottom(box) {
        // scrollTop is subpixel-precise, while scrollHeight and clientHeight are
        // rounded. A small tolerance preserves the expected follow behaviour.
        return (box.scrollHeight - box.clientHeight - box.scrollTop) <= 32;
    }

    function renderMessages(messages, append, options) {
        options = options || {};
        messages = Array.isArray(messages) ? messages : [];
        var box = panel.querySelector('.ppfc-messages');
        var scrollTop = box.scrollTop;
        var shouldFollow = !!options.forceScroll || isNearMessageBottom(box);
        var changed = false;
        if (!append) {
            changed = box.querySelectorAll('.ppfc-message').length > 0;
            box.querySelectorAll('.ppfc-message').forEach(function (message) { message.remove(); });
            lastMessageId = 0;
        }
        var rendered = box.querySelectorAll('.ppfc-message');
        var previous = rendered.length ? rendered[rendered.length - 1] : null;
        messages.forEach(function (message) {
            if (box.querySelector('[data-message-id="' + Number(message.id) + '"]')) return;
            var item = document.createElement('div');
            item.className = 'ppfc-message ppfc-message-' + (message.sender_type === 'admin' ? 'admin' : 'visitor');
            item.dataset.messageId = message.id;
            if (message.sender_type === 'admin') {
                item.innerHTML = teamMessageAvatar() + '<span class="ppfc-agent-welcome-content ppfc-message-team-content"><span class="ppfc-agent-welcome-bubble">' + escapeHtml(message.message).replace(/\n/g, '<br>') + '</span><small>' + escapeHtml(teamMessageName()) + '</small><time datetime="' + escapeHtml(message.created_at) + '">' + escapeHtml(formatMessageTime(message.created_at)) + '</time></span>';
            } else {
                item.innerHTML = '<div>' + escapeHtml(message.message).replace(/\n/g, '<br>') + '</div><time datetime="' + escapeHtml(message.created_at) + '">' + escapeHtml(formatMessageTime(message.created_at)) + '</time>';
            }
            item.dataset.senderType = message.sender_type;
            item.dataset.messageMinute = messageMinute(message.created_at);
            if (previous && previous.dataset.senderType === item.dataset.senderType && previous.dataset.messageMinute === item.dataset.messageMinute) {
                item.classList.add('ppfc-message-grouped');
                var previousTime = previous.querySelector('time');
                if (previousTime) previousTime.hidden = true;
            }
            box.appendChild(item);
            changed = true;
            previous = item;
            lastMessageId = Math.max(lastMessageId, Number(message.id));
        });
        if (!changed) return;
        window.requestAnimationFrame(function () {
            box.scrollTop = shouldFollow ? box.scrollHeight : scrollTop;
        });
    }

    function applyConversation(payload, options) {
        options = options || {};
        current = payload.conversation || null;
        var start = panel.querySelector('.ppfc-start');
        var conversation = panel.querySelector('.ppfc-conversation');
        if (!current) {
            start.hidden = false;
            conversation.hidden = true;
            setOnboardingStep('name');
            syncBubbleUnread();
            return;
        }
        start.hidden = true;
        conversation.hidden = false;
        var closed = current.status === 'closed';
        conversation.querySelector('.ppfc-closed').hidden = !closed;
        conversation.querySelector('.ppfc-composer').hidden = closed;
        if (payload.messages) renderMessages(payload.messages, false, {forceScroll: !!options.forceScroll});
        syncBubbleUnread();
        if (panelIsOpen()) markCurrentRead();
        schedulePoll();
    }

    function loadConversation() {
        return request('conversation').then(function (payload) {
            if (!payload.conversation) return applyConversation(payload);
            return request('messages?conversation_id=' + Number(payload.conversation.id)).then(function (messages) {
                applyConversation(messages, {forceScroll: true});
            });
        }).catch(showError);
    }

    function hydrateConversation() {
        if (conversationHydration) return conversationHydration;
        conversationHydration = loadConversation().finally(function () {
            conversationHydration = null;
        });
        return conversationHydration;
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
                syncBubbleUnread();
                markCurrentRead();
            }).catch(function () {}).finally(schedulePoll);
        }, 5000);
    }

    function revealPanel(options) {
        options = options || {};
        panel.classList.remove('ppfc-route-transition');
        panel.style.removeProperty('height');
        panel.hidden = false;
        panel.setAttribute('aria-hidden', 'false');

        if (!options.embeddedClassic && window.PrayerPopMotion) {
            window.PrayerPopMotion.enter(panel);
        }

        var bubble = document.getElementById('prayer-pop-bubble');
        if (bubble) bubble.setAttribute('aria-expanded', 'true');
        document.body.classList.add('ppfc-open');
        panelOpening = false;
		// An explicit open always starts at the latest message. Polling itself
		// preserves the visitor's position once the conversation is open.
		if (current) {
			window.requestAnimationFrame(function () {
				var messages = panel.querySelector('.ppfc-messages');
				if (messages) messages.scrollTop = messages.scrollHeight;
			});
        }
        schedulePoll();
        markCurrentRead();
        focusPanel();
    }

    function openPanel(options) {
        if (panelIsOpen() || panelOpening) return;
        options = options || {};
        panelOpening = true;
        lastFocusedElement = document.activeElement;

        // Resolve the visitor's existing conversation while the Popup remains
        // visible. The first Chat frame is therefore always the right screen,
        // never the empty onboarding screen followed by a late replacement.
        hydrateConversation().finally(function () {
            var host = options.embeddedClassic ? classicPanelHost() : null;
            if (host) {
                transitionClassicHost(host, function () {
                    var modal = document.getElementById('prayer-pop-modal');
                    if (modal) {
                        modal.style.display = 'block';
                        modal.setAttribute('aria-hidden', 'false');
                    }
                    host.classList.add('ppfc-classic-chat-active');
                    revealPanel({embeddedClassic: true});
                });
                return;
            }
            revealPanel();
        });
    }

    function closePanel(options) {
        if (!panelIsOpen() || panelClosing) return;
        options = options && options.immediate ? options : {};
        panelClosing = true;
        var finish = function () {
            var host = classicPanelHost();
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
            panel.classList.remove('ppfc-route-transition');
            panel.style.removeProperty('height');
            var embedded = host && host.classList.contains('ppfc-classic-chat-active');
            if (embedded) {
                host.classList.remove('ppfc-classic-chat-active');
                host.style.removeProperty('height');
                var modal = document.getElementById('prayer-pop-modal');
                if (modal && options.revealClassic) {
                    modal.style.display = 'block';
                    modal.setAttribute('aria-hidden', 'false');
                } else if (modal) {
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                }
            }
            var bubble = document.getElementById('prayer-pop-bubble');
            if (bubble) bubble.setAttribute('aria-expanded', embedded && options.revealClassic ? 'true' : 'false');
            document.body.classList.remove('ppfc-open');
            clearTimeout(pollTimer);
            syncBubbleUnread();
            scheduleBackgroundStatus();
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

    function restoreClassicPopup() {
        var options = document.getElementById('prayer-pop-initial-options');
        var form = document.getElementById('prayer-pop-form-wrapper');
        var modal = document.getElementById('prayer-pop-modal');
        var container = document.getElementById('prayer-pop-form-container');
        var bubble = document.getElementById('prayer-pop-bubble');
        var host = classicPanelHost();

        if (!host) {
            closePanel({immediate: true, revealClassic: true});
            return;
        }

        transitionClassicHost(host, function () {
            panel.hidden = true;
            panel.setAttribute('aria-hidden', 'true');
            panel.classList.remove('ppfc-route-transition');
            panel.style.removeProperty('height');
            host.classList.remove('ppfc-classic-chat-active');
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
            document.body.classList.remove('ppfc-open');
            clearTimeout(pollTimer);
            syncBubbleUnread();
            scheduleBackgroundStatus();
            panelOpening = false;
            panelClosing = false;
        }, function () {
            window.setTimeout(function () {
                var target = options && options.querySelector('button:not([hidden])');
                if (!target) target = container;
                if (target && typeof target.focus === 'function') target.focus();
            }, 0);
        });
    }

    function returnToClassicPopup() {
        restoreClassicPopup();
    }

    function openFromClassicPopup(event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        var container = classicPanelHost();
        panel.classList.remove('fade-in', 'gentle-rise', 'soft-scale', 'slide-up', 'bounce-in');
        openPanel({embeddedClassic: !!container});
    }

    mountClassicPanel();

    panel.querySelector('.ppfc-back').addEventListener('click', returnToClassicPopup);
    panel.querySelector('.ppfc-close').addEventListener('click', closePanel);

    document.querySelectorAll('.ppm-classic-chat-launch').forEach(function (button) {
        button.addEventListener('click', openFromClassicPopup, true);
    });

    bubble = document.getElementById('prayer-pop-bubble');
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
            applyConversation(payload, {forceScroll: true});
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
            applyConversation(payload, {forceScroll: true});
        }).catch(showError).finally(function () { button.disabled = false; });
    });

    panel.querySelector('.ppfc-closed button').addEventListener('click', function () {
        current = null;
		var startForm = panel.querySelector('.ppfc-start-form');
		startForm.reset();
		startForm.elements.started_at.value = Math.floor(Date.now() / 1000);
        applyConversation({conversation: null});
    });

    // Warm the Chat state while the page is loading so opening the Popup does
    // not briefly expose a wrong empty state for returning visitors.
    hydrateConversation().finally(function () {
        syncBubbleUnread();
        scheduleBackgroundStatus();
    });

    if (new URLSearchParams(window.location.search).get('prayerpop_chat') === 'open') openPanel();
}());
