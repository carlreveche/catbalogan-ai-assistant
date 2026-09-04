(function () {
  const chatWindow = document.getElementById('chatWindow');
  const chatForm = document.getElementById('chatForm');
  const chatInput = document.getElementById('chatInput');
  const newChatBtn = document.getElementById('newChatBtn');
  const topicButtons = document.querySelectorAll('.topic-btn');
  const historyItems = document.getElementById('historyItems');
  const historySearch = document.getElementById('historySearch');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const csrf = document.querySelector('meta[name="csrf-token"]').content;

  const toastContainer = document.getElementById('toastContainer');
  const renameModal = document.getElementById('renameModal');
  const renameInput = document.getElementById('renameInput');
  const renameSave = document.getElementById('renameSave');
  const deleteModal = document.getElementById('deleteModal');
  const deleteConfirm = document.getElementById('deleteConfirm');

  const userName = (document.body.dataset.userName || '').trim();
  const userInitial = userName.charAt(0).toUpperCase() || 'U';

  let conversationId = null;
  let pendingRenameConvId = null;
  let pendingDeleteConvId = null;

  /* ---------- helpers ---------- */

  function formatTime(iso) {
    const d = iso ? new Date(iso) : new Date();
    if (isNaN(d)) return '';
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  }

  function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('show');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
  }

  /* ---------- Bootstrap toasts ---------- */

  function showToast(message, type) {
    type = type || 'success';
    const toast = document.createElement('div');
    toast.className = 'toast toast-civic toast-' + type;
    toast.setAttribute('role', 'status');

    const body = document.createElement('div');
    body.className = 'toast-body';
    body.textContent = message;
    toast.appendChild(body);

    toastContainer.appendChild(toast);
    const instance = bootstrap.Toast.getOrCreateInstance(toast, { delay: 3200 });
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
    instance.show();
  }

  /* ---------- Bootstrap modals ---------- */

  function openModal(modal) {
    bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  function closeModal(modal) {
    bootstrap.Modal.getOrCreateInstance(modal).hide();
  }

  /* ---------- message rendering ---------- */

  function buildAvatar(role) {
    const avatar = document.createElement('div');
    if (role === 'assistant') {
      avatar.className = 'msg-avatar msg-avatar-assistant';
      avatar.setAttribute('aria-hidden', 'true');
      avatar.innerHTML =
        '<svg viewBox="0 0 32 32" width="32" height="32">' +
          '<defs><linearGradient id="avGrad" x1="0" y1="0" x2="1" y2="1">' +
            '<stop offset="0" stop-color="#c2410c"/><stop offset="1" stop-color="#9a3412"/>' +
          '</linearGradient></defs>' +
          '<circle cx="16" cy="16" r="15" fill="url(#avGrad)"/>' +
          '<circle cx="16" cy="16" r="13.5" fill="none" stroke="#fed7aa" stroke-width="1.5"/>' +
          '<text x="16" y="20.5" text-anchor="middle" font-family="Public Sans, Arial, sans-serif" ' +
            'font-size="10.5" font-weight="700" fill="#ffffff">CC</text>' +
        '</svg>';
    } else {
      avatar.className = 'msg-avatar msg-avatar-user';
      avatar.textContent = userInitial;
      avatar.setAttribute('aria-hidden', 'true');
    }
    return avatar;
  }

  async function copyMessage(text, btn) {
    const done = () => {
      const original = btn.innerHTML;
      btn.innerHTML = '\u2713';
      btn.classList.add('copied');
      btn.title = 'Copied';
      setTimeout(() => {
        btn.innerHTML = original;
        btn.classList.remove('copied');
        btn.title = 'Copy to clipboard';
      }, 1500);
    };

    try {
      await navigator.clipboard.writeText(text);
      done();
    } catch (err) {
      try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        done();
      } catch (err2) {
        showToast('Could not copy the answer.', 'error');
      }
    }
  }

  function addMessage(role, text, opts) {
    opts = opts || {};

    const wrapper = document.createElement('div');
    wrapper.className = 'msg ' + (role === 'user' ? 'msg-user' : 'msg-assistant');

    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    if (role === 'assistant') {
      bubble.innerHTML = renderMarkdown(text);
    } else {
      bubble.textContent = text;
    }

    if (role === 'assistant') {
      attachAssistantMeta(bubble, text, opts);
    }

    if (role === 'user') {
      wrapper.appendChild(bubble);
      wrapper.appendChild(buildAvatar('user'));
    } else {
      wrapper.appendChild(buildAvatar('assistant'));
      wrapper.appendChild(bubble);
    }

    chatWindow.appendChild(wrapper);
    chatWindow.scrollTop = chatWindow.scrollHeight;
  }

  function attachAssistantMeta(bubble, text, opts) {
    opts = opts || {};

    const meta = document.createElement('div');
    meta.className = 'msg-meta';

    if (opts.topic) {
      const tag = document.createElement('span');
      tag.className = 'msg-topic-tag';
      tag.textContent = opts.followUp ? 'Following up on: ' + opts.topic : opts.topic;
      meta.appendChild(tag);
    }

    if (opts.confidence !== undefined && opts.confidence > 0) {
      const conf = document.createElement('span');
      conf.className = 'msg-confidence';
      conf.textContent = opts.confidence + '% confidence';
      meta.appendChild(conf);
    }

    const time = document.createElement('span');
    time.className = 'msg-time';
    time.textContent = formatTime(opts.createdAt);
    meta.appendChild(time);

    const copy = document.createElement('button');
    copy.className = 'copy-btn';
    copy.type = 'button';
    copy.title = 'Copy to clipboard';
    copy.setAttribute('aria-label', 'Copy answer to clipboard');
    copy.innerHTML = '\u2398';
    copy.addEventListener('click', () => copyMessage(text, copy));
    meta.appendChild(copy);

    bubble.appendChild(meta);

    // Feedback buttons (only for rated-able assistant replies)
    if (opts.chatId) {
      const fb = document.createElement('div');
      fb.className = 'feedback-btns';
      fb.dataset.chatId = opts.chatId;

      const up = document.createElement('button');
      up.className = 'feedback-btn up' + (opts.isHelpful === true ? ' active' : '');
      up.title = 'Helpful';
      up.textContent = '\uD83D\uDC4D';

      const down = document.createElement('button');
      down.className = 'feedback-btn down' + (opts.isHelpful === false ? ' active' : '');
      down.title = 'Not helpful';
      down.textContent = '\uD83D\uDC4E';

      fb.appendChild(up);
      fb.appendChild(down);
      bubble.appendChild(fb);
    }

    // Suggestion chips
    if (opts.suggestions && opts.suggestions.length) {
      const chips = document.createElement('div');
      chips.className = 'chips';
      opts.suggestions.forEach((s) => {
        const chip = document.createElement('button');
        chip.className = 'chip';
        chip.textContent = s;
        chips.appendChild(chip);
      });
      bubble.appendChild(chips);
    }
  }

  function createStreamingBubble() {
    const wrapper = document.createElement('div');
    wrapper.className = 'msg msg-assistant';
    wrapper.appendChild(buildAvatar('assistant'));
    const bubble = document.createElement('div');
    bubble.className = 'bubble';
    wrapper.appendChild(bubble);
    chatWindow.appendChild(wrapper);
    chatWindow.scrollTop = chatWindow.scrollHeight;
    return bubble;
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /* ---------- safe markdown rendering ---------- */

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function inlineMarkdown(text) {
    return text
      .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
      .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
      .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
  }

  // Renders a safe markdown subset: h1-h3 (as h6), bold, italic,
  // unordered/ordered lists, https links, and simple pipe tables.
  // Input is HTML-escaped first, so no raw HTML ever reaches the DOM.
  function renderMarkdown(text) {
    const lines = escapeHtml(text).split('\n');
    let html = '';
    let list = null;
    let table = null;

    const flushList = () => {
      if (list) {
        html += '<' + list.type + '>' +
          list.items.map((i) => '<li>' + i + '</li>').join('') +
          '</' + list.type + '>';
        list = null;
      }
    };
    const flushTable = () => {
      if (table) {
        const head = table.rows.find((r) => r.isHeader);
        const body = table.rows.filter((r) => !r.isHeader);
        let t = '<table>';
        if (head) {
          t += '<thead><tr>' + head.cells.map((c) => '<th>' + c + '</th>').join('') + '</tr></thead>';
        }
        t += '<tbody>' + body.map((r) => '<tr>' + r.cells.map((c) => '<td>' + c + '</td>').join('') + '</tr>').join('') + '</tbody></table>';
        html += t;
        table = null;
      }
    };

    for (const line of lines) {
      const blank = line.trim() === '';
      const header = line.match(/^\s*(#{1,3})\s+(.*)$/);
      const ul = line.match(/^\s*[-*]\s+(.*)$/);
      const ol = line.match(/^\s*\d+\.\s+(.*)$/);
      const tbl = line.match(/^\s*\|(.+)\|\s*$/);

      if (tbl) {
        flushList();
        const cells = tbl[1].split('|').map((c) => c.trim());
        const isSeparator = cells.every((c) => /^:?-{3,}:?$/.test(c));
        if (isSeparator && table && table.rows.length === 1) {
          table.rows[0].isHeader = true;
        } else {
          if (!table) table = { rows: [] };
          table.rows.push({ cells, isHeader: false });
        }
        continue;
      }

      if (table) flushTable();
      if (header) {
        flushList();
        html += '<h6>' + inlineMarkdown(header[2]) + '</h6>';
      } else if (ul || ol) {
        const type = ul ? 'ul' : 'ol';
        if (!list || list.type !== type) {
          flushList();
          list = { type, items: [] };
        }
        list.items.push(inlineMarkdown((ul || ol)[1]));
      } else if (blank) {
        flushList();
      } else {
        flushList();
        html += inlineMarkdown(line) + '\n';
      }
    }
    flushList();
    flushTable();
    return html;
  }

  function showTyping() {
    const wrapper = document.createElement('div');
    wrapper.className = 'msg msg-assistant typing-row';
    wrapper.id = 'typingRow';
    wrapper.innerHTML = '<div class="bubble typing"><span></span><span></span><span></span></div>';
    chatWindow.appendChild(wrapper);
    chatWindow.scrollTop = chatWindow.scrollHeight;
  }

  function hideTyping() {
    const row = document.getElementById('typingRow');
    if (row) row.remove();
  }

  function clearChat() {
    chatWindow.innerHTML = '';
  }

  /* ---------- conversation sending / loading ---------- */

  async function sendMessage(text) {
    addMessage('user', text);
    chatInput.value = '';
    chatInput.disabled = true;

    const startedAt = Date.now();
    showTyping();
    let bubble = null;
    let accumulated = '';
    let meta = null;

    try {
      const res = await fetch('api/stream.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf,
        },
        body: JSON.stringify({
          message: text,
          conversation_id: conversationId,
        }),
      });

      if (res.status === 419) {
        await sleep(600);
        hideTyping();
        addMessage('assistant', 'Session expired. Please refresh the page and try again.');
        return;
      }
      if (!res.ok) throw new Error('Request failed');

      // Always let the typing indicator be visible for a moment
      await sleep(Math.max(0, 600 - (Date.now() - startedAt)));
      hideTyping();

      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';

      for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let idx;
        while ((idx = buffer.indexOf('\n\n')) !== -1) {
          const raw = buffer.slice(0, idx);
          buffer = buffer.slice(idx + 2);

          for (const line of raw.split('\n')) {
            if (!line.startsWith('data: ')) continue;
            let payload;
            try {
              payload = JSON.parse(line.slice(6));
            } catch (err) {
              continue;
            }

            if (payload.type === 'token') {
              if (!bubble) bubble = createStreamingBubble();
              accumulated += payload.content;
              bubble.textContent = accumulated;
              chatWindow.scrollTop = chatWindow.scrollHeight;
            } else if (payload.type === 'done') {
              meta = payload.metadata;
            }
          }
        }
      }

      if (!bubble || !accumulated || !meta) throw new Error('Empty answer');

      // Final render with safe markdown (streamed as plain text for speed)
      bubble.innerHTML = renderMarkdown(accumulated);

      attachAssistantMeta(bubble, accumulated, {
        chatId: meta.assistant_chat_id,
        topic: meta.topic,
        followUp: meta.follow_up,
        confidence: meta.confidence,
        createdAt: new Date().toISOString(),
        suggestions: meta.suggestions,
      });

      const wasNew = !conversationId;
      conversationId = meta.conversation_id;

      if (wasNew && meta.conversation_title) {
        addHistoryRow(meta.conversation_id, meta.conversation_title);
      }
    } catch (err) {
      hideTyping();
      if (!bubble) {
        addMessage('assistant', 'Sorry, something went wrong reaching the assistant. Please try again.');
      } else {
        bubble.innerHTML = renderMarkdown(accumulated);
        attachAssistantMeta(bubble, accumulated, { createdAt: new Date().toISOString() });
      }
      console.error(err);
    } finally {
      chatInput.disabled = false;
      chatInput.focus();
    }
  }

  async function loadConversation(convId) {
    conversationId = convId;
    clearChat();

    // highlight active row
    document.querySelectorAll('.history-item').forEach((b) =>
      b.classList.toggle('active', b.dataset.conv === convId));

    try {
      const res = await fetch('api/history.php?conversation_id=' + encodeURIComponent(convId));
      const data = await res.json();
      (data.messages || []).forEach((m) => {
        addMessage(m.role, m.message, {
          chatId: m.role === 'assistant' ? m.id : null,
          topic: m.topic_name,
          isHelpful: m.is_helpful,
          createdAt: m.created_at,
        });
      });
    } catch (err) {
      console.error(err);
    }

    closeSidebar();
  }

  /* ---------- history list management ---------- */

  function addHistoryRow(convId, title) {
    const row = document.createElement('div');
    row.className = 'history-item-row';
    row.dataset.title = title;

    const btn = document.createElement('button');
    btn.className = 'history-item';
    btn.dataset.conv = convId;
    btn.textContent = title;
    btn.title = title;

    const wrap = document.createElement('div');
    wrap.className = 'dropdown history-item-menu-wrap';

    const menu = document.createElement('button');
    menu.className = 'history-item-menu';
    menu.dataset.conv = convId;
    menu.title = 'Conversation options';
    menu.setAttribute('aria-label', 'Conversation options');
    menu.setAttribute('aria-expanded', 'false');
    menu.textContent = '\u22EE';

    const menuList = document.createElement('div');
    menuList.className = 'dropdown-menu';

    const header = document.createElement('h6');
    header.className = 'dropdown-header';
    header.textContent = 'Conversation options';

    const rename = document.createElement('button');
    rename.type = 'button';
    rename.className = 'dropdown-item';
    rename.dataset.action = 'rename';
    rename.dataset.conv = convId;
    rename.textContent = 'Rename';

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'dropdown-item dropdown-item-danger';
    del.dataset.action = 'delete';
    del.dataset.conv = convId;
    del.textContent = 'Delete';

    menuList.appendChild(header);
    menuList.appendChild(rename);
    menuList.appendChild(del);
    wrap.appendChild(menu);
    wrap.appendChild(menuList);
    row.appendChild(btn);
    row.appendChild(wrap);

    const empty = document.getElementById('historyEmpty');
    if (empty) empty.remove();

    historyItems.prepend(row);
    initDropdown(menu);
    applyHistoryFilter();
  }

  /* ---------- Bootstrap dropdowns (fixed strategy escapes sidebar overflow) ---------- */

  function initDropdown(menuBtn) {
    menuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      bootstrap.Dropdown.getOrCreateInstance(menuBtn, {
        popperConfig: { strategy: 'fixed' },
      }).toggle();
    });
  }

  document.querySelectorAll('.history-item-menu').forEach(initDropdown);

  function applyHistoryFilter() {
    const q = historySearch.value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.history-item-row').forEach((row) => {
      const match = !q || (row.dataset.title || '').toLowerCase().includes(q);
      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    let hint = document.getElementById('historyEmpty');
    if (visible === 0) {
      if (!hint) {
        hint = document.createElement('p');
        hint.className = 'empty-hint';
        hint.id = 'historyEmpty';
        hint.textContent = 'No conversations found.';
        historyItems.appendChild(hint);
      }
    } else if (hint) {
      hint.remove();
    }
  }

  /* ---------- rename / delete ---------- */

  function openRenameModal(convId) {
    pendingRenameConvId = convId;
    const row = document.querySelector('.history-item[data-conv="' + convId + '"]');
    renameInput.value = row ? row.textContent : '';
    renameInput.maxLength = 120;
    openModal(renameModal);
    renameInput.focus();
    renameInput.select();
  }

  async function submitRename() {
    if (!pendingRenameConvId) return;
    const title = renameInput.value.trim();
    if (!title) {
      showToast('Please enter a name for the conversation.', 'error');
      renameInput.focus();
      return;
    }
    const convId = pendingRenameConvId;
    pendingRenameConvId = null;
    closeModal(renameModal);

    try {
      const res = await fetch('api/conversation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ action: 'rename', conversation_id: convId, title: title }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Rename failed');
      const row = document.querySelector('.history-item[data-conv="' + convId + '"]');
      if (row) {
        row.textContent = data.title;
        row.title = data.title;
        row.parentElement.dataset.title = data.title;
      }
      applyHistoryFilter();
      showToast('Conversation renamed.');
    } catch (err) {
      showToast('Could not rename conversation: ' + err.message, 'error');
    }
  }

  function openDeleteModal(convId) {
    pendingDeleteConvId = convId;
    openModal(deleteModal);
  }

  async function submitDelete() {
    if (!pendingDeleteConvId) return;
    const convId = pendingDeleteConvId;
    pendingDeleteConvId = null;
    closeModal(deleteModal);

    try {
      const res = await fetch('api/conversation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ action: 'delete', conversation_id: convId }),
      });
      if (!res.ok) throw new Error('Delete failed');

      document.querySelectorAll('.history-item-row').forEach((r) => {
        if (r.querySelector('.history-item')?.dataset.conv === convId) r.remove();
      });

      if (conversationId === convId) {
        conversationId = null;
        clearChat();
        addMessage('assistant', 'Conversation deleted. What would you like to ask about?');
      }
      applyHistoryFilter();
      showToast('Conversation deleted.');
    } catch (err) {
      showToast('Could not delete conversation.', 'error');
    }
  }

  /* ---------- feedback ---------- */

  async function sendFeedback(chatId, isHelpful, fbEl) {
    try {
      const res = await fetch('api/feedback.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({ chat_id: chatId, is_helpful: isHelpful }),
      });
      if (!res.ok) throw new Error('Feedback failed');
      const data = await res.json();

      const up = fbEl.querySelector('.up');
      const down = fbEl.querySelector('.down');
      up.classList.toggle('active', data.is_helpful === true);
      down.classList.toggle('active', data.is_helpful === false);
    } catch (err) {
      console.error(err);
    }
  }

  /* ---------- events ---------- */

  chatForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const text = chatInput.value.trim();
    if (!text) return;
    sendMessage(text);
  });

  newChatBtn.addEventListener('click', () => {
    conversationId = null;
    clearChat();
    document.querySelectorAll('.history-item').forEach((b) => b.classList.remove('active'));
    addMessage('assistant', 'Starting a new conversation. What would you like to ask about municipal permits or clearances?');
    closeSidebar();
  });

  topicButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      sendMessage('Tell me about ' + btn.dataset.topic);
      closeSidebar();
    });
  });

  // Delegated: open conversations from history rows
  historyItems.addEventListener('click', (e) => {
    const item = e.target.closest('.history-item');
    if (item) loadConversation(item.dataset.conv);
  });

  // Delegated: dropdown menu actions (rename / delete)
  document.addEventListener('click', (e) => {
    const item = e.target.closest('.dropdown-item[data-action]');
    if (!item) return;
    const wrap = item.closest('.dropdown');
    if (wrap) {
      const btn = wrap.querySelector('.history-item-menu');
      if (btn) bootstrap.Dropdown.getInstance(btn)?.hide();
    }
    if (item.dataset.action === 'rename') openRenameModal(item.dataset.conv);
    else if (item.dataset.action === 'delete') openDeleteModal(item.dataset.conv);
  });

  chatWindow.addEventListener('click', (e) => {
    const chip = e.target.closest('.chip');
    if (chip) {
      sendMessage(chip.textContent);
      return;
    }
    const btn = e.target.closest('.feedback-btn');
    if (btn) {
      const fbEl = btn.closest('.feedback-btns');
      sendFeedback(fbEl.dataset.chatId, btn.classList.contains('up'), fbEl);
    }
  });

  // Modals
  renameSave.addEventListener('click', submitRename);
  deleteConfirm.addEventListener('click', submitDelete);

  renameInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      submitRename();
    }
  });

  renameModal.addEventListener('hidden.bs.modal', () => {
    pendingRenameConvId = null;
    renameInput.value = '';
  });

  deleteModal.addEventListener('hidden.bs.modal', () => {
    pendingDeleteConvId = null;
  });

  historySearch.addEventListener('input', applyHistoryFilter);

  sidebarToggle.addEventListener('click', openSidebar);
  sidebarOverlay.addEventListener('click', closeSidebar);
})();