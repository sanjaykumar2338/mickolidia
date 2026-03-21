import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
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
        const typeButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-type]'));
        const sizeButtons = Array.from(challengeSelector.querySelectorAll('[data-challenge-size]'));
        const checkoutSelect = document.querySelector('[data-checkout-plan-select]');

        if (catalogScript instanceof HTMLScriptElement) {
            const catalog = JSON.parse(catalogScript.textContent ?? '{}');
            let activeType = challengeSelector.dataset.defaultType ?? Object.keys(catalog)[0];
            let activeSize = challengeSelector.dataset.defaultSize ?? Object.keys(catalog[activeType]?.plans ?? {})[0];

            const typeActiveClasses = ['border-amber-300/30', 'bg-amber-400/12', 'text-white', 'shadow-lg', 'shadow-amber-950/15'];
            const typeInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-300'];
            const sizeActiveClasses = ['border-amber-300/30', 'bg-amber-400', 'text-slate-950', 'shadow-lg', 'shadow-amber-950/20'];
            const sizeInactiveClasses = ['border-white/8', 'bg-white/3', 'text-slate-200'];

            const planBadge = challengeSelector.querySelector('[data-challenge-badge]');
            const planTitle = challengeSelector.querySelector('[data-plan-title]');
            const planPrice = challengeSelector.querySelector('[data-plan-price]');
            const description = challengeSelector.querySelector('[data-challenge-description-text]');
            const planProfitShare = challengeSelector.querySelector('[data-plan-profit-share]');
            const planDailyLoss = challengeSelector.querySelector('[data-plan-daily-loss]');
            const planTotalLoss = challengeSelector.querySelector('[data-plan-total-loss]');
            const planMinimumDays = challengeSelector.querySelector('[data-plan-minimum-days]');
            const planFirstWithdrawal = challengeSelector.querySelector('[data-plan-first-withdrawal]');
            const planMaxDays = challengeSelector.querySelector('[data-plan-max-days]');

            const formatCurrency = (amount, currency = 'EUR') => {
                const normalized = Number(amount);

                if (currency === 'EUR') {
                    return `€${normalized.toLocaleString()}`;
                }

                return `${currency} ${normalized.toLocaleString()}`;
            };

            const formatSize = (amount) => `${Number(amount) / 1000}K`;

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
                    planPrice.textContent = formatCurrency(plan.entry_fee, plan.currency);
                }

                if (description) {
                    description.textContent = activeTypeDescription;
                }

                if (planProfitShare) {
                    planProfitShare.textContent = `${plan.profit_share}%`;
                }

                if (planDailyLoss) {
                    planDailyLoss.textContent = `${plan.daily_loss_limit}%`;
                }

                if (planTotalLoss) {
                    planTotalLoss.textContent = `${plan.max_loss_limit}%`;
                }

                if (planMinimumDays) {
                    planMinimumDays.textContent = String(plan.minimum_trading_days);
                }

                if (planFirstWithdrawal) {
                    planFirstWithdrawal.textContent = `${plan.first_payout_days} ${challengeSelector.dataset.daysLabel ?? 'days'}`;
                }

                if (planMaxDays) {
                    planMaxDays.textContent = plan.maximum_trading_days === null
                        ? challengeSelector.dataset.unlimitedLabel ?? 'Unlimited'
                        : String(plan.maximum_trading_days);
                }

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
