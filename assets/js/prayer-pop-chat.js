(function () {
    'use strict';

    var cfg = window.PrayerPopChat;
    var panel = document.getElementById('ppfc-panel');
    var trigger = document.querySelector('.ppfc-trigger');
    if (!cfg || !panel || !trigger) return;

    var current = null;
    var lastMessageId = 0;
    var pollTimer = null;

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

    function showError(error) {
        var box = panel.querySelector('.ppfc-error');
        box.textContent = error && error.message ? error.message : cfg.i18n.error;
        box.hidden = false;
    }

    function renderMessages(messages, append) {
        var box = panel.querySelector('.ppfc-messages');
        if (!append) {
            box.innerHTML = '';
            lastMessageId = 0;
        }
        (messages || []).forEach(function (message) {
            if (box.querySelector('[data-message-id="' + Number(message.id) + '"]')) return;
            var item = document.createElement('div');
            item.className = 'ppfc-message ppfc-message-' + (message.sender_type === 'admin' ? 'admin' : 'visitor');
            item.dataset.messageId = message.id;
            item.innerHTML = '<div>' + escapeHtml(message.message).replace(/\n/g, '<br>') + '</div><time>' + escapeHtml(message.created_at) + '</time>';
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
        request('conversation').then(function (payload) {
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

    function openPanel() {
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        loadConversation();
    }

    function closePanel() {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        clearTimeout(pollTimer);
    }

    trigger.addEventListener('click', function () { panel.hidden ? openPanel() : closePanel(); });
    panel.querySelector('.ppfc-close').addEventListener('click', closePanel);

    panel.querySelector('.ppfc-start-form').addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var button = form.querySelector('button[type="submit"]');
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
            applyConversation(payload);
        }).catch(showError).finally(function () { button.disabled = false; });
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
        panel.querySelector('.ppfc-start-form [name="started_at"]').value = Math.floor(Date.now() / 1000);
        applyConversation({conversation: null});
    });

    if (new URLSearchParams(window.location.search).get('prayerpop_chat') === 'open') openPanel();
}());
