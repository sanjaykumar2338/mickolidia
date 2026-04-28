const parseJsonConfig = (node) => {
    if (!(node instanceof HTMLScriptElement)) {
        return {};
    }

    try {
        return JSON.parse(node.textContent ?? '{}');
    } catch (error) {
        return {};
    }
};

const messageToneClass = (tone) => {
    switch (tone) {
        case 'emerald':
            return 'is-emerald';
        case 'rose':
            return 'is-rose';
        case 'sky':
            return 'is-sky';
        case 'amber':
            return 'is-amber';
        default:
            return 'is-slate';
    }
};

const buildStatCard = (stat) => {
    const card = document.createElement('div');
    card.className = `wolfi-dashboard-stat-card ${messageToneClass(stat.tone)}`;

    const label = document.createElement('p');
    label.className = 'wolfi-dashboard-stat-label';
    label.textContent = stat.label ?? '';

    const value = document.createElement('p');
    value.className = 'wolfi-dashboard-stat-value';
    value.textContent = stat.value ?? '';

    card.append(label, value);

    return card;
};

const buildMessage = (payload, assistantName) => {
    const article = document.createElement('article');
    article.className = 'wolfi-dashboard-message wolfi-dashboard-message-assistant';

    const header = document.createElement('div');
    header.className = 'wolfi-dashboard-message-head';

    const badge = document.createElement('span');
    badge.className = 'wolfi-dashboard-message-badge';
    badge.textContent = String(assistantName ?? 'W').trim().slice(0, 1).toUpperCase();

    const meta = document.createElement('div');
    const name = document.createElement('p');
    name.className = 'wolfi-dashboard-message-name';
    name.textContent = assistantName ?? 'Wolfi';

    const title = document.createElement('p');
    title.className = 'wolfi-dashboard-message-title';
    title.textContent = payload.title ?? 'Wolfi update';

    meta.append(name, title);
    header.append(badge, meta);
    article.append(header);

    if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        const body = document.createElement('p');
        body.className = 'wolfi-dashboard-message-copy';
        body.textContent = payload.message.trim();
        article.append(body);
    }

    if (Array.isArray(payload.bullets) && payload.bullets.length > 0) {
        const list = document.createElement('ul');
        list.className = 'wolfi-dashboard-message-list';

        payload.bullets.forEach((bullet) => {
            if (typeof bullet !== 'string' || bullet.trim() === '') {
                return;
            }

            const item = document.createElement('li');
            item.className = 'wolfi-dashboard-message-list-item';

            const dot = document.createElement('span');
            dot.className = 'wolfi-dashboard-message-list-dot';

            const text = document.createElement('span');
            text.textContent = bullet.trim();

            item.append(dot, text);
            list.append(item);
        });

        article.append(list);
    }

    if (Array.isArray(payload.stats) && payload.stats.length > 0) {
        const statsWrap = document.createElement('div');
        statsWrap.className = 'wolfi-dashboard-stats-grid';
        payload.stats.forEach((stat) => {
            statsWrap.append(buildStatCard(stat));
        });
        article.append(statsWrap);
    }

    return article;
};

const buildUserMessage = (message) => {
    const article = document.createElement('article');
    article.className = 'wolfi-dashboard-message wolfi-dashboard-message-user';

    const label = document.createElement('p');
    label.className = 'wolfi-dashboard-user-label';
    label.textContent = 'You';

    const body = document.createElement('p');
    body.className = 'wolfi-dashboard-user-copy';
    body.textContent = message;

    article.append(label, body);

    return article;
};

const renderSuggestions = (container, suggestions, onSelect) => {
    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (!Array.isArray(suggestions) || suggestions.length === 0) {
        return;
    }

    container.innerHTML = '';

    suggestions.forEach((suggestion) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'wolfi-dashboard-chip';
        button.dataset.wolfiPrompt = suggestion.prompt ?? '';
        button.textContent = suggestion.label ?? suggestion.prompt ?? '';
        container.append(button);
    });
};

