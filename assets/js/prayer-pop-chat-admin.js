(function () {
    'use strict';
    var cfg = window.PrayerPopChatAdmin;
    if (!cfg) return;
    var activeId = 0;
    var listTimer;
    var threadTimer;
    var settingsToggle = document.getElementById('ppfc-settings-toggle');
    var settingsPanel = document.getElementById('ppfc-settings');

    if (settingsToggle && settingsPanel) {
        settingsToggle.addEventListener('click', function () {
            settingsPanel.hidden = !settingsPanel.hidden;
            settingsToggle.setAttribute('aria-expanded', settingsPanel.hidden ? 'false' : 'true');
            document.querySelector('.ppm-admin-wrap').classList.toggle('ppfc-settings-open', !settingsPanel.hidden);
            if (!settingsPanel.hidden) settingsPanel.querySelector('input:not([type="hidden"])').focus();
        });
        document.querySelector('.ppm-admin-wrap').classList.toggle('ppfc-settings-open', !settingsPanel.hidden);
    }

    function text(key, fallback) { return cfg.i18n[key] || fallback; }
    function esc(value) { return String(value || '').replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]; }); }
    function api(path, options) {
        options = options || {};
        options.headers = Object.assign({'Content-Type':'application/json','X-WP-Nonce':cfg.nonce}, options.headers || {});
        return fetch(cfg.root + path, options).then(function (response) { return response.json().then(function (json) { if (!response.ok) throw json; return json; }); });
    }
    function initials(name) { return String(name || '?').trim().split(/\s+/).slice(0,2).map(function (p) { return p.charAt(0).toUpperCase(); }).join(''); }
    function dateLabel(value) {
        if (!value) return '—';
        var date = new Date(String(value).replace(' ', 'T') + 'Z');
        return isNaN(date.getTime()) ? String(value) : date.toLocaleString([], {dateStyle:'medium', timeStyle:'short'});
    }
    function shortTime(value) {
        if (!value) return '';
        var date = new Date(String(value).replace(' ', 'T') + 'Z');
        if (isNaN(date.getTime())) return String(value);
        var seconds = Math.max(0, Math.round((Date.now() - date.getTime()) / 1000));
        if (seconds < 60) return 'Now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h';
        return date.toLocaleDateString([], {month:'short', day:'numeric'});
    }
    function renderContact(c) {
        var panel = document.getElementById('ppfc-contact-panel');
        panel.innerHTML = '<div class="ppm-contact-card"><span class="ppm-contact-avatar">' + esc(initials(c.visitor_name)) + '</span><h2>' + esc(c.visitor_name) + '</h2><span class="ppm-status-pill ' + (c.status === 'closed' ? 'closed' : '') + '">' + esc(c.status) + '</span></div>' +
            '<div class="ppm-contact-section"><h3>' + esc(text('contactDetails','Contact details')) + '</h3><a href="mailto:' + encodeURIComponent(c.visitor_email) + '">' + esc(c.visitor_email) + '</a></div>' +
            '<div class="ppm-contact-section"><dl><dt>' + esc(text('status','Status')) + '</dt><dd>' + esc(c.status) + '</dd><dt>' + esc(text('started','Started')) + '</dt><dd>' + esc(dateLabel(c.created_at)) + '</dd><dt>' + esc(text('lastMessage','Last message')) + '</dt><dd>' + esc(dateLabel(c.last_message_at)) + '</dd></dl></div>';
    }
    function resetWorkspace() {
        activeId = 0;
        clearTimeout(threadTimer);
        document.getElementById('ppfc-thread').innerHTML = '<div class="ppm-thread-empty"><span class="dashicons dashicons-format-chat"></span><h2>' + esc(text('yourMessages','Your messages')) + '</h2><p>' + esc(text('choose','Choose a conversation from the left to read and reply.')) + '</p></div>';
        document.getElementById('ppfc-contact-panel').innerHTML = '<div class="ppm-contact-empty"><span class="dashicons dashicons-admin-users"></span><p>' + esc(text('visitorDetails','Visitor details will appear here.')) + '</p></div>';
        loadList();
    }
    function scheduleList() { clearTimeout(listTimer); listTimer = setTimeout(loadList, 5000); }
    function renderList(rows) {
        var box = document.getElementById('ppfc-conversations');
        document.getElementById('ppfc-count').textContent = rows.length;
        if (!rows.length) { box.innerHTML = '<div class="ppm-list-empty"><span class="dashicons dashicons-format-chat"></span><strong>' + esc(text('empty','No conversations yet.')) + '</strong><small>' + esc(text('newMessages','New website messages will appear here.')) + '</small></div>'; return; }
        box.innerHTML = rows.map(function (c) {
            return '<button type="button" data-id="' + Number(c.id) + '" class="ppm-conversation ' + (Number(c.id) === activeId ? 'active' : '') + '"><span class="ppm-list-avatar">' + esc(initials(c.visitor_name)) + '</span><span class="ppm-list-copy"><strong>' + esc(c.visitor_name) + '</strong><span>' + esc(c.last_message_excerpt) + '</span></span><span class="ppm-list-meta">' + (c.admin_unread ? '<i>' + Number(c.admin_unread) + '</i>' : '') + '</span></button>';
        }).join('');
        box.querySelectorAll('[data-id]').forEach(function (button) { button.addEventListener('click', function () { openConversation(button.dataset.id); }); });
    }
    function loadList() { api('conversations').then(renderList).catch(function () {}).finally(scheduleList); }
    function messageMarkup(message) { return '<div class="ppm-admin-message ' + esc(message.sender_type) + '"><div>' + esc(message.message).replace(/\n/g,'<br>') + '</div><time>' + esc(shortTime(message.created_at)) + '</time></div>'; }
    function renderThread(payload) {
        var c = payload.conversation;
        var root = document.getElementById('ppfc-thread');
        var closed = c.status === 'closed';
        renderContact(c);
        root.innerHTML = '<header class="ppm-thread-header"><div class="ppm-thread-person"><span class="ppm-list-avatar">' + esc(initials(c.visitor_name)) + '</span><div><h2>' + esc(c.visitor_name) + '</h2><small>' + esc(c.visitor_email) + '</small></div></div><div class="ppm-thread-actions"><button type="button" class="ppm-status-action">' + esc(closed ? text('reopen','Reopen conversation') : text('close','Close conversation')) + '</button><button type="button" class="ppm-delete" aria-label="' + esc(text('deleteConversation','Delete conversation')) + '"><span class="dashicons dashicons-trash"></span></button></div></header><div class="ppm-admin-messages">' + (payload.messages || []).map(messageMarkup).join('') + '</div><form class="ppm-admin-composer"' + (closed ? ' hidden' : '') + '><textarea rows="1" maxlength="2000" required placeholder="' + esc(text('reply','Write a reply…')) + '"></textarea><button type="submit" aria-label="' + esc(text('send','Send reply')) + '"><span class="dashicons dashicons-arrow-up-alt2"></span></button></form>';
		root.querySelector('.ppm-status-action').addEventListener('click', function () { api('conversations/' + activeId + '/status', {method:'POST',body:JSON.stringify({status:closed?'open':'closed'})}).then(function () { return api('conversations/' + activeId).then(renderThread); }).then(loadList); });
		root.querySelector('.ppm-delete').addEventListener('click', function () { if (window.confirm(text('deleteConfirm','Delete this conversation permanently?'))) { api('conversations/' + activeId, {method:'DELETE'}).then(resetWorkspace).catch(function (error) { window.alert(error.message || text('error','Something went wrong.')); }); } });
        var form = root.querySelector('.ppm-admin-composer');
        if (form) form.addEventListener('submit', function (event) { event.preventDefault(); var button=form.querySelector('button'); button.disabled=true; api('conversations/' + activeId + '/messages',{method:'POST',body:JSON.stringify({message:form.querySelector('textarea').value})}).then(renderThread).then(loadList).catch(function (error) { window.alert(error.message || text('error','Something went wrong.')); }).finally(function(){button.disabled=false;}); });
        var messages = root.querySelector('.ppm-admin-messages'); messages.scrollTop = messages.scrollHeight;
    }
    function pollThread() { clearTimeout(threadTimer); var draft=document.querySelector('.ppm-admin-composer textarea'); if (!activeId || document.hidden || (draft && (draft.value || document.activeElement === draft))) { threadTimer=setTimeout(pollThread,4000); return; } api('conversations/' + activeId).then(renderThread).catch(function(){}).finally(function(){threadTimer=setTimeout(pollThread,4000);}); }
    function openConversation(id) { activeId=Number(id); api('conversations/' + activeId).then(function(payload){renderThread(payload);api('conversations/' + activeId + '/read',{method:'POST',body:'{}'});loadList();pollThread();}); }
    loadList();
    var requested = Number(new URLSearchParams(window.location.search).get('conversation')); if (requested) openConversation(requested);
}());
