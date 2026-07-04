(function () {
    'use strict';

    var INJECTED_BASE = @js(rtrim(config('app.url'), '/'));

    var script = document.currentScript;
    var base = INJECTED_BASE;

    if (!base && script && script.src) {
        try { base = new URL(script.src).origin; } catch (e) { base = ''; }
    }

    var botId = script ? script.getAttribute('data-bot-id') : null;

    if (!botId) {
        console.error('[helply] widget script is missing data-bot-id');
        return;
    }

    var SESSION_KEY = 'helply_session_' + botId;

    function sessionId() {
        var id = null;
        try { id = localStorage.getItem(SESSION_KEY); } catch (e) {}
        if (!id) {
            id = (window.crypto && crypto.randomUUID)
                ? crypto.randomUUID()
                : String(Date.now()) + Math.random().toString(16).slice(2);
            try { localStorage.setItem(SESSION_KEY, id); } catch (e) {}
        }
        return id;
    }

    var host = document.createElement('div');
    host.setAttribute('data-helply-widget', botId);
    document.body.appendChild(host);

    var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;

    var css = ''
        + '.launcher{position:fixed;bottom:20px;right:20px;height:56px;width:56px;border-radius:50%;border:0;background:#4f46e5;color:#fff;font-size:22px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.25);z-index:2147483647}'
        + '.panel{position:fixed;bottom:88px;right:20px;width:340px;max-width:calc(100vw - 40px);height:460px;max-height:calc(100vh - 120px);background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.2);display:flex;flex-direction:column;overflow:hidden;font-family:system-ui,sans-serif;z-index:2147483647}'
        + '.panel[hidden]{display:none}'
        + '.head{background:#4f46e5;color:#fff;padding:12px 14px;font-weight:600;font-size:14px}'
        + '.msgs{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px}'
        + '.msg{padding:8px 10px;border-radius:10px;font-size:14px;line-height:1.4;max-width:85%;white-space:pre-wrap;word-wrap:break-word}'
        + '.user{align-self:flex-end;background:#4f46e5;color:#fff}'
        + '.bot{align-self:flex-start;background:#f1f5f9;color:#0f172a}'
        + '.form{display:flex;border-top:1px solid #e2e8f0}'
        + '.input{flex:1;border:0;padding:12px;font-size:14px;outline:none}'
        + '.send{border:0;background:#4f46e5;color:#fff;padding:0 16px;cursor:pointer;font-size:14px}';

    var wrap = document.createElement('div');
    wrap.innerHTML = ''
        + '<button class="launcher" type="button" aria-label="Open chat">&#128172;</button>'
        + '<div class="panel" hidden>'
        + '  <div class="head">Ask a question</div>'
        + '  <div class="msgs"></div>'
        + '  <form class="form"><input class="input" type="text" placeholder="Type your message..." autocomplete="off" /><button class="send" type="submit">Send</button></form>'
        + '</div>';

    var style = document.createElement('style');
    style.textContent = css;
    root.appendChild(style);
    root.appendChild(wrap);

    var launcher = wrap.querySelector('.launcher');
    var panel = wrap.querySelector('.panel');
    var msgs = wrap.querySelector('.msgs');
    var form = wrap.querySelector('.form');
    var input = wrap.querySelector('.input');

    launcher.addEventListener('click', function () {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) { input.focus(); }
    });

    function addMessage(role, text) {
        var el = document.createElement('div');
        el.className = 'msg ' + (role === 'user' ? 'user' : 'bot');
        el.textContent = text;
        msgs.appendChild(el);
        msgs.scrollTop = msgs.scrollHeight;
        return el;
    }

    function send(text) {
        addMessage('user', text);
        var pending = addMessage('bot', '...');

        fetch(base + '/api/widget/' + botId + '/chat', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ session_id: sessionId(), message: text })
        }).then(function (res) {
            if (res.status === 429) { pending.textContent = 'Too many messages right now. Please wait a moment.'; return null; }
            if (!res.ok) { pending.textContent = 'Sorry, something went wrong. Please try again.'; return null; }
            return res.json();
        }).then(function (data) {
            if (data) { pending.textContent = data.answer || ''; }
        }).catch(function () {
            pending.textContent = 'Network error. Please try again.';
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var text = input.value.trim();
        if (!text) { return; }
        input.value = '';
        send(text);
    });
})();