export const initWolfiDashboard = () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    document.querySelectorAll('[data-wolfi-dashboard]').forEach((root) => {
        if (!(root instanceof HTMLElement) || root.dataset.wolfiBound === 'true') {
            return;
        }

        root.dataset.wolfiBound = 'true';

        const config = parseJsonConfig(root.querySelector('[data-wolfi-dashboard-config]'));
        const form = root.querySelector('[data-wolfi-form]');
        const input = form?.querySelector('input[name="message"]');
        const thread = root.querySelector('[data-wolfi-thread]');
        const suggestions = root.querySelector('[data-wolfi-suggestions]');
        const statusNode = root.querySelector('[data-wolfi-status]');
        const liveLabel = root.querySelector('[data-wolfi-live-label]');
        const submitButton = form?.querySelector('button[type="submit"]');
        let activeRequestId = 0;
        let activeAbortController = null;

        if (!(form instanceof HTMLFormElement) || !(input instanceof HTMLInputElement) || !(thread instanceof HTMLElement)) {
            return;
        }

        const setStatus = (text) => {
            if (statusNode instanceof HTMLElement && typeof text === 'string' && text.trim() !== '') {
                statusNode.textContent = text;
            }
        };

        const setLiveLabel = (text) => {
            if (liveLabel instanceof HTMLElement && typeof text === 'string' && text.trim() !== '') {
                liveLabel.textContent = text;
            }
        };

        const setPending = (pending) => {
            root.classList.toggle('is-thinking', pending);
            if (submitButton instanceof HTMLButtonElement) {
                submitButton.disabled = pending;
            }
            input.disabled = pending;
        };

        const appendAssistantMessage = (payload) => {
            thread.append(buildMessage(payload, config.assistant_name));
            thread.scrollTo({
                top: thread.scrollHeight,
                behavior: 'smooth',
            });
            root.classList.add('is-responding');
            window.setTimeout(() => {
                root.classList.remove('is-responding');
            }, 1800);
        };

        const submitPrompt = async (prompt) => {
            const trimmedPrompt = String(prompt ?? '').trim();

            if (trimmedPrompt === '') {
                return;
            }

            activeRequestId += 1;
            const requestId = activeRequestId;

            if (activeAbortController instanceof AbortController) {
                activeAbortController.abort();
            }

            activeAbortController = new AbortController();

            thread.append(buildUserMessage(trimmedPrompt));
            thread.scrollTo({
                top: thread.scrollHeight,
                behavior: 'smooth',
            });

            input.value = '';
            setPending(true);
            setStatus(config.status_thinking ?? 'Wolfi is reviewing your dashboard context');
            setLiveLabel(config.status_thinking ?? 'Wolfi is reviewing your dashboard context');

            try {
                const response = await window.fetch(config.endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        message: trimmedPrompt,
                        page: config.page ?? 'dashboard',
                        account_id: config.account_id ?? null,
                    }),
                    signal: activeAbortController.signal,
                });

                const payload = await response.json().catch(() => ({}));

                if (requestId !== activeRequestId) {
                    return;
                }

                if (!response.ok) {
                    throw new Error(payload.message ?? config.status_error ?? 'Wolfi hit a temporary issue. Please try again.');
                }

                appendAssistantMessage(payload);
                renderSuggestions(suggestions, payload.suggestions, submitPrompt);
                setStatus(payload.title ?? config.status_idle ?? 'Ready to guide your next step');
                setLiveLabel(payload.title ?? config.status_idle ?? 'Ready to guide your next step');
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                if (requestId !== activeRequestId) {
                    return;
                }

                appendAssistantMessage({
                    title: 'Temporary issue',
                    message: error instanceof Error
                        ? error.message
                        : (config.status_error ?? 'Wolfi hit a temporary issue. Please try again.'),
                    bullets: [],
                    stats: [],
                });
                setStatus(config.status_error ?? 'Wolfi hit a temporary issue. Please try again.');
                setLiveLabel(config.status_error ?? 'Wolfi hit a temporary issue. Please try again.');
            } finally {
                if (requestId === activeRequestId) {
                    activeAbortController = null;
                    setPending(false);
                    input.focus();
                }
            }
        };

        root.addEventListener('click', async (event) => {
            const target = event.target instanceof Element
                ? event.target.closest('[data-wolfi-prompt]')
                : null;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            const prompt = target.dataset.wolfiPrompt ?? '';

            if (prompt.trim() === '') {
                return;
            }

            event.preventDefault();
            await submitPrompt(prompt);
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            await submitPrompt(input.value);
        });
    });
};
