import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const storage = {
        get(key) {
            try {
                return window.localStorage.getItem(key);
            } catch (error) {
                return null;
            }
        },
        set(key, value) {
            try {
                window.localStorage.setItem(key, value);
            } catch (error) {
                // Ignore storage access issues.
            }
        },
    };

    const normalizeText = (value) => (value ?? '')
        .toString()
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();

    const escapeHtml = (value) => (value ?? '')
        .toString()
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const fixedDisclaimer = document.querySelector('[data-fixed-disclaimer]');

    if (fixedDisclaimer instanceof HTMLElement) {
        const closeButton = fixedDisclaimer.querySelector('[data-fixed-disclaimer-close]');
        const storageKey = 'wolforix-fixed-disclaimer-dismissed';

        if (storage.get(storageKey) === '1') {
            fixedDisclaimer.classList.add('hidden');
        }

        if (closeButton instanceof HTMLButtonElement) {
            closeButton.addEventListener('click', () => {
                fixedDisclaimer.classList.add('hidden');
                storage.set(storageKey, '1');
            });
        }
    }

    const launchPopup = document.querySelector('[data-launch-popup]');

    if (launchPopup instanceof HTMLElement) {
        const closeButtons = launchPopup.querySelectorAll('[data-launch-popup-close]');
        const storageKey = 'wolforix-launch-popup-dismissed';

        const closePopup = () => {
            launchPopup.classList.remove('flex');
            launchPopup.classList.add('hidden');
            document.documentElement.classList.remove('overflow-hidden');
            document.body.classList.remove('overflow-hidden');
            storage.set(storageKey, '1');
        };

        if (storage.get(storageKey) !== '1') {
            launchPopup.classList.remove('hidden');
            launchPopup.classList.add('flex');
            document.documentElement.classList.add('overflow-hidden');
            document.body.classList.add('overflow-hidden');
        }

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                closePopup();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && launchPopup.classList.contains('flex')) {
                closePopup();
            }
        });
    }

    const localeSwitchers = document.querySelectorAll('[data-locale-switcher]');

    localeSwitchers.forEach((switcher) => {
        const toggle = switcher.querySelector('[data-locale-toggle]');
        const menu = switcher.querySelector('[data-locale-menu]');

        if (!(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
            return;
        }

        const closeMenu = () => {
            menu.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        };

        menu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const isClosed = menu.classList.contains('hidden');

            localeSwitchers.forEach((item) => {
                const itemToggle = item.querySelector('[data-locale-toggle]');
                const itemMenu = item.querySelector('[data-locale-menu]');

                if (item !== switcher && itemMenu instanceof HTMLElement && itemToggle instanceof HTMLButtonElement) {
                    itemMenu.classList.add('hidden');
                    itemToggle.setAttribute('aria-expanded', 'false');
                }
            });

            if (isClosed) {
                menu.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            } else {
                closeMenu();
            }
        });

        document.addEventListener('click', (event) => {
            if (!switcher.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('resize', closeMenu);
    });

    const siteSearch = document.querySelector('[data-site-search]');

    if (siteSearch instanceof HTMLElement) {
        const openButtons = document.querySelectorAll('[data-site-search-open]');
        const closeButtons = siteSearch.querySelectorAll('[data-site-search-close]');
        const searchForm = siteSearch.querySelector('[data-site-search-form]');
        const searchInput = siteSearch.querySelector('[data-site-search-input]');
        const results = siteSearch.querySelector('[data-site-search-results]');
        const emptyState = siteSearch.querySelector('[data-site-search-empty]');
        const stateLabel = siteSearch.querySelector('[data-site-search-state]');
        const indexScript = siteSearch.querySelector('[data-site-search-index]');
        const searchIndex = indexScript instanceof HTMLScriptElement
            ? JSON.parse(indexScript.textContent ?? '[]')
            : [];

        const renderResults = () => {
            if (!(results instanceof HTMLElement) || !(searchInput instanceof HTMLInputElement)) {
                return;
            }

            const query = normalizeText(searchInput.value);
            const rankedItems = searchIndex
                .map((item) => {
                    const title = item.title ?? '';
                    const description = item.description ?? '';
                    const keywords = item.keywords ?? '';
                    const haystack = normalizeText(`${title} ${description} ${keywords}`);
                    let score = 0;

                    if (query === '') {
                        score = 1;
                    } else if (haystack.includes(query)) {
                        score += 100;
                    }

                    query.split(/\s+/).filter(Boolean).forEach((token) => {
                        if (haystack.includes(token)) {
                            score += 12;
                        }

                        if (normalizeText(title).includes(token)) {
                            score += 8;
                        }
                    });

                    return {
                        ...item,
                        score,
                    };
                })
                .filter((item) => (query === '' ? true : item.score > 0))
                .sort((left, right) => right.score - left.score)
                .slice(0, query === '' ? 6 : 8);

            if (stateLabel instanceof HTMLElement) {
                stateLabel.textContent = query === ''
                    ? stateLabel.dataset.featuredTitle ?? stateLabel.textContent
                    : `${rankedItems.length} ${rankedItems.length === 1 ? stateLabel.dataset.resultsOne ?? 'result' : stateLabel.dataset.resultsMany ?? 'results'}`;
            }

            if (emptyState instanceof HTMLElement) {
                emptyState.classList.toggle('hidden', rankedItems.length !== 0);
            }

            results.innerHTML = rankedItems.map((item) => `
                <a
                    href="${escapeHtml(item.url ?? '#')}"
                    class="block rounded-[1.5rem] border border-white/8 bg-white/4 px-5 py-4 transition hover:border-amber-400/20 hover:bg-white/6"
                >
                    <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-amber-300">${escapeHtml(item.section ?? '')}</p>
                    <p class="mt-2 text-lg font-semibold text-white">${escapeHtml(item.title ?? '')}</p>
                    <p class="mt-2 text-sm leading-7 text-slate-400">${escapeHtml(item.description ?? '')}</p>
                </a>
            `).join('');
        };

        const openSearch = () => {
            siteSearch.classList.remove('hidden');
            siteSearch.classList.add('flex');
            document.documentElement.classList.add('overflow-hidden');
            document.body.classList.add('overflow-hidden');
            renderResults();
            window.requestAnimationFrame(() => {
                searchInput?.focus();
            });
        };

        const closeSearch = () => {
            siteSearch.classList.remove('flex');
            siteSearch.classList.add('hidden');
            document.documentElement.classList.remove('overflow-hidden');
            document.body.classList.remove('overflow-hidden');
        };

        if (stateLabel instanceof HTMLElement) {
            stateLabel.dataset.featuredTitle = stateLabel.textContent ?? '';
        }

        openButtons.forEach((button) => {
            button.addEventListener('click', () => {
                openSearch();
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                closeSearch();
            });
        });

        if (searchInput instanceof HTMLInputElement) {
            searchInput.addEventListener('input', renderResults);
        }

        if (searchForm instanceof HTMLFormElement) {
            searchForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const firstResult = results?.querySelector('a');

                if (firstResult instanceof HTMLAnchorElement) {
                    window.location.href = firstResult.href;
                }
            });
        }

        results?.addEventListener('click', (event) => {
            if (event.target instanceof HTMLElement && event.target.closest('a')) {
                closeSearch();
            }
        });

        siteSearch.addEventListener('click', (event) => {
            if (event.target === siteSearch) {
                closeSearch();
            }
        });

        document.addEventListener('keydown', (event) => {
            const commandPalette = (event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k';
            const slashOpen = event.key === '/' && !(event.target instanceof HTMLInputElement) && !(event.target instanceof HTMLTextAreaElement);

            if (commandPalette || slashOpen) {
                event.preventDefault();
                openSearch();
            }

            if (event.key === 'Escape' && siteSearch.classList.contains('flex')) {
                closeSearch();
            }
        });
    }

    const challengeSelector = document.querySelector('[data-challenge-selector]');

    if (challengeSelector instanceof HTMLElement) {
        const catalogScript = challengeSelector.querySelector('[data-challenge-catalog]');
        const currencyScript = challengeSelector.querySelector('[data-challenge-currencies]');
        const uiScript = challengeSelector.querySelector('[data-challenge-ui]');
        const currencyButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-currency]'));
        const typeButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-type]'));
        const sizeButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-size]'));
        const checkoutCtas = Array.from(document.querySelectorAll('[data-checkout-cta]'));
        const checkoutPlanTitle = document.querySelector('[data-checkout-plan-title]');
        const checkoutPlanPrice = document.querySelector('[data-checkout-plan-price]');
        const checkoutPlanCurrency = document.querySelector('[data-checkout-plan-currency]');

        if (catalogScript instanceof HTMLScriptElement) {
            const catalog = JSON.parse(catalogScript.textContent ?? '{}');
            const currencies = currencyScript instanceof HTMLScriptElement ? JSON.parse(currencyScript.textContent ?? '{}') : {};
            const ui = uiScript instanceof HTMLScriptElement ? JSON.parse(uiScript.textContent ?? '{}') : {};
            const availableCurrencies = Object.keys(currencies);
            const currencyStorageKey = 'wolforix-selected-currency';
            let activeCurrency = storage.get(currencyStorageKey) ?? challengeSelector.dataset.defaultCurrency ?? availableCurrencies[0] ?? 'USD';
            let activeType = challengeSelector.dataset.defaultType ?? Object.keys(catalog)[0];
            let activeSize = challengeSelector.dataset.defaultSize ?? Object.keys(catalog[activeType]?.plans ?? {})[0];

            if (!availableCurrencies.includes(activeCurrency)) {
                activeCurrency = challengeSelector.dataset.defaultCurrency ?? availableCurrencies[0] ?? 'USD';
            }

            const currencyActiveClasses = ['border-amber-300/30', 'bg-amber-400/12', 'text-white', 'shadow-lg', 'shadow-amber-950/15'];
            const currencyInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-300'];
            const typeActiveClasses = ['border-amber-300/30', 'bg-amber-400/12', 'text-white', 'shadow-lg', 'shadow-amber-950/15'];
            const typeInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-300'];
            const sizeActiveClasses = ['border-amber-300/30', 'bg-amber-400', 'text-slate-950', 'shadow-lg', 'shadow-amber-950/20'];
            const sizeInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-200'];

            const planBadge = challengeSelector.querySelector('[data-challenge-badge]');
            const planTitle = challengeSelector.querySelector('[data-plan-title]');
            const planPrice = challengeSelector.querySelector('[data-plan-price]');
            const planCurrencyCode = challengeSelector.querySelector('[data-plan-currency-code]');
            const planCurrencyFlag = challengeSelector.querySelector('[data-plan-currency-flag]');
            const planOriginalWrap = challengeSelector.querySelector('[data-plan-original-wrap]');
            const planOriginalPrice = challengeSelector.querySelector('[data-plan-original-price]');
            const planDiscountBadge = challengeSelector.querySelector('[data-plan-discount-badge]');
            const planDiscountUrgency = challengeSelector.querySelector('[data-plan-discount-urgency]');
            const description = challengeSelector.querySelector('[data-challenge-description-text]');
            const detailGroups = challengeSelector.querySelector('[data-plan-detail-groups]');
            const planNoteTitle = challengeSelector.querySelector('[data-plan-note-title]');
            const planNoteBody = challengeSelector.querySelector('[data-plan-note-body]');

            const convertCurrency = (amount, currency) => {
                const normalizedAmount = Number(amount);
                const rate = Number(currencies[currency]?.rate ?? 1);

                if (!Number.isFinite(normalizedAmount) || !Number.isFinite(rate)) {
                    return 0;
                }

                return Math.round(normalizedAmount * rate);
            };

            const formatCurrency = (amount, currency = 'USD') => {
                const normalized = Number(amount);

                if (!Number.isFinite(normalized)) {
                    return '';
                }

                try {
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency,
                        maximumFractionDigits: 0,
                    }).format(normalized);
                } catch (error) {
                    return `${currency} ${normalized.toLocaleString('en-US')}`;
                }
            };

            const formatSize = (amount) => `${Number(amount) / 1000}K`;
            const replaceTokens = (template, replacements) => Object.entries(replacements).reduce(
                (result, [key, value]) => result.replace(`:${key}`, String(value)),
                template,
            );
            const formatDays = (days) => replaceTokens(ui.value_templates?.days ?? ':days days', { days });
            const formatAfterDays = (days) => replaceTokens(ui.value_templates?.after_days ?? 'After :days days', { days });
            const formatScaling = (percent, months) => replaceTokens(
                ui.value_templates?.scaling ?? '+:percent% capital every :months months if profitable',
                { percent, months },
            );
            const formatProfitSplitUpgrade = (upgrade) => replaceTokens(
                ui.value_templates?.profit_split_upgrade ?? ':percent% after :payouts consecutive payouts',
                {
                    percent: upgrade?.profit_split ?? '',
                    payouts: upgrade?.after_consecutive_payouts ?? '',
                },
            );
            const renderMetricRows = (rows) => rows.map(([label, value]) => `
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                    <dt class="text-slate-400">${label}</dt>
                    <dd class="font-semibold text-white">${value}</dd>
                </div>
            `).join('');

            const renderDetailGroups = (plan) => {
                if (!(detailGroups instanceof HTMLElement)) {
                    return;
                }

                const phaseCards = (plan.phases ?? []).map((phase) => {
                    const phaseRows = [
                        [ui.metrics?.profit_target ?? 'Profit target', `${phase.profit_target}%`],
                        [ui.metrics?.daily_loss ?? 'Max daily loss', `${phase.daily_loss_limit}%`],
                        [ui.metrics?.total_loss ?? 'Max total loss', `${phase.max_loss_limit}%`],
                        [ui.metrics?.minimum_days ?? 'Min trading days', String(phase.minimum_trading_days)],
                        [ui.metrics?.max_trading_days ?? 'Max trading days', phase.maximum_trading_days === null ? (ui.unlimited ?? 'Unlimited') : String(phase.maximum_trading_days)],
                    ];

                    if (phase.leverage) {
                        phaseRows.push([ui.metrics?.leverage ?? 'Leverage', phase.leverage]);
                    }

                    return `
                        <section class="surface-card rounded-[1.6rem] p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">${ui.phase_titles?.[phase.key] ?? phase.key}</p>
                            <dl class="mt-4 space-y-3 text-sm">
                                ${renderMetricRows(phaseRows)}
                            </dl>
                        </section>
                    `;
                }).join('');

                const fundedRows = [
                    [ui.metrics?.profit_share ?? 'Profit split', `${plan.funded.profit_split}%`],
                    [ui.metrics?.payout_cycle ?? 'Payout cycle', formatDays(plan.funded.payout_cycle_days)],
                ];

                if (plan.funded.first_withdrawal_days) {
                    fundedRows.push([ui.metrics?.first_withdrawal ?? 'First withdrawal', formatAfterDays(plan.funded.first_withdrawal_days)]);
                }

                if (plan.funded.scaling_capital_percent && plan.funded.scaling_interval_months) {
                    fundedRows.push([
                        ui.metrics?.scaling ?? 'Scaling',
                        formatScaling(plan.funded.scaling_capital_percent, plan.funded.scaling_interval_months),
                    ]);
                }

                if (plan.funded.profit_split_upgrade) {
                    fundedRows.push([
                        ui.metrics?.profit_share_upgrade ?? 'Profit split upgrade',
                        formatProfitSplitUpgrade(plan.funded.profit_split_upgrade),
                    ]);
                }

                if (plan.funded.consistency_rule_required) {
                    fundedRows.push([
                        ui.metrics?.consistency_rule ?? 'Consistency rule',
                        ui.consistency_required ?? 'Obligatory',
                    ]);
                }

                detailGroups.innerHTML = `
                    ${phaseCards}
                    <section class="surface-card rounded-[1.6rem] p-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">${ui.phase_titles?.funded ?? 'Funded Account'}</p>
                        <dl class="mt-4 space-y-3 text-sm">
                            ${renderMetricRows(fundedRows)}
                        </dl>
                    </section>
                `;
            };

            const updateToggleState = (button, active, activeClasses, inactiveClasses) => {
                button.classList.remove(...(active ? inactiveClasses : activeClasses));
                button.classList.add(...(active ? activeClasses : inactiveClasses));
            };

            const updateSizeButtonState = (button, active, available) => {
                button.classList.remove(...(active ? sizeInactiveClasses : sizeActiveClasses));
                button.classList.add(...(active ? sizeActiveClasses : sizeInactiveClasses));
                button.classList.toggle('opacity-45', !available);
                button.classList.toggle('cursor-not-allowed', !available);
                button.disabled = !available;
            };

            const renderPlan = () => {
                const currentType = catalog[activeType];

                if (!currentType) {
                    return;
                }

                const availableSizes = Object.keys(currentType.plans ?? {});

                if (!availableSizes.includes(String(activeSize))) {
                    [activeSize] = availableSizes;
                }

                const plan = currentType.plans?.[activeSize];
                const activeTypeButton = typeButtons.find((button) => button.dataset.challengeType === activeType);
                const activeTypeLabel = activeTypeButton?.dataset.label ?? '';
                const activeTypeDescription = activeTypeButton?.dataset.description ?? '';
                const launchPrice = convertCurrency(plan?.discounted_price ?? plan?.entry_fee ?? 0, activeCurrency);
                const regularPrice = convertCurrency(plan?.list_price ?? plan?.entry_fee ?? 0, activeCurrency);

                if (!plan) {
                    return;
                }

                if (planBadge) {
                    planBadge.textContent = activeTypeLabel;
                }

                if (planTitle) {
                    planTitle.textContent = `${activeTypeLabel} / ${formatSize(plan.account_size)}`;
                }

                if (planCurrencyCode) {
                    planCurrencyCode.textContent = activeCurrency;
                }

                if (planCurrencyFlag) {
                    planCurrencyFlag.textContent = currencies[activeCurrency]?.flag ?? '';
                }

                if (planPrice) {
                    planPrice.textContent = formatCurrency(launchPrice, activeCurrency);
                }

                if (planOriginalPrice) {
                    planOriginalPrice.textContent = formatCurrency(regularPrice, activeCurrency);
                }

                if (planOriginalWrap instanceof HTMLElement) {
                    planOriginalWrap.classList.toggle('hidden', !plan.discount?.enabled);
                }

                if (planDiscountBadge instanceof HTMLElement) {
                    planDiscountBadge.classList.toggle('hidden', !plan.discount?.enabled);
                    planDiscountBadge.textContent = ui.discount_badge ?? planDiscountBadge.textContent ?? '';
                }

                if (planDiscountUrgency instanceof HTMLElement) {
                    planDiscountUrgency.classList.toggle('hidden', !plan.discount?.enabled);
                    planDiscountUrgency.textContent = ui.discount_urgency ?? planDiscountUrgency.textContent ?? '';
                }

                if (description) {
                    description.textContent = activeTypeDescription;
                }

                if (checkoutPlanTitle) {
                    checkoutPlanTitle.textContent = `${activeTypeLabel} / ${formatSize(plan.account_size)}`;
                }

                if (checkoutPlanPrice) {
                    checkoutPlanPrice.textContent = formatCurrency(launchPrice, activeCurrency);
                }

                if (checkoutPlanCurrency) {
                    checkoutPlanCurrency.textContent = activeCurrency;
                }

                const checkoutPlanCurrencyFlag = document.querySelector('[data-checkout-plan-currency-flag]');

                if (checkoutPlanCurrencyFlag instanceof HTMLElement) {
                    checkoutPlanCurrencyFlag.textContent = currencies[activeCurrency]?.flag ?? '';
                }

                if (planNoteTitle) {
                    planNoteTitle.textContent = activeTypeButton?.dataset.noteTitle ?? '';
                }

                if (planNoteBody) {
                    planNoteBody.textContent = activeTypeButton?.dataset.noteBody ?? '';
                }

                renderDetailGroups(plan);

                checkoutCtas.forEach((link) => {
                    if (!(link instanceof HTMLAnchorElement)) {
                        return;
                    }

                    const base = link.dataset.checkoutBase ?? link.href;
                    const checkoutUrl = new URL(base, window.location.origin);

                    checkoutUrl.searchParams.set('challenge_type', activeType);
                    checkoutUrl.searchParams.set('account_size', String(plan.account_size));
                    checkoutUrl.searchParams.set('currency', activeCurrency);

                    link.href = checkoutUrl.toString();
                });

                currencyButtons.forEach((button) => {
                    updateToggleState(button, button.dataset.challengeCurrency === activeCurrency, currencyActiveClasses, currencyInactiveClasses);
                });

                typeButtons.forEach((button) => {
                    updateToggleState(button, button.dataset.challengeType === activeType, typeActiveClasses, typeInactiveClasses);
                });

                sizeButtons.forEach((button) => {
                    const buttonSize = button.dataset.challengeSize ?? '';
                    const available = availableSizes.includes(buttonSize);
                    updateSizeButtonState(button, available && buttonSize === String(activeSize), available);
                });
            };

            currencyButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    const nextCurrency = button.dataset.challengeCurrency ?? activeCurrency;

                    if (!availableCurrencies.includes(nextCurrency)) {
                        return;
                    }

                    activeCurrency = nextCurrency;
                    storage.set(currencyStorageKey, activeCurrency);
                    renderPlan();
                });
            });

            typeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeType = button.dataset.challengeType ?? activeType;
                    renderPlan();
                });
            });

            sizeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (button.disabled) {
                        return;
                    }

                    activeSize = button.dataset.challengeSize ?? activeSize;
                    renderPlan();
                });
            });

            renderPlan();
        }
    }

    const searchInput = document.querySelector('[data-faq-search]');

    if (searchInput instanceof HTMLInputElement) {
        const sections = document.querySelectorAll('[data-faq-section]');
        const emptyState = document.querySelector('[data-faq-empty]');

        const filterFaq = () => {
            const query = searchInput.value.trim().toLowerCase();
            let visibleItems = 0;

            sections.forEach((section) => {
                let visibleInSection = 0;

                section.querySelectorAll('[data-faq-item]').forEach((item) => {
                    const haystack = (item.getAttribute('data-faq-text') ?? '').toLowerCase();
                    const matches = query === '' || haystack.includes(query);

                    item.classList.toggle('hidden', !matches);

                    if (matches) {
                        visibleItems += 1;
                        visibleInSection += 1;
                    }
                });

                section.classList.toggle('hidden', visibleInSection === 0);
            });

            if (emptyState) {
                emptyState.classList.toggle('hidden', visibleItems !== 0);
            }
        };

        searchInput.addEventListener('input', filterFaq);
        filterFaq();
    }

    const contactChatButton = document.querySelector('[data-contact-chat-launch]');

    if (contactChatButton instanceof HTMLButtonElement) {
        const messageField = document.querySelector('[data-contact-chat-message]');
        const status = document.querySelector('[data-contact-chat-status]');
        const supportEmail = contactChatButton.dataset.contactChatEmail ?? '';
        const subject = contactChatButton.dataset.contactChatSubject ?? 'Support request';

        contactChatButton.addEventListener('click', () => {
            const message = messageField instanceof HTMLTextAreaElement
                ? messageField.value.trim()
                : '';

            if (message === '') {
                if (status instanceof HTMLElement) {
                    status.textContent = status.dataset.emptyMessage ?? status.textContent ?? '';
                }

                messageField?.focus();
                return;
            }

            const params = new URLSearchParams({
                subject,
                body: message,
            });

            window.location.href = `mailto:${supportEmail}?${params.toString()}`;
        });
    }

    const voiceAssistant = document.querySelector('[data-voice-assistant]');

    if (voiceAssistant instanceof HTMLElement) {
        const indexScript = voiceAssistant.querySelector('[data-voice-assistant-index]');
        const input = voiceAssistant.querySelector('[data-voice-question]');
        const submitButton = voiceAssistant.querySelector('[data-voice-submit]');
        const micButton = voiceAssistant.querySelector('[data-voice-mic]');
        const status = voiceAssistant.querySelector('[data-voice-status]');
        const answerQuestion = voiceAssistant.querySelector('[data-voice-answer-question]');
        const answerText = voiceAssistant.querySelector('[data-voice-answer-text]');
        const voiceIndex = indexScript instanceof HTMLScriptElement
            ? JSON.parse(indexScript.textContent ?? '[]')
            : [];
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

        const findBestAnswer = (query) => {
            const normalizedQuery = normalizeText(query);
            const tokens = normalizedQuery.split(/\s+/).filter(Boolean);

            if (normalizedQuery === '') {
                return null;
            }

            return voiceIndex
                .map((item) => {
                    const haystack = normalizeText(`${item.question ?? ''} ${item.answer ?? ''} ${item.search_text ?? ''} ${item.section ?? ''}`);
                    let score = haystack.includes(normalizedQuery) ? 120 : 0;

                    tokens.forEach((token) => {
                        if (haystack.includes(token)) {
                            score += 14;
                        }

                        if (normalizeText(item.question ?? '').includes(token)) {
                            score += 10;
                        }
                    });

                    return {
                        ...item,
                        score,
                    };
                })
                .sort((left, right) => right.score - left.score)
                .find((item) => item.score > 0) ?? null;
        };

        const renderAnswer = (query) => {
            const match = findBestAnswer(query);

            if (!(answerQuestion instanceof HTMLElement) || !(answerText instanceof HTMLElement)) {
                return;
            }

            if (!match) {
                answerQuestion.textContent = query;
                answerText.textContent = '';

                if (status instanceof HTMLElement) {
                    status.textContent = status.dataset.noMatchMessage ?? status.textContent ?? '';
                }

                return;
            }

            answerQuestion.textContent = match.question ?? '';
            answerText.textContent = match.answer ?? '';

            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(new SpeechSynthesisUtterance(match.answer ?? ''));
            }
        };

        if (status instanceof HTMLElement) {
            status.dataset.emptyState = status.dataset.readyMessage ?? status.textContent ?? '';
        }

        if (submitButton instanceof HTMLButtonElement && input instanceof HTMLInputElement) {
            submitButton.addEventListener('click', () => {
                renderAnswer(input.value);
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    renderAnswer(input.value);
                }
            });
        }

        if (!(SpeechRecognition instanceof Function)) {
            if (micButton instanceof HTMLButtonElement) {
                micButton.disabled = true;
                micButton.classList.add('opacity-60', 'cursor-not-allowed');
            }

            if (status instanceof HTMLElement) {
                status.textContent = micButton?.dataset.unsupported ?? status.textContent ?? '';
            }
        } else if (micButton instanceof HTMLButtonElement && input instanceof HTMLInputElement) {
            const recognition = new SpeechRecognition();
            recognition.lang = document.documentElement.lang || 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;

            micButton.addEventListener('click', () => {
                recognition.start();
            });

            recognition.addEventListener('start', () => {
                if (status instanceof HTMLElement) {
                    status.textContent = micButton.dataset.listening ?? status.textContent ?? '';
                }
            });

            recognition.addEventListener('result', (event) => {
                const transcript = event.results?.[0]?.[0]?.transcript ?? '';
                input.value = transcript;
                renderAnswer(transcript);
            });

            recognition.addEventListener('end', () => {
                if (status instanceof HTMLElement) {
                    status.textContent = status.dataset.emptyState ?? status.textContent ?? '';
                }
            });
        }
    }

    document.querySelectorAll('[data-flash]').forEach((flash) => {
        window.setTimeout(() => {
            flash.classList.add('opacity-0', 'translate-y-2');
        }, 3600);
    });
});
