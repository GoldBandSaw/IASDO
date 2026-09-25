<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-bootstrap.php'; ?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CampusFlow — Chat promo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/styles.css">
    <link rel="stylesheet" href="/modern.css">
    <link rel="stylesheet" href="/page-shell.css">
    <link rel="stylesheet" href="/chat.css">
</head>
<body data-page="chat">
    <div class="app-shell">
        <?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'student-sidebar.php'; ?>
        <main class="page-content" style="padding:0;display:flex;flex-direction:column;height:calc(100vh - 20px)">
            <div class="chat-container">
                <div class="chat-header">
                    <h1>💬 Chat promo</h1>
                    <span class="chat-online-count">10 membres</span>
                </div>
                <div class="chat-messages" id="chat-messages">
                    <div class="chat-empty">Début de la conversation… Envoie le premier message !</div>
                </div>
                <form class="chat-input-area" id="chat-form">
                    <input type="text" class="chat-input" id="chat-input" placeholder="Écris un message…" maxlength="1000" autocomplete="off" required>
                    <button type="submit" class="chat-send" id="chat-send" aria-label="Envoyer">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
    (function() {
        const currentUser = <?= json_encode($currentUser['username']) ?>;
        const currentDisplayName = <?= json_encode($currentUser['display_name'] ?? $currentUser['username']) ?>;
        const currentProfilePicture = <?= json_encode($currentUser['profile_picture'] ?? '') ?>;
        const messagesEl = document.getElementById('chat-messages');
        const form = document.getElementById('chat-form');
        const input = document.getElementById('chat-input');
        const sendBtn = document.getElementById('chat-send');
        let lastId = 0;
        let isFirstLoad = true;
        const renderedMessageIds = new Set();

        // Apply dark mode immediately
        try {
            const settings = JSON.parse(localStorage.getItem("campusflow-settings"));
            if (settings && settings.darkMode) document.body.classList.add("dark-mode");
        } catch(e) {}

        const colors = ['#A6620C','#2D6A4F','#1B4965','#7B2D8B','#C44536','#4A6FA5','#6B4226','#2E5339','#8B4513','#4A148C'];
        function getColor(username) {
            let hash = 0;
            for (let i = 0; i < username.length; i++) hash = username.charCodeAt(i) + ((hash << 5) - hash);
            return colors[Math.abs(hash) % colors.length];
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function timeAgo(dateStr) {
            const now = new Date();
            const date = new Date(dateStr);
            const diff = Math.floor((now - date) / 1000);
            if (diff < 60) return 'À l\'instant';
            if (diff < 3600) return Math.floor(diff / 60) + ' min';
            if (diff < 86400) return Math.floor(diff / 3600) + ' h';
            return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
        }

        function renderMessage(msg) {
            const isOwn = msg.username === currentUser;
            const name = msg.display_name || msg.username;
            const initial = name.charAt(0).toUpperCase();
            const avatarHtml = msg.profile_picture 
                ? `<img src="${escapeHtml(msg.profile_picture)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`
                : escapeHtml(initial);
            return `
                <div class="chat-message ${isOwn ? 'own' : 'other'}">
                    <div class="chat-avatar" style="${msg.profile_picture ? 'background:transparent' : `background:${getColor(msg.username)}`}">${avatarHtml}</div>
                    <div class="chat-bubble">
                        <div class="chat-bubble-header">
                            <span class="chat-username">${escapeHtml(name)}</span>
                            <span class="chat-time">${timeAgo(msg.created_at)}</span>
                        </div>
                        <div class="chat-text">${escapeHtml(msg.content)}</div>
                    </div>
                </div>
            `;
        }

        async function loadMessages() {
            try {
                const res = await fetch('/api/chat?after=' + lastId);
                if (!res.ok) return;
                const data = await res.json();
                if (data.messages && data.messages.length > 0) {
                    if (isFirstLoad) {
                        messagesEl.innerHTML = '';
                        isFirstLoad = false;
                    }
                    data.messages.forEach(msg => {
                        const msgId = parseInt(msg.id);
                        if (!renderedMessageIds.has(msgId)) {
                            messagesEl.insertAdjacentHTML('beforeend', renderMessage(msg));
                            renderedMessageIds.add(msgId);
                            lastId = Math.max(lastId, msgId);
                        }
                    });
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                }
            } catch (e) {
                console.error('Chat load error:', e);
            }
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const content = input.value.trim();
            if (!content) return;
            
            // Optimistic UI
            const tempMsg = {
                id: 'temp-' + Date.now(),
                username: currentUser,
                display_name: currentDisplayName,
                profile_picture: currentProfilePicture,
                content: content,
                created_at: new Date().toISOString()
            };
            if (isFirstLoad) {
                messagesEl.innerHTML = '';
                isFirstLoad = false;
            }
            messagesEl.insertAdjacentHTML('beforeend', renderMessage(tempMsg));
            messagesEl.scrollTop = messagesEl.scrollHeight;
            
            input.value = '';
            sendBtn.disabled = true;
            try {
                const res = await fetch('/api/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content })
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.message) {
                        renderedMessageIds.add(parseInt(data.message.id));
                        lastId = Math.max(lastId, parseInt(data.message.id));
                    }
                    await loadMessages();
                }
            } catch (e) {
                console.error('Chat send error:', e);
            } finally {
                sendBtn.disabled = false;
                input.focus();
            }
        });

        // Send on Enter (form already handles this)
        // Initial load + polling every 3 seconds
        loadMessages();
        setInterval(loadMessages, 3000);
    })();
    </script>
    <script src="app.js?v=3"></script>
</body>
</html>
