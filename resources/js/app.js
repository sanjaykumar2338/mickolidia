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
        remove(key) {
            try {
                window.localStorage.removeItem(key);
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
    const normalizeLocaleCode = (value) => String(value ?? '').toLowerCase().split(/[-_]/)[0];
    const uniqueValues = (values) => Array.from(new Set(values.filter(Boolean)));

    const escapeHtml = (value) => (value ?? '')
        .toString()
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
    const assistantLocaleProfiles = {
        en: {
            regex: /\b(hello|hi|hey|when|what|how|can|payout|withdraw|rule|challenge|funded|account|support|login|checkout|discount)\b/i,
            tokens: ['hello', 'hi', 'hey', 'when', 'what', 'how', 'can', 'payout', 'withdraw', 'rules', 'challenge', 'funded', 'account', 'support', 'login', 'checkout', 'discount', 'plan', 'drawdown', 'news'],
        },
        es: {
            regex: /[¿¡ñáéíóú]|\b(hola|cuando|como|puedo|retiro|payout|regla|desafio|challenge|cuenta|funded|soporte|inicio|descuento|checkout)\b/i,
            tokens: ['hola', 'cuando', 'como', 'puedo', 'retiro', 'payout', 'regla', 'desafio', 'challenge', 'cuenta', 'funded', 'soporte', 'iniciar', 'descuento', 'checkout', 'plan', 'drawdown', 'noticias'],
        },
        fr: {
            regex: /[àâçéèêëîïôùûüÿœæ]|\b(bonjour|quand|comment|puis|paiement|retrait|regle|challenge|compte|support|connexion|remise)\b/i,
            tokens: ['bonjour', 'quand', 'comment', 'puis', 'paiement', 'retrait', 'regle', 'challenge', 'compte', 'support', 'connexion', 'remise', 'plan', 'drawdown', 'actualites'],
        },
        de: {
            regex: /[äöüß]|\b(hallo|wann|wie|kann|auszahlung|regel|challenge|konto|support|login|rabatt|checkout)\b/i,
            tokens: ['hallo', 'wann', 'wie', 'kann', 'auszahlung', 'regel', 'challenge', 'konto', 'support', 'login', 'rabatt', 'checkout', 'plan', 'drawdown', 'nachrichten'],
        },
    };
    const assistantIntentProfiles = {
        greeting: ['hello', 'hi', 'hey', 'hola', 'bonjour', 'salut', 'hallo'],
        support: ['support', 'help', 'contact', 'email', 'ayuda', 'soporte', 'aide', 'hilfe', 'kontakt', 'billing'],
        login: ['login', 'log in', 'sign in', 'signin', 'password', 'register', 'account', 'google', 'facebook', 'apple', 'contraseña', 'passwort', 'mot de passe', 'anmelden', 'registro', 'registrarse'],
        payout: ['payout', 'withdraw', 'withdrawal', 'profit split', 'payment', 'retiro', 'retirar', 'retrait', 'auszahlung', 'zahlung', 'primer payout', 'first payout', 'first withdrawal'],
        rules: ['rule', 'rules', 'drawdown', 'loss', 'daily loss', 'consistency', 'news', 'regla', 'regeln', 'verlust', 'regel', 'nachrichten', 'noticias', 'nouvelles', 'daily drawdown', 'max loss'],
        plans: ['plan', 'challenge', 'account size', 'size', 'model', 'one step', 'two step', 'desafio', 'cuenta', 'konto', 'compte', 'funded', 'phase', 'fase', 'phase 1', 'phase 2'],
        checkout: ['checkout', 'buy', 'purchase', 'order', 'plan kaufen', 'comprar', 'pedido', 'commande'],
        discount: ['discount', 'promo', 'promo code', 'launch code', 'rabatt', 'descuento', 'remise', 'coupon'],
    };
    const assistantConversationProfiles = {
        en: {
            followups: {
                intro: 'Ask me about payouts, rules, or the best next step for your account.',
                default: 'If you want, I can also break that down step by step.',
                support: 'If you want, I can point you to the fastest support option.',
                plans: 'If you want, I can compare the 1-Step and 2-Step models for you.',
                payout: 'If you want, I can also explain the timing and what happens after you submit a request.',
                rules: 'If you want, I can turn the rules into a quick checklist.',
                checkout: 'If you want, I can guide you through the checkout flow step by step.',
                discount: 'If you want, I can tell you exactly where to apply the code.',
            },
        },
        es: {
            followups: {
                intro: 'Preguntame por payouts, reglas o por el mejor siguiente paso para tu cuenta.',
                default: 'Si quieres, tambien te lo explico paso a paso.',
                support: 'Si quieres, te llevo a la opcion de soporte mas rapida.',
                plans: 'Si quieres, te comparo el modelo 1-Step con el 2-Step.',
                payout: 'Si quieres, tambien te explico el calendario y que pasa despues de enviar la solicitud.',
                rules: 'Si quieres, te resumo las reglas en una checklist rapida.',
                checkout: 'Si quieres, te guio por el checkout paso a paso.',
                discount: 'Si quieres, te digo exactamente donde aplicar el codigo.',
            },
        },
        de: {
            followups: {
                intro: 'Frag mich nach Auszahlungen, Regeln oder dem besten naechsten Schritt fuer dein Konto.',
                default: 'Wenn du willst, erklaere ich dir das auch Schritt fuer Schritt.',
                support: 'Wenn du willst, zeige ich dir den schnellsten Support-Weg.',
                plans: 'Wenn du willst, vergleiche ich dir 1-Step und 2-Step direkt.',
                payout: 'Wenn du willst, erklaere ich dir auch den Zeitplan und was nach der Anfrage passiert.',
                rules: 'Wenn du willst, fasse ich dir die Regeln als kurze Checkliste zusammen.',
                checkout: 'Wenn du willst, fuehre ich dich Schritt fuer Schritt durch den Checkout.',
                discount: 'Wenn du willst, sage ich dir genau, wo du den Code eintraegst.',
            },
        },
        fr: {
            followups: {
                intro: 'Demandez-moi les payouts, les regles ou le meilleur prochain pas pour votre compte.',
                default: 'Si vous voulez, je peux aussi vous l expliquer etape par etape.',
                support: 'Si vous voulez, je peux vous orienter vers l option de support la plus rapide.',
                plans: 'Si vous voulez, je peux comparer pour vous les modeles 1-Step et 2-Step.',
                payout: 'Si vous voulez, je peux aussi expliquer le timing et ce qui se passe apres votre demande.',
                rules: 'Si vous voulez, je peux resumer les regles sous forme de checklist rapide.',
                checkout: 'Si vous voulez, je peux vous guider dans le checkout etape par etape.',
                discount: 'Si vous voulez, je peux vous dire exactement ou appliquer le code.',
            },
        },
    };
    const assistantSpeechProfiles = {
        en: {
            rate: 0.97,
            pitch: 0.82,
            volume: 0.95,
            maleVoices: ['male', 'guy', 'davis', 'daniel', 'alex', 'arthur', 'thomas', 'tom', 'fred', 'gordon', 'nathan', 'oliver', 'matthew', 'michael', 'aaron', 'david'],
            femaleVoices: ['female', 'aria', 'jenny', 'samantha', 'moira', 'serena', 'allison', 'ava', 'sofia', 'anna', 'eva'],
        },
        es: {
            rate: 0.98,
            pitch: 0.84,
            volume: 0.96,
            maleVoices: ['male', 'jorge', 'diego', 'carlos', 'enrique', 'raul', 'pablo', 'antonio', 'alvaro', 'juan', 'jose'],
            femaleVoices: ['female', 'monica', 'paulina', 'helena', 'sofia', 'lucia', 'maria'],
        },
        de: {
            rate: 0.96,
            pitch: 0.82,
            volume: 0.95,
            maleVoices: ['male', 'daniel', 'markus', 'hans', 'florian', 'stefan', 'klaus'],
            femaleVoices: ['female', 'anna', 'petra', 'helena', 'sabina', 'eva'],
        },
        fr: {
            rate: 0.97,
            pitch: 0.84,
            volume: 0.95,
            maleVoices: ['male', 'thomas', 'daniel', 'henri', 'alexandre', 'antoine', 'nicolas'],
            femaleVoices: ['female', 'amelie', 'aurelie', 'virginie', 'marie', 'julie'],
        },
    };
    const resolveAssistantConversationProfile = (locale) => assistantConversationProfiles[normalizeLocaleCode(locale)] ?? assistantConversationProfiles.en;
    const resolveAssistantSpeechProfile = (locale) => assistantSpeechProfiles[normalizeLocaleCode(locale)] ?? assistantSpeechProfiles.en;
    const ensureSentenceEnding = (value) => {
        const normalized = String(value ?? '').replace(/\s+/g, ' ').trim();

        if (normalized === '') {
            return '';
        }

        return /[.!?…]$/.test(normalized) ? normalized : `${normalized}.`;
    };
    const maybeAppendSentence = (value, addition) => {
        const base = ensureSentenceEnding(value);
        const extra = ensureSentenceEnding(addition);

        if (base === '' || extra === '') {
            return base || extra;
        }

        const comparableExtra = extra.replace(/[.!?…]$/, '');

        if (normalizeText(base).includes(normalizeText(comparableExtra))) {
            return base;
        }

        return `${base} ${extra}`.trim();
    };

    const launchPromoStorageKey = 'wolforix-launch-promo-code';
    const normalizePromoCode = (value) => (value ?? '').toString().trim();
    const getLaunchPromoCode = () => normalizePromoCode(storage.get(launchPromoStorageKey));
    const setLaunchPromoCode = (value) => {
        const normalized = normalizePromoCode(value);

        if (normalized !== '') {
            storage.set(launchPromoStorageKey, normalized);
        }

        return normalized;
    };
    const withLaunchPromoCode = (value, promoCode = getLaunchPromoCode()) => {
        const normalizedPromoCode = normalizePromoCode(promoCode);

        if (typeof value !== 'string' || value.trim() === '') {
            return value;
        }

        try {
            const url = new URL(value, window.location.origin);

            if (normalizedPromoCode !== '') {
                url.searchParams.set('promo_code', normalizedPromoCode);
            } else {
                url.searchParams.delete('promo_code');
            }

            return url.toString();
        } catch (error) {
            return value;
        }
    };
    const syncLaunchPromoCodeToCheckoutLinks = (promoCode = getLaunchPromoCode()) => {
        document.querySelectorAll('[data-checkout-cta]').forEach((link) => {
            if (!(link instanceof HTMLAnchorElement)) {
                return;
            }

            link.href = withLaunchPromoCode(link.href, promoCode);
        });
    };

    const sessionLaunchPromoCode = normalizePromoCode(document.body?.dataset.launchPromoCode ?? '');

    if (sessionLaunchPromoCode !== '') {
        setLaunchPromoCode(sessionLaunchPromoCode);
    } else {
        storage.remove(launchPromoStorageKey);
    }

    syncLaunchPromoCodeToCheckoutLinks(sessionLaunchPromoCode);

    const setBodyState = (className, enabled) => {
        document.body.classList.toggle(className, enabled);
        document.documentElement.classList.toggle(className, enabled);
    };

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

    const cookieBanner = document.querySelector('[data-cookie-banner]');

    if (cookieBanner instanceof HTMLElement) {
        const acceptButton = cookieBanner.querySelector('[data-cookie-banner-accept]');
        const storageKey = 'wolforix-cookie-consent-accepted';

        const hideCookieBanner = () => {
            cookieBanner.classList.add('hidden');
            setBodyState('cookie-banner-visible', false);
        };

        const showCookieBanner = () => {
            cookieBanner.classList.remove('hidden');
            setBodyState('cookie-banner-visible', true);
        };

        if (storage.get(storageKey) === '1') {
            hideCookieBanner();
        } else {
            showCookieBanner();
        }

        if (acceptButton instanceof HTMLButtonElement) {
            acceptButton.addEventListener('click', () => {
                storage.set(storageKey, '1');
                hideCookieBanner();
            });
        }
    }

    const launchPopup = document.querySelector('[data-launch-popup]');

    if (launchPopup instanceof HTMLElement && !launchPopup.classList.contains('hidden')) {
        const ignoreForm = document.getElementById('launch-offer-ignore-form');
        const closeButtons = launchPopup.querySelectorAll('[data-launch-popup-close]');

        closeButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', (event) => {
                if (!(ignoreForm instanceof HTMLFormElement)) {
                    return;
                }

                event.preventDefault();
                ignoreForm.requestSubmit();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && ignoreForm instanceof HTMLFormElement) {
                ignoreForm.requestSubmit();
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

    const mobileNav = document.querySelector('[data-mobile-nav]');

    if (mobileNav instanceof HTMLElement) {
        const toggle = mobileNav.querySelector('[data-mobile-nav-toggle]');
        const panel = mobileNav.querySelector('[data-mobile-nav-panel]');
        const openIcon = mobileNav.querySelector('[data-mobile-nav-open-icon]');
        const closeIcon = mobileNav.querySelector('[data-mobile-nav-close-icon]');
        const closeTriggers = mobileNav.querySelectorAll('[data-mobile-nav-close]');

        if (toggle instanceof HTMLButtonElement && panel instanceof HTMLElement) {
            const syncMobileNavState = (open) => {
                panel.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? (toggle.dataset.closeLabel ?? '') : (toggle.dataset.openLabel ?? ''));

                if (openIcon instanceof SVGElement) {
                    openIcon.classList.toggle('hidden', open);
                }

                if (closeIcon instanceof SVGElement) {
                    closeIcon.classList.toggle('hidden', !open);
                }
            };

            const closeMobileNav = () => {
                syncMobileNavState(false);
            };

            toggle.addEventListener('click', () => {
                const shouldOpen = panel.classList.contains('hidden');
                syncMobileNavState(shouldOpen);
            });

            closeTriggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    closeMobileNav();
                });
            });

            document.addEventListener('click', (event) => {
                if (!mobileNav.contains(event.target)) {
                    closeMobileNav();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeMobileNav();
                }
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    closeMobileNav();
                }
            });

            syncMobileNavState(false);
        }
    }

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
                <div class="challenge-metric-row rounded-2xl border border-white/6 bg-black/15 px-4 py-3">
                    <dt class="challenge-metric-term text-slate-400">${label}</dt>
                    <dd class="challenge-metric-value font-semibold text-white">${value}</dd>
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
                        <section class="challenge-detail-card surface-card rounded-[1.6rem] p-5">
                            <p class="challenge-detail-title text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">${ui.phase_titles?.[phase.key] ?? phase.key}</p>
                            <dl class="challenge-metric-list mt-4 text-sm">
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
                    <section class="challenge-detail-card surface-card rounded-[1.6rem] p-5">
                        <p class="challenge-detail-title text-xs font-semibold uppercase tracking-[0.24em] text-amber-300">${ui.phase_titles?.funded ?? 'Funded Account'}</p>
                        <dl class="challenge-metric-list mt-4 text-sm">
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

                    link.href = withLaunchPromoCode(checkoutUrl.toString());
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

    const checkoutPromo = document.querySelector('[data-checkout-promo]');
    const checkoutPricing = document.querySelector('[data-checkout-pricing]');

    if (checkoutPromo instanceof HTMLElement && checkoutPricing instanceof HTMLElement) {
        const input = checkoutPromo.querySelector('[data-promo-code-input]');
        const applyButton = checkoutPromo.querySelector('[data-promo-code-apply]');
        const feedback = checkoutPromo.querySelector('[data-promo-code-feedback]');
        const finalPrice = checkoutPricing.querySelector('[data-checkout-final-price]');
        const currencyLabel = checkoutPricing.querySelector('[data-checkout-currency]');
        const originalWrap = checkoutPricing.querySelector('[data-checkout-original-wrap]');
        const originalPrice = checkoutPricing.querySelector('[data-checkout-original-price]');
        const discountBadge = checkoutPricing.querySelector('[data-checkout-discount-badge]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const basePricingScript = checkoutPricing.querySelector('[data-checkout-base-pricing]');
        const selectedPricingScript = checkoutPricing.querySelector('[data-checkout-selected-pricing]');

        const parsePricingState = (script) => {
            if (!(script instanceof HTMLScriptElement)) {
                return null;
            }

            try {
                return JSON.parse(script.textContent ?? 'null');
            } catch (error) {
                return null;
            }
        };

        const basePricing = parsePricingState(basePricingScript);
        const selectedPricing = parsePricingState(selectedPricingScript) ?? basePricing;

        if (
            input instanceof HTMLInputElement
            && applyButton instanceof HTMLButtonElement
            && feedback instanceof HTMLElement
            && finalPrice instanceof HTMLElement
            && currencyLabel instanceof HTMLElement
            && originalWrap instanceof HTMLElement
            && originalPrice instanceof HTMLElement
            && discountBadge instanceof HTMLElement
            && basePricing
            && selectedPricing
        ) {
            let appliedCode = normalizePromoCode(checkoutPromo.dataset.appliedCode ?? '');
            const previewUrl = checkoutPromo.dataset.previewUrl ?? '';
            const challengeType = checkoutPromo.dataset.challengeType ?? '';
            const accountSize = checkoutPromo.dataset.accountSize ?? '';
            const currency = checkoutPromo.dataset.currency ?? '';
            const helpMessage = checkoutPromo.dataset.helpMessage ?? '';
            const successMessage = checkoutPromo.dataset.successMessage ?? '';
            const invalidMessage = checkoutPromo.dataset.invalidMessage ?? '';
            const feedbackStates = ['text-slate-400', 'text-emerald-100', 'text-rose-100'];

            const renderFeedback = (state, message) => {
                feedback.dataset.feedbackState = state;
                feedback.classList.remove(...feedbackStates);
                feedback.classList.add(
                    state === 'success'
                        ? 'text-emerald-100'
                        : state === 'error'
                            ? 'text-rose-100'
                            : 'text-slate-400',
                );
                feedback.textContent = message;
            };

            const renderPricing = (pricing) => {
                finalPrice.textContent = pricing.discounted_price ?? basePricing.discounted_price ?? '';
                currencyLabel.textContent = pricing.currency ?? basePricing.currency ?? '';
                const discountEnabled = Boolean(pricing.discount_enabled);

                originalWrap.classList.toggle('hidden', !discountEnabled);
                discountBadge.classList.toggle('hidden', !discountEnabled);

                if (discountEnabled) {
                    if (typeof pricing.discount_badge === 'string' && pricing.discount_badge.trim() !== '') {
                        discountBadge.textContent = pricing.discount_badge;
                    }

                    originalPrice.textContent = `${pricing.list_price} ${pricing.currency}`;
                }
            };

            const setIdleState = () => {
                appliedCode = '';
                renderPricing(basePricing);
                renderFeedback('idle', helpMessage);
                storage.remove(launchPromoStorageKey);
                syncLaunchPromoCodeToCheckoutLinks('');
            };

            renderPricing(selectedPricing);

            if (appliedCode !== '') {
                renderFeedback('success', feedback.textContent.trim() || successMessage);
            }

            input.addEventListener('input', () => {
                const normalizedInput = normalizePromoCode(input.value);

                if (normalizedInput === '' || normalizedInput.toLowerCase() !== appliedCode.toLowerCase()) {
                    setIdleState();
                }
            });

            applyButton.addEventListener('click', async () => {
                const promoCode = normalizePromoCode(input.value);

                if (promoCode === '') {
                    setIdleState();
                    return;
                }

                if (previewUrl === '' || challengeType === '' || accountSize === '' || currency === '') {
                    renderFeedback('error', invalidMessage);
                    return;
                }

                applyButton.disabled = true;

                try {
                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            challenge_type: challengeType,
                            account_size: accountSize,
                            currency,
                            promo_code: promoCode,
                        }),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message ?? invalidMessage);
                    }

                    renderPricing(payload.pricing ?? basePricing);

                    if (payload.applied) {
                        appliedCode = normalizePromoCode(payload.promo_code ?? promoCode);
                        input.value = appliedCode;
                        renderFeedback('success', payload.message ?? successMessage);
                        setLaunchPromoCode(appliedCode);
                        syncLaunchPromoCodeToCheckoutLinks(appliedCode);
                    } else {
                        appliedCode = '';
                        renderFeedback('error', payload.message ?? invalidMessage);
                        storage.remove(launchPromoStorageKey);
                        syncLaunchPromoCodeToCheckoutLinks('');
                    }
                } catch (error) {
                    appliedCode = '';
                    renderPricing(basePricing);
                    renderFeedback('error', error instanceof Error ? error.message : invalidMessage);
                    storage.remove(launchPromoStorageKey);
                    syncLaunchPromoCodeToCheckoutLinks('');
                } finally {
                    applyButton.disabled = false;
                }
            });
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

    document.querySelectorAll('[data-voice-assistant]').forEach((voiceAssistant) => {
        const indexScript = voiceAssistant.querySelector('[data-voice-assistant-index]');
        const configScript = voiceAssistant.querySelector('[data-voice-assistant-config]');
        const input = voiceAssistant.querySelector('[data-voice-question]');
        const submitButton = voiceAssistant.querySelector('[data-voice-submit]');
        const micButton = voiceAssistant.querySelector('[data-voice-mic]');
        const playButton = voiceAssistant.querySelector('[data-voice-play]');
        const status = voiceAssistant.querySelector('[data-voice-status]');
        const answerQuestion = voiceAssistant.querySelector('[data-voice-answer-question]');
        const answerText = voiceAssistant.querySelector('[data-voice-answer-text]');
        const suggestionButtons = [...voiceAssistant.querySelectorAll('[data-voice-suggestion]')];
        const modalRoot = voiceAssistant.closest('[data-wolfi-modal]');
        const voiceIndex = indexScript instanceof HTMLScriptElement
            ? JSON.parse(indexScript.textContent ?? '[]')
            : [];
        const assistantConfig = configScript instanceof HTMLScriptElement
            ? JSON.parse(configScript.textContent ?? '{}')
            : {};
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const speechLocaleMap = {
            en: 'en-US',
            de: 'de-DE',
            es: 'es-ES',
            fr: 'fr-FR',
        };
        const pageLocale = voiceAssistant.dataset.pageLocale ?? document.documentElement.lang ?? 'en';
        const pageLocaleBase = normalizeLocaleCode(pageLocale) || 'en';
        const preferredLanguages = uniqueValues([
            pageLocale,
            ...(Array.isArray(window.navigator.languages) ? window.navigator.languages : []),
            window.navigator.language,
        ].map(normalizeLocaleCode)).filter(Boolean);
        const introBlockedMessage = assistantConfig.intro_blocked ?? '';
        const enrichedVoiceIndex = voiceIndex.map((item) => {
            const localeBase = normalizeLocaleCode(item.locale);
            const questionNormalized = normalizeText(item.question ?? '');
            const answerNormalized = normalizeText(item.answer ?? '');
            const searchNormalized = normalizeText(item.search_text ?? '');

            return {
                ...item,
                localeBase,
                questionNormalized,
                answerNormalized,
                searchNormalized,
                haystack: normalizeText(`${item.question ?? ''} ${item.answer ?? ''} ${item.search_text ?? ''} ${item.section ?? ''}`),
            };
        });

        const resolveSpeechLocale = (localeCandidates = preferredLanguages) => {
            for (const candidate of localeCandidates) {
                const normalized = String(candidate).toLowerCase();
                const baseLocale = normalizeLocaleCode(normalized);

                if (speechLocaleMap[normalized]) {
                    return speechLocaleMap[normalized];
                }

                if (speechLocaleMap[baseLocale]) {
                    return speechLocaleMap[baseLocale];
                }
            }

            return speechLocaleMap[pageLocaleBase] ?? 'en-US';
        };

        let recognition = null;
        let activeSpeechLocale = resolveSpeechLocale();
        let availableSpeechVoices = [];
        let currentAnswerText = '';
        let currentAnswerSpeechLocale = activeSpeechLocale;
        let isSpeakingReply = false;
        let isListening = false;
        let hasConversation = false;
        let hasPlayedIntro = false;
        let replyPlaybackTimeoutId = null;
        let activeReplyUtterance = null;
        let queuedReplyPlayback = null;
        let microphonePermissionState = 'prompt';
        let permissionListenerBound = false;
        let isRenderingResponse = false;
        let renderingStateTimeoutId = null;
        let suggestionRevealTimeoutId = null;
        let answerRevealToken = 0;
        let answerRevealTimeoutIds = [];
        let answerQuestionTimeoutId = null;
        const reducedMotionQuery = typeof window.matchMedia === 'function'
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        const voiceDebug = () => {};
        const prefersReducedMotion = () => reducedMotionQuery?.matches ?? false;

        const getPreferredAssistantLocales = (query = '') => {
            const rawQuery = String(query ?? '');
            const normalizedQuery = normalizeText(rawQuery);
            const tokens = normalizedQuery.split(/\s+/).filter(Boolean);
            const detectedLocales = Object.entries(assistantLocaleProfiles)
                .map(([locale, profile]) => {
                    let score = profile.regex.test(rawQuery) ? 4 : 0;

                    tokens.forEach((token) => {
                        if (profile.tokens.some((word) => word.includes(token) || token.includes(word))) {
                            score += 1;
                        }
                    });

                    return {
                        locale,
                        score,
                    };
                })
                .filter((item) => item.score > 0)
                .sort((left, right) => right.score - left.score)
                .map((item) => item.locale);

            return uniqueValues([
                ...detectedLocales,
                ...preferredLanguages,
                pageLocaleBase,
            ]);
        };

        const detectAssistantIntents = (query) => {
            const normalizedQuery = normalizeText(query);

            return Object.entries(assistantIntentProfiles)
                .filter(([, keywords]) => keywords.some((keyword) => normalizedQuery.includes(normalizeText(keyword))))
                .map(([intent]) => intent);
        };

        const clearTimedCallbacks = (timeoutIds) => {
            timeoutIds.forEach((timeoutId) => {
                window.clearTimeout(timeoutId);
            });
            timeoutIds.length = 0;
        };

        const syncAssistantVisualState = () => {
            voiceAssistant.classList.toggle('is-speaking', isSpeakingReply);
            voiceAssistant.classList.toggle('is-listening', isListening);
            voiceAssistant.classList.toggle('is-rendering', isRenderingResponse);

            if (modalRoot instanceof HTMLElement) {
                modalRoot.classList.toggle('is-speaking', isSpeakingReply);
                modalRoot.classList.toggle('is-listening', isListening);
                modalRoot.classList.toggle('is-rendering', isRenderingResponse);
                modalRoot.dispatchEvent(new CustomEvent('wolfi:statechange', {
                    detail: {
                        speaking: isSpeakingReply,
                        listening: isListening,
                        rendering: isRenderingResponse,
                    },
                    bubbles: true,
                }));
            }
        };

        const setRenderingState = (rendering, { settleAfter = null } = {}) => {
            if (renderingStateTimeoutId !== null) {
                window.clearTimeout(renderingStateTimeoutId);
                renderingStateTimeoutId = null;
            }

            isRenderingResponse = rendering;
            syncAssistantVisualState();

            if (rendering && typeof settleAfter === 'number' && settleAfter >= 0) {
                renderingStateTimeoutId = window.setTimeout(() => {
                    renderingStateTimeoutId = null;
                    isRenderingResponse = false;
                    syncAssistantVisualState();
                }, settleAfter);
            }
        };

        const clearSuggestionReveal = () => {
            if (suggestionRevealTimeoutId !== null) {
                window.clearTimeout(suggestionRevealTimeoutId);
                suggestionRevealTimeoutId = null;
            }
        };

        const revealSuggestionPrompts = ({ reset = false, immediate = prefersReducedMotion() } = {}) => {
            if (suggestionButtons.length === 0) {
                return;
            }

            clearSuggestionReveal();

            if (reset) {
                voiceAssistant.classList.remove('show-suggestions');
            }

            if (immediate) {
                voiceAssistant.classList.add('show-suggestions');
                return;
            }

            suggestionRevealTimeoutId = window.setTimeout(() => {
                suggestionRevealTimeoutId = null;
                voiceAssistant.classList.add('show-suggestions');
            }, reset ? 140 : 60);
        };

        const resetSuggestionPrompts = () => {
            clearSuggestionReveal();
            voiceAssistant.classList.remove('show-suggestions');
        };

        const stopAnswerReveal = ({ clear = false } = {}) => {
            answerRevealToken += 1;
            clearTimedCallbacks(answerRevealTimeoutIds);

            if (answerText instanceof HTMLElement) {
                answerText.classList.remove('is-revealing');

                if (clear) {
                    answerText.textContent = '';
                } else if (answerText.querySelector('.wolfi-answer-cursor')) {
                    answerText.textContent = currentAnswerText;
                }
            }
        };

        const getAnswerRevealDelay = (token) => {
            if (/[.!?]$/.test(token)) {
                return 116;
            }

            if (/[,;:]$/.test(token)) {
                return 72;
            }

            return 34;
        };

        const animateAnswerQuestion = (text) => {
            if (!(answerQuestion instanceof HTMLElement)) {
                return;
            }

            if (answerQuestionTimeoutId !== null) {
                window.clearTimeout(answerQuestionTimeoutId);
                answerQuestionTimeoutId = null;
            }

            answerQuestion.classList.add('is-updating');
            answerQuestion.textContent = text;

            answerQuestionTimeoutId = window.setTimeout(() => {
                answerQuestion.classList.remove('is-updating');
                answerQuestionTimeoutId = null;
            }, prefersReducedMotion() ? 0 : 180);
        };

        const revealAnswerText = (text) => {
            if (!(answerText instanceof HTMLElement)) {
                return;
            }

            const nextText = String(text ?? '').trim();

            stopAnswerReveal({
                clear: true,
            });

            if (nextText === '') {
                answerText.textContent = '';
                return;
            }

            if (prefersReducedMotion()) {
                answerText.textContent = nextText;
                return;
            }

            const revealToken = answerRevealToken;
            const words = nextText.split(/\s+/).filter(Boolean);
            const content = document.createElement('span');
            const cursor = document.createElement('span');
            cursor.className = 'wolfi-answer-cursor';
            cursor.setAttribute('aria-hidden', 'true');
            answerText.classList.add('is-revealing');
            answerText.replaceChildren(content, cursor);
            let visibleWordCount = 0;

            const step = () => {
                if (revealToken !== answerRevealToken) {
                    return;
                }

                visibleWordCount = Math.min(words.length, visibleWordCount + (visibleWordCount < 8 ? 2 : 3));
                content.textContent = words.slice(0, visibleWordCount).join(' ');

                if (visibleWordCount < words.length) {
                    const timeoutId = window.setTimeout(step, getAnswerRevealDelay(words[visibleWordCount - 1] ?? ''));
                    answerRevealTimeoutIds.push(timeoutId);
                    return;
                }

                const finalizeTimeoutId = window.setTimeout(() => {
                    if (revealToken !== answerRevealToken) {
                        return;
                    }

                    cursor.remove();
                    answerText.classList.remove('is-revealing');
                }, 180);

                answerRevealTimeoutIds.push(finalizeTimeoutId);
            };

            step();
        };

        const setPlayButtonState = (speaking, enabled = currentAnswerText.trim() !== '') => {
            if (!(playButton instanceof HTMLButtonElement)) {
                isSpeakingReply = speaking;
                syncAssistantVisualState();
                return;
            }

            isSpeakingReply = speaking;
            playButton.textContent = speaking
                ? (playButton.dataset.stopLabel ?? playButton.textContent ?? '')
                : (playButton.dataset.playLabel ?? playButton.textContent ?? '');
            playButton.setAttribute('aria-disabled', enabled ? 'false' : 'true');
            playButton.classList.toggle('text-slate-400', !enabled);
            playButton.classList.toggle('border-white/8', !enabled);
            playButton.classList.toggle('border-amber-400/35', speaking);
            playButton.classList.toggle('bg-amber-400/10', speaking);
            playButton.classList.toggle('text-amber-50', speaking);
            playButton.classList.toggle('is-active', speaking && enabled);
            syncAssistantVisualState();
        };

        const setActiveSpeechLocale = (nextLocale) => {
            if (typeof nextLocale !== 'string' || nextLocale.trim() === '') {
                return activeSpeechLocale;
            }

            activeSpeechLocale = nextLocale;

            if (recognition) {
                recognition.lang = activeSpeechLocale;
            }

            return activeSpeechLocale;
        };

        const refreshSpeechVoices = () => {
            if (!('speechSynthesis' in window)) {
                return;
            }

            availableSpeechVoices = window.speechSynthesis.getVoices() ?? [];
            voiceDebug('voices refreshed', {
                count: availableSpeechVoices.length,
                locales: availableSpeechVoices.map((voice) => voice.lang).filter(Boolean).slice(0, 10),
            });
        };

        const rankSpeechVoice = (voice, locale) => {
            const requestedLocale = String(locale ?? '').toLowerCase();
            const requestedBase = normalizeLocaleCode(requestedLocale);
            const voiceLocale = String(voice.lang ?? '').toLowerCase();
            const voiceBase = normalizeLocaleCode(voiceLocale);
            const voiceName = String(voice.name ?? '').toLowerCase();
            const speechProfile = resolveAssistantSpeechProfile(requestedBase);
            let score = 0;

            if (voiceLocale === requestedLocale) {
                score += 80;
            }

            if (voiceBase === requestedBase) {
                score += 54;
            }

            if (voiceName.includes('natural') || voiceName.includes('neural')) {
                score += 18;
            }

            if (speechProfile.maleVoices.some((keyword) => voiceName.includes(keyword))) {
                score += 20;
            }

            if (speechProfile.femaleVoices.some((keyword) => voiceName.includes(keyword))) {
                score -= 8;
            }

            if (voiceName.includes('premium') || voiceName.includes('enhanced')) {
                score += 6;
            }

            if (voice.default) {
                score += 8;
            }

            if (voice.localService) {
                score += 4;
            }

            return score;
        };

        const findSpeechVoice = (locale) => {
            return [...availableSpeechVoices]
                .sort((left, right) => rankSpeechVoice(right, locale) - rankSpeechVoice(left, locale))[0]
                ?? null;
        };

        const resolveItemSpeechLocale = (item) => {
            const itemLocale = normalizeLocaleCode(item?.locale);

            return item?.speech_locale
                ?? speechLocaleMap[itemLocale]
                ?? activeSpeechLocale;
        };

        const hasSecureVoiceContext = window.isSecureContext
            || ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);

        const checkMicrophonePermission = async () => {
            if (!navigator.permissions?.query) {
                return microphonePermissionState;
            }

            try {
                const permissionStatus = await navigator.permissions.query({
                    name: 'microphone',
                });

                microphonePermissionState = permissionStatus.state ?? microphonePermissionState;

                if (!permissionListenerBound && typeof permissionStatus.addEventListener === 'function') {
                    permissionStatus.addEventListener('change', () => {
                        microphonePermissionState = permissionStatus.state ?? microphonePermissionState;
                    });
                    permissionListenerBound = true;
                }
            } catch (error) {
                // Ignore permissions API failures and fall back to direct access requests.
            }

            return microphonePermissionState;
        };

        const requestMicrophoneAccess = async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                return {
                    ok: true,
                    message: '',
                };
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                });

                stream.getTracks().forEach((track) => {
                    track.stop();
                });

                microphonePermissionState = 'granted';

                return {
                    ok: true,
                    message: '',
                };
            } catch (error) {
                const errorName = error instanceof DOMException
                    ? error.name
                    : String(error?.name ?? '');

                if (['NotAllowedError', 'PermissionDeniedError'].includes(errorName)) {
                    microphonePermissionState = 'denied';

                    return {
                        ok: false,
                        message: micButton?.dataset.micBlocked ?? '',
                    };
                }

                if (['NotFoundError', 'DevicesNotFoundError'].includes(errorName)) {
                    return {
                        ok: false,
                        message: micButton?.dataset.audioCapture ?? '',
                    };
                }

                return {
                    ok: false,
                    message: micButton?.dataset.micBlocked ?? '',
                };
            }
        };

        if ('speechSynthesis' in window) {
            refreshSpeechVoices();

            if (typeof window.speechSynthesis.addEventListener === 'function') {
                window.speechSynthesis.addEventListener('voiceschanged', refreshSpeechVoices);
            }
        }

        const clearPendingReplyPlayback = () => {
            if (replyPlaybackTimeoutId !== null) {
                window.clearTimeout(replyPlaybackTimeoutId);
                replyPlaybackTimeoutId = null;
                voiceDebug('cleared pending playback timeout');
            }
        };

        const cancelSpokenReply = (enabled = currentAnswerText.trim() !== '') => {
            clearPendingReplyPlayback();
            activeReplyUtterance = null;

            if ('speechSynthesis' in window) {
                try {
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.resume();
                } catch (error) {
                    // Ignore browser-level speech cleanup failures.
                }
            }

            setPlayButtonState(false, enabled);
        };

        const focusInput = () => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            window.requestAnimationFrame(() => {
                input.focus();
            });
        };

        const resetAssistantSurface = () => {
            stopAnswerReveal();
            resetSuggestionPrompts();
            setRenderingState(false);

            if (answerQuestionTimeoutId !== null) {
                window.clearTimeout(answerQuestionTimeoutId);
                answerQuestionTimeoutId = null;
            }

            if (answerQuestion instanceof HTMLElement) {
                answerQuestion.classList.remove('is-updating');
            }
        };

        const speakAnswer = (
            text = currentAnswerText,
            locale = currentAnswerSpeechLocale,
            {
                userInitiated = false,
            } = {},
        ) => {
            const normalizedText = String(text ?? '').trim();

            if (!('speechSynthesis' in window) || normalizedText === '') {
                cancelSpokenReply(false);
                return;
            }

            refreshSpeechVoices();

            const synthesis = window.speechSynthesis;
            const utterance = new SpeechSynthesisUtterance(normalizedText);
            const speechProfile = resolveAssistantSpeechProfile(locale);
            const selectedVoice = findSpeechVoice(locale)
                ?? availableSpeechVoices.find((voice) => voice.default)
                ?? availableSpeechVoices[0]
                ?? null;
            const needsSpeechReset = synthesis.speaking
                || synthesis.pending
                || synthesis.paused
                || replyPlaybackTimeoutId !== null
                || activeReplyUtterance !== null;

            if (needsSpeechReset) {
                cancelSpokenReply(true);
            } else {
                clearPendingReplyPlayback();
                setPlayButtonState(false, true);
            }

            utterance.lang = selectedVoice?.lang ?? locale;
            utterance.volume = speechProfile.volume;
            utterance.rate = speechProfile.rate;
            utterance.pitch = speechProfile.pitch;

            if (selectedVoice) {
                utterance.voice = selectedVoice;
            }

            utterance.addEventListener('start', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                setPlayButtonState(true);
            });

            utterance.addEventListener('end', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                clearPendingReplyPlayback();
                activeReplyUtterance = null;
                setPlayButtonState(false, true);
            });

            utterance.addEventListener('error', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                clearPendingReplyPlayback();
                activeReplyUtterance = null;
                setPlayButtonState(false, true);

                if (userInitiated && status instanceof HTMLElement && introBlockedMessage !== '') {
                    status.textContent = introBlockedMessage;
                }
            });

            const startPlayback = () => {
                activeReplyUtterance = utterance;

                try {
                    synthesis.resume();
                    synthesis.speak(utterance);

                    window.setTimeout(() => {
                        if (
                            userInitiated
                            && activeReplyUtterance === utterance
                            && !synthesis.speaking
                            && !synthesis.pending
                            && status instanceof HTMLElement
                            && introBlockedMessage !== ''
                        ) {
                            status.textContent = introBlockedMessage;
                        }
                    }, 180);
                } catch (error) {
                    if (activeReplyUtterance === utterance) {
                        clearPendingReplyPlayback();
                        activeReplyUtterance = null;
                        setPlayButtonState(false, true);
                    }

                    if (userInitiated && status instanceof HTMLElement && introBlockedMessage !== '') {
                        status.textContent = introBlockedMessage;
                    }
                }
            };

            const waitForSpeechIdleAndStart = (attempt = 0) => {
                const stillBusy = synthesis.speaking || synthesis.pending || synthesis.paused;

                if (!stillBusy || attempt >= 10) {
                    startPlayback();
                    return;
                }

                try {
                    synthesis.cancel();
                    synthesis.resume();
                } catch (error) {
                    // Ignore browser-level speech reset failures.
                }

                replyPlaybackTimeoutId = window.setTimeout(() => {
                    replyPlaybackTimeoutId = null;
                    waitForSpeechIdleAndStart(attempt + 1);
                }, 140);
            };

            if (needsSpeechReset) {
                waitForSpeechIdleAndStart();
                return;
            }

            startPlayback();
        };

        const buildAssistantResponse = ({
            localeBase = pageLocaleBase,
            question = assistantConfig.assistant_name ?? '',
            answer = '',
            source = 'fallback',
            intent = 'default',
        } = {}) => {
            return {
                locale: localeBase,
                speech_locale: speechLocaleMap[localeBase] ?? activeSpeechLocale,
                question,
                answer,
                source,
                intent,
            };
        };

        const buildIntroResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.intro_title ?? assistantConfig.assistant_name ?? '',
            answer: assistantConfig.intro_message ?? '',
            source: 'intro',
            intent: 'intro',
        });

        const buildSupportFallbackResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.assistant_name ?? '',
            answer: assistantConfig.support_fallback ?? '',
            source: 'fallback',
            intent: 'support',
        });

        const buildPlanFallbackResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.assistant_name ?? '',
            answer: assistantConfig.plan_fallback ?? '',
            source: 'fallback',
            intent: 'plans',
        });

        const buildPayoutFallbackResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.assistant_name ?? '',
            answer: assistantConfig.payout_fallback ?? '',
            source: 'fallback',
            intent: 'payout',
        });

        const buildRulesFallbackResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.assistant_name ?? '',
            answer: assistantConfig.rules_fallback ?? '',
            source: 'fallback',
            intent: 'rules',
        });

        const buildCheckoutFallbackResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.assistant_name ?? '',
            answer: assistantConfig.checkout_fallback ?? '',
            source: 'fallback',
            intent: 'checkout',
        });

        const buildDiscountFallbackResponse = (localeBase = pageLocaleBase) => buildAssistantResponse({
            localeBase,
            question: assistantConfig.assistant_name ?? '',
            answer: assistantConfig.discount_fallback ?? '',
            source: 'fallback',
            intent: 'discount',
        });

        const enhanceAssistantResponse = (response) => {
            if (!response) {
                return response;
            }

            const localeBase = normalizeLocaleCode(response.locale) || pageLocaleBase;
            const followups = resolveAssistantConversationProfile(localeBase).followups ?? {};
            let answer = ensureSentenceEnding(response.answer ?? '');

            if (response.source === 'intro') {
                answer = maybeAppendSentence(answer, followups.intro ?? '');
            } else if (response.source === 'fallback') {
                answer = maybeAppendSentence(answer, followups[response.intent] ?? followups.default ?? '');
            }

            return {
                ...response,
                answer,
            };
        };

        const scoreVoiceItem = (item, normalizedQuery, tokens, preferredLocales, intents) => {
            let score = 0;

            if (item.questionNormalized === normalizedQuery) {
                score += 240;
            }

            if (item.questionNormalized.startsWith(normalizedQuery)) {
                score += 140;
            }

            if (item.questionNormalized.includes(normalizedQuery)) {
                score += 110;
            }

            if (item.haystack.includes(normalizedQuery)) {
                score += 80;
            }

            if (preferredLocales[0] === item.localeBase) {
                score += 48;
            } else if (preferredLocales.includes(item.localeBase)) {
                score += 24;
            }

            tokens.forEach((token, index) => {
                if (item.questionNormalized.includes(token)) {
                    score += index === 0 ? 18 : 14;
                }

                if (item.answerNormalized.includes(token)) {
                    score += 10;
                }

                if (item.searchNormalized.includes(token)) {
                    score += 8;
                }
            });

            const meaningfulTokens = tokens.filter((token) => token.length > 2);
            const matchedTokens = meaningfulTokens.filter((token) => item.haystack.includes(token));

            score += matchedTokens.length * 9;

            if (matchedTokens.length >= Math.min(3, meaningfulTokens.length) && matchedTokens.length > 0) {
                score += 18;
            }

            intents.forEach((intent) => {
                assistantIntentProfiles[intent]?.forEach((keyword) => {
                    if (item.haystack.includes(normalizeText(keyword))) {
                        score += 6;
                    }
                });
            });

            return score;
        };

        const findBestResponse = (query) => {
            const normalizedQuery = normalizeText(query);
            const tokens = normalizedQuery.split(/\s+/).filter(Boolean);
            const preferredLocales = getPreferredAssistantLocales(query);
            const intents = detectAssistantIntents(query);

            if (normalizedQuery === '' || (intents.includes('greeting') && tokens.length <= 4)) {
                return buildIntroResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            const rankedMatches = enrichedVoiceIndex
                .map((item) => ({
                    ...item,
                    score: scoreVoiceItem(item, normalizedQuery, tokens, preferredLocales, intents),
                }))
                .sort((left, right) => right.score - left.score);

            const bestMatch = rankedMatches.find((item) => item.score > 0) ?? null;

            if (bestMatch && bestMatch.score >= 24) {
                return {
                    ...bestMatch,
                    source: 'faq',
                    intent: intents[0] ?? 'default',
                };
            }

            if (intents.includes('payout') && String(assistantConfig.payout_fallback ?? '').trim() !== '') {
                return buildPayoutFallbackResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            if (intents.includes('rules') && String(assistantConfig.rules_fallback ?? '').trim() !== '') {
                return buildRulesFallbackResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            if (intents.includes('plans') && String(assistantConfig.plan_fallback ?? '').trim() !== '') {
                return buildPlanFallbackResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            if ((intents.includes('checkout') || intents.includes('login')) && String(assistantConfig.checkout_fallback ?? '').trim() !== '') {
                return buildCheckoutFallbackResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            if (intents.includes('discount') && String(assistantConfig.discount_fallback ?? '').trim() !== '') {
                return buildDiscountFallbackResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            if ((intents.includes('support') || intents.includes('login')) && String(assistantConfig.support_fallback ?? '').trim() !== '') {
                return buildSupportFallbackResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            return null;
        };

        const presentResponse = (
            response,
            {
                autoSpeak = true,
                userInitiated = false,
                statusMessage = status?.dataset.readyMessage ?? status?.textContent ?? '',
            } = {},
        ) => {
            if (!(answerQuestion instanceof HTMLElement) || !(answerText instanceof HTMLElement)) {
                return;
            }

            if (!response) {
                currentAnswerText = '';
                queuedReplyPlayback = null;
                stopAnswerReveal({
                    clear: true,
                });
                animateAnswerQuestion(input instanceof HTMLInputElement ? input.value : '');
                setPlayButtonState(false, false);
                setRenderingState(false);
                revealSuggestionPrompts();

                if (status instanceof HTMLElement) {
                    status.textContent = status.dataset.noMatchMessage ?? status.textContent ?? '';
                }

                return;
            }

            const enhancedResponse = enhanceAssistantResponse(response);

            animateAnswerQuestion(enhancedResponse.question ?? '');
            revealAnswerText(enhancedResponse.answer ?? '');
            currentAnswerText = enhancedResponse.answer ?? '';
            setRenderingState(true, {
                settleAfter: prefersReducedMotion() ? 0 : 640,
            });
            revealSuggestionPrompts({
                reset: enhancedResponse.source === 'intro',
            });

            const responseSpeechLocale = setActiveSpeechLocale(resolveItemSpeechLocale(enhancedResponse));
            currentAnswerSpeechLocale = responseSpeechLocale;
            setPlayButtonState(false, currentAnswerText.trim() !== '');

            if (status instanceof HTMLElement && statusMessage !== '') {
                status.textContent = statusMessage;
            }

            if (!autoSpeak || !('speechSynthesis' in window)) {
                queuedReplyPlayback = null;
                return;
            }

            if (isListening) {
                queuedReplyPlayback = {
                    text: currentAnswerText,
                    locale: responseSpeechLocale,
                    userInitiated,
                };
                return;
            }

            queuedReplyPlayback = null;
            speakAnswer(currentAnswerText, responseSpeechLocale, { userInitiated });
        };

        const renderAnswer = (
            query,
            {
                autoSpeak = true,
                userInitiated = false,
            } = {},
        ) => {
            const normalizedQuery = normalizeText(query);

            if (normalizedQuery !== '') {
                hasConversation = true;
            }

            presentResponse(findBestResponse(query), {
                autoSpeak,
                userInitiated,
            });
        };

        const activateIntro = ({
            userInitiated = false,
            force = false,
        } = {}) => {
            if (!force && (hasConversation || hasPlayedIntro) && currentAnswerText.trim() !== '') {
                revealSuggestionPrompts();
                focusInput();
                return;
            }

            hasPlayedIntro = true;
            resetSuggestionPrompts();
            presentResponse(buildIntroResponse(getPreferredAssistantLocales(input instanceof HTMLInputElement ? input.value : '')[0] ?? pageLocaleBase), {
                autoSpeak: userInitiated,
                userInitiated,
                statusMessage: status?.dataset.readyMessage ?? status?.textContent ?? '',
            });
            focusInput();
        };

        let cleanupVoiceSession = () => {
            cancelSpokenReply();
        };

        if (status instanceof HTMLElement) {
            status.dataset.emptyState = status.dataset.readyMessage ?? status.textContent ?? '';
        }

        setPlayButtonState(false, false);

        if (submitButton instanceof HTMLButtonElement && input instanceof HTMLInputElement) {
            submitButton.addEventListener('click', () => {
                renderAnswer(input.value, {
                    userInitiated: true,
                });
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    renderAnswer(input.value, {
                        userInitiated: true,
                    });
                }
            });

            input.addEventListener('focus', () => {
                revealSuggestionPrompts();
            });
        }

        suggestionButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement) || !(input instanceof HTMLInputElement)) {
                return;
            }

            button.addEventListener('click', () => {
                const nextQuestion = button.dataset.question?.trim() ?? button.textContent?.trim() ?? '';

                if (nextQuestion === '') {
                    return;
                }

                input.value = nextQuestion;
                renderAnswer(nextQuestion, {
                    userInitiated: true,
                });
                focusInput();
            });
        });

        if (playButton instanceof HTMLButtonElement) {
            if (!('speechSynthesis' in window)) {
                playButton.disabled = true;
                playButton.classList.add('opacity-60', 'cursor-not-allowed');
            } else {
                playButton.addEventListener('click', () => {
                    if (isSpeakingReply) {
                        cancelSpokenReply();
                        return;
                    }

                    if (currentAnswerText.trim() === '') {
                        if (status instanceof HTMLElement) {
                            status.textContent = playButton.dataset.emptyMessage ?? status.textContent ?? '';
                        }

                        focusInput();
                        return;
                    }

                    speakAnswer(currentAnswerText, currentAnswerSpeechLocale, {
                        userInitiated: true,
                    });
                });
            }
        }

        if (!(SpeechRecognition instanceof Function) || !hasSecureVoiceContext) {
            if (micButton instanceof HTMLButtonElement) {
                micButton.disabled = true;
                micButton.classList.add('opacity-60', 'cursor-not-allowed');
            }

            if (status instanceof HTMLElement) {
                status.textContent = hasSecureVoiceContext
                    ? (micButton?.dataset.unsupported ?? status.textContent ?? '')
                    : (micButton?.dataset.secureContext ?? status.textContent ?? '');
            }
        } else if (micButton instanceof HTMLButtonElement && input instanceof HTMLInputElement) {
            recognition = new SpeechRecognition();
            recognition.lang = activeSpeechLocale;
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            let pendingStatusMessage = status?.dataset.emptyState ?? status?.textContent ?? '';

            const setMicState = (listening) => {
                isListening = listening;
                micButton.textContent = listening
                    ? (micButton.dataset.stopLabel ?? micButton.textContent ?? '')
                    : (micButton.dataset.startLabel ?? micButton.textContent ?? '');
                micButton.classList.toggle('border-amber-400/35', listening);
                micButton.classList.toggle('bg-amber-400/10', listening);
                micButton.classList.toggle('text-amber-50', listening);
                micButton.classList.toggle('is-active', listening);
                syncAssistantVisualState();
            };

            const setStatusMessage = (message) => {
                if (status instanceof HTMLElement) {
                    status.textContent = message;
                }
            };

            const stopVoiceSession = (message = micButton.dataset.stopped ?? status?.dataset.emptyState ?? '') => {
                pendingStatusMessage = message;
                cancelSpokenReply();

                if (!isListening) {
                    setMicState(false);
                    setStatusMessage(message);
                    return;
                }

                try {
                    recognition.abort();
                } catch (error) {
                    setMicState(false);
                    setStatusMessage(message);
                }
            };

            cleanupVoiceSession = () => {
                stopVoiceSession(status?.dataset.emptyState ?? micButton.dataset.stopped ?? '');
            };

            const resolveErrorMessage = (errorType) => {
                switch (errorType) {
                    case 'not-allowed':
                    case 'service-not-allowed':
                        return micButton.dataset.micBlocked ?? status?.dataset.emptyState ?? '';
                    case 'audio-capture':
                        return micButton.dataset.audioCapture ?? status?.dataset.emptyState ?? '';
                    case 'no-speech':
                        return micButton.dataset.noSpeech ?? status?.dataset.emptyState ?? '';
                    case 'aborted':
                        return micButton.dataset.stopped ?? status?.dataset.emptyState ?? '';
                    default:
                        return status?.dataset.emptyState ?? status?.textContent ?? '';
                }
            };

            micButton.addEventListener('click', async () => {
                if (isListening) {
                    stopVoiceSession();
                    return;
                }

                pendingStatusMessage = status?.dataset.emptyState ?? status?.textContent ?? '';
                setStatusMessage(micButton.dataset.permissionChecking ?? status?.textContent ?? '');

                const permissionState = await checkMicrophonePermission();

                if (permissionState === 'denied') {
                    setMicState(false);
                    setStatusMessage(micButton.dataset.micBlocked ?? status?.dataset.emptyState ?? '');
                    return;
                }

                const access = await requestMicrophoneAccess();

                if (!access.ok) {
                    setMicState(false);
                    setStatusMessage(access.message || status?.dataset.emptyState || '');
                    return;
                }

                pendingStatusMessage = status?.dataset.emptyState ?? status?.textContent ?? '';

                try {
                    recognition.lang = activeSpeechLocale;
                    recognition.start();
                } catch (error) {
                    setMicState(false);
                    setStatusMessage(micButton.dataset.micBlocked ?? status?.dataset.emptyState ?? '');
                }
            });

            recognition.addEventListener('start', () => {
                setMicState(true);
                setStatusMessage(micButton.dataset.listening ?? status?.textContent ?? '');
            });

            recognition.addEventListener('result', (event) => {
                const transcript = event.results?.[0]?.[0]?.transcript ?? '';
                input.value = transcript;
                pendingStatusMessage = status?.dataset.emptyState ?? status?.textContent ?? '';
                renderAnswer(transcript, {
                    userInitiated: true,
                });
            });

            recognition.addEventListener('error', (event) => {
                pendingStatusMessage = resolveErrorMessage(event.error);
            });

            recognition.addEventListener('end', () => {
                setMicState(false);
                setStatusMessage(pendingStatusMessage);

                if (queuedReplyPlayback && 'speechSynthesis' in window) {
                    const queuedPlayback = queuedReplyPlayback;
                    queuedReplyPlayback = null;
                    replyPlaybackTimeoutId = window.setTimeout(() => {
                        replyPlaybackTimeoutId = null;
                        speakAnswer(queuedPlayback.text, queuedPlayback.locale, {
                            userInitiated: queuedPlayback.userInitiated,
                        });
                    }, 180);
                }
            });

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    cleanupVoiceSession();
                }
            });

            window.addEventListener('pagehide', cleanupVoiceSession);
            window.addEventListener('beforeunload', cleanupVoiceSession);

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && isListening) {
                    stopVoiceSession();
                }
            });
        }

        const initialQuestion = voiceAssistant.dataset.initialQuestion?.trim() || (input instanceof HTMLInputElement ? input.value.trim() : '');

        if (initialQuestion !== '') {
            if (input instanceof HTMLInputElement) {
                input.value = initialQuestion;
            }

            renderAnswer(initialQuestion, {
                autoSpeak: false,
            });
        } else if (!(modalRoot instanceof HTMLElement)) {
            revealSuggestionPrompts({
                immediate: prefersReducedMotion(),
            });
        }

        syncAssistantVisualState();

        voiceAssistant.__wolfiController = {
            activateIntro,
            focusInput,
            cleanup: () => {
                cancelSpokenReply();
                cleanupVoiceSession();
                resetAssistantSurface();
                syncAssistantVisualState();
            },
        };
    });

    const wolfiModal = document.querySelector('[data-wolfi-modal]');
    const wolfiLaunchButtons = document.querySelectorAll('[data-wolfi-launch]');

    if (wolfiModal instanceof HTMLElement) {
        const closeButtons = wolfiModal.querySelectorAll('[data-wolfi-close]');
        const modalAssistant = wolfiModal.querySelector('[data-voice-assistant]');
        const modalController = modalAssistant?.__wolfiController ?? null;
        const wolfiAvatarShell = wolfiModal.querySelector('.wolfi-avatar-shell');
        const wolfiAvatarVideo = wolfiModal.querySelector('[data-wolfi-avatar-video]');
        const modalMotionQuery = typeof window.matchMedia === 'function'
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        let modalIsOpen = false;
        let modalHideTimeoutId = null;
        let lastWolfiTrigger = null;

        const prefersReducedMotion = () => modalMotionQuery?.matches ?? false;
        const getWolfiTransitionDuration = () => (prefersReducedMotion() ? 20 : 360);

        const clearWolfiModalHideTimeout = () => {
            if (modalHideTimeoutId !== null) {
                window.clearTimeout(modalHideTimeoutId);
                modalHideTimeoutId = null;
            }
        };

        const syncWolfiAvatarPlayback = ({
            active = modalIsOpen && (
                wolfiModal.classList.contains('is-speaking')
                || wolfiModal.classList.contains('is-listening')
                || wolfiModal.classList.contains('is-rendering')
            ),
        } = {}) => {
            if (!(wolfiAvatarVideo instanceof HTMLVideoElement)) {
                return;
            }

            if (!modalIsOpen || !active) {
                wolfiAvatarShell?.classList.remove('is-video-playing');
                wolfiAvatarVideo.pause();

                if (wolfiAvatarVideo.currentTime > 0.01) {
                    try {
                        wolfiAvatarVideo.currentTime = 0;
                    } catch (error) {
                        // Ignore seek failures while resetting the idle frame.
                    }
                }

                return;
            }

            wolfiAvatarVideo.muted = true;
            wolfiAvatarShell?.classList.add('is-video-playing');

            const playPromise = wolfiAvatarVideo.play();

            if (playPromise instanceof Promise) {
                playPromise.catch(() => {
                    wolfiAvatarShell?.classList.remove('is-video-playing');
                });
            }
        };

        const syncWolfiLaunchButtons = ({
            open = modalIsOpen,
            speaking = wolfiModal.classList.contains('is-speaking'),
            listening = wolfiModal.classList.contains('is-listening'),
        } = {}) => {
            wolfiLaunchButtons.forEach((button) => {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                button.classList.toggle('is-open', open);
                button.classList.toggle('is-speaking', open && speaking);
                button.classList.toggle('is-listening', open && listening);
            });
        };

        const scheduleWolfiModalHide = () => {
            clearWolfiModalHideTimeout();

            modalHideTimeoutId = window.setTimeout(() => {
                modalHideTimeoutId = null;

                if (modalIsOpen) {
                    return;
                }

                wolfiModal.classList.add('hidden');
                wolfiModal.classList.remove('flex', 'is-mounted', 'is-speaking', 'is-listening', 'is-rendering');
            }, getWolfiTransitionDuration());
        };

        const setWolfiModalState = (open, { userInitiated = false, triggerButton = null } = {}) => {
            if (open && modalIsOpen) {
                modalController?.focusInput?.();
                return;
            }

            if (!open && !modalIsOpen) {
                return;
            }

            if (open) {
                modalIsOpen = true;
                clearWolfiModalHideTimeout();

                if (triggerButton instanceof HTMLElement) {
                    lastWolfiTrigger = triggerButton;
                }

                wolfiModal.classList.remove('hidden');
                wolfiModal.classList.add('flex', 'is-mounted');
                wolfiModal.setAttribute('aria-hidden', 'false');
                syncWolfiLaunchButtons({
                    open: true,
                    speaking: false,
                    listening: false,
                });

                window.requestAnimationFrame(() => {
                    wolfiModal.classList.add('is-open');
                    syncWolfiAvatarPlayback({
                        active: true,
                    });
                });

                modalController?.activateIntro({
                    userInitiated,
                    force: true,
                });
                modalController?.focusInput?.();
                return;
            }

            modalIsOpen = false;
            wolfiModal.setAttribute('aria-hidden', 'true');
            wolfiModal.classList.remove('is-open');
            modalController?.cleanup?.();
            syncWolfiLaunchButtons({
                open: false,
                speaking: false,
                listening: false,
            });
            syncWolfiAvatarPlayback({
                active: false,
            });
            scheduleWolfiModalHide();

            if (lastWolfiTrigger instanceof HTMLElement) {
                const triggerToFocus = lastWolfiTrigger;
                window.setTimeout(() => {
                    triggerToFocus.focus();
                }, prefersReducedMotion() ? 0 : 140);
            }
        };

        wolfiModal.addEventListener('wolfi:statechange', (event) => {
            syncWolfiLaunchButtons({
                open: modalIsOpen,
                speaking: Boolean(event.detail?.speaking),
                listening: Boolean(event.detail?.listening),
            });
            syncWolfiAvatarPlayback({
                active: Boolean(event.detail?.speaking)
                    || Boolean(event.detail?.listening)
                    || Boolean(event.detail?.rendering),
            });
        });

        wolfiLaunchButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                setWolfiModalState(true, {
                    userInitiated: true,
                    triggerButton: button,
                });
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setWolfiModalState(false);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modalIsOpen) {
                setWolfiModalState(false);
            }
        });
    }

    document.querySelectorAll('[data-flash]').forEach((flash) => {
        window.setTimeout(() => {
            flash.classList.add('opacity-0', 'translate-y-2');
        }, 3600);
    });
});
