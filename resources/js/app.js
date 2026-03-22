import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    const fixedDisclaimer = document.querySelector('[data-fixed-disclaimer]');

    if (fixedDisclaimer instanceof HTMLElement) {
        const closeButton = fixedDisclaimer.querySelector('[data-fixed-disclaimer-close]');
        const storageKey = 'wolforix-fixed-disclaimer-dismissed';

        try {
            if (window.localStorage.getItem(storageKey) === '1') {
                fixedDisclaimer.classList.add('hidden');
            }
        } catch (error) {
            // Ignore storage access issues and keep the disclaimer visible.
        }

        if (closeButton instanceof HTMLButtonElement) {
            closeButton.addEventListener('click', () => {
                fixedDisclaimer.classList.add('hidden');

                try {
                    window.localStorage.setItem(storageKey, '1');
                } catch (error) {
                    // Ignore storage access issues; hiding for the current session is sufficient.
                }
            });
        }
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

        toggle.addEventListener('click', (event) => {
            event.preventDefault();

            const isClosed = menu.classList.contains('hidden');

            localeSwitchers.forEach((item) => {
                const itemToggle = item.querySelector('[data-locale-toggle]');
                const itemMenu = item.querySelector('[data-locale-menu]');

                if (item !== switcher && itemMenu instanceof HTMLElement && itemToggle instanceof HTMLButtonElement) {
                    itemMenu.classList.add('hidden');
                    itemToggle.setAttribute('aria-expanded', 'false');
                }
            });

            menu.classList.toggle('hidden', !isClosed);
            toggle.setAttribute('aria-expanded', String(isClosed));
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
    });

    const challengeSelector = document.querySelector('[data-challenge-selector]');

    if (challengeSelector instanceof HTMLElement) {
        const catalogScript = challengeSelector.querySelector('[data-challenge-catalog]');
        const uiScript = challengeSelector.querySelector('[data-challenge-ui]');
        const typeButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-type]'));
        const sizeButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-size]'));
        const checkoutSelect = document.querySelector('[data-checkout-plan-select]');

        if (catalogScript instanceof HTMLScriptElement) {
            const catalog = JSON.parse(catalogScript.textContent ?? '{}');
            const ui = uiScript instanceof HTMLScriptElement ? JSON.parse(uiScript.textContent ?? '{}') : {};
            let activeType = challengeSelector.dataset.defaultType ?? Object.keys(catalog)[0];
            let activeSize = challengeSelector.dataset.defaultSize ?? Object.keys(catalog[activeType]?.plans ?? {})[0];

            const typeActiveClasses = ['border-amber-300/30', 'bg-amber-400/12', 'text-white', 'shadow-lg', 'shadow-amber-950/15'];
            const typeInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-300'];
            const sizeActiveClasses = ['border-amber-300/30', 'bg-amber-400', 'text-slate-950', 'shadow-lg', 'shadow-amber-950/20'];
            const sizeInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-200'];

            const planBadge = challengeSelector.querySelector('[data-challenge-badge]');
            const planTitle = challengeSelector.querySelector('[data-plan-title]');
            const planPrice = challengeSelector.querySelector('[data-plan-price]');
            const planOriginalWrap = challengeSelector.querySelector('[data-plan-original-wrap]');
            const planOriginalPrice = challengeSelector.querySelector('[data-plan-original-price]');
            const planDiscountBadge = challengeSelector.querySelector('[data-plan-discount-badge]');
            const planDiscountUrgency = challengeSelector.querySelector('[data-plan-discount-urgency]');
            const description = challengeSelector.querySelector('[data-challenge-description-text]');
            const detailGroups = challengeSelector.querySelector('[data-plan-detail-groups]');
            const planNoteTitle = challengeSelector.querySelector('[data-plan-note-title]');
            const planNoteBody = challengeSelector.querySelector('[data-plan-note-body]');

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
            const formatAfterDays = (days) => replaceTokens(ui.value_templates?.after_days ?? ':days days', { days });
            const formatScaling = (percent, months) => replaceTokens(
                ui.value_templates?.scaling ?? '+:percent% capital every :months months if profitable',
                { percent, months },
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

            const updateTypeButtonState = (button, active) => {
                button.classList.remove(...(active ? typeInactiveClasses : typeActiveClasses));
                button.classList.add(...(active ? typeActiveClasses : typeInactiveClasses));
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

                if (!plan) {
                    return;
                }

                if (planBadge) {
                    planBadge.textContent = activeTypeLabel;
                }

                if (planTitle) {
                    planTitle.textContent = `${activeTypeLabel} / ${formatSize(plan.account_size)}`;
                }

                if (planPrice) {
                    planPrice.textContent = formatCurrency(plan.discounted_price ?? plan.entry_fee, plan.currency);
                }

                if (planOriginalPrice) {
                    planOriginalPrice.textContent = formatCurrency(plan.list_price ?? plan.entry_fee, plan.currency);
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

                if (planNoteTitle) {
                    planNoteTitle.textContent = activeTypeButton?.dataset.noteTitle ?? '';
                }

                if (planNoteBody) {
                    planNoteBody.textContent = activeTypeButton?.dataset.noteBody ?? '';
                }

                renderDetailGroups(plan);

                if (checkoutSelect instanceof HTMLSelectElement) {
                    checkoutSelect.value = plan.slug;
                }

                typeButtons.forEach((button) => {
                    updateTypeButtonState(button, button.dataset.challengeType === activeType);
                });

                sizeButtons.forEach((button) => {
                    const buttonSize = button.dataset.challengeSize ?? '';
                    const available = availableSizes.includes(buttonSize);
                    updateSizeButtonState(button, available && buttonSize === String(activeSize), available);
                });
            };

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

    document.querySelectorAll('[data-flash]').forEach((flash) => {
        window.setTimeout(() => {
            flash.classList.add('opacity-0', 'translate-y-2');
        }, 3600);
    });
});
