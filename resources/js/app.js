import './bootstrap';
import { initWolfiDashboard } from './wolfi-dashboard';

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
            tokens: ['hello', 'hi', 'hey', 'when', 'what', 'how', 'can', 'payout', 'withdraw', 'rules', 'challenge', 'funded', 'account', 'support', 'login', 'checkout', 'discount', 'plan', 'drawdown', 'news', 'trial', 'demo'],
        },
        es: {
            regex: /[¿¡ñáéíóú]|\b(hola|cuando|como|puedo|retiro|payout|regla|desafio|challenge|cuenta|funded|soporte|inicio|descuento|checkout)\b/i,
            tokens: ['hola', 'cuando', 'como', 'puedo', 'retiro', 'payout', 'regla', 'desafio', 'challenge', 'cuenta', 'funded', 'soporte', 'iniciar', 'descuento', 'checkout', 'plan', 'drawdown', 'noticias', 'prueba', 'demo'],
        },
        fr: {
            regex: /[àâçéèêëîïôùûüÿœæ]|\b(bonjour|quand|comment|puis|paiement|retrait|regle|challenge|compte|support|connexion|remise)\b/i,
            tokens: ['bonjour', 'quand', 'comment', 'puis', 'paiement', 'retrait', 'regle', 'challenge', 'compte', 'support', 'connexion', 'remise', 'plan', 'drawdown', 'actualites', 'essai', 'demo'],
        },
        de: {
            regex: /[äöüß]|\b(hallo|wann|wie|kann|auszahlung|regel|challenge|konto|support|login|rabatt|checkout)\b/i,
            tokens: ['hallo', 'wann', 'wie', 'kann', 'auszahlung', 'regel', 'challenge', 'konto', 'support', 'login', 'rabatt', 'checkout', 'plan', 'drawdown', 'nachrichten', 'test', 'demo'],
        },
        hi: {
            regex: /[\u0900-\u097F]|\b(namaste|kab|kaise|madad|niyam|challenge|khata|account|support|login|discount|payout)\b/i,
            tokens: ['namaste', 'kab', 'kaise', 'madad', 'niyam', 'challenge', 'khata', 'account', 'support', 'login', 'discount', 'payout', 'plan', 'drawdown', 'news', 'trial', 'demo'],
        },
        it: {
            regex: /[àèéìíîòóù]|\b(ciao|quando|come|posso|pagamento|prelievo|regola|sfida|challenge|conto|supporto|accesso|sconto|checkout)\b/i,
            tokens: ['ciao', 'quando', 'come', 'posso', 'pagamento', 'prelievo', 'regola', 'sfida', 'challenge', 'conto', 'supporto', 'accesso', 'sconto', 'checkout', 'piano', 'drawdown', 'notizie', 'prova', 'demo'],
        },
        pt: {
            regex: /[ãõáàâéêíóôúç]|\b(ola|olá|quando|como|posso|pagamento|saque|regra|desafio|challenge|conta|suporte|login|desconto|checkout)\b/i,
            tokens: ['ola', 'olá', 'quando', 'como', 'posso', 'pagamento', 'saque', 'regra', 'desafio', 'challenge', 'conta', 'suporte', 'login', 'desconto', 'checkout', 'plano', 'drawdown', 'noticias', 'notícias', 'teste', 'demo'],
        },
    };
    const assistantIntentProfiles = {
        greeting: ['hello', 'hi', 'hey', 'hola', 'bonjour', 'salut', 'hallo', 'namaste', 'ciao', 'ola', 'olá'],
        support: ['support', 'help', 'contact', 'email', 'live chat', 'ticket', 'customer service', 'ayuda', 'soporte', 'aide', 'hilfe', 'kontakt', 'billing', 'refund', 'madad', 'supporto', 'suporte', 'contatto', 'contato'],
        login: ['login', 'log in', 'sign in', 'signin', 'password', 'register', 'sign up', 'account', 'google', 'facebook', 'apple', 'contraseña', 'passwort', 'mot de passe', 'anmelden', 'registro', 'registrarse', 'connexion', 'accedi', 'acceso', 'cadastro', 'senha', 'khata'],
        trial: ['trial', 'free trial', 'free demo', 'demo', 'demo account', 'practice account', 'trial access', 'demo login', 'essai', 'demo gratuit', 'prueba', 'prueba gratis', 'demokonto', 'testkonto', 'prova gratuita', 'teste gratis', 'teste grátis', 'muft trial', 'free test'],
        payout: ['payout', 'get paid', 'paid', 'cash out', 'withdraw', 'withdrawal', 'withdraw money', 'profit split', 'payment', 'processing time', 'retiro', 'retirar', 'retrait', 'auszahlung', 'zahlung', 'primer payout', 'first payout', 'first withdrawal', 'saque', 'pagamento', 'prelievo', 'pagamento del profitto'],
        rules: ['rule', 'rules', 'drawdown', 'loss', 'daily loss', 'max daily loss', 'max total loss', 'consistency', 'news', 'news trading', 'stop loss', 'trading days', 'regla', 'regeln', 'verlust', 'regel', 'nachrichten', 'noticias', 'nouvelles', 'daily drawdown', 'max loss', 'niyam', 'regola', 'regole', 'regra', 'regras'],
        plans: ['plan', 'challenge', 'account size', 'size', 'model', 'which challenge', 'which plan', 'best plan', 'difference', 'compare', 'single phase', 'funded account', 'one step', 'two step', '1-step', '2-step', 'desafio', 'cuenta', 'konto', 'compte', 'funded', 'phase', 'fase', 'phase 1', 'phase 2', 'piano', 'sfida', 'plano', 'khata size'],
        checkout: ['checkout', 'buy', 'purchase', 'order', 'pay', 'payment method', 'stripe', 'paypal', 'plan kaufen', 'comprar', 'pedido', 'commande', 'acquista', 'ordine', 'pagare', 'comprar agora', 'pagamento online'],
        discount: ['discount', 'promo', 'promo code', 'launch code', 'launch offer', 'coupon', 'rabatt', 'descuento', 'remise', 'sconto', 'desconto', 'codice promo', 'coupon code'],
    };
    const assistantNoiseWords = new Set([
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'can', 'do', 'for', 'from', 'help', 'how', 'i', 'if', 'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'please', 'the', 'this', 'to', 'we', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your',
        'al', 'algo', 'como', 'con', 'cual', 'cuales', 'de', 'del', 'donde', 'el', 'en', 'es', 'esta', 'estoy', 'hola', 'la', 'las', 'lo', 'los', 'me', 'mi', 'para', 'por', 'puedo', 'que', 'se', 'si', 'tu', 'una', 'uno', 'y',
        'aber', 'auch', 'bei', 'bin', 'bitte', 'das', 'dass', 'dein', 'dem', 'den', 'der', 'die', 'du', 'ein', 'eine', 'einer', 'eines', 'es', 'fuer', 'für', 'ich', 'im', 'ist', 'mit', 'oder', 'und', 'was', 'wenn', 'wie', 'wo', 'zu',
        'alors', 'avec', 'bonjour', 'comment', 'dans', 'de', 'des', 'du', 'est', 'et', 'je', 'la', 'le', 'les', 'mon', 'ou', 'pour', 'pouvez', 'puis', 'que', 'quoi', 'si', 'sur', 'une', 'vous',
        'aur', 'bhi', 'hai', 'ho', 'ka', 'ke', 'ki', 'ko', 'kya', 'main', 'mera', 'meri', 'mujhe', 'par', 'se', 'ye', 'आप', 'और', 'का', 'के', 'की', 'को', 'क्या', 'मुझे', 'में', 'यह', 'से', 'है',
        'anche', 'che', 'chi', 'come', 'con', 'cosa', 'dei', 'del', 'della', 'delle', 'dello', 'di', 'e', 'gli', 'il', 'in', 'io', 'la', 'le', 'mi', 'per', 'puoi', 'puo', 'può', 'se', 'su', 'un', 'una',
        'a', 'as', 'com', 'como', 'da', 'das', 'de', 'do', 'dos', 'e', 'em', 'esse', 'esta', 'eu', 'me', 'minha', 'no', 'nos', 'nós', 'o', 'os', 'para', 'por', 'posso', 'que', 'se', 'um', 'uma', 'voces', 'vocês',
    ].map(normalizeText));
    const assistantFollowupMarkers = [
        'also',
        'and',
        'and for',
        'and if',
        'how about',
        'plus',
        'what about',
        'what if',
        'ademas',
        'además',
        'tambien',
        'también',
        'y',
        'y para',
        'y si',
        'auch',
        'und',
        'und wenn',
        'wie sieht es mit',
        'aussi',
        'et',
        'et pour',
        'et si',
        'aur',
        'aur agar',
        'bhi',
        'और',
        'और अगर',
        'anche',
        'e',
        'e per',
        'e se',
        'tambem',
        'também',
        'e',
        'e para',
        'e se',
    ].map(normalizeText);
    const assistantConversationProfiles = {
        en: {
            followups: {
                intro: 'Ask me about payouts, rules, or the best next step for your account.',
                default: 'If you want, I can also break that down step by step.',
                support: 'If you want, I can point you to the fastest support option.',
                trial: 'If you want, I can guide you into the free demo flow.',
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
                trial: 'Si quieres, te guio para entrar al flujo de demo gratis.',
                plans: 'Si quieres, te comparo el modelo 1-Step con el 2-Step.',
                payout: 'Si quieres, tambien te explico el calendario y que pasa despues de enviar la solicitud.',
                rules: 'Si quieres, te resumo las reglas en una checklist rapida.',
                checkout: 'Si quieres, te guio por el checkout paso a paso.',
                discount: 'Si quieres, te digo exactamente donde aplicar el codigo.',
            },
        },
        de: {
            followups: {
                intro: 'Frag mich nach Auszahlungen, Regeln oder dem besten nächsten Schritt für dein Konto.',
                default: 'Wenn du willst, erkläre ich dir das auch Schritt für Schritt.',
                support: 'Wenn du willst, zeige ich dir den schnellsten Weg zum Support.',
                trial: 'Wenn du willst, führe ich dich direkt in den kostenlosen Demoablauf.',
                plans: 'Wenn du willst, vergleiche ich 1-Step und 2-Step direkt für dich.',
                payout: 'Wenn du willst, erkläre ich dir auch den Zeitplan und was nach deiner Anfrage passiert.',
                rules: 'Wenn du willst, fasse ich dir die Regeln als kurze Checkliste zusammen.',
                checkout: 'Wenn du willst, führe ich dich Schritt für Schritt durch den Checkout.',
                discount: 'Wenn du willst, zeige ich dir genau, wo du den Code eingibst.',
            },
        },
        fr: {
            followups: {
                intro: 'Demandez-moi les payouts, les regles ou le meilleur prochain pas pour votre compte.',
                default: 'Si vous voulez, je peux aussi vous l expliquer etape par etape.',
                support: 'Si vous voulez, je peux vous orienter vers l option de support la plus rapide.',
                trial: 'Si vous voulez, je peux vous guider vers le parcours de demo gratuit.',
                plans: 'Si vous voulez, je peux comparer pour vous les modeles 1-Step et 2-Step.',
                payout: 'Si vous voulez, je peux aussi expliquer le timing et ce qui se passe apres votre demande.',
                rules: 'Si vous voulez, je peux resumer les regles sous forme de checklist rapide.',
                checkout: 'Si vous voulez, je peux vous guider dans le checkout etape par etape.',
                discount: 'Si vous voulez, je peux vous dire exactement ou appliquer le code.',
            },
        },
        hi: {
            followups: {
                intro: 'मुझसे पेआउट, नियमों या आपके अकाउंट के अगले सही कदम के बारे में पूछिए।',
                default: 'अगर आप चाहें, तो मैं इसे चरण दर चरण समझा सकता हूँ।',
                support: 'अगर आप चाहें, तो मैं आपको सबसे तेज़ सपोर्ट विकल्प तक पहुँचा सकता हूँ।',
                trial: 'अगर आप चाहें, तो मैं आपको मुफ्त डेमो फ्लो में ले चल सकता हूँ।',
                plans: 'अगर आप चाहें, तो मैं आपके लिए 1-Step और 2-Step मॉडल की तुलना कर सकता हूँ।',
                payout: 'अगर आप चाहें, तो मैं समय-सीमा और अनुरोध भेजने के बाद क्या होता है, यह भी समझा सकता हूँ।',
                rules: 'अगर आप चाहें, तो मैं नियमों को एक छोटी चेकलिस्ट में बदल सकता हूँ।',
                checkout: 'अगर आप चाहें, तो मैं आपको चेकआउट प्रक्रिया चरण दर चरण समझा सकता हूँ।',
                discount: 'अगर आप चाहें, तो मैं आपको ठीक बता सकता हूँ कि कोड कहाँ लागू करना है।',
            },
        },
        it: {
            followups: {
                intro: 'Chiedimi di payout, regole o del miglior prossimo passo per il tuo account.',
                default: 'Se vuoi, posso anche spiegartelo passo dopo passo.',
                support: 'Se vuoi, posso indicarti l’opzione di supporto più rapida.',
                trial: 'Se vuoi, posso guidarti nel flusso demo gratuito.',
                plans: 'Se vuoi, posso confrontare per te i modelli 1-Step e 2-Step.',
                payout: 'Se vuoi, posso spiegarti anche le tempistiche e cosa succede dopo aver inviato la richiesta.',
                rules: 'Se vuoi, posso trasformare le regole in una checklist rapida.',
                checkout: 'Se vuoi, posso guidarti nel checkout passo dopo passo.',
                discount: 'Se vuoi, posso dirti esattamente dove applicare il codice.',
            },
        },
        pt: {
            followups: {
                intro: 'Pergunte-me sobre payouts, regras ou qual é o melhor próximo passo para a sua conta.',
                default: 'Se quiser, também posso explicar isso passo a passo.',
                support: 'Se quiser, posso indicar a opção de suporte mais rápida.',
                trial: 'Se quiser, posso guiá-lo no fluxo de demo gratuito.',
                plans: 'Se quiser, posso comparar os modelos 1-Step e 2-Step para si.',
                payout: 'Se quiser, também posso explicar os prazos e o que acontece depois de enviar o pedido.',
                rules: 'Se quiser, posso transformar as regras numa checklist rápida.',
                checkout: 'Se quiser, posso guiá-lo pelo checkout passo a passo.',
                discount: 'Se quiser, posso mostrar exatamente onde aplicar o código.',
            },
        },
    };
    const assistantSpeechProfiles = {
        en: {
            rate: 0.93,
            pitch: 0.8,
            volume: 1,
            maleVoices: ['male', 'guy', 'davis', 'daniel', 'alex', 'arthur', 'thomas', 'tom', 'fred', 'gordon', 'nathan', 'oliver', 'matthew', 'michael', 'aaron', 'david'],
            femaleVoices: ['female', 'aria', 'jenny', 'samantha', 'moira', 'serena', 'allison', 'ava', 'sofia', 'anna', 'eva'],
            preferredVoices: ['arthur', 'oliver', 'thomas', 'alex', 'matthew'],
        },
        es: {
            rate: 0.96,
            pitch: 0.84,
            volume: 1,
            maleVoices: ['male', 'jorge', 'diego', 'carlos', 'enrique', 'raul', 'pablo', 'antonio', 'alvaro', 'juan', 'jose', 'miguel', 'javier', 'felipe', 'alejandro', 'andres', 'mateo', 'nicolas', 'sebastian', 'sergio', 'tomas', 'bruno', 'martin', 'gael', 'eddy', 'reed', 'rocko', 'grandpa'],
            femaleVoices: ['female', 'monica', 'paulina', 'helena', 'sofia', 'lucia', 'maria', 'paloma', 'conchita', 'carmen', 'marisol', 'isabela', 'elvira', 'flo', 'grandma', 'sandy', 'shelley'],
            preferredVoices: ['diego', 'jorge', 'alvaro', 'carlos', 'sebastian', 'alejandro', 'andres', 'javier', 'nicolas', 'mateo', 'eddy', 'rocko', 'reed', 'grandpa'],
            avoidVoices: ['monica', 'paulina', 'paloma', 'sofia', 'lucia', 'maria', 'conchita', 'carmen', 'marisol', 'flo', 'grandma', 'sandy', 'shelley'],
        },
        de: {
            rate: 0.94,
            pitch: 0.82,
            volume: 1,
            maleVoices: ['male', 'daniel', 'markus', 'hans', 'florian', 'stefan', 'klaus', 'jonas', 'lukas', 'felix', 'johann', 'moritz', 'benjamin', 'eddy', 'reed', 'rocko', 'grandpa'],
            femaleVoices: ['female', 'anna', 'petra', 'helena', 'sabina', 'eva', 'vicki', 'katja', 'susi', 'flo', 'grandma', 'sandy', 'shelley'],
            preferredVoices: ['daniel', 'markus', 'florian', 'stefan', 'jonas', 'lukas', 'felix', 'eddy', 'rocko', 'reed', 'grandpa'],
            avoidVoices: ['anna', 'petra', 'helena', 'sabina', 'eva', 'vicki', 'katja', 'susi', 'flo', 'grandma', 'sandy', 'shelley'],
        },
        fr: {
            rate: 0.94,
            pitch: 0.8,
            volume: 1,
            maleVoices: ['male', 'thomas', 'daniel', 'henri', 'alexandre', 'antoine', 'nicolas', 'paul', 'remy', 'yannick'],
            femaleVoices: ['female', 'amelie', 'aurelie', 'virginie', 'marie', 'julie'],
            preferredVoices: ['thomas', 'alexandre', 'antoine', 'nicolas', 'henri'],
        },
        hi: {
            rate: 0.92,
            pitch: 0.82,
            volume: 1,
            maleVoices: ['male', 'aditya', 'arjun', 'rahul', 'rohan', 'aman', 'vivek', 'karan', 'raj', 'amit'],
            femaleVoices: ['female', 'ananya', 'priya', 'neha', 'kavya', 'isha'],
            preferredVoices: ['arjun', 'rahul', 'vivek', 'rohan', 'aditya'],
        },
        it: {
            rate: 0.95,
            pitch: 0.82,
            volume: 1,
            maleVoices: ['male', 'luca', 'marco', 'matteo', 'andrea', 'alessandro', 'davide', 'riccardo', 'simone', 'paolo'],
            femaleVoices: ['female', 'giulia', 'sofia', 'chiara', 'martina', 'sara'],
            preferredVoices: ['luca', 'marco', 'matteo', 'alessandro', 'davide'],
        },
        pt: {
            rate: 0.95,
            pitch: 0.82,
            volume: 1,
            maleVoices: ['male', 'joao', 'joão', 'miguel', 'tiago', 'andre', 'andré', 'rafael', 'diogo', 'pedro', 'bruno'],
            femaleVoices: ['female', 'ana', 'ines', 'inês', 'beatriz', 'maria', 'sofia'],
            preferredVoices: ['miguel', 'tiago', 'joao', 'joão', 'andré'],
        },
    };
    const assistantSpeechCleanupProfiles = {
        en: {
            andWord: 'and',
            percentWord: 'percent',
            aroundClock: 'twenty four seven',
            oneStep: 'one step',
            twoStep: 'two step',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
        es: {
            andWord: 'y',
            percentWord: 'por ciento',
            aroundClock: 'veinticuatro siete',
            oneStep: 'uno step',
            twoStep: 'dos step',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
        de: {
            andWord: 'und',
            percentWord: 'Prozent',
            aroundClock: 'vierundzwanzig sieben',
            oneStep: 'eins step',
            twoStep: 'zwei step',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
        fr: {
            andWord: 'et',
            percentWord: 'pour cent',
            aroundClock: 'vingt quatre sur sept',
            oneStep: 'un step',
            twoStep: 'deux step',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
        hi: {
            andWord: 'और',
            percentWord: 'प्रतिशत',
            aroundClock: 'चौबीस सात',
            oneStep: 'वन स्टेप',
            twoStep: 'टू स्टेप',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
        it: {
            andWord: 'e',
            percentWord: 'percento',
            aroundClock: 'ventiquattro su sette',
            oneStep: 'uno step',
            twoStep: 'due step',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
        pt: {
            andWord: 'e',
            percentWord: 'por cento',
            aroundClock: 'vinte e quatro sete',
            oneStep: 'um step',
            twoStep: 'dois step',
            cTrader: 'C Trader',
            isoLabel: 'I S O I E C 27001',
        },
    };
    const resolveAssistantConversationProfile = (locale) => assistantConversationProfiles[normalizeLocaleCode(locale)] ?? assistantConversationProfiles.en;
    const resolveAssistantSpeechProfile = (locale) => assistantSpeechProfiles[normalizeLocaleCode(locale)] ?? assistantSpeechProfiles.en;
    const getVoiceMatchKey = (voice) => normalizeText(`${voice?.name ?? ''} ${voice?.voiceURI ?? ''}`);
    const voiceMatchesKeywords = (voiceKey, keywords = []) => keywords.some((keyword) => voiceKey.includes(normalizeText(keyword)));
    const isNaturalVoiceKey = (voiceKey) => ['natural', 'neural', 'online', 'premium', 'enhanced'].some((keyword) => voiceKey.includes(keyword));
    const prepareAnswerTextForSpeech = (text, locale) => {
        const localeBase = normalizeLocaleCode(locale);
        const profile = assistantSpeechCleanupProfiles[localeBase] ?? assistantSpeechCleanupProfiles.en;

        return String(text ?? '')
            .replace(/\s*\/\s*/g, ', ')
            .replace(/\s*&\s*/g, ` ${profile.andWord} `)
            .replace(/24\s*\/\s*7/gi, profile.aroundClock)
            .replace(/\b1[\s-]*step\b/gi, profile.oneStep)
            .replace(/\b2[\s-]*step\b/gi, profile.twoStep)
            .replace(/\bcTrader\b/gi, profile.cTrader)
            .replace(/ISO\s*\/\s*IEC\s*27001/gi, profile.isoLabel)
            .replace(/(\d+)\s*%/g, `$1 ${profile.percentWord}`)
            .replace(/\b(\d{1,3})K\b/g, '$1 K')
            .replace(/[;:]/g, ', ')
            .replace(/\(([^)]+)\)/g, ', $1, ')
            .replace(/["“”]/g, '')
            .replace(/\s+/g, ' ')
            .replace(/,\s*,+/g, ', ')
            .trim();
    };
    const extractAssistantSearchTerms = (query = '') => {
        const normalizedQuery = normalizeText(query);
        const rawTokens = normalizedQuery.split(/\s+/).filter(Boolean);
        const meaningfulTokens = uniqueValues(rawTokens.filter((token) => token.length > 1 && !assistantNoiseWords.has(token)));
        const phraseTokens = uniqueValues(meaningfulTokens.flatMap((token, index) => {
            const next = meaningfulTokens[index + 1];

            return next ? [`${token} ${next}`] : [];
        }).filter((token) => token.length > 5));

        return {
            normalizedQuery,
            rawTokens,
            meaningfulTokens,
            phraseTokens,
        };
    };
    const isFollowupQuery = (query = '', meaningfulTokens = []) => {
        const normalizedQuery = normalizeText(query);

        if (normalizedQuery === '' || meaningfulTokens.length === 0 || meaningfulTokens.length > 5) {
            return false;
        }

        return assistantFollowupMarkers.some((marker) => (
            normalizedQuery === marker
            || normalizedQuery.startsWith(`${marker} `)
            || normalizedQuery.includes(` ${marker} `)
        ));
    };
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
    const parseJsonAttribute = (value, fallback = null) => {
        if (typeof value !== 'string' || value.trim() === '') {
            return fallback;
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return fallback;
        }
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

    if (launchPopup instanceof HTMLElement) {
        const closeButtons = launchPopup.querySelectorAll('[data-launch-popup-close]');
        const popupDelay = Number.parseInt(launchPopup.dataset.launchPopupDelay ?? '10000', 10);
        const resolvedPopupDelay = Number.isFinite(popupDelay) && popupDelay >= 0 ? popupDelay : 10000;
        const popupEndpoint = launchPopup.dataset.launchPopupEndpoint ?? '';
        const popupDecision = launchPopup.dataset.launchPopupDecision ?? 'ignore';
        let launchPopupTimer = null;
        let launchPopupClosed = false;

        const showLaunchPopup = () => {
            if (launchPopupClosed) {
                return;
            }

            launchPopup.style.display = 'flex';
            launchPopup.setAttribute('aria-hidden', 'false');
        };
        const isLaunchPopupVisible = () => launchPopup.style.display !== 'none';

        const hideLaunchPopup = () => {
            launchPopupClosed = true;
            launchPopup.style.display = 'none';
            launchPopup.setAttribute('aria-hidden', 'true');

            if (launchPopupTimer !== null) {
                window.clearTimeout(launchPopupTimer);
                launchPopupTimer = null;
            }
        };

        const persistLaunchPopupDecision = () => {
            if (popupEndpoint === '') {
                return;
            }

            const formData = new FormData();
            const launchPopupCsrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            formData.set('decision', popupDecision);
            formData.set('redirect_to', window.location.href);

            window.fetch(popupEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': launchPopupCsrfToken,
                },
                body: formData,
                keepalive: true,
            }).catch(() => {});
        };

        const scheduleLaunchPopup = () => {
            launchPopupTimer = window.setTimeout(showLaunchPopup, resolvedPopupDelay);
        };

        if (document.readyState === 'complete') {
            scheduleLaunchPopup();
        } else {
            window.addEventListener('load', scheduleLaunchPopup, { once: true });
        }

        window.addEventListener('pagehide', () => {
            if (launchPopupTimer !== null) {
                window.clearTimeout(launchPopupTimer);
            }
        }, { once: true });

        closeButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                hideLaunchPopup();
                persistLaunchPopupDecision();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isLaunchPopupVisible()) {
                event.preventDefault();
                hideLaunchPopup();
                persistLaunchPopupDecision();
            }
        });
    }

    const localeSwitchers = document.querySelectorAll('[data-locale-switcher]');
    const localeControls = [];

    localeSwitchers.forEach((switcher) => {
        const toggle = switcher.querySelector('[data-locale-toggle]');
        const menu = switcher.querySelector('[data-locale-menu]');

        if (!(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
            return;
        }

        const control = {
            switcher,
            toggle,
            menu,
            closeMenu: null,
        };

        localeControls.push(control);

        const positionMenu = () => {
            const viewportPadding = 12;
            const toggleRect = toggle.getBoundingClientRect();
            const isFullWidth = switcher.dataset.localeFullWidth === 'true';
            const preferredWidth = isFullWidth
                ? Math.max(toggleRect.width, 240)
                : 288;
            const width = Math.min(preferredWidth, window.innerWidth - (viewportPadding * 2));
            const left = Math.min(
                Math.max(
                    isFullWidth ? toggleRect.left : toggleRect.right - width,
                    viewportPadding,
                ),
                window.innerWidth - width - viewportPadding,
            );
            let top = toggleRect.bottom + 10;
            let maxHeight = window.innerHeight - top - viewportPadding;

            if (maxHeight < 220) {
                top = viewportPadding;
                maxHeight = window.innerHeight - (viewportPadding * 2);
            }

            menu.style.left = `${left}px`;
            menu.style.top = `${Math.max(top, viewportPadding)}px`;
            menu.style.width = `${width}px`;
            menu.style.maxHeight = `${Math.max(maxHeight, 180)}px`;
        };

        const mountMenuToViewport = () => {
            if (menu.parentElement !== document.body) {
                document.body.appendChild(menu);
            }
        };

        const closeMenu = () => {
            menu.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        };

        control.closeMenu = closeMenu;

        mountMenuToViewport();

        menu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const isClosed = menu.classList.contains('hidden');

            localeControls.forEach((item) => {
                if (item.switcher !== switcher && typeof item.closeMenu === 'function') {
                    item.closeMenu();
                }
            });

            if (isClosed) {
                mountMenuToViewport();
                menu.classList.remove('hidden');
                positionMenu();
                toggle.setAttribute('aria-expanded', 'true');
                window.requestAnimationFrame(positionMenu);
            } else {
                closeMenu();
            }
        });

        document.addEventListener('click', (event) => {
            if (!switcher.contains(event.target) && !menu.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('resize', closeMenu);
        window.addEventListener('scroll', () => {
            if (!menu.classList.contains('hidden')) {
                positionMenu();
            }
        }, true);
    });

    document.querySelectorAll('[data-mobile-nav]').forEach((mobileNav) => {
        if (!(mobileNav instanceof HTMLElement)) {
            return;
        }

        const toggle = mobileNav.querySelector('[data-mobile-nav-toggle]');
        const panel = mobileNav.querySelector('[data-mobile-nav-panel]');
        const openIcon = mobileNav.querySelector('[data-mobile-nav-open-icon]');
        const closeIcon = mobileNav.querySelector('[data-mobile-nav-close-icon]');
        const closeTriggers = mobileNav.querySelectorAll('[data-mobile-nav-close]');
        const shouldAutoScroll = mobileNav.hasAttribute('data-mobile-nav-autoscroll');

        if (!(toggle instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) {
            return;
        }

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

        const revealMobileNavPanel = () => {
            if (!shouldAutoScroll) {
                return;
            }

            window.requestAnimationFrame(() => {
                const panelBounds = panel.getBoundingClientRect();
                const viewportPadding = 24;
                const overflow = panelBounds.bottom - (window.innerHeight - viewportPadding);

                if (overflow > 0) {
                    window.scrollBy({
                        top: overflow,
                        behavior: 'smooth',
                    });
                }
            });
        };

        toggle.addEventListener('click', () => {
            const shouldOpen = panel.classList.contains('hidden');
            syncMobileNavState(shouldOpen);

            if (shouldOpen) {
                revealMobileNavPanel();
            }
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
    });

    document.querySelectorAll('[data-account-filter]').forEach((filterRoot) => {
        if (!(filterRoot instanceof HTMLElement)) {
            return;
        }

        const tabs = [...filterRoot.querySelectorAll('[data-account-filter-tab]')].filter((button) => button instanceof HTMLButtonElement);
        const statusSelect = filterRoot.querySelector('[data-account-status-filter]');
        const cards = [...filterRoot.querySelectorAll('[data-account-filter-card]')].filter((card) => card instanceof HTMLElement);
        const emptyState = filterRoot.querySelector('[data-account-filter-empty]');
        let accountState = 'all';
        let statusState = 'all';

        const setActiveTab = () => {
            tabs.forEach((button) => {
                const isActive = button.dataset.filter === accountState;

                button.dataset.active = isActive ? 'true' : 'false';
                button.classList.toggle('bg-amber-400', isActive);
                button.classList.toggle('text-slate-950', isActive);
                button.classList.toggle('text-white', false);
                button.classList.toggle('text-slate-400', !isActive);
            });
        };

        const renderAccountFilters = () => {
            let visibleCount = 0;

            cards.forEach((card) => {
                const matchesAccountState = accountState === 'all' || card.dataset.accountState === accountState;
                const matchesStatusState = statusState === 'all' || card.dataset.accountStatus === statusState;
                const shouldShow = matchesAccountState && matchesStatusState;

                card.classList.toggle('hidden', !shouldShow);

                if (shouldShow) {
                    visibleCount += 1;
                }
            });

            if (emptyState instanceof HTMLElement) {
                emptyState.classList.toggle('hidden', visibleCount > 0);
            }

            setActiveTab();
        };

        tabs.forEach((button) => {
            button.addEventListener('click', () => {
                accountState = button.dataset.filter ?? 'all';
                renderAccountFilters();
            });
        });

        if (statusSelect instanceof HTMLSelectElement) {
            statusSelect.addEventListener('change', () => {
                statusState = statusSelect.value || 'all';
                renderAccountFilters();
            });
        }

        renderAccountFilters();
    });

    const dashboardModals = [...document.querySelectorAll('[data-dashboard-modal]')].filter((modal) => modal instanceof HTMLElement);

    if (dashboardModals.length > 0) {
        let activeDashboardModal = null;

        const setModalOpen = (modal, open) => {
            modal.classList.toggle('hidden', !open);
            modal.classList.toggle('flex', open);
            modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        };

        const closeDashboardModal = () => {
            if (!(activeDashboardModal instanceof HTMLElement)) {
                return;
            }

            setModalOpen(activeDashboardModal, false);
            activeDashboardModal = null;
            document.body.classList.remove('overflow-hidden');
            document.documentElement.classList.remove('overflow-hidden');
        };

        const openDashboardModal = (modalName) => {
            const modal = dashboardModals.find((item) => item.dataset.dashboardModal === modalName);

            if (!(modal instanceof HTMLElement)) {
                return;
            }

            dashboardModals.forEach((item) => setModalOpen(item, false));
            activeDashboardModal = modal;
            setModalOpen(modal, true);
            document.body.classList.add('overflow-hidden');
            document.documentElement.classList.add('overflow-hidden');
        };

        dashboardModals.forEach((modal) => {
            setModalOpen(modal, false);

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeDashboardModal();
                }
            });

            modal.querySelectorAll('[data-dashboard-modal-close]').forEach((button) => {
                button.addEventListener('click', closeDashboardModal);
            });
        });

        document.querySelectorAll('[data-dashboard-modal-open]').forEach((button) => {
            button.addEventListener('click', () => {
                openDashboardModal(button.dataset.dashboardModalOpen);
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeDashboardModal();
            }
        });
    }

    const copyTextToClipboard = async (value) => {
        const text = String(value ?? '');

        if (text === '') {
            return false;
        }

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);

                return true;
            }
        } catch (error) {
            // Fall back to a temporary textarea below.
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            return document.execCommand('copy');
        } catch (error) {
            return false;
        } finally {
            textarea.remove();
        }
    };

    document.querySelectorAll('[data-dashboard-copy]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const originalHtml = button.innerHTML;

        button.addEventListener('click', async () => {
            const copied = await copyTextToClipboard(button.dataset.dashboardCopy);

            if (!copied) {
                return;
            }

            button.innerHTML = button.textContent?.trim()
                ? 'Copied'
                : '<span class="sr-only">Copied</span>';
            button.classList.add('border-emerald-300/35', 'bg-emerald-400/14', 'text-emerald-50');

            window.setTimeout(() => {
                button.innerHTML = originalHtml;
                button.classList.remove('border-emerald-300/35', 'bg-emerald-400/14', 'text-emerald-50');
            }, 1400);
        });
    });

    document.querySelectorAll('[data-dashboard-chart]').forEach((chartRoot) => {
        if (!(chartRoot instanceof HTMLElement)) {
            return;
        }

        const payload = parseJsonAttribute(chartRoot.dataset.dashboardChartPayload, null);

        if (!payload || typeof payload !== 'object') {
            return;
        }

        const ranges = payload.ranges ?? {};
        const buttons = [...chartRoot.querySelectorAll('[data-dashboard-chart-range]')].filter((button) => button instanceof HTMLButtonElement);
        const areaPath = chartRoot.querySelector('[data-dashboard-chart-area]');
        const balancePath = chartRoot.querySelector('[data-dashboard-chart-balance]');
        const equityPath = chartRoot.querySelector('[data-dashboard-chart-equity]');
        const emptyState = chartRoot.querySelector('[data-dashboard-chart-empty]');
        const startLabel = chartRoot.querySelector('[data-dashboard-chart-label-start]');
        const midLabel = chartRoot.querySelector('[data-dashboard-chart-label-mid]');
        const endLabel = chartRoot.querySelector('[data-dashboard-chart-label-end]');
        const changeLabel = chartRoot.querySelector('[data-dashboard-chart-change]');
        const rangeHint = chartRoot.querySelector('[data-dashboard-chart-range-hint]');
        const balanceValue = chartRoot.querySelector('[data-dashboard-chart-balance-value]');
        const equityValue = chartRoot.querySelector('[data-dashboard-chart-equity-value]');
        const highValue = chartRoot.querySelector('[data-dashboard-chart-high]');
        const lowValue = chartRoot.querySelector('[data-dashboard-chart-low]');
        const toneClasses = ['text-amber-100', 'text-emerald-100', 'text-rose-100', 'text-sky-100', 'text-white'];
        const toneClassMap = {
            amber: 'text-amber-100',
            emerald: 'text-emerald-100',
            rose: 'text-rose-100',
            sky: 'text-sky-100',
            slate: 'text-white',
        };

        if (!(areaPath instanceof SVGPathElement) || !(balancePath instanceof SVGPathElement) || !(equityPath instanceof SVGPathElement)) {
            return;
        }

        const availableRangeKeys = Object.keys(ranges).filter((key) => ranges[key]?.is_available);
        const fallbackRangeKey = availableRangeKeys[0] ?? null;

        if (!fallbackRangeKey) {
            return;
        }

        const setActiveButton = (activeKey) => {
            buttons.forEach((button) => {
                const isActive = button.dataset.dashboardChartRange === activeKey;

                button.classList.toggle('border-amber-400/25', isActive);
                button.classList.toggle('bg-amber-400/15', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('border-white/10', !isActive);
                button.classList.toggle('bg-white/5', !isActive);
                button.classList.toggle('text-slate-300', !isActive);
            });
        };

        const buildCoordinates = (points, key, min, max) => {
            const paddingX = 5;
            const paddingY = 8;
            const width = 100 - (paddingX * 2);
            const height = 100 - (paddingY * 2);
            const span = max - min || 1;

            return points.map((point, index) => {
                const divisor = Math.max(points.length - 1, 1);
                const x = paddingX + ((width * index) / divisor);
                const numericValue = Number.parseFloat(point[key] ?? 0);
                const y = 100 - paddingY - (((numericValue - min) / span) * height);

                return { x, y };
            });
        };

        const buildLinePath = (coords) => coords
            .map((coord, index) => `${index === 0 ? 'M' : 'L'} ${coord.x.toFixed(2)} ${coord.y.toFixed(2)}`)
            .join(' ');

        const buildAreaPath = (coords) => {
            if (coords.length === 0) {
                return '';
            }

            const first = coords[0];
            const last = coords[coords.length - 1];
            const line = buildLinePath(coords);

            return `${line} L ${last.x.toFixed(2)} 92 L ${first.x.toFixed(2)} 92 Z`;
        };

        const setChartLabels = (points) => {
            const midIndex = Math.floor(Math.max(points.length - 1, 0) / 2);

            if (startLabel instanceof HTMLElement) {
                startLabel.textContent = points[0]?.label ?? '—';
            }

            if (midLabel instanceof HTMLElement) {
                midLabel.textContent = points[midIndex]?.label ?? '—';
            }

            if (endLabel instanceof HTMLElement) {
                endLabel.textContent = points[points.length - 1]?.label ?? '—';
            }
        };

        const setChangeTone = (tone = 'slate') => {
            if (!(changeLabel instanceof HTMLElement)) {
                return;
            }

            changeLabel.classList.remove(...toneClasses);
            changeLabel.classList.add(toneClassMap[tone] ?? toneClassMap.slate);
        };

        const renderRange = (requestedKey) => {
            const activeKey = ranges[requestedKey]?.is_available ? requestedKey : fallbackRangeKey;
            const activeRange = ranges[activeKey];
            const points = Array.isArray(activeRange?.points) ? activeRange.points : [];
            const summary = activeRange?.summary ?? {};

            setActiveButton(activeKey);

            if (points.length === 0) {
                if (emptyState instanceof HTMLElement) {
                    emptyState.classList.remove('hidden');
                }

                areaPath.setAttribute('d', '');
                balancePath.setAttribute('d', '');
                equityPath.setAttribute('d', '');

                return;
            }

            if (emptyState instanceof HTMLElement) {
                emptyState.classList.add('hidden');
            }

            const values = points.flatMap((point) => [
                Number.parseFloat(point.balance ?? 0),
                Number.parseFloat(point.equity ?? 0),
            ]).filter((value) => Number.isFinite(value));
            let min = Math.min(...values);
            let max = Math.max(...values);

            if (min === max) {
                min -= 1;
                max += 1;
            }

            const balanceCoords = buildCoordinates(points, 'balance', min, max);
            const equityCoords = buildCoordinates(points, 'equity', min, max);

            areaPath.setAttribute('d', buildAreaPath(balanceCoords));
            balancePath.setAttribute('d', buildLinePath(balanceCoords));
            equityPath.setAttribute('d', buildLinePath(equityCoords));

            setChartLabels(points);

            if (changeLabel instanceof HTMLElement) {
                changeLabel.textContent = summary.change ?? '$0.00';
            }

            setChangeTone(summary.change_tone ?? 'slate');

            if (rangeHint instanceof HTMLElement) {
                rangeHint.textContent = summary.range_hint ?? 'No synced data yet';
            }

            if (balanceValue instanceof HTMLElement) {
                balanceValue.textContent = summary.last_balance ?? '$0.00';
            }

            if (equityValue instanceof HTMLElement) {
                equityValue.textContent = summary.last_equity ?? '$0.00';
            }

            if (highValue instanceof HTMLElement) {
                highValue.textContent = summary.high ?? '$0.00';
            }

            if (lowValue instanceof HTMLElement) {
                lowValue.textContent = summary.low ?? '$0.00';
            }
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                renderRange(button.dataset.dashboardChartRange ?? fallbackRangeKey);
            });
        });

        renderRange(payload.default_range ?? fallbackRangeKey);
    });

    document.querySelectorAll('[data-dashboard-trades]').forEach((tradesRoot) => {
        if (!(tradesRoot instanceof HTMLElement)) {
            return;
        }

        const rows = [...tradesRoot.querySelectorAll('[data-dashboard-trades-row]')].filter((row) => row instanceof HTMLElement);
        const buttons = [...tradesRoot.querySelectorAll('[data-dashboard-trades-filter]')].filter((button) => button instanceof HTMLButtonElement);
        const countLabel = tradesRoot.querySelector('[data-dashboard-trades-count]');
        const emptyState = tradesRoot.querySelector('[data-dashboard-trades-empty]');
        const summary = parseJsonAttribute(tradesRoot.dataset.dashboardTradesSummary, { both: 0, open: 0, closed: 0 });

        if (rows.length === 0 || buttons.length === 0) {
            return;
        }

        const setActiveButton = (activeKey) => {
            buttons.forEach((button) => {
                const isActive = button.dataset.dashboardTradesFilter === activeKey;

                button.classList.toggle('border-amber-400/25', isActive);
                button.classList.toggle('bg-amber-400/15', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('border-white/10', !isActive);
                button.classList.toggle('bg-white/5', !isActive);
                button.classList.toggle('text-slate-300', !isActive);
            });
        };

        const renderFilter = (filterKey) => {
            rows.forEach((row) => {
                const matches = filterKey === 'both' || row.dataset.tradeFilter === filterKey;
                row.classList.toggle('hidden', !matches);
            });

            if (countLabel instanceof HTMLElement) {
                countLabel.textContent = String(summary[filterKey] ?? 0);
            }

            if (emptyState instanceof HTMLElement) {
                emptyState.classList.toggle('hidden', (summary[filterKey] ?? 0) !== 0);
            }

            setActiveButton(filterKey);
        };

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                renderFilter(button.dataset.dashboardTradesFilter ?? 'both');
            });
        });

        renderFilter('both');
    });

    const floatingFooterNav = document.querySelector('[data-floating-footer-nav]');

    if (floatingFooterNav instanceof HTMLElement) {
        const toggle = floatingFooterNav.querySelector('[data-floating-footer-nav-toggle]');
        const panel = floatingFooterNav.querySelector('[data-floating-footer-nav-panel]');
        const openIcon = floatingFooterNav.querySelector('[data-floating-footer-nav-open-icon]');
        const closeIcon = floatingFooterNav.querySelector('[data-floating-footer-nav-close-icon]');
        const closeTriggers = floatingFooterNav.querySelectorAll('[data-floating-footer-nav-close]');

        if (toggle instanceof HTMLButtonElement && panel instanceof HTMLElement) {
            const syncFloatingFooterNavState = (open) => {
                panel.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

                if (open) {
                    floatingFooterNav.classList.add('is-open');
                } else {
                    floatingFooterNav.classList.remove('is-open');
                }

                if (toggle.dataset.openLabel && toggle.dataset.closeLabel) {
                    toggle.setAttribute('aria-label', open ? toggle.dataset.closeLabel : toggle.dataset.openLabel);
                }

                if (openIcon instanceof SVGElement) {
                    openIcon.classList.toggle('hidden', open);
                }

                if (closeIcon instanceof SVGElement) {
                    closeIcon.classList.toggle('hidden', !open);
                }
            };

            const closeFloatingFooterNav = () => {
                syncFloatingFooterNavState(false);
            };

            toggle.addEventListener('click', () => {
                const shouldOpen = panel.classList.contains('hidden');
                syncFloatingFooterNavState(shouldOpen);
            });

            closeTriggers.forEach((trigger) => {
                trigger.addEventListener('click', () => {
                    closeFloatingFooterNav();
                });
            });

            document.addEventListener('click', (event) => {
                if (!floatingFooterNav.contains(event.target)) {
                    closeFloatingFooterNav();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeFloatingFooterNav();
                }
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth >= 1024) {
                    closeFloatingFooterNav();
                }
            });

            syncFloatingFooterNavState(false);
        }
    }

    const footerPanels = [...document.querySelectorAll('[data-footer-panel]')];

    if (footerPanels.length > 0) {
        const closeFooterPanel = (panelRoot) => {
            if (!(panelRoot instanceof HTMLElement)) {
                return;
            }

            const toggle = panelRoot.querySelector('[data-footer-panel-toggle]');
            const panel = panelRoot.querySelector('[data-footer-panel-content]');
            const openIcon = panelRoot.querySelector('[data-footer-panel-open-icon]');
            const closeIcon = panelRoot.querySelector('[data-footer-panel-close-icon]');

            if (panel instanceof HTMLElement) {
                panel.classList.add('hidden');
            }

            if (toggle instanceof HTMLButtonElement) {
                toggle.setAttribute('aria-expanded', 'false');

                if (toggle.dataset.openLabel) {
                    toggle.setAttribute('aria-label', toggle.dataset.openLabel);
                }
            }

            if (openIcon instanceof SVGElement) {
                openIcon.classList.remove('hidden');
            }

            if (closeIcon instanceof SVGElement) {
                closeIcon.classList.add('hidden');
            }
        };

        const openFooterPanel = (panelRoot) => {
            if (!(panelRoot instanceof HTMLElement)) {
                return;
            }

            footerPanels.forEach((item) => {
                if (item !== panelRoot) {
                    closeFooterPanel(item);
                }
            });

            const toggle = panelRoot.querySelector('[data-footer-panel-toggle]');
            const panel = panelRoot.querySelector('[data-footer-panel-content]');
            const openIcon = panelRoot.querySelector('[data-footer-panel-open-icon]');
            const closeIcon = panelRoot.querySelector('[data-footer-panel-close-icon]');

            if (panel instanceof HTMLElement) {
                panel.classList.remove('hidden');
            }

            if (toggle instanceof HTMLButtonElement) {
                toggle.setAttribute('aria-expanded', 'true');

                if (toggle.dataset.closeLabel) {
                    toggle.setAttribute('aria-label', toggle.dataset.closeLabel);
                }
            }

            if (openIcon instanceof SVGElement) {
                openIcon.classList.add('hidden');
            }

            if (closeIcon instanceof SVGElement) {
                closeIcon.classList.remove('hidden');
            }
        };

        footerPanels.forEach((panelRoot) => {
            const toggle = panelRoot.querySelector('[data-footer-panel-toggle]');
            const panel = panelRoot.querySelector('[data-footer-panel-content]');

            if (!(toggle instanceof HTMLButtonElement) || !(panel instanceof HTMLElement)) {
                return;
            }

            closeFooterPanel(panelRoot);

            toggle.addEventListener('click', () => {
                const shouldOpen = panel.classList.contains('hidden');

                if (shouldOpen) {
                    openFooterPanel(panelRoot);
                    return;
                }

                closeFooterPanel(panelRoot);
            });
        });

        document.addEventListener('click', (event) => {
            footerPanels.forEach((panelRoot) => {
                if (!panelRoot.contains(event.target)) {
                    closeFooterPanel(panelRoot);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            footerPanels.forEach((panelRoot) => {
                closeFooterPanel(panelRoot);
            });
        });
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
                    const searchText = item.search_text ?? '';
                    const haystack = normalizeText(`${title} ${description} ${keywords} ${searchText}`);
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
        const comparisonCards = Array.from(challengeSelector.querySelectorAll('[data-challenge-plan-card]'));
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
            const formatPlanPhaseTargets = (plan) => (plan?.phases ?? [])
                .map((phase) => `${ui.phase_titles?.[phase.key] ?? phase.key} ${phase.profit_target}%`.toUpperCase())
                .join(' / ');
            const formatPlanProfitShare = (plan) => {
                const funded = plan?.funded ?? {};
                const baseSplit = funded.profit_split;
                const upgradedSplit = funded.profit_split_upgrade?.profit_split;

                if (baseSplit && upgradedSplit) {
                    return `${baseSplit}% to ${upgradedSplit}%`;
                }

                return baseSplit ? `${baseSplit}%` : '';
            };
            const buildPlanCheckoutUrl = (base, challengeType, plan) => {
                const checkoutUrl = new URL(base, window.location.origin);

                checkoutUrl.searchParams.set('challenge_type', challengeType);
                checkoutUrl.searchParams.set('account_size', String(plan.account_size));
                checkoutUrl.searchParams.set('currency', activeCurrency);

                return withLaunchPromoCode(checkoutUrl.toString());
            };
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
            const renderComparisonPlans = (currentType) => {
                comparisonCards.forEach((card) => {
                    if (!(card instanceof HTMLElement)) {
                        return;
                    }

                    const size = card.dataset.challengeSize ?? '';
                    const plan = currentType?.plans?.[size];

                    card.classList.toggle('hidden', !plan);

                    if (!plan) {
                        return;
                    }

                    const firstPhase = plan.phases?.[0] ?? {};
                    const accountSize = card.querySelector('[data-comparison-account-size]');
                    const phaseTargets = card.querySelector('[data-comparison-phases]');
                    const dailyLoss = card.querySelector('[data-comparison-daily-loss]');
                    const totalLoss = card.querySelector('[data-comparison-total-loss]');
                    const minDays = card.querySelector('[data-comparison-min-days]');
                    const maxDays = card.querySelector('[data-comparison-max-days]');
                    const profitShare = card.querySelector('[data-comparison-profit-share]');
                    const price = card.querySelector('[data-comparison-price]');
                    const originalWrap = card.querySelector('[data-comparison-original-wrap]');
                    const originalPrice = card.querySelector('[data-comparison-original-price]');
                    const discountBadge = card.querySelector('[data-comparison-discount]');
                    const cta = card.querySelector('[data-comparison-cta]');

                    card.classList.toggle('is-active', String(plan.account_size) === String(activeSize));

                    if (accountSize) {
                        accountSize.textContent = `$${Number(plan.account_size).toLocaleString('en-US')}`;
                    }

                    if (phaseTargets) {
                        phaseTargets.textContent = formatPlanPhaseTargets(plan);
                    }

                    if (dailyLoss) {
                        dailyLoss.textContent = `${firstPhase.daily_loss_limit ?? ''}%`;
                    }

                    if (totalLoss) {
                        totalLoss.textContent = `${firstPhase.max_loss_limit ?? ''}%`;
                    }

                    if (minDays) {
                        minDays.textContent = formatDays(firstPhase.minimum_trading_days ?? '');
                    }

                    if (maxDays) {
                        maxDays.textContent = firstPhase.maximum_trading_days === null
                            ? (ui.unlimited ?? 'Unlimited')
                            : formatDays(firstPhase.maximum_trading_days ?? '');
                    }

                    if (profitShare) {
                        profitShare.textContent = formatPlanProfitShare(plan);
                    }

                    if (price) {
                        price.textContent = formatCurrency(convertCurrency(plan.discounted_price ?? plan.entry_fee ?? 0, activeCurrency), activeCurrency);
                    }

                    if (originalPrice) {
                        originalPrice.textContent = formatCurrency(convertCurrency(plan.list_price ?? plan.entry_fee ?? 0, activeCurrency), activeCurrency);
                    }

                    if (originalWrap instanceof HTMLElement) {
                        originalWrap.classList.toggle('hidden', !plan.discount?.enabled);
                    }

                    if (discountBadge instanceof HTMLElement) {
                        discountBadge.classList.toggle('hidden', !plan.discount?.enabled);
                        discountBadge.textContent = ui.discount_badge ?? discountBadge.textContent ?? '';
                    }

                    if (cta instanceof HTMLAnchorElement) {
                        cta.href = buildPlanCheckoutUrl(cta.dataset.comparisonCheckoutBase ?? cta.href, activeType, plan);
                    }
                });
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
                renderComparisonPlans(currentType);

                checkoutCtas.forEach((link) => {
                    if (!(link instanceof HTMLAnchorElement)) {
                        return;
                    }

                    const base = link.dataset.checkoutBase ?? link.href;
                    link.href = buildPlanCheckoutUrl(base, activeType, plan);
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

            comparisonCards.forEach((card) => {
                if (!(card instanceof HTMLElement)) {
                    return;
                }

                card.addEventListener('click', (event) => {
                    if (event.target instanceof HTMLElement && event.target.closest('a')) {
                        return;
                    }

                    const nextSize = card.dataset.challengeSize ?? activeSize;

                    if (nextSize === '') {
                        return;
                    }

                    activeSize = nextSize;
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

        const openFaqHashTarget = () => {
            const hash = window.location.hash ? decodeURIComponent(window.location.hash.slice(1)) : '';

            if (hash === '') {
                return;
            }

            const target = document.getElementById(hash);

            if (target instanceof HTMLDetailsElement) {
                const shouldReduceMotion = typeof window.matchMedia === 'function'
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                target.open = true;
                target.classList.remove('hidden');
                target.closest('[data-faq-section]')?.classList.remove('hidden');
                target.scrollIntoView({ block: 'start', behavior: shouldReduceMotion ? 'auto' : 'smooth' });
            }
        };

        window.addEventListener('hashchange', openFaqHashTarget);
        openFaqHashTarget();
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
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const speechLocaleMap = {
            en: 'en-US',
            de: 'de-DE',
            es: 'es-ES',
            fr: 'fr-FR',
            hi: 'hi-IN',
            it: 'it-IT',
            pt: 'pt-PT',
        };
        const ttsEndpoint = typeof assistantConfig.tts_endpoint === 'string'
            ? assistantConfig.tts_endpoint
            : '';
        const ttsAvailable = assistantConfig.tts_available === true;
        const pageLocale = voiceAssistant.dataset.pageLocale ?? document.documentElement.lang ?? 'en';
        const pageLocaleBase = normalizeLocaleCode(pageLocale) || 'en';
        const preferredLanguages = uniqueValues([
            pageLocale,
            ...(Array.isArray(window.navigator.languages) ? window.navigator.languages : []),
            window.navigator.language,
        ].map(normalizeLocaleCode)).filter(Boolean);
        const speechRecognitionLocales = uniqueValues([
            ...(Array.isArray(window.navigator.languages) ? window.navigator.languages : []),
            window.navigator.language,
            pageLocale,
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
        let activeSpeechLocale = resolveSpeechLocale(speechRecognitionLocales);
        let availableSpeechVoices = [];
        let currentAnswerText = '';
        let currentAnswerSpeechLocale = activeSpeechLocale;
        let activeConversationLocale = speechRecognitionLocales[0] ?? pageLocaleBase;
        let lastResolvedContext = {
            intent: 'default',
            locale: pageLocaleBase,
            terms: [],
        };
        let isSpeakingReply = false;
        let isListening = false;
        let hasConversation = false;
        let hasPlayedIntro = false;
        let replyPlaybackTimeoutId = null;
        let activeReplyUtterance = null;
        let activeReplyAudio = null;
        let activeReplyAudioUrl = '';
        let activeReplyAudioAbortController = null;
        let queuedReplyPlayback = null;
        let microphonePermissionState = 'prompt';
        let permissionListenerBound = false;
        let isRenderingResponse = false;
        let renderingStateTimeoutId = null;
        let suggestionRevealTimeoutId = null;
        let answerRevealToken = 0;
        let answerRevealTimeoutIds = [];
        let answerQuestionTimeoutId = null;
        let speechVoiceRequestToken = 0;
        let pendingSpeechVoiceResolvers = [];
        const reducedMotionQuery = typeof window.matchMedia === 'function'
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        const voiceDebugEnabled = (() => {
            try {
                const params = new URLSearchParams(window.location.search);

                return ['1', 'true', 'yes', 'on'].includes(normalizeText(params.get('wolfi_debug')));
            } catch (error) {
                return false;
            }
        })();
        const voiceDebug = (label, payload = {}) => {
            if (!voiceDebugEnabled) {
                return;
            }

            const entry = {
                label,
                payload,
                timestamp: new Date().toISOString(),
            };

            window.__wolfiVoiceDebug = window.__wolfiVoiceDebug ?? [];
            window.__wolfiVoiceDebug.push(entry);
            console.info('[Wolfi voice]', label, payload);
        };
        const prefersReducedMotion = () => reducedMotionQuery?.matches ?? false;
        const canUseBrowserSpeech = () => 'speechSynthesis' in window;
        const canUseServerSpeech = () => ttsAvailable && ttsEndpoint !== '';
        const canPlayVoiceReplies = () => canUseServerSpeech() || canUseBrowserSpeech();
        let wolfiVoiceLevel = 0;
        let wolfiVoiceLevelAnimationFrameId = null;
        let wolfiVoicePulseTimeoutId = null;
        let wolfiVoiceFallbackIntervalId = null;
        let wolfiVoiceAudioContext = null;
        let wolfiVoiceAudioSource = null;
        let wolfiVoiceAnalyser = null;
        let wolfiVoiceUsesDynamicLevel = false;

        const setWolfiVoiceLevel = (level) => {
            const normalizedLevel = Math.max(0, Math.min(1, Number.isFinite(level) ? level : 0));
            wolfiVoiceLevel = normalizedLevel;

            if (modalRoot instanceof HTMLElement) {
                modalRoot.style.setProperty('--wolfi-voice-level', normalizedLevel.toFixed(3));
            }
        };

        const clearWolfiVoicePulseTimeout = () => {
            if (wolfiVoicePulseTimeoutId !== null) {
                window.clearTimeout(wolfiVoicePulseTimeoutId);
                wolfiVoicePulseTimeoutId = null;
            }
        };

        const clearWolfiVoiceFallbackInterval = () => {
            if (wolfiVoiceFallbackIntervalId !== null) {
                window.clearInterval(wolfiVoiceFallbackIntervalId);
                wolfiVoiceFallbackIntervalId = null;
            }
        };

        const disconnectWolfiVoiceAnalyser = () => {
            if (wolfiVoiceLevelAnimationFrameId !== null) {
                window.cancelAnimationFrame(wolfiVoiceLevelAnimationFrameId);
                wolfiVoiceLevelAnimationFrameId = null;
            }

            try {
                wolfiVoiceAudioSource?.disconnect?.();
            } catch (error) {
                // Ignore disconnection failures while resetting reactive playback visuals.
            }

            try {
                wolfiVoiceAnalyser?.disconnect?.();
            } catch (error) {
                // Ignore analyser disconnect failures while resetting reactive playback visuals.
            }

            if (wolfiVoiceAudioContext && typeof wolfiVoiceAudioContext.close === 'function' && wolfiVoiceAudioContext.state !== 'closed') {
                wolfiVoiceAudioContext.close().catch(() => {});
            }

            wolfiVoiceAudioContext = null;
            wolfiVoiceAudioSource = null;
            wolfiVoiceAnalyser = null;
        };

        const syncWolfiAmbientVoiceLevel = () => {
            if (wolfiVoiceUsesDynamicLevel) {
                return;
            }

            if (!(modalRoot instanceof HTMLElement) || prefersReducedMotion()) {
                setWolfiVoiceLevel(0);
                return;
            }

            if (isListening) {
                setWolfiVoiceLevel(0.54);
                return;
            }

            if (isRenderingResponse) {
                setWolfiVoiceLevel(0.22);
                return;
            }

            if (isSpeakingReply) {
                setWolfiVoiceLevel(0.18);
                return;
            }

            setWolfiVoiceLevel(0);
        };

        const stopWolfiVoiceReactivity = ({ keepAmbient = true } = {}) => {
            clearWolfiVoicePulseTimeout();
            clearWolfiVoiceFallbackInterval();
            disconnectWolfiVoiceAnalyser();
            wolfiVoiceUsesDynamicLevel = false;

            if (keepAmbient) {
                syncWolfiAmbientVoiceLevel();
                return;
            }

            setWolfiVoiceLevel(0);
        };

        const pulseWolfiVoiceLevel = (level = 0.72, holdMs = 170) => {
            if (prefersReducedMotion()) {
                return;
            }

            wolfiVoiceUsesDynamicLevel = true;
            setWolfiVoiceLevel(level);
            clearWolfiVoicePulseTimeout();
            wolfiVoicePulseTimeoutId = window.setTimeout(() => {
                wolfiVoicePulseTimeoutId = null;
                wolfiVoiceUsesDynamicLevel = false;
                syncWolfiAmbientVoiceLevel();
            }, holdMs);
        };

        const startWolfiSpeechFallbackPulse = () => {
            if (prefersReducedMotion()) {
                syncWolfiAmbientVoiceLevel();
                return;
            }

            clearWolfiVoiceFallbackInterval();
            clearWolfiVoicePulseTimeout();
            wolfiVoiceUsesDynamicLevel = true;

            const tick = () => {
                setWolfiVoiceLevel(0.26 + Math.random() * 0.36);
            };

            tick();
            wolfiVoiceFallbackIntervalId = window.setInterval(tick, 170);
        };

        const startWolfiAudioReactivity = async (audioElement) => {
            stopWolfiVoiceReactivity({
                keepAmbient: false,
            });

            if (!(audioElement instanceof HTMLAudioElement) || prefersReducedMotion()) {
                startWolfiSpeechFallbackPulse();
                return;
            }

            const AudioContextConstructor = window.AudioContext || window.webkitAudioContext;

            if (!(AudioContextConstructor instanceof Function)) {
                startWolfiSpeechFallbackPulse();
                return;
            }

            try {
                const audioContext = new AudioContextConstructor();
                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                analyser.smoothingTimeConstant = 0.76;

                const source = audioContext.createMediaElementSource(audioElement);
                source.connect(analyser);
                analyser.connect(audioContext.destination);

                if (audioContext.state === 'suspended' && typeof audioContext.resume === 'function') {
                    await audioContext.resume();
                }

                wolfiVoiceAudioContext = audioContext;
                wolfiVoiceAudioSource = source;
                wolfiVoiceAnalyser = analyser;
                wolfiVoiceUsesDynamicLevel = true;

                const frequencyData = new Uint8Array(analyser.frequencyBinCount);

                const updateVoiceLevel = () => {
                    if (activeReplyAudio !== audioElement || audioElement.paused || audioElement.ended) {
                        stopWolfiVoiceReactivity();
                        return;
                    }

                    analyser.getByteFrequencyData(frequencyData);

                    let weightedEnergy = 0;
                    const sampleCount = Math.min(frequencyData.length, 32);

                    for (let index = 0; index < sampleCount; index += 1) {
                        weightedEnergy += frequencyData[index] * (index < 10 ? 1.25 : 1);
                    }

                    const normalizedLevel = Math.min(1, (weightedEnergy / sampleCount) / 118);
                    setWolfiVoiceLevel(0.12 + (normalizedLevel * 0.88));
                    wolfiVoiceLevelAnimationFrameId = window.requestAnimationFrame(updateVoiceLevel);
                };

                updateVoiceLevel();
            } catch (error) {
                voiceDebug('wolfi voice visualization fallback', {
                    message: error instanceof Error ? error.message : String(error),
                });
                startWolfiSpeechFallbackPulse();
            }
        };

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
            syncWolfiAmbientVoiceLevel();

            if (modalRoot instanceof HTMLElement) {
                modalRoot.classList.toggle('is-speaking', isSpeakingReply);
                modalRoot.classList.toggle('is-listening', isListening);
                modalRoot.classList.toggle('is-rendering', isRenderingResponse);
                modalRoot.dispatchEvent(new CustomEvent('wolfi:statechange', {
                    detail: {
                        speaking: isSpeakingReply,
                        listening: isListening,
                        rendering: isRenderingResponse,
                        voiceLevel: wolfiVoiceLevel,
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

        const syncConversationLocale = (nextLocale) => {
            const normalizedLocale = normalizeLocaleCode(nextLocale);

            if (normalizedLocale === '') {
                return activeConversationLocale;
            }

            activeConversationLocale = normalizedLocale;
            setActiveSpeechLocale(resolveSpeechLocale([
                activeConversationLocale,
                ...speechRecognitionLocales,
            ]));

            return activeConversationLocale;
        };
        const rememberResolvedContext = (response) => {
            if (!response || response.source === 'intro' || response.source === 'clarify') {
                return;
            }

            const localeBase = normalizeLocaleCode(response.locale) || activeConversationLocale;
            const contextSeed = `${response.question ?? ''} ${response.answer ?? ''}`;
            const { meaningfulTokens } = extractAssistantSearchTerms(contextSeed);

            lastResolvedContext = {
                intent: response.intent ?? lastResolvedContext.intent ?? 'default',
                locale: localeBase,
                terms: meaningfulTokens.slice(0, 6),
            };
        };

        const refreshSpeechVoices = () => {
            if (!('speechSynthesis' in window)) {
                return;
            }

            availableSpeechVoices = window.speechSynthesis.getVoices() ?? [];
            voiceDebug('voices refreshed', {
                count: availableSpeechVoices.length,
                locales: availableSpeechVoices.map((voice) => voice.lang).filter(Boolean).slice(0, 10),
                voices: availableSpeechVoices.map((voice) => ({
                    name: voice.name,
                    lang: voice.lang,
                    default: !!voice.default,
                    localService: !!voice.localService,
                })),
            });

            if (availableSpeechVoices.length > 0 && pendingSpeechVoiceResolvers.length > 0) {
                const resolvers = [...pendingSpeechVoiceResolvers];
                pendingSpeechVoiceResolvers = [];
                resolvers.forEach((resolve) => resolve([...availableSpeechVoices]));
            }
        };

        const waitForSpeechVoices = (timeout = 1800) => {
            if (!('speechSynthesis' in window)) {
                return Promise.resolve([]);
            }

            refreshSpeechVoices();

            if (availableSpeechVoices.length > 0) {
                return Promise.resolve([...availableSpeechVoices]);
            }

            return new Promise((resolve) => {
                const finish = (voices = availableSpeechVoices) => {
                    window.clearTimeout(timeoutId);
                    pendingSpeechVoiceResolvers = pendingSpeechVoiceResolvers.filter((resolver) => resolver !== finish);
                    resolve([...voices]);
                };
                const timeoutId = window.setTimeout(() => {
                    refreshSpeechVoices();
                    finish(availableSpeechVoices);
                }, timeout);

                pendingSpeechVoiceResolvers.push(finish);
            });
        };

        const rankSpeechVoice = (voice, locale) => {
            const requestedLocale = String(locale ?? '').toLowerCase();
            const requestedBase = normalizeLocaleCode(requestedLocale);
            const voiceLocale = String(voice.lang ?? '').toLowerCase();
            const voiceBase = normalizeLocaleCode(voiceLocale);
            const voiceKey = getVoiceMatchKey(voice);
            const speechProfile = resolveAssistantSpeechProfile(requestedBase);
            const isMaleVoice = voiceMatchesKeywords(voiceKey, speechProfile.maleVoices);
            const isFemaleVoice = voiceMatchesKeywords(voiceKey, speechProfile.femaleVoices);
            const isPreferredVoice = voiceMatchesKeywords(voiceKey, speechProfile.preferredVoices ?? []);
            const isAvoidVoice = voiceMatchesKeywords(voiceKey, speechProfile.avoidVoices ?? []);
            const isNaturalVoice = isNaturalVoiceKey(voiceKey);
            let score = 0;

            if (voiceLocale === requestedLocale) {
                score += 80;
            }

            if (voiceBase === requestedBase) {
                score += 54;
            }

            if (isNaturalVoice) {
                score += 18;
            }

            if (isMaleVoice) {
                score += 28;
            }

            if (isPreferredVoice) {
                score += 22;
            }

            if (isFemaleVoice) {
                score -= 24;
            }

            if (isAvoidVoice) {
                score -= 32;
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
            const requestedLocale = String(locale ?? '').toLowerCase();
            const requestedBase = normalizeLocaleCode(requestedLocale);
            const speechProfile = resolveAssistantSpeechProfile(requestedBase);
            const rankedVoices = [...availableSpeechVoices]
                .map((voice) => {
                    const voiceKey = getVoiceMatchKey(voice);
                    const voiceLocale = String(voice.lang ?? '').toLowerCase();
                    const voiceBase = normalizeLocaleCode(voiceLocale);

                    return {
                        voice,
                        score: rankSpeechVoice(voice, locale),
                        exactLocale: voiceLocale === requestedLocale,
                        baseLocale: voiceBase === requestedBase,
                        isMaleVoice: voiceMatchesKeywords(voiceKey, speechProfile.maleVoices),
                        isFemaleVoice: voiceMatchesKeywords(voiceKey, speechProfile.femaleVoices),
                        isPreferredVoice: voiceMatchesKeywords(voiceKey, speechProfile.preferredVoices ?? []),
                        isNaturalVoice: isNaturalVoiceKey(voiceKey),
                    };
                })
                .sort((left, right) => right.score - left.score);

            return rankedVoices.find((item) => item.exactLocale && item.isMaleVoice && item.isNaturalVoice)?.voice
                ?? rankedVoices.find((item) => item.exactLocale && item.isPreferredVoice && item.isNaturalVoice)?.voice
                ?? rankedVoices.find((item) => item.baseLocale && item.isMaleVoice && item.isNaturalVoice)?.voice
                ?? rankedVoices.find((item) => item.baseLocale && item.isPreferredVoice && item.isNaturalVoice)?.voice
                ?? rankedVoices.find((item) => item.exactLocale && item.isMaleVoice)?.voice
                ?? rankedVoices.find((item) => item.exactLocale && item.isPreferredVoice)?.voice
                ?? rankedVoices.find((item) => item.exactLocale && !item.isFemaleVoice)?.voice
                ?? rankedVoices.find((item) => item.baseLocale && item.isMaleVoice)?.voice
                ?? rankedVoices.find((item) => item.baseLocale && item.isPreferredVoice)?.voice
                ?? rankedVoices.find((item) => item.baseLocale && !item.isFemaleVoice && item.isNaturalVoice)?.voice
                ?? rankedVoices.find((item) => item.isMaleVoice && (item.isNaturalVoice || item.voice.default))?.voice
                ?? rankedVoices.find((item) => item.baseLocale && !item.isFemaleVoice)?.voice
                ?? rankedVoices[0]?.voice
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

        const clearReplyAudioElement = () => {
            stopWolfiVoiceReactivity();

            if (activeReplyAudio instanceof HTMLAudioElement) {
                activeReplyAudio.pause();
                activeReplyAudio.removeAttribute('src');
                activeReplyAudio.load();
            }

            activeReplyAudio = null;

            if (activeReplyAudioUrl !== '') {
                URL.revokeObjectURL(activeReplyAudioUrl);
                activeReplyAudioUrl = '';
            }
        };

        const abortReplyAudioRequest = () => {
            if (activeReplyAudioAbortController instanceof AbortController) {
                activeReplyAudioAbortController.abort();
            }

            activeReplyAudioAbortController = null;
        };

        if (canUseBrowserSpeech()) {
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
            speechVoiceRequestToken += 1;
            clearPendingReplyPlayback();
            activeReplyUtterance = null;
            stopWolfiVoiceReactivity({
                keepAmbient: false,
            });
            abortReplyAudioRequest();
            clearReplyAudioElement();

            if (canUseBrowserSpeech()) {
                try {
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.resume();
                } catch (error) {
                    // Ignore browser-level speech cleanup failures.
                }
            }

            setPlayButtonState(false, enabled);

            if (status instanceof HTMLElement) {
                status.textContent = status.dataset.readyMessage ?? status.textContent ?? '';
            }
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

        const playServerReplyAudio = async (
            spokenText,
            locale,
            {
                playbackRequestToken,
            } = {},
        ) => {
            if (!canUseServerSpeech() || spokenText === '') {
                return {
                    status: 'unavailable',
                };
            }

            abortReplyAudioRequest();
            clearReplyAudioElement();
            clearPendingReplyPlayback();
            setPlayButtonState(true, true);

            if (status instanceof HTMLElement) {
                status.textContent = playButton?.dataset.generating ?? status.textContent ?? '';
            }

            const abortController = new AbortController();
            activeReplyAudioAbortController = abortController;

            voiceDebug('requesting server voice', {
                endpoint: ttsEndpoint,
                locale,
                textLength: spokenText.length,
            });

            try {
                const response = await fetch(ttsEndpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'audio/mpeg',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        text: spokenText,
                        locale,
                    }),
                    signal: abortController.signal,
                });

                if (playbackRequestToken !== speechVoiceRequestToken) {
                    return {
                        status: 'aborted',
                    };
                }

                if (!response.ok) {
                    voiceDebug('server voice request failed', {
                        locale,
                        status: response.status,
                    });

                    if (status instanceof HTMLElement && playButton?.dataset.fallbackMessage) {
                        status.textContent = playButton.dataset.fallbackMessage;
                    }

                    return {
                        status: 'failed',
                    };
                }

                const audioBlob = await response.blob();

                if (audioBlob.size === 0) {
                    voiceDebug('server voice response empty', {
                        locale,
                    });

                    return {
                        status: 'failed',
                    };
                }

                if (playbackRequestToken !== speechVoiceRequestToken) {
                    return {
                        status: 'aborted',
                    };
                }

                const audioUrl = URL.createObjectURL(audioBlob);
                const audio = new Audio(audioUrl);
                audio.preload = 'auto';
                activeReplyAudio = audio;
                activeReplyAudioUrl = audioUrl;
                activeReplyAudioAbortController = null;

                audio.addEventListener('play', () => {
                    if (activeReplyAudio !== audio) {
                        return;
                    }

                    startWolfiAudioReactivity(audio);

                    if (status instanceof HTMLElement) {
                        status.textContent = playButton?.dataset.speaking ?? status.textContent ?? '';
                    }

                    setPlayButtonState(true, true);
                });

                audio.addEventListener('ended', () => {
                    if (activeReplyAudio !== audio) {
                        return;
                    }

                    clearReplyAudioElement();
                    setPlayButtonState(false, true);

                    if (status instanceof HTMLElement) {
                        status.textContent = status.dataset.readyMessage ?? status.textContent ?? '';
                    }
                });

                audio.addEventListener('error', () => {
                    if (activeReplyAudio !== audio) {
                        return;
                    }

                    clearReplyAudioElement();
                    setPlayButtonState(false, true);

                    if (status instanceof HTMLElement) {
                        status.textContent = playButton?.dataset.unavailableMessage ?? status.textContent ?? '';
                    }
                });

                await audio.play();

                voiceDebug('server voice playback started', {
                    locale,
                    provider: 'openai',
                });

                return {
                    status: 'played',
                };
            } catch (error) {
                const aborted = error instanceof DOMException && error.name === 'AbortError';

                voiceDebug(aborted ? 'server voice request aborted' : 'server voice playback error', {
                    locale,
                    message: error instanceof Error ? error.message : String(error),
                });

                if (!aborted && status instanceof HTMLElement && playButton?.dataset.fallbackMessage) {
                    status.textContent = playButton.dataset.fallbackMessage;
                }

                return {
                    status: aborted ? 'aborted' : 'failed',
                };
            } finally {
                if (activeReplyAudioAbortController === abortController) {
                    activeReplyAudioAbortController = null;
                }
            }
        };

        const speakAnswer = async (
            text = currentAnswerText,
            locale = currentAnswerSpeechLocale,
            {
                userInitiated = false,
            } = {},
        ) => {
            const playbackRequestToken = ++speechVoiceRequestToken;
            const normalizedText = String(text ?? '').trim();
            const spokenText = prepareAnswerTextForSpeech(normalizedText, locale);

            if (spokenText === '') {
                cancelSpokenReply(false);
                return;
            }

            const serverPlayback = await playServerReplyAudio(spokenText, locale, {
                playbackRequestToken,
            });

            if (serverPlayback.status === 'played' || serverPlayback.status === 'aborted') {
                return;
            }

            if (!canUseBrowserSpeech()) {
                setPlayButtonState(false, currentAnswerText.trim() !== '');

                if (status instanceof HTMLElement) {
                    status.textContent = playButton?.dataset.unavailableMessage ?? status.textContent ?? '';
                }

                return;
            }

            await waitForSpeechVoices(userInitiated ? 2200 : 1800);

            if (playbackRequestToken !== speechVoiceRequestToken) {
                voiceDebug('playback aborted before selection', {
                    locale,
                    reason: 'superseded-request',
                });
                return;
            }

            const synthesis = window.speechSynthesis;
            const utterance = new SpeechSynthesisUtterance(spokenText);
            const speechProfile = resolveAssistantSpeechProfile(locale);
            const preferredVoice = findSpeechVoice(locale);
            const selectedVoice = preferredVoice
                ?? availableSpeechVoices.find((voice) => voice.default)
                ?? availableSpeechVoices[0]
                ?? null;
            const fallbackReason = preferredVoice
                ? null
                : (selectedVoice ? 'default-ranked-fallback' : 'browser-default-no-voice');
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

            voiceDebug('voice selected', {
                locale,
                voice: selectedVoice?.name ?? null,
                voiceURI: selectedVoice?.voiceURI ?? null,
                voiceLang: selectedVoice?.lang ?? null,
                fallbackReason,
                voiceCount: availableSpeechVoices.length,
                spokenText,
            });

            if (selectedVoice) {
                utterance.voice = selectedVoice;
            }

            utterance.addEventListener('start', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                startWolfiSpeechFallbackPulse();

                if (status instanceof HTMLElement) {
                    status.textContent = playButton?.dataset.speaking ?? status.textContent ?? '';
                }

                setPlayButtonState(true);
            });

            utterance.addEventListener('boundary', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                pulseWolfiVoiceLevel(0.76, 120);
            });

            utterance.addEventListener('end', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                clearPendingReplyPlayback();
                activeReplyUtterance = null;
                stopWolfiVoiceReactivity();
                setPlayButtonState(false, true);

                if (status instanceof HTMLElement) {
                    status.textContent = status.dataset.readyMessage ?? status.textContent ?? '';
                }
            });

            utterance.addEventListener('error', () => {
                if (activeReplyUtterance !== utterance) {
                    return;
                }

                clearPendingReplyPlayback();
                activeReplyUtterance = null;
                stopWolfiVoiceReactivity();
                setPlayButtonState(false, true);

                if (status instanceof HTMLElement) {
                    status.textContent = userInitiated && introBlockedMessage !== ''
                        ? introBlockedMessage
                        : (status.dataset.readyMessage ?? status.textContent ?? '');
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
                        stopWolfiVoiceReactivity();
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
            userQuestion = '',
            answer = '',
            source = 'fallback',
            intent = 'default',
        } = {}) => {
            return {
                locale: localeBase,
                speech_locale: speechLocaleMap[localeBase] ?? activeSpeechLocale,
                question,
                user_question: userQuestion || question,
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

        const buildSupportFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.support_fallback ?? '',
            source: 'fallback',
            intent: 'support',
        });

        const buildPlanFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.plan_fallback ?? '',
            source: 'fallback',
            intent: 'plans',
        });

        const buildTrialFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.trial_fallback ?? '',
            source: 'fallback',
            intent: 'trial',
        });

        const buildPayoutFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.payout_fallback ?? '',
            source: 'fallback',
            intent: 'payout',
        });

        const buildRulesFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.rules_fallback ?? '',
            source: 'fallback',
            intent: 'rules',
        });

        const buildCheckoutFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.checkout_fallback ?? '',
            source: 'fallback',
            intent: 'checkout',
        });

        const buildDiscountFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.discount_fallback ?? '',
            source: 'fallback',
            intent: 'discount',
        });

        const buildGeneralFallbackResponse = (localeBase = pageLocaleBase, userQuestion = '') => buildAssistantResponse({
            localeBase,
            question: userQuestion || (assistantConfig.assistant_name ?? ''),
            userQuestion,
            answer: assistantConfig.general_fallback ?? '',
            source: 'fallback',
            intent: 'default',
        });
        const buildClarificationResponse = (localeBase = pageLocaleBase, suggestions = [], userQuestion = '') => {
            const normalizedSuggestions = uniqueValues(suggestions.map((suggestion) => String(suggestion ?? '').trim()).filter(Boolean)).slice(0, 3);
            const fallbackSuggestions = Array.isArray(assistantConfig.example_questions)
                ? assistantConfig.example_questions.filter(Boolean).slice(0, 3)
                : [];
            const suggestionText = (normalizedSuggestions.length > 0 ? normalizedSuggestions : fallbackSuggestions).join(' / ');
            const template = String(assistantConfig.clarify_intro ?? '').trim();
            const answer = suggestionText !== ''
                ? template.replace(':suggestions', suggestionText)
                : (assistantConfig.general_fallback ?? '');

            return buildAssistantResponse({
                localeBase,
                question: userQuestion || (assistantConfig.clarify_title ?? assistantConfig.assistant_name ?? ''),
                userQuestion,
                answer,
                source: 'clarify',
                intent: 'default',
            });
        };

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

        const scoreVoiceItem = (item, normalizedQuery, searchTerms, meaningfulTokens, contextTokens, preferredLocales, intents) => {
            let score = 0;
            const matchedSearchTerms = searchTerms.filter((term) => item.haystack.includes(term));
            const matchedMeaningfulTokens = meaningfulTokens.filter((token) => item.haystack.includes(token));
            const matchedContextTokens = contextTokens.filter((token) => item.haystack.includes(token));
            const matchedPhraseTerms = searchTerms.filter((term) => term.includes(' ') && item.haystack.includes(term));

            if (normalizedQuery !== '' && item.questionNormalized === normalizedQuery) {
                score += 240;
            }

            if (normalizedQuery.length >= 8 && item.questionNormalized.startsWith(normalizedQuery)) {
                score += 140;
            }

            if (normalizedQuery.length >= 10 && item.questionNormalized.includes(normalizedQuery)) {
                score += 110;
            }

            if (normalizedQuery.length >= 12 && item.haystack.includes(normalizedQuery)) {
                score += 80;
            }

            if (preferredLocales[0] === item.localeBase) {
                score += 48;
            } else if (preferredLocales.includes(item.localeBase)) {
                score += 24;
            }

            meaningfulTokens.forEach((token, index) => {
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

            score += matchedMeaningfulTokens.length * 10;
            score += matchedContextTokens.length * 6;
            score += matchedPhraseTerms.length * 12;

            if (matchedMeaningfulTokens.length >= Math.min(3, meaningfulTokens.length) && matchedMeaningfulTokens.length > 0) {
                score += 18;
            }

            intents.forEach((intent) => {
                assistantIntentProfiles[intent]?.forEach((keyword) => {
                    if (item.haystack.includes(normalizeText(keyword))) {
                        score += 6;
                    }
                });
            });

            return {
                score,
                matchedSearchTermsCount: matchedSearchTerms.length,
                matchedMeaningfulTokensCount: matchedMeaningfulTokens.length,
                matchedContextTokensCount: matchedContextTokens.length,
                coverage: meaningfulTokens.length > 0
                    ? matchedMeaningfulTokens.length / meaningfulTokens.length
                    : 0,
            };
        };

        const findBestResponse = (query) => {
            const {
                normalizedQuery,
                rawTokens,
                meaningfulTokens,
                phraseTokens,
            } = extractAssistantSearchTerms(query);
            const preferredLocales = getPreferredAssistantLocales(query);
            const followupQuery = isFollowupQuery(query, meaningfulTokens);
            let intents = detectAssistantIntents(query);

            if (followupQuery && lastResolvedContext.intent !== 'default' && !intents.includes(lastResolvedContext.intent)) {
                intents = [
                    ...intents,
                    lastResolvedContext.intent,
                ];
            }

            const contextTokens = followupQuery
                ? lastResolvedContext.terms.slice(0, 4)
                : [];
            const searchTerms = uniqueValues([
                ...meaningfulTokens,
                ...phraseTokens,
                ...contextTokens,
            ]);

            if (normalizedQuery === '' || (intents.includes('greeting') && rawTokens.length <= 4)) {
                return buildIntroResponse(preferredLocales[0] ?? pageLocaleBase);
            }

            const rankedMatches = enrichedVoiceIndex
                .map((item) => ({
                    ...item,
                    ...scoreVoiceItem(item, normalizedQuery, searchTerms, meaningfulTokens, contextTokens, preferredLocales, intents),
                }))
                .sort((left, right) => right.score - left.score);
            const positiveMatches = rankedMatches.filter((item) => item.score > 0);
            const bestMatch = positiveMatches[0] ?? null;
            const runnerUp = positiveMatches[1] ?? null;
            const hasStrongFaqMatch = Boolean(bestMatch) && (
                bestMatch.score >= 82
                || (
                    bestMatch.matchedMeaningfulTokensCount >= Math.max(2, Math.min(3, meaningfulTokens.length))
                    && bestMatch.coverage >= 0.55
                )
                || (
                    bestMatch.score >= 56
                    && bestMatch.coverage >= 0.38
                    && (!runnerUp || bestMatch.score - runnerUp.score >= 14)
                )
                || (
                    meaningfulTokens.length <= 2
                    && bestMatch.score >= 68
                    && bestMatch.coverage >= 1
                    && (
                        bestMatch.questionNormalized === normalizedQuery
                        || bestMatch.questionNormalized.includes(normalizedQuery)
                        || bestMatch.matchedPhraseTermsCount > 0
                    )
                )
            );

            if (hasStrongFaqMatch) {
                return {
                    ...bestMatch,
                    user_question: query,
                    source: 'faq',
                    intent: intents[0] ?? 'default',
                };
            }

            if (intents.includes('payout') && String(assistantConfig.payout_fallback ?? '').trim() !== '') {
                return buildPayoutFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            if (intents.includes('trial') && String(assistantConfig.trial_fallback ?? '').trim() !== '') {
                return buildTrialFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            if (intents.includes('rules') && String(assistantConfig.rules_fallback ?? '').trim() !== '') {
                return buildRulesFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            if (intents.includes('plans') && String(assistantConfig.plan_fallback ?? '').trim() !== '') {
                return buildPlanFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            if ((intents.includes('checkout') || intents.includes('login')) && String(assistantConfig.checkout_fallback ?? '').trim() !== '') {
                return buildCheckoutFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            if (intents.includes('discount') && String(assistantConfig.discount_fallback ?? '').trim() !== '') {
                return buildDiscountFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            if ((intents.includes('support') || intents.includes('login')) && String(assistantConfig.support_fallback ?? '').trim() !== '') {
                return buildSupportFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
            }

            const clarificationCandidates = positiveMatches
                .filter((item) => item.localeBase === (preferredLocales[0] ?? pageLocaleBase) && item.score >= 20)
                .slice(0, 3)
                .map((item) => item.question);

            if (clarificationCandidates.length > 0) {
                return buildClarificationResponse(preferredLocales[0] ?? pageLocaleBase, clarificationCandidates, query);
            }

            if (String(assistantConfig.general_fallback ?? '').trim() !== '') {
                return buildGeneralFallbackResponse(preferredLocales[0] ?? pageLocaleBase, query);
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
            syncConversationLocale(enhancedResponse.locale ?? pageLocaleBase);
            rememberResolvedContext(enhancedResponse);

            animateAnswerQuestion(enhancedResponse.user_question ?? enhancedResponse.question ?? '');
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

            if (!autoSpeak || !canPlayVoiceReplies()) {
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
            const detectedLocales = getPreferredAssistantLocales(query);

            if (normalizedQuery !== '') {
                hasConversation = true;
                syncConversationLocale(detectedLocales[0] ?? activeConversationLocale);
            }

            presentResponse(findBestResponse(query), {
                autoSpeak,
                userInitiated,
            });
        };

        const activateIntro = ({
            userInitiated = false,
            force = false,
            focusInputAfterRender = true,
        } = {}) => {
            if (!force && (hasConversation || hasPlayedIntro) && currentAnswerText.trim() !== '') {
                revealSuggestionPrompts();
                if (focusInputAfterRender) {
                    focusInput();
                }
                return;
            }

            hasPlayedIntro = true;
            resetSuggestionPrompts();
            presentResponse(buildIntroResponse(getPreferredAssistantLocales(input instanceof HTMLInputElement ? input.value : '')[0] ?? pageLocaleBase), {
                autoSpeak: userInitiated,
                userInitiated,
                statusMessage: status?.dataset.readyMessage ?? status?.textContent ?? '',
            });
            if (focusInputAfterRender) {
                focusInput();
            }
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
            if (!canPlayVoiceReplies()) {
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

                pendingStatusMessage = status?.dataset.emptyState ?? status?.textContent ?? '';

                try {
                    recognition.lang = resolveSpeechLocale([
                        activeConversationLocale,
                        ...speechRecognitionLocales,
                    ]);
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

                if (queuedReplyPlayback && canPlayVoiceReplies()) {
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
            askQuestion: (question, { userInitiated = true, focusInputAfterRender = true } = {}) => {
                const nextQuestion = String(question ?? '').trim();

                if (nextQuestion === '' || !(input instanceof HTMLInputElement)) {
                    activateIntro({
                        userInitiated,
                        force: true,
                        focusInputAfterRender,
                    });
                    return;
                }

                input.value = nextQuestion;
                renderAnswer(nextQuestion, {
                    userInitiated,
                });
                if (focusInputAfterRender) {
                    focusInput();
                }
            },
            startListening: () => {
                if (micButton instanceof HTMLButtonElement && !micButton.disabled) {
                    micButton.click();
                    return true;
                }

                focusInput();
                return false;
            },
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
    const wolfiFabVideos = [...document.querySelectorAll('[data-wolfi-fab-video]')]
        .filter((video) => video instanceof HTMLVideoElement);

    if (wolfiModal instanceof HTMLElement) {
        const closeButtons = wolfiModal.querySelectorAll('[data-wolfi-close]');
        const wolfiModalCard = wolfiModal.querySelector('[data-wolfi-card]');
        const modalAssistant = wolfiModal.querySelector('[data-voice-assistant]');
        const modalController = modalAssistant?.__wolfiController ?? null;
        const modalStatus = modalAssistant?.querySelector('[data-voice-status]');
        const modalMicButton = modalAssistant?.querySelector('[data-voice-mic]');
        const modalPlayButton = modalAssistant?.querySelector('[data-voice-play]');
        const wolfiAvatarShell = wolfiModal.querySelector('.wolfi-avatar-shell');
        const wolfiAvatarVideo = wolfiModal.querySelector('[data-wolfi-avatar-video]');
        const wolfiHeroTitle = wolfiModal.querySelector('[data-wolfi-hero-title]');
        const wolfiHeroStatus = wolfiModal.querySelector('[data-wolfi-hero-status]');
        const wolfiTalkControl = wolfiModal.querySelector('[data-wolfi-talk-control]');
        const wolfiTalkControlLabel = wolfiTalkControl?.querySelector('[data-wolfi-talk-control-label]');
        const modalMotionQuery = typeof window.matchMedia === 'function'
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        const coarsePointerQuery = typeof window.matchMedia === 'function'
            ? window.matchMedia('(pointer: coarse)')
            : null;
        let modalIsOpen = false;
        let modalHideTimeoutId = null;
        let lastWolfiTrigger = null;
        let lockedWolfiScrollY = 0;
        let wolfiScrollLocked = false;

        const prefersReducedMotion = () => modalMotionQuery?.matches ?? false;
        const isLikelyTouchDevice = () => (coarsePointerQuery?.matches ?? false) || (window.navigator.maxTouchPoints ?? 0) > 0;
        const getWolfiTransitionDuration = () => (prefersReducedMotion() ? 20 : 360);

        const clearWolfiModalHideTimeout = () => {
            if (modalHideTimeoutId !== null) {
                window.clearTimeout(modalHideTimeoutId);
                modalHideTimeoutId = null;
            }
        };

        const scrollWolfiModalToTop = () => {
            if (!(wolfiModalCard instanceof HTMLElement)) {
                return;
            }

            if (typeof wolfiModalCard.scrollTo === 'function') {
                wolfiModalCard.scrollTo({
                    top: 0,
                    behavior: 'auto',
                });
                return;
            }

            wolfiModalCard.scrollTop = 0;
        };

        const lockWolfiPageScroll = () => {
            if (wolfiScrollLocked) {
                return;
            }

            lockedWolfiScrollY = window.scrollY || document.documentElement.scrollTop || 0;
            wolfiScrollLocked = true;
            document.documentElement.classList.add('wolfi-modal-scroll-lock');
            document.body.classList.add('wolfi-modal-scroll-lock');
            document.body.style.position = 'fixed';
            document.body.style.top = `-${lockedWolfiScrollY}px`;
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
        };

        const unlockWolfiPageScroll = () => {
            if (!wolfiScrollLocked) {
                return;
            }

            wolfiScrollLocked = false;
            document.documentElement.classList.remove('wolfi-modal-scroll-lock');
            document.body.classList.remove('wolfi-modal-scroll-lock');
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';

            window.requestAnimationFrame(() => {
                window.scrollTo({
                    top: lockedWolfiScrollY,
                    left: 0,
                    behavior: 'auto',
                });
            });
        };

        const focusWolfiModalTopControl = () => {
            const focusTarget = wolfiTalkControl instanceof HTMLButtonElement
                ? wolfiTalkControl
                : (closeButtons[0] instanceof HTMLElement ? closeButtons[0] : null);

            if (!(focusTarget instanceof HTMLElement)) {
                return;
            }

            window.requestAnimationFrame(() => {
                try {
                    focusTarget.focus({
                        preventScroll: true,
                    });
                } catch (error) {
                    if (!isLikelyTouchDevice()) {
                        focusTarget.focus();
                    }
                }
            });
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

        const syncWolfiFabPlayback = (active) => {
            if (isLikelyTouchDevice()) {
                wolfiFabVideos.forEach((video) => {
                    video.pause();
                });
                return;
            }

            wolfiFabVideos.forEach((video) => {
                if (!active) {
                    video.pause();

                    if (video.currentTime > 0.01) {
                        try {
                            video.currentTime = 0;
                        } catch (error) {
                            // Ignore seek failures while returning the launcher to its idle frame.
                        }
                    }

                    return;
                }

                video.muted = true;

                const playPromise = video.play();

                if (playPromise instanceof Promise) {
                    playPromise.catch(() => {});
                }
            });
        };

        const syncWolfiLaunchButtons = ({
            open = modalIsOpen,
            speaking = wolfiModal.classList.contains('is-speaking'),
            listening = wolfiModal.classList.contains('is-listening'),
            rendering = wolfiModal.classList.contains('is-rendering'),
        } = {}) => {
            wolfiLaunchButtons.forEach((button) => {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                button.classList.toggle('is-open', open);
                button.classList.toggle('is-speaking', open && speaking);
                button.classList.toggle('is-listening', open && listening);
                button.classList.toggle('is-rendering', open && rendering);
            });

            syncWolfiFabPlayback(open && (speaking || listening || rendering));
        };

        const syncWolfiHeroState = ({
            open = modalIsOpen,
            speaking = wolfiModal.classList.contains('is-speaking'),
            listening = wolfiModal.classList.contains('is-listening'),
            rendering = wolfiModal.classList.contains('is-rendering'),
        } = {}) => {
            const nextState = listening
                ? 'listening'
                : (speaking ? 'speaking' : (rendering ? 'rendering' : 'idle'));

            if (wolfiHeroTitle instanceof HTMLElement) {
                const nextTitle = wolfiHeroTitle.dataset[nextState] ?? '';

                if (nextTitle !== '') {
                    wolfiHeroTitle.textContent = nextTitle;
                }
            }

            if (wolfiHeroStatus instanceof HTMLElement) {
                const nextStatus = modalStatus instanceof HTMLElement && modalStatus.textContent?.trim() !== ''
                    ? modalStatus.textContent.trim()
                    : (wolfiHeroStatus.dataset.default ?? wolfiHeroStatus.textContent ?? '');

                wolfiHeroStatus.textContent = nextStatus;
            }

            if (wolfiTalkControl instanceof HTMLButtonElement) {
                const nextLabel = speaking
                    ? (wolfiTalkControl.dataset.speakingLabel ?? '')
                    : (listening
                        ? (wolfiTalkControl.dataset.listeningLabel ?? '')
                        : (rendering
                            ? (wolfiTalkControl.dataset.renderingLabel ?? '')
                            : (wolfiTalkControl.dataset.idleLabel ?? '')));

                if (wolfiTalkControlLabel instanceof HTMLElement && nextLabel !== '') {
                    wolfiTalkControlLabel.textContent = nextLabel;
                }

                wolfiTalkControl.classList.toggle('is-active', open && (speaking || listening));
                wolfiTalkControl.classList.toggle('is-thinking', open && rendering);
                wolfiTalkControl.disabled = !open;
                wolfiTalkControl.setAttribute('aria-pressed', (speaking || listening) ? 'true' : 'false');
            }
        };

        if (modalStatus instanceof HTMLElement && typeof MutationObserver !== 'undefined') {
            const wolfiStatusObserver = new MutationObserver(() => {
                syncWolfiHeroState();
            });

            wolfiStatusObserver.observe(modalStatus, {
                characterData: true,
                childList: true,
                subtree: true,
            });
        }

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
            const triggerQuestion = triggerButton instanceof HTMLElement
                ? (triggerButton.dataset.wolfiQuestion?.trim() ?? '')
                : '';

            if (open && modalIsOpen) {
                scrollWolfiModalToTop();

                if (triggerQuestion !== '') {
                    modalController?.askQuestion?.(triggerQuestion, {
                        userInitiated: false,
                        focusInputAfterRender: false,
                    });
                } else {
                    modalController?.activateIntro?.({
                        userInitiated: false,
                        force: true,
                        focusInputAfterRender: false,
                    });
                }
                focusWolfiModalTopControl();
                return;
            }

            if (!open && !modalIsOpen) {
                return;
            }

            if (open) {
                modalIsOpen = true;
                clearWolfiModalHideTimeout();
                lockWolfiPageScroll();

                if (triggerButton instanceof HTMLElement) {
                    lastWolfiTrigger = triggerButton;
                }

                scrollWolfiModalToTop();
                wolfiModal.classList.remove('hidden');
                wolfiModal.classList.add('flex', 'is-mounted');
                wolfiModal.setAttribute('aria-hidden', 'false');
                syncWolfiLaunchButtons({
                    open: true,
                    speaking: false,
                    listening: false,
                    rendering: false,
                });
                syncWolfiHeroState({
                    open: true,
                    speaking: false,
                    listening: false,
                    rendering: false,
                });

                window.requestAnimationFrame(() => {
                    wolfiModal.classList.add('is-open');
                    syncWolfiAvatarPlayback({
                        active: true,
                    });
                });

                if (triggerQuestion !== '') {
                    modalController?.askQuestion?.(triggerQuestion, {
                        userInitiated: false,
                        focusInputAfterRender: false,
                    });
                } else {
                    modalController?.activateIntro({
                        userInitiated: false,
                        force: true,
                        focusInputAfterRender: false,
                    });
                }
                focusWolfiModalTopControl();
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
                rendering: false,
            });
            syncWolfiHeroState({
                open: false,
                speaking: false,
                listening: false,
                rendering: false,
            });
            syncWolfiAvatarPlayback({
                active: false,
            });
            scheduleWolfiModalHide();
            unlockWolfiPageScroll();

            if (lastWolfiTrigger instanceof HTMLElement) {
                const triggerToFocus = lastWolfiTrigger;
                window.setTimeout(() => {
                    try {
                        triggerToFocus.focus({
                            preventScroll: true,
                        });
                    } catch (error) {
                        if (!isLikelyTouchDevice()) {
                            triggerToFocus.focus();
                        }
                    }
                }, prefersReducedMotion() ? 0 : 140);
            }
        };

        wolfiModal.addEventListener('wolfi:statechange', (event) => {
            syncWolfiLaunchButtons({
                open: modalIsOpen,
                speaking: Boolean(event.detail?.speaking),
                listening: Boolean(event.detail?.listening),
                rendering: Boolean(event.detail?.rendering),
            });
            syncWolfiHeroState({
                open: modalIsOpen,
                speaking: Boolean(event.detail?.speaking),
                listening: Boolean(event.detail?.listening),
                rendering: Boolean(event.detail?.rendering),
            });
            syncWolfiAvatarPlayback({
                active: Boolean(event.detail?.speaking)
                    || Boolean(event.detail?.listening)
                    || Boolean(event.detail?.rendering),
            });
        });

        if (wolfiTalkControl instanceof HTMLButtonElement) {
            wolfiTalkControl.addEventListener('click', () => {
                if (!modalIsOpen) {
                    return;
                }

                if (wolfiModal.classList.contains('is-speaking') && modalPlayButton instanceof HTMLButtonElement) {
                    modalPlayButton.click();
                    return;
                }

                if (modalMicButton instanceof HTMLButtonElement) {
                    modalController?.startListening?.() || modalMicButton.click();
                    return;
                }

                modalController?.focusInput?.();
            });
        }

        const wolfiLaunchGestures = new WeakMap();
        const wolfiLaunchTapMovementLimit = 14;
        const wolfiLaunchSyntheticClickWindow = 700;

        const getWolfiPointerPoint = (event) => {
            if ('clientX' in event && 'clientY' in event) {
                return {
                    x: event.clientX,
                    y: event.clientY,
                };
            }

            const touch = event.touches?.[0] ?? event.changedTouches?.[0] ?? null;

            if (touch) {
                return {
                    x: touch.clientX,
                    y: touch.clientY,
                };
            }

            return null;
        };

        const getWolfiLaunchGesture = (button) => wolfiLaunchGestures.get(button) ?? {
            startX: 0,
            startY: 0,
            moved: false,
            handledClickUntil: 0,
            ignoreClickUntil: 0,
        };

        const setWolfiLaunchGesture = (button, nextState) => {
            wolfiLaunchGestures.set(button, {
                ...getWolfiLaunchGesture(button),
                ...nextState,
            });
        };

        const beginWolfiLaunchGesture = (button, event) => {
            const point = getWolfiPointerPoint(event);

            if (!point) {
                return;
            }

            setWolfiLaunchGesture(button, {
                startX: point.x,
                startY: point.y,
                moved: false,
            });
        };

        const trackWolfiLaunchGesture = (button, event) => {
            const state = wolfiLaunchGestures.get(button);
            const point = getWolfiPointerPoint(event);

            if (!state || !point) {
                return;
            }

            const movedX = Math.abs(point.x - state.startX);
            const movedY = Math.abs(point.y - state.startY);

            if (movedX > wolfiLaunchTapMovementLimit || movedY > wolfiLaunchTapMovementLimit) {
                state.moved = true;
            }
        };

        const openWolfiFromLaunchButton = (button, event) => {
            event.preventDefault();
            event.stopPropagation();
            setWolfiModalState(true, {
                userInitiated: true,
                triggerButton: button,
            });
        };

        wolfiLaunchButtons.forEach((button) => {
            button.addEventListener('pointerdown', (event) => {
                beginWolfiLaunchGesture(button, event);
            }, { passive: true });

            button.addEventListener('pointermove', (event) => {
                trackWolfiLaunchGesture(button, event);
            }, { passive: true });

            button.addEventListener('pointercancel', () => {
                setWolfiLaunchGesture(button, {
                    ignoreClickUntil: Date.now() + wolfiLaunchSyntheticClickWindow,
                });
            }, { passive: true });

            button.addEventListener('touchstart', (event) => {
                beginWolfiLaunchGesture(button, event);
            }, { passive: true });

            button.addEventListener('touchmove', (event) => {
                trackWolfiLaunchGesture(button, event);
            }, { passive: true });

            button.addEventListener('touchend', (event) => {
                trackWolfiLaunchGesture(button, event);

                const state = getWolfiLaunchGesture(button);
                const suppressUntil = Date.now() + wolfiLaunchSyntheticClickWindow;

                if (state.moved) {
                    setWolfiLaunchGesture(button, {
                        ignoreClickUntil: suppressUntil,
                    });
                    return;
                }

                setWolfiLaunchGesture(button, {
                    handledClickUntil: suppressUntil,
                });
                openWolfiFromLaunchButton(button, event);
            });

            button.addEventListener('touchcancel', () => {
                setWolfiLaunchGesture(button, {
                    ignoreClickUntil: Date.now() + wolfiLaunchSyntheticClickWindow,
                });
            }, { passive: true });

            button.addEventListener('click', (event) => {
                const state = getWolfiLaunchGesture(button);
                const now = Date.now();

                if (state.handledClickUntil > now || state.ignoreClickUntil > now || state.moved) {
                    event.preventDefault();
                    event.stopPropagation();
                    setWolfiLaunchGesture(button, {
                        moved: false,
                        handledClickUntil: 0,
                        ignoreClickUntil: 0,
                    });
                    return;
                }

                openWolfiFromLaunchButton(button, event);
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                setWolfiModalState(false);
            });
        });

        syncWolfiHeroState({
            open: false,
            speaking: false,
            listening: false,
            rendering: false,
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modalIsOpen) {
                setWolfiModalState(false);
            }
        });
    }

    document.querySelectorAll('[data-wolfi-voice-lab]').forEach((voiceLab) => {
        const previewUrl = voiceLab.dataset.previewUrl ?? '';
        const previewText = voiceLab.dataset.previewText ?? '';
        const unavailableMessage = voiceLab.dataset.previewUnavailableMessage ?? 'Voice preview is unavailable right now.';
        const readyMessage = voiceLab.dataset.previewReadyMessage ?? 'Preview ready.';
        const generatingMessage = voiceLab.dataset.previewGeneratingMessage ?? 'Generating preview audio...';
        const sessionMessage = voiceLab.dataset.previewSessionMessage ?? 'Your admin session expired. Please sign in again.';
        const autoplayMessage = voiceLab.dataset.previewAutoplayMessage ?? 'Audio playback was blocked by the browser. Click Play Preview again.';
        const playingTemplate = voiceLab.dataset.previewPlayingTemplate ?? 'Playing __VOICE__ preview...';
        const browserFallbackMessage = voiceLab.dataset.previewBrowserFallbackMessage ?? 'Using browser voice preview.';
        const browserUnavailableMessage = voiceLab.dataset.previewBrowserUnavailableMessage ?? 'Browser voice preview is not available on this device.';
        const statusNode = voiceLab.querySelector('[data-wolfi-voice-status]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            ?? voiceLab.querySelector('input[name="_token"]')?.value
            ?? '';
        const cardNodes = [...voiceLab.querySelectorAll('[data-wolfi-voice-card]')];
        const previewButtons = [...voiceLab.querySelectorAll('[data-wolfi-voice-preview]')];
        const voiceChoices = [...voiceLab.querySelectorAll('[data-wolfi-voice-choice]')];
        const activeClasses = ['border-amber-400/35', 'bg-amber-400/10', 'shadow-[0_20px_60px_rgba(251,191,36,0.14)]'];
        const idleClasses = ['border-white/10', 'bg-slate-950/72'];
        let activeAudio = null;
        let activeAudioUrl = '';
        let activeButton = null;
        let activeRequestController = null;
        let activeUtterance = null;

        const setStatusMessage = (message) => {
            if (!(statusNode instanceof HTMLElement)) {
                return;
            }

            statusNode.textContent = message;
        };

        const setPreviewButtonState = (button, playing) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const playLabel = button.dataset.playLabel ?? 'Play Preview';
            const stopLabel = button.dataset.stopLabel ?? 'Stop Preview';
            button.textContent = playing ? stopLabel : playLabel;
            button.classList.toggle('border-amber-200/50', playing);
            button.classList.toggle('bg-amber-300/20', playing);
        };

        const isAudioResponse = (response) => {
            const contentType = String(response.headers.get('Content-Type') ?? '').toLowerCase();

            return contentType.startsWith('audio/');
        };

        const canUseBrowserSpeech = () => (
            'speechSynthesis' in window
            && typeof window.SpeechSynthesisUtterance === 'function'
        );

        const browserVoices = () => {
            if (!canUseBrowserSpeech()) {
                return [];
            }

            return window.speechSynthesis.getVoices() ?? [];
        };

        const normalizeVoiceKey = (value) => String(value ?? '')
            .toLowerCase()
            .trim();

        const hashString = (value) => {
            let hash = 0;

            for (let index = 0; index < value.length; index += 1) {
                hash = ((hash << 5) - hash) + value.charCodeAt(index);
                hash |= 0;
            }

            return Math.abs(hash);
        };

        const selectBrowserVoice = (voiceId) => {
            const allVoices = browserVoices();

            if (allVoices.length === 0) {
                return null;
            }

            const localeTag = String(document.documentElement.lang ?? 'en').toLowerCase();
            const localeBase = localeTag.split(/[-_]/)[0];
            const localeVoices = allVoices.filter((voice) => {
                const voiceLocale = String(voice.lang ?? '').toLowerCase();
                const voiceLocaleBase = voiceLocale.split(/[-_]/)[0];

                return voiceLocale === localeTag || voiceLocaleBase === localeBase;
            });
            const voicePool = localeVoices.length > 0 ? localeVoices : allVoices;
            const voiceNeedle = normalizeVoiceKey(voiceId);

            const directMatch = voicePool.find((voice) => {
                const key = normalizeVoiceKey(`${voice.name ?? ''} ${voice.voiceURI ?? ''}`);

                return key.includes(voiceNeedle);
            });

            if (directMatch) {
                return directMatch;
            }

            const index = hashString(voiceNeedle || 'wolfi') % voicePool.length;

            return voicePool[index] ?? voicePool[0] ?? null;
        };

        const resolveFailureMessage = async (response) => {
            if (response.redirected && /\/login\b|\/admin\/login\b/.test(response.url)) {
                return sessionMessage;
            }

            const contentType = String(response.headers.get('Content-Type') ?? '').toLowerCase();

            if (contentType.includes('application/json')) {
                try {
                    const payload = await response.json();
                    const message = typeof payload?.message === 'string' ? payload.message.trim() : '';

                    if (message !== '') {
                        return message;
                    }
                } catch (error) {
                    // Ignore parsing errors and continue fallback resolution.
                }
            }

            if (contentType.includes('text/html')) {
                return sessionMessage;
            }

            return unavailableMessage;
        };

        const clearActiveAudio = () => {
            if (activeAudio instanceof HTMLAudioElement) {
                activeAudio.pause();
                activeAudio.removeAttribute('src');
                activeAudio.load();
            }

            activeAudio = null;

            if (activeAudioUrl !== '') {
                URL.revokeObjectURL(activeAudioUrl);
                activeAudioUrl = '';
            }
        };

        const clearBrowserSpeech = () => {
            if (!canUseBrowserSpeech()) {
                activeUtterance = null;
                return;
            }

            try {
                window.speechSynthesis.cancel();
            } catch (error) {
                // Ignore browser speech cancellation errors.
            }

            activeUtterance = null;
        };

        const stopActivePreview = ({ keepStatus = false } = {}) => {
            if (activeRequestController instanceof AbortController) {
                activeRequestController.abort();
            }

            activeRequestController = null;
            clearActiveAudio();
            clearBrowserSpeech();

            if (activeButton instanceof HTMLButtonElement) {
                setPreviewButtonState(activeButton, false);
            }

            activeButton = null;

            if (!keepStatus) {
                setStatusMessage(readyMessage);
            }
        };

        const playBrowserPreview = async (voiceId, button) => {
            if (!canUseBrowserSpeech()) {
                throw new Error(browserUnavailableMessage);
            }

            const speechText = String(previewText ?? '').trim();

            if (speechText === '') {
                throw new Error(unavailableMessage);
            }

            const utterance = new window.SpeechSynthesisUtterance(speechText);
            const voice = selectBrowserVoice(voiceId);

            if (voice) {
                utterance.voice = voice;
                utterance.lang = voice.lang ?? utterance.lang;
            } else {
                utterance.lang = String(document.documentElement.lang ?? 'en');
            }

            utterance.rate = 0.95;
            utterance.pitch = 0.92;
            utterance.volume = 1;

            activeUtterance = utterance;
            activeButton = button;
            setPreviewButtonState(button, true);
            setStatusMessage(browserFallbackMessage);

            return new Promise((resolve, reject) => {
                utterance.onstart = () => {
                    const playingLabel = playingTemplate.replace('__VOICE__', voiceId);
                    setStatusMessage(playingLabel);
                };

                utterance.onend = () => {
                    if (activeUtterance !== utterance) {
                        resolve();
                        return;
                    }

                    stopActivePreview();
                    resolve();
                };

                utterance.onerror = () => {
                    if (activeUtterance !== utterance) {
                        resolve();
                        return;
                    }

                    stopActivePreview({
                        keepStatus: true,
                    });
                    reject(new Error(browserUnavailableMessage));
                };

                try {
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(utterance);
                } catch (error) {
                    reject(error instanceof Error ? error : new Error(browserUnavailableMessage));
                }
            });
        };

        const syncSelectedCards = () => {
            cardNodes.forEach((cardNode) => {
                if (!(cardNode instanceof HTMLElement)) {
                    return;
                }

                const choice = cardNode.querySelector('[data-wolfi-voice-choice]');
                const indicator = cardNode.querySelector('[data-wolfi-selected-indicator]');
                const selected = choice instanceof HTMLInputElement && choice.checked;

                cardNode.classList.remove(...activeClasses, ...idleClasses);
                cardNode.classList.add(...(selected ? activeClasses : idleClasses));

                if (indicator instanceof HTMLElement) {
                    indicator.classList.toggle('hidden', !selected);
                }
            });
        };

        voiceChoices.forEach((choice) => {
            if (!(choice instanceof HTMLInputElement)) {
                return;
            }

            choice.addEventListener('change', syncSelectedCards);
        });

        syncSelectedCards();

        voiceLab.addEventListener('submit', () => {
            stopActivePreview({
                keepStatus: true,
            });
        });

        previewButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', async () => {
                if (previewUrl === '') {
                    setStatusMessage(unavailableMessage);
                    return;
                }

                const voiceId = button.dataset.voiceId ?? '';
                const voiceProvider = button.dataset.voiceProvider ?? '';

                if (voiceId === '') {
                    setStatusMessage(unavailableMessage);
                    return;
                }

                if (activeButton === button && activeAudio instanceof HTMLAudioElement && !activeAudio.paused) {
                    stopActivePreview();
                    return;
                }

                if (activeButton === button && activeUtterance !== null) {
                    stopActivePreview();
                    return;
                }

                stopActivePreview({
                    keepStatus: true,
                });

                setPreviewButtonState(button, true);
                activeButton = button;
                setStatusMessage(generatingMessage);

                const controller = new AbortController();
                activeRequestController = controller;

                try {
                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'audio/mpeg',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            voice_id: voiceId,
                            text: previewText,
                            locale: document.documentElement.lang ?? 'en',
                        }),
                        signal: controller.signal,
                    });

                    if (activeRequestController !== controller) {
                        return;
                    }

                    if (!response.ok || !isAudioResponse(response)) {
                        const failureMessage = await resolveFailureMessage(response);
                        const failureError = new Error(failureMessage);
                        failureError.isProviderFailure = true;
                        throw failureError;
                    }

                    const audioBlob = await response.blob();

                    if (audioBlob.size === 0) {
                        throw new Error(unavailableMessage);
                    }

                    const audioUrl = URL.createObjectURL(audioBlob);
                    const audio = new Audio(audioUrl);
                    audio.preload = 'auto';
                    activeAudio = audio;
                    activeAudioUrl = audioUrl;
                    activeRequestController = null;

                    audio.addEventListener('ended', () => {
                        if (activeAudio !== audio) {
                            return;
                        }

                        stopActivePreview();
                    });

                    audio.addEventListener('error', () => {
                        if (activeAudio !== audio) {
                            return;
                        }

                        stopActivePreview({
                            keepStatus: true,
                        });
                        setStatusMessage(unavailableMessage);
                    });

                    await audio.play();
                    setStatusMessage(playingTemplate.replace('__VOICE__', voiceId));
                } catch (error) {
                    const aborted = error instanceof DOMException && error.name === 'AbortError';

                    stopActivePreview({
                        keepStatus: true,
                    });

                    if (!aborted) {
                        const isPlaybackPermissionIssue = error instanceof DOMException
                            && error.name === 'NotAllowedError';
                        const looksLikeProviderFailure = error?.isProviderFailure === true
                            || (error instanceof Error
                                && error.message.toLowerCase().includes('unavailable'));

                        if (looksLikeProviderFailure) {
                            try {
                                await playBrowserPreview(
                                    voiceProvider === 'web_speech' ? voiceId : 'webspeech-en-guide',
                                    button,
                                );
                                return;
                            } catch (browserError) {
                                const browserMessage = browserError instanceof Error && browserError.message.trim() !== ''
                                    ? browserError.message
                                    : browserUnavailableMessage;
                                setStatusMessage(browserMessage);
                                return;
                            }
                        }

                        const message = isPlaybackPermissionIssue
                            ? autoplayMessage
                            : (error instanceof Error && error.message.trim() !== ''
                                ? error.message
                                : unavailableMessage);
                        setStatusMessage(message);
                    }
                } finally {
                    if (activeRequestController === controller) {
                        activeRequestController = null;
                    }
                }
            });
        });
    });

    document.querySelectorAll('[data-flash]').forEach((flash) => {
        window.setTimeout(() => {
            flash.classList.add('opacity-0', 'translate-y-2');
        }, 3600);
    });

    const backToTopButton = document.querySelector('[data-back-to-top]');

    if (backToTopButton instanceof HTMLButtonElement) {
        const prefersReducedMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const tapMovementLimit = 14;
        let backToTopStartPoint = null;
        let backToTopGestureMoved = false;

        const syncBackToTopState = () => {
            backToTopButton.classList.toggle('is-visible', window.scrollY > 720);
        };

        const getTouchPoint = (event) => {
            const touch = event.touches?.[0] ?? event.changedTouches?.[0] ?? null;

            return touch
                ? {
                    x: touch.clientX,
                    y: touch.clientY,
                }
                : null;
        };

        backToTopButton.addEventListener('touchstart', (event) => {
            backToTopStartPoint = getTouchPoint(event);
            backToTopGestureMoved = false;
        }, { passive: true });

        backToTopButton.addEventListener('touchmove', (event) => {
            const point = getTouchPoint(event);

            if (!backToTopStartPoint || !point) {
                return;
            }

            if (
                Math.abs(point.x - backToTopStartPoint.x) > tapMovementLimit
                || Math.abs(point.y - backToTopStartPoint.y) > tapMovementLimit
            ) {
                backToTopGestureMoved = true;
            }
        }, { passive: true });

        backToTopButton.addEventListener('touchcancel', () => {
            backToTopGestureMoved = true;
        }, { passive: true });

        backToTopButton.addEventListener('click', (event) => {
            if (backToTopGestureMoved) {
                event.preventDefault();
                event.stopPropagation();
                backToTopGestureMoved = false;
                backToTopStartPoint = null;
                return;
            }

            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
            });
        });

        syncBackToTopState();
        window.addEventListener('scroll', syncBackToTopState, { passive: true });
    }

    const counterElements = [...document.querySelectorAll('[data-counter]')];

    if (counterElements.length > 0) {
        const prefersReducedMotion = typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const animateCounter = (element) => {
            if (!(element instanceof HTMLElement) || element.dataset.counterAnimated === 'true') {
                return;
            }

            const valueNode = element.querySelector('[data-counter-value]');
            const target = Number.parseInt(element.dataset.counterTarget ?? '', 10);

            if (!(valueNode instanceof HTMLElement) || !Number.isFinite(target)) {
                return;
            }

            element.dataset.counterAnimated = 'true';

            if (prefersReducedMotion) {
                valueNode.textContent = String(target);
                return;
            }

            const duration = 1400;
            const startTime = performance.now();
            valueNode.textContent = '0';

            const step = (timestamp) => {
                const progress = Math.min((timestamp - startTime) / duration, 1);
                const eased = 1 - ((1 - progress) ** 3);
                valueNode.textContent = String(Math.round(target * eased));

                if (progress < 1) {
                    window.requestAnimationFrame(step);
                    return;
                }

                valueNode.textContent = String(target);
            };

            window.requestAnimationFrame(step);
        };

        if (typeof window.IntersectionObserver !== 'function' || prefersReducedMotion) {
            counterElements.forEach((element) => {
                animateCounter(element);
            });
        } else {
            const counterObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                });
            }, {
                threshold: 0.4,
            });

            counterElements.forEach((element) => {
                counterObserver.observe(element);
            });
        }
    }

    initWolfiDashboard();
});
