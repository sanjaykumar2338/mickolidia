<?php

$en = require __DIR__.'/../en/site.php';

return array_replace_recursive($en, [
    'meta' => [
        'default_title' => 'Piattaforma Prop Firm Wolforix',
        'description' => 'Base premium multilingua per la piattaforma prop firm Wolforix con pagine pubbliche, struttura legale e accesso autenticato alla dashboard.',
    ],
    'languages' => [
        'en' => 'Inglese',
        'de' => 'Tedesco',
        'es' => 'Spagnolo',
        'fr' => 'Francese',
        'hi' => 'Hindi',
        'it' => 'Italiano',
        'pt' => 'Portoghese',
    ],
    'locale' => [
        'current_label' => 'Lingua',
        'menu_title' => 'Seleziona lingua',
        'future_label' => 'Pronto per altre lingue',
    ],
    'public_layout' => [
        'preview_badge' => 'Anteprima piattaforma',
        'simulated_notice' => 'Fai trading senza paura. Vinci davvero.',
    ],
    'ai_assistant' => [
        'name' => 'Wolfi',
        'eyebrow' => 'Assistente Wolfi',
        'title' => 'WOLFI',
        'description' => 'Lascia che Wolfi ti guidi. Il tuo assistente esperto per regole, payout, accesso MT5 e il prossimo passo giusto sulla piattaforma.',
        'home_headline' => 'Lascia che Wolfi ti guidi.',
        'home_description' => 'Il tuo assistente esperto per regole, payout, accesso MT5 e il prossimo passo giusto sulla piattaforma.',
        'multi_language' => 'Disponibile 24/7',
        'start_chat' => 'Parla con Wolfi',
        'floating_label' => 'Apri Wolfi',
        'floating_cta' => 'Chiedi a Wolfi',
        'floating_aria' => 'Apri Wolfi, il tuo assistente AI',
        'close_aria' => 'Chiudi Wolfi',
        'preview_label' => 'Anteprima Wolfi',
        'preview_title' => 'Chiedi a Wolfi prima di acquistare',
        'preview_badge' => '24/7',
        'preview_copy' => 'Apri l’assistente per avere chiarezza immediata su regole, news trading, limiti di drawdown, tempi di payout e quale challenge si adatta meglio al tuo profilo.',
        'visual_title' => 'Sempre attivo. Sempre pronto.',
        'visual_copy' => 'Usa Wolfi come guida visibile per regole, payout, accesso MT5 e domande sulla piattaforma.',
        'visual_response_label' => 'Risposta live',
        'visual_response_preview' => 'Il tuo drawdown massimo su questo account è del 5%.',
        'visual_response_hint' => 'Regole e indicazioni sull’account in tempo reale.',
        'visual_cta_hint' => 'Chiedi a Wolfi prima della prossima operazione.',
        'visual_alt' => 'Illustrazione luminosa della mascotte Wolfi',
        'home_visual_alt' => 'Anteprima approvata della homepage di Wolfi mostrata in un layout mobile',
        'example_questions' => [
            'Posso fare trading durante le news?',
            'Ogni quanto vengono elaborati i payout?',
            'Quale piano è migliore per me?',
        ],
    ],
    'nav' => [
        'home' => 'Home',
        'about' => 'Chi siamo',
        'about_us' => 'Chi siamo',
        'security' => 'Sicurezza',
        'contact' => 'Contattaci',
        'plans' => 'Piani',
        'faq' => 'FAQ',
        'news' => 'NEWS',
        'legal' => 'Legale',
        'dashboard' => 'Dashboard',
        'dashboard_preview' => 'Dashboard',
        'login' => 'Accedi',
        'logout' => 'Esci',
        'search' => 'Cerca',
        'search_aria' => 'Cerca nel sito',
        'menu_open' => 'Apri menu',
        'menu_close' => 'Chiudi menu',
    ],
    'home' => [
        'eyebrow' => 'Prop Trading Moderno',
        'title' => 'Ottieni un account funded. Ricevi payout. Senza limiti di tempo.',
        'description' => 'Supera la challenge. Accedi agli account funded. Ritira velocemente.',
        'mobile_title' => [
            'line_1' => 'Ottieni un account funded.',
            'line_2' => 'Ricevi payout.',
            'line_3' => 'Senza limiti di tempo.',
        ],
        'mobile_description' => [
            'line_1' => 'Supera la challenge. Accedi agli account funded.',
            'line_2' => 'Ritira velocemente.',
        ],
        'primary_cta' => 'Inizia la challenge',
        'free_trial_cta' => 'Prova gratuita',
        'free_trial_caption' => 'Nessun rischio. Nessuna carta.',
        'secondary_cta' => 'Apri dashboard',
        'days' => 'giorni',
        'badges' => [
            'Infrastruttura sicura',
            'Trading rewards in 24h',
            'Market Pulse (news live)',
            'Assistente AI Wolfi',
        ],
        'feature_cards' => [
            'Accesso rapido al funding',
            'Payout veloci',
            'Scaling del capitale +25%',
            'Fino al 90% di profit split',
        ],
        'hero_visual' => [
            'label' => 'Anteprima trading desk',
            'platform' => 'Workspace esecutivo dark',
            'card_title' => 'Centrato sui grafici. Regole visibili. Creato per sembrare funded.',
            'card_copy' => 'La homepage ora si apre con una visual di trading pulita che ricorda più un vero workspace da prop firm che una card promozionale decorativa.',
            'image_alt' => 'Interfaccia di trading in stile laptop con grafico e pannelli di mercato',
        ],
        'plans' => [
            'eyebrow' => 'Piani challenge',
            'title' => 'Fai trading su oltre 1.000 strumenti con MT5',
            'description' => 'Tutti gli strumenti disponibili nel tuo account MT5 sono supportati e tracciati automaticamente.',
            'platform_label' => 'Piattaforma',
            'platform_value' => 'MT5',
            'badge' => 'Modelli finali pronti per il lancio',
            'entry_fee' => 'Quota di accesso',
            'profit_target' => 'Target di profitto',
            'daily_loss' => 'Perdita giornaliera',
            'max_loss' => 'Perdita totale',
            'steps' => 'Step',
            'profit_share' => 'Profit split',
            'first_payout' => 'Primo prelievo',
            'minimum_days' => 'Giorni minimi di trading',
        ],
        'trust' => [
            'eyebrow' => 'Fiducia / Sicurezza',
            'title' => 'Posizionamento sicurezza visibile fin dal primo accesso.',
            'description' => 'Wolforix sta costruendo fiducia operativa attorno a infrastruttura sicura, controlli di rischio, monitoraggio e percorso di allineamento ISO/IEC 27001 attualmente in corso.',
            'cta' => 'Vedi sicurezza',
            'items' => [
                [
                    'title' => 'Infrastruttura sicura',
                    'description' => 'Hosting protetto e accesso operativo controllato sui sistemi principali.',
                ],
                [
                    'title' => 'Controllo del rischio avanzato',
                    'description' => 'Controlli preventivi e percorsi di revisione progettati per ridurre il rischio operativo.',
                ],
                [
                    'title' => 'Monitoraggio in tempo reale',
                    'description' => 'Visibilità continua su attività di piattaforma, eventi e stato dei servizi.',
                ],
                [
                    'title' => 'Allineamento ISO/IEC 27001',
                    'description' => 'Roadmap di allineamento in corso senza alcuna pretesa di certificazione.',
                ],
            ],
            'support_items' => [
                'Controlli di protezione dei dati con accesso limitato e gestione controllata.',
                'La roadmap di sicurezza e il lavoro di miglioramento continuo restano attivi.',
            ],
        ],
        'global_reach' => [
            'eyebrow' => 'Portata globale',
            'title_prefix' => 'A supporto dei trader in',
            'title_suffix' => 'paesi. Uno standard unico.',
            'description' => 'Wolforix connette trader di tutto il mondo sotto un’unica infrastruttura: veloce, precisa e costruita per la performance.',
            'image_alt' => 'Visual della rete globale di trader Wolforix',
            'visual_label' => 'Copertura globale',
            'visual_status' => 'Espansione live',
            'visual_card_label' => 'Flusso connesso',
            'visual_card_title' => 'Una piattaforma, una community di trading mondiale.',
            'visual_card_copy' => 'Pensata per trader che operano tra sessioni, regioni e cicli di mercato senza perdere velocità, chiarezza o focus esecutivo.',
            'highlights' => [
                [
                    'title' => 'Accesso multi-regione',
                    'description' => 'Dall’Europa all’America Latina, all’Asia e al Medio Oriente.',
                ],
                [
                    'title' => 'Esperienza unificata',
                    'description' => 'Lo stesso flusso challenge, la stessa struttura di payout e la stessa direzione di supporto ovunque.',
                ],
                [
                    'title' => 'Progettato per scalare',
                    'description' => 'Pensato per un pubblico di trader più ampio senza perdere un feel premium.',
                ],
            ],
        ],
        'market_pulse' => [
            'eyebrow' => 'Direzione piattaforma',
            'title' => 'Market Pulse',
            'description' => 'Insight in tempo reale per tradare in modo più intelligente e reagire più velocemente.',
            'cta' => 'Apri news di mercato live',
            'view_all' => 'Vedi il calendario completo',
            'preview_label' => 'Accesso alle news live',
            'preview_copy' => 'Controlla i prossimi eventi macro, i cambi di forecast e i livelli di impatto prima del tuo prossimo trade.',
            'source_caption' => 'Fonte: :source. Orari mostrati in :timezone (:abbr).',
            'empty' => 'Market Pulse sta preparando i prossimi aggiornamenti live. Apri il calendario completo per vedere gli eventi più recenti.',
            'cards' => [
                [
                    'title' => 'Eventi ad alto impatto',
                    'description' => 'Segui i rilasci che possono muovere di più volatilità, spread e condizioni di rischio di breve periodo.',
                ],
                [
                    'title' => 'Focus multi-valuta',
                    'description' => 'Segui USD, EUR, GBP, JPY e altre valute chiave da un unico feed macro live.',
                ],
                [
                    'title' => 'Filtri rapidi sugli eventi',
                    'description' => 'Apri il calendario completo per ordinare per impatto, valuta e orizzonte temporale in pochi secondi.',
                ],
            ],
        ],
        'challenge_selector' => [
            'currency_label' => 'Valuta',
            'type_label' => 'Tipo di challenge',
            'size_label' => 'Dimensione account',
            'insight_title' => 'Panoramica modello',
            'entry_fee' => 'Quota di ingresso',
            'current_price' => 'Prezzo attuale',
            'original_price' => 'Prezzo standard',
            'discount_badge' => '20% OFF - Offerta di lancio limitata',
            'discount_urgency' => 'Sconto di lancio - Tempo limitato',
            'best_value' => 'Miglior valore',
            'start_button' => 'Ottieni il piano',
            'review_policy' => 'Vedi policy payout',
            'faq_link' => 'Leggi FAQ',
            'unlimited' => 'Illimitato',
            'highlights' => [
                'Prezzo di lancio -20% attivo',
                'Primo prelievo dopo 21 giorni',
                'Durata di valutazione illimitata',
            ],
            'currencies' => [
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP',
            ],
            'phase_titles' => [
                'single_phase' => 'Fase unica',
                'phase_1' => 'Fase 1',
                'phase_2' => 'Fase 2',
                'funded' => 'Account funded',
            ],
            'metrics' => [
                'profit_target' => 'Target di profitto',
                'profit_share' => 'Profit split',
                'profit_share_upgrade' => 'Upgrade split',
                'daily_loss' => 'Perdita giornaliera max',
                'total_loss' => 'Perdita totale max',
                'minimum_days' => 'Giorni minimi di trading',
                'first_withdrawal' => 'Primo prelievo',
                'max_trading_days' => 'Giorni massimi di trading',
                'leverage' => 'Leva',
                'payout_cycle' => 'Ciclo payout',
                'scaling' => 'Scaling',
                'consistency_rule' => 'Regola di consistenza',
            ],
            'value_templates' => [
                'days' => ':days giorni',
                'after_days' => 'Dopo :days giorni',
                'scaling' => '+:percent% di capitale ogni :months mesi se profittevole',
                'profit_split_upgrade' => ':percent% dopo :payouts payout consecutivi',
            ],
            'consistency_required' => 'Obbligatoria',
            'types' => [
                'one_step' => [
                    'label' => '1-Step Instant',
                    'description' => 'Supera tutto in un solo step. Ottieni funding più rapidamente. Nessun ritardo. Nessuna seconda fase.',
                    'note_title' => 'Modello funded 1-Step Instant',
                    'note_body' => 'Regole più rigide, controllo del rischio più stretto e accesso diretto a un account funded con consistenza obbligatoria. Meno step. Standard più alti. Risultati più rapidi.',
                ],
                'two_step' => [
                    'label' => '2-Step Pro',
                    'description' => 'Rischio più basso. Maggiore potenziale di scaling. Pensato per costanza e crescita a lungo termine.',
                    'note_title' => 'Modello funded 2-Step Pro',
                    'note_body' => 'Valutazione in due fasi con leva 1:100 in Fase 1, primo payout dopo 21 giorni, payout ogni 14 giorni da quel momento e sistema di scaling per account funded profittevoli. Costruisci consistenza. Scala con decisione.',
                ],
            ],
        ],
        'about' => [
            'eyebrow' => 'Su Wolforix',
            'title' => 'Una nuova generazione di prop firm costruita attorno ad accesso, disciplina e performance.',
            'intro' => 'Wolforix è una proprietary trading firm che rappresenta una nuova generazione di prop firm costruite per sbloccare il potenziale dei trader impegnati in un ambiente equo, accessibile e orientato alla performance.',
            'mission_label' => 'La nostra missione',
            'mission' => 'Individuare, formare e finanziare trader pronti a performare.',
            'pillars' => [
                'Valutazione strutturata',
                'Accesso alla prova gratuita',
                'Funding guidato dalla performance',
            ],
            'blocks' => [
                [
                    'title' => 'Perché esistiamo',
                    'description' => 'Crediamo che il talento da solo non basti. L’accesso al capitale resta il vero ostacolo per molti trader disciplinati che possiedono già la costanza e la mentalità necessarie per avere successo.',
                ],
                [
                    'title' => 'Come avanzano i trader',
                    'description' => 'Attraverso un sistema di valutazione strutturato e opportunità di prova gratuita, i trader possono affinare il proprio processo, acquisire esperienza e dimostrare costanza in un ambiente controllato prima di gestire capitale funded.',
                ],
                [
                    'title' => 'Cosa sostiene Wolforix',
                    'description' => 'Wolforix è supportata da laureati in economia con anni di esperienza nei mercati finanziari, nel trading e negli investimenti. L’azienda viene costruita come un ecosistema trasparente, equo e guidato dalla performance.',
                ],
            ],
            'closing_label' => 'Cosa costruiamo',
            'closing' => 'Non finanziamo soltanto trader. Costruiamo professionisti disciplinati e costanti, capaci di raggiungere successo finanziario nel lungo periodo.',
        ],
    ],
    'news' => [
        'eyebrow' => 'Calendario di rischio macro',
        'title' => 'Calendario economico',
        'description' => 'Monitora le prossime pubblicazioni macro ed economiche per gestire l’esposizione durante sessioni di trading sensibili alla volatilità.',
        'warning_title' => 'Avviso sulla volatilità',
        'warning_copy' => 'Le news ad alto impatto possono aumentare la volatilità e influenzare le condizioni di trading. Monitora gli eventi programmati come parte del tuo processo di gestione del rischio.',
        'data_source_label' => 'Fonte del calendario',
        'mode_demo' => 'Modalità calendario demo',
        'mode_live' => 'Modalità calendario live',
        'demo_notice' => 'Il calendario è attualmente in modalità demo con eventi realistici di esempio finché non vengono configurate credenziali API con licenza.',
        'live_notice' => 'Il calendario sta attualmente usando il provider API con licenza configurato.',
        'timezone_badge' => 'Visualizzazione :timezone (:abbr)',
        'range_caption' => 'Eventi mostrati da :from a :to',
        'filters' => [
            'impact' => 'Impatto',
            'currency' => 'Valuta',
            'range' => 'Intervallo date',
            'high_only' => 'Solo alto impatto',
            'apply' => 'Applica filtri',
            'reset' => 'Resetta',
            'all_impacts' => 'Tutti gli impatti',
            'all_currencies' => 'Tutte le valute',
            'range_options' => [
                'today' => 'Oggi',
                'this_week' => 'Questa settimana',
                'next_week' => 'Prossima settimana',
            ],
        ],
        'table' => [
            'time' => 'Ora',
            'currency' => 'Valuta',
            'impact' => 'Impatto',
            'event' => 'Nome evento',
            'forecast' => 'Previsione',
            'previous' => 'Precedente',
            'empty' => 'Nessun evento del calendario economico corrisponde ai filtri selezionati.',
        ],
        'impact' => [
            'high' => 'Alto',
            'medium' => 'Medio',
            'low' => 'Basso',
        ],
        'sources' => [
            'title' => 'Fonti dati',
            'copy' => 'Per trasparenza, di seguito sono elencati la modalità corrente del calendario, l’architettura del provider live e i siti di riferimento del mercato.',
            'current_demo' => 'Fonte demo corrente',
            'current_live' => 'Fonte live corrente',
            'provider' => 'Architettura provider configurata',
            'reference' => 'Solo riferimento',
            'legal_notice' => 'I siti di riferimento sono elencati solo per consapevolezza del trader. Wolforix non effettua scraping, mirroring o embedding tramite iframe di siti calendario di terze parti.',
        ],
    ],
    'launch_popup' => [
        'title' => '20% OFF - Accesso di lancio in scadenza',
        'description' => 'Attiva il tuo sconto di lancio del 20% prima che l’offerta scompaia.',
        'secondary_copy' => 'I posti disponibili sono limitati. Una volta esauriti, i prezzi aumenteranno.',
        'promo_label' => 'Codice promo',
        'auto_apply_notice' => 'Lo sconto si attiva solo se scegli Ottieni sconto. Se ignori, restano visibili i prezzi regolari.',
        'copy_code' => 'Copia codice',
        'code_copied' => 'Codice copiato',
        'primary_action' => 'Ottieni sconto',
        'secondary_action' => 'Ignora',
        'benefits' => [
            '20% risparmiato subito',
            'Opportunità su capitale reale',
            'Attivazione basata sulla sessione',
        ],
        'close' => 'Chiudi offerta di lancio',
    ],
    'auth' => [
        'eyebrow' => 'Accesso sicuro',
        'title' => 'Accedi o crea il tuo account per continuare.',
        'description' => 'Il checkout delle challenge a pagamento ora richiede autenticazione, così ordini, risultati di pagamento e challenge acquistate restano collegati al corretto account utente.',
        'notice' => 'La challenge e la valuta selezionate verranno mantenute dopo login o registrazione e tornerai direttamente al checkout.',
        'home_action' => 'Torna alla home',
        'dashboard_action' => 'Dashboard',
        'email_placeholder' => 'tuo@email.com',
        'login' => [
            'title' => 'Accedi',
            'copy' => 'Usa le credenziali del tuo account Wolforix esistente per continuare verso il checkout sicuro.',
            'email' => 'Email',
            'password' => 'Password',
            'forgot_password' => 'Password dimenticata?',
            'remember' => 'Mantienimi connesso su questo dispositivo',
            'submit' => 'Accedi',
            'invalid' => 'Queste credenziali non corrispondono ai nostri dati.',
            'social_divider' => 'oppure continua con',
            'social_google' => 'Continua con Google',
            'social_facebook' => 'Continua con Facebook',
            'social_apple' => 'Continua con Apple',
            'social_unavailable_badge' => 'Setup',
            'social_setup_notice' => 'Il social sign-in appare automaticamente quando le credenziali del provider vengono configurate nell’ambiente.',
            'social_unavailable_error' => 'Questo provider di social sign-in non è ancora configurato.',
            'social_failed' => 'Il social sign-in non è stato completato. Riprova oppure usa il login via email.',
            'social_cancelled' => 'Il social sign-in è stato annullato prima del completamento.',
            'social_state_invalid' => 'La sessione di social sign-in è scaduta. Riprova.',
        ],
        'register' => [
            'title' => 'Crea account',
            'copy' => 'Nuovo su Wolforix? Crea prima il tuo account, poi completa i dati di fatturazione e continua con il metodo di pagamento scelto al checkout.',
            'name' => 'Nome completo',
            'email' => 'Email',
            'password' => 'Password',
            'password_confirmation' => 'Conferma password',
            'submit' => 'Crea account',
        ],
        'passwords' => [
            'request' => [
                'title' => 'Reimposta la tua password',
                'copy' => 'Inserisci l’email collegata al tuo account Wolforix e ti invieremo un link sicuro per il reset.',
                'email' => 'Email',
                'submit' => 'Invia link di reset',
                'back_to_login' => 'Torna al login',
            ],
            'reset' => [
                'title' => 'Crea una nuova password',
                'copy' => 'Scegli una nuova password per il tuo account Wolforix.',
                'password' => 'Nuova password',
                'password_confirmation' => 'Conferma nuova password',
                'submit' => 'Aggiorna password',
            ],
            'status' => [
                'sent' => 'Ti abbiamo inviato via email il link per reimpostare la password.',
                'user' => 'Non riusciamo a trovare un utente con questo indirizzo email.',
                'throttled' => 'Attendi prima di riprovare.',
                'token' => 'Questo link di reset password non è valido oppure è scaduto.',
                'reset' => 'La tua password è stata reimpostata. Ora puoi accedere.',
            ],
        ],
    ],
    'trial' => [
        'eyebrow' => 'Prova gratuita',
        'register' => [
            'title' => 'Avvia la tua prova gratuita',
            'description' => 'Registrati con email e password per accedere a un account di prova Wolforix solo demo, con la stessa logica di esecuzione mostrata e la stessa visibilità delle regole dell’ambiente challenge principale.',
            'what_you_get_title' => 'Incluso subito',
            'balance_line' => 'Saldo demo: :amount',
            'take_profit_line' => 'Target take profit: :percent%',
            'minimum_days_line' => 'Giorni minimi di trading: :days',
            'markets_line' => 'Mercati disponibili: :markets',
            'restrictions_line' => 'Solo demo. Nessun prelievo. Non conta come challenge.',
            'email' => 'Email',
            'password' => 'Password',
            'password_placeholder' => 'Minimo 8 caratteri',
            'submit' => 'Crea account prova gratuita',
            'success' => 'Il tuo account prova gratuita è pronto.',
            'existing_account_error' => 'Questa email è già associata a un account Wolforix. Inserisci qui la password corretta per continuare nella prova gratuita.',
        ],
        'dashboard' => [
            'title' => 'Dashboard prova',
            'description' => 'Monitora lo stato attuale del tuo account demo gratuito prima di passare a una valutazione a pagamento.',
            'banner_title' => 'Questo è un account di prova.',
            'banner_copy' => 'L’account usa condizioni solo demo ed è separato da challenge a pagamento, payout e idoneità agli account funded.',
            'passed_title' => 'Hai completato il modello di prova gratuita.',
            'passed_copy' => 'Ottimo lavoro. Hai raggiunto il target della prova rispettando le regole mostrate. Il passo successivo è passare a un Simulation Account per proseguire con un modello di valutazione strutturato.',
            'passed_button' => 'Vedi piani simulation',
            'ended_title' => 'La tua prova è terminata.',
            'ended_copy' => 'Questo account demo non è più attivo perché le regole della prova mostrate sono state violate. Avvia una nuova prova per continuare ad allenarti con la stessa logica di regole.',
            'retry_button' => 'Riprova la prova',
            'restrictions_title' => 'Limiti della prova',
            'restrictions' => [
                'Nessun prelievo',
                'Non conta come challenge',
                'Solo ambiente demo',
            ],
            'markets_title' => 'Mercati disponibili',
            'rules_title' => 'Logica delle regole visualizzata',
            'rule_labels' => [
                'starting_balance' => 'Saldo iniziale',
                'daily_limit' => 'Limite drawdown giornaliero',
                'take_profit' => 'Take profit',
                'max_limit' => 'Limite drawdown massimo',
                'minimum_days' => 'Giorni minimi di trading',
                'status' => 'Stato attuale',
            ],
            'metrics' => [
                'balance' => 'Saldo',
                'equity' => 'Equity',
                'daily_drawdown' => 'Drawdown giornaliero',
                'max_drawdown' => 'Drawdown massimo',
                'profit_loss' => 'Profitto / Perdita',
            ],
        ],
        'milestones' => [
            'three' => 'Stai andando bene.',
            'five' => 'Stai andando bene.',
        ],
        'encouragement_subject' => 'Continua così e migliora la tua performance.',
        'retry' => [
            'success' => 'È stato creato un nuovo account di prova.',
        ],
        'statuses' => [
            'active' => 'Attivo',
            'passed' => 'Superato',
            'ended' => 'Terminato',
        ],
    ],
    'checkout' => [
        'meta_title' => 'Checkout',
        'eyebrow' => 'Checkout sicuro',
        'title' => 'Passa dalla selezione del piano al vero flusso di pagamento.',
        'description' => 'La selezione della challenge resta nella homepage, mentre dati di fatturazione, scelta del provider, creazione ordine e pagamento con redirect sono gestiti in una pagina checkout dedicata.',
        'page_title' => 'Completa il tuo ordine challenge',
        'page_description' => 'Wolforix crea prima un vero record ordine, poi avvia Stripe o PayPal checkout usando prezzi lato server e lo stesso flusso protetto di fulfillment.',
        'secure_badge' => 'Prezzi lato server attivi',
        'order_summary' => 'Riepilogo ordine',
        'supporting_title' => 'Cosa succede dopo',
        'supporting_copy' => 'La challenge selezionata viene prima convertita in un ordine interno, poi il provider scelto gestisce il pagamento esternamente. Stato ordine, retry e futuri gateway aggiuntivi useranno tutti lo stesso flusso interno di acquisto.',
        'kyc_notice' => 'I dati di fatturazione vengono salvati con l’ordine e preparati per future registrazioni, revisioni di compliance e controlli legati ai payout.',
        'helper_points' => [
            'Il prezzo lato server è la fonte di verità, quindi gli importi lato client non vengono mai considerati affidabili durante il checkout.',
            'Gli ordini restano disponibili per un nuovo tentativo se il pagamento viene annullato o fallisce.',
            'Il testo di consenso dichiara esplicitamente che la challenge è una valutazione di trading simulato.',
        ],
        'current_selection' => 'Selezione attuale',
        'redirect_note' => 'La homepage ora porta direttamente a una pagina checkout dedicata, dove dati di fatturazione, scelta del provider di pagamento e checkout sicuro Stripe o PayPal vengono gestiti in sicurezza dal server.',
        'promo_code_title' => 'Codice promo',
        'promo_code_label' => 'Codice promo',
        'promo_code_placeholder' => 'Inserisci il tuo codice promo',
        'promo_code_badge' => 'Accesso lancio 20%',
        'promo_code_help' => 'Inserisci un codice promo e clicca Applica per aggiornare subito il totale del checkout.',
        'promo_code_apply' => 'Applica',
        'promo_code_applied' => 'Codice applicato',
        'promo_code_applied_copy' => 'Il codice lancio è stato applicato automaticamente e l’offerta lancio del 20% è già riflessa nel totale qui sopra.',
        'promo_code_feedback' => [
            'success' => 'Codice applicato con successo',
            'invalid' => 'Codice non valido / scaduto',
        ],
        'billing_title' => 'Informazioni di fatturazione',
        'payment_methods_title' => 'Scegli il tuo metodo di pagamento',
        'payment_methods_subtitle' => 'Checkout sicuro • Attivazione account immediata',
        'client_data_title' => 'Dati cliente / dettagli registrazione',
        'full_name' => 'Nome completo',
        'email' => 'Indirizzo email',
        'street_address' => 'Indirizzo',
        'city' => 'Città',
        'postal_code' => 'CAP',
        'country' => 'Paese',
        'select_country' => 'Seleziona paese',
        'plan' => 'Piano challenge',
        'select_plan' => 'Seleziona un piano',
        'platform' => 'Piattaforma',
        'platform_value' => 'MT5',
        'agreement' => 'Il checkout richiede l’accettazione dei Terms & Conditions, la conferma del tuo attuale paese di residenza e l’accettazione della Cancellation and Refund Policy.',
        'confirmation_title' => 'Conferme richieste',
        'confirmations' => [
            'terms_and_residency_html' => 'Dichiaro di aver letto e accettato i <a href=":terms_url" target="_blank" rel="noopener noreferrer" class="font-semibold text-white underline underline-offset-4">Terms & Conditions</a> e dichiaro che il paese di residenza indicato nell’ordine sopra è il mio paese di residenza attuale.',
            'refund_policy_html' => 'Dichiaro di aver letto e accettato la <a href=":refund_url" target="_blank" rel="noopener noreferrer" class="font-semibold text-white underline underline-offset-4">Cancellation and Refund Policy</a>.',
        ],
        'submit' => 'Continua al checkout sicuro',
        'provider_available' => 'Disponibile',
        'provider_coming_soon' => 'In arrivo',
        'provider_recommended' => 'Consigliato',
        'back_to_plans' => 'Torna ai piani',
        'trust_message' => 'I tuoi dati sono protetti con pratiche di sicurezza standard di settore allineate a ISO/IEC 27001.',
        'payment_method_points' => [
            'Pagamento sicuro',
            'Attivazione account immediata',
            'Nessuna commissione nascosta',
        ],
        'buttons' => [
            'stripe' => 'Paga con carta tramite Stripe usando lo stesso flusso protetto di ordine e fulfillment.',
            'paypal' => 'Paga con PayPal usando approval redirect e acquisizione ordine lato server.',
        ],
        'providers' => [
            'stripe' => [
                'label' => 'Stripe',
                'description' => 'Checkout carta sicuro esterno con conferma ordine basata su webhook.',
                'summary' => 'Paga in sicurezza con carta',
                'supporting' => 'Conferma rapida e immediata',
                'cta' => 'Paga con carta',
            ],
            'paypal' => [
                'label' => 'PayPal',
                'description' => 'Flusso di approvazione PayPal sicuro con capture lato server e lo stesso percorso di fulfillment ordini Wolforix.',
                'summary' => 'Paga usando il tuo account PayPal',
                'supporting' => 'Sicuro e affidabile',
                'cta' => 'Paga con PayPal',
            ],
        ],
        'success' => [
            'eyebrow' => 'Pagamento riuscito',
            'title' => 'Ordine challenge confermato',
            'description' => 'Il tuo pagamento è stato confermato e la challenge acquistata è stata collegata al tuo record ordine Wolforix.',
            'pending_description' => 'Il checkout è tornato con successo. Stiamo finalizzando la conferma con il provider di pagamento e manterremo questo ordine collegato fino al completamento.',
            'plan' => 'Piano acquistato',
            'amount' => 'Importo pagato',
            'provider' => 'Provider di pagamento',
            'order_number' => 'Numero ordine',
            'next_steps' => 'Passo successivo: la tua challenge a pagamento è conservata separatamente dal flusso di prova gratuita ed è pronta per attivazione e collegamento futuro alla dashboard.',
            'open_dashboard' => 'Apri dashboard',
            'back_home' => 'Torna alla home',
        ],
        'cancel' => [
            'eyebrow' => 'Checkout annullato',
            'title' => 'Il tuo ordine è ancora salvato',
            'description' => 'Il pagamento è stato annullato prima del completamento. Il record ordine resta disponibile così puoi riprovare senza perdere challenge selezionata e dati di fatturazione.',
            'order_number' => 'Numero ordine',
            'plan' => 'Piano selezionato',
            'amount' => 'Importo dovuto',
            'retry' => 'Riprova pagamento',
            'back_to_plans' => 'Torna ai piani',
        ],
        'errors' => [
            'provider_unavailable' => 'Il provider di pagamento selezionato non è riuscito ad avviare una sessione checkout. Verifica le credenziali del provider e riprova.',
        ],
        'validation' => [
            'accept_terms_and_residency' => 'Devi accettare i Terms & Conditions e confermare il tuo attuale paese di residenza prima di continuare.',
            'accept_refund_policy' => 'Devi accettare la Cancellation and Refund Policy prima di continuare.',
            'promo_code' => 'Codice non valido / scaduto',
        ],
    ],
    'faq' => [
        'eyebrow' => 'Domande frequenti',
        'title' => 'Tutto ciò di cui hai bisogno. Subito.',
        'description' => 'Tutte le tue regole di trading, pagamenti e informazioni dell’account — chiare e accessibili.',
        'search_label' => 'Cerca',
        'search_placeholder' => 'Cerca nelle FAQ...',
        'no_results' => 'Nessun elemento FAQ corrisponde a questa ricerca.',
        'sections' => [
            [
                'title' => 'Generale',
                'items' => [
                    [
                        'question' => 'Che cos’è Wolforix?',
                        'answer' => 'Wolforix Ltd. è una società di valutazione e formazione nel proprietary trading. Tutte le attività di trading si svolgono in un ambiente simulato a fini educativi.',
                    ],
                    [
                        'question' => 'Si tratta di denaro reale o trading simulato?',
                        'answer' => 'Tutti gli account operano in un ambiente di trading simulato. Nessun capitale reale viene assegnato agli utenti.',
                    ],
                    [
                        'question' => 'Chi può partecipare?',
                        'answer' => 'Gli utenti devono avere almeno 18 anni e rispettare tutte le leggi applicabili nella propria giurisdizione.',
                    ],
                ],
            ],
            [
                'title' => 'Piattaforma',
                'items' => [
                    [
                        'question' => 'Quale piattaforma usa Wolforix?',
                        'answer' => 'Wolforix usa MetaTrader 5 (MT5).',
                    ],
                    [
                        'question' => 'Come accedo a MT5?',
                        'answer_sections' => [
                            [
                                'title' => 'App mobile',
                                'bullets' => [
                                    '1. Scarica MetaTrader 5.',
                                    '2. Vai su "Manage Accounts".',
                                    '3. Tocca "+".',
                                    '4. Seleziona "Login to an existing account".',
                                    '5. Cerca: MetaQuotes-Demo.',
                                    '6. Inserisci le tue credenziali.',
                                ],
                            ],
                            [
                                'title' => 'Piattaforma desktop',
                                'bullets' => [
                                    '1. Apri MT5.',
                                    '2. File -> Accedi al conto di trading.',
                                    '3. Inserisci i dati di accesso.',
                                    '4. Server selezionato: MetaQuotes-Demo.',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'Wolforix non usa un broker proprio.',
                                    'Usiamo il server MetaQuotes-Demo.',
                                    'Il tuo account è collegato a Wolforix.',
                                    'Tutta l’attività viene sincronizzata con la dashboard.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Strumenti negoziabili',
                'items' => [
                    [
                        'question' => 'Cosa posso tradare?',
                        'answer' => 'Wolforix offre accesso a un’ampia gamma di strumenti CFD disponibili in MT5.',
                        'answer_sections' => [
                            [
                                'title' => 'Forex',
                                'bullets' => [
                                    'EURUSD, GBPUSD, USDJPY, USDCHF, USDCAD',
                                    'AUDUSD, NZDUSD, EURJPY, GBPJPY, EURGBP e altri',
                                ],
                            ],
                            [
                                'title' => 'Indici',
                                'bullets' => [
                                    'SPX500, NDX100, US30',
                                    'GER30, UK100, FRA40',
                                    'JP225 e altri',
                                ],
                            ],
                            [
                                'title' => 'Materie prime',
                                'bullets' => [
                                    'XAUUSD (Oro)',
                                    'XAGUSD (Argento)',
                                    'XPTUSD (Platino)',
                                    'UKOUSD (Brent)',
                                    'USOUSD (Crude Oil)',
                                ],
                            ],
                            [
                                'title' => 'Criptovalute',
                                'bullets' => [
                                    'BTCUSD, ETHUSD, XRPUSD',
                                    'ADAUSD, LTCUSD, XLMUSD',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Come posso vedere tutti gli strumenti?',
                        'answer_sections' => [
                            [
                                'title' => 'Osservazione del mercato MT5',
                                'bullets' => [
                                    '1. Apri MT5.',
                                    '2. Vai su Market Watch.',
                                    '3. Fai clic destro.',
                                    '4. Seleziona "Show All".',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Regole di trading',
                'items' => [
                    [
                        'question' => 'Che cos’è la regola di consistenza?',
                        'answer' => 'Non più del 40% dei profitti totali può essere generato in un singolo giorno di trading. I profitti devono essere distribuiti su più giorni per essere idonei al payout.',
                    ],
                    [
                        'question' => 'Come viene calcolato il limite di profitto giornaliero?',
                        'answer' => 'Il sistema confronta il profitto di oggi con il profitto totale dell’account. Se il profitto di oggi supera il 40%, verrà mostrato un avviso in dashboard e l’idoneità al payout può essere influenzata.',
                    ],
                    [
                        'question' => 'Cosa sono i drawdown massimi?',
                        'answer' => 'Ogni account ha limiti di drawdown definiti. Superarli può comportare la squalifica dell’account dal programma di valutazione.',
                    ],
                    [
                        'question' => 'Posso fare trading durante news ad alto impatto?',
                        'answer' => 'È vietato aprire o chiudere trade 5 minuti prima e 5 minuti dopo una news ad alto impatto. La restrizione si applica a ordini a mercato e ordini pendenti, incluse attivazioni di stop-loss o take-profit. Puoi mantenere posizioni esistenti durante l’evento, ma non puoi aprire né chiudere trade in quella finestra.',
                    ],
                    [
                        'question' => 'Quali orari di trading sono consentiti?',
                        'answer' => 'Wolforix consente il trading durante gli orari di mercato standard in base allo strumento tradato.',
                        'answer_sections' => [
                            [
                                'title' => 'Regola generale',
                                'bullets' => [
                                    'Il trading è disponibile 24 ore, 5 giorni a settimana, dal lunedì al venerdì, in linea con le sessioni globali.',
                                    'La disponibilità può variare in base allo strumento, inclusi Forex, indici, crypto e altri CFD.',
                                ],
                            ],
                            [
                                'title' => 'Mantenimento posizioni',
                                'bullets' => [
                                    'Le posizioni possono essere mantenute intraday o overnight, salvo restrizioni specifiche dell’account.',
                                    'I trader sono responsabili della gestione dell’esposizione durante periodi di bassa liquidità.',
                                ],
                            ],
                            [
                                'title' => 'Chiusure di mercato',
                                'bullets' => [
                                    'Il trading non è disponibile nei weekend.',
                                    'Alcuni strumenti possono avere pause giornaliere o finestre di manutenzione.',
                                    'Durante le festività, gli orari di mercato possono essere ridotti o modificati.',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'I trader devono conoscere orari di sessione e condizioni di liquidità.',
                                    'Wolforix non è responsabile per perdite causate da trading in periodi illiquidi o volatili.',
                                ],
                            ],
                            [
                                'title' => 'Restrizioni',
                                'bullets' => [
                                    'Le restrizioni relative alle news (±5 minuti) restano valide.',
                                    'Tutte le altre regole di trading rimangono in vigore indipendentemente dall’orario.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Quali strategie di trading sono consentite?',
                        'answer_paragraphs' => [
                            'Wolforix consente trading discrezionale, trading algoritmico ed Expert Advisors (EAs), purché le strategie siano legittime, riflettano condizioni reali di mercato, rispettino una solida gestione del rischio e non includano pratiche vietate.',
                            'Le strategie devono essere replicabili in condizioni reali di mercato e in grado di produrre risultati live coerenti.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Condizioni di trading',
                                'bullets' => [
                                    'Lo Stop Loss non è obbligatorio, ma il controllo del rischio è fortemente raccomandato.',
                                    'Il trading deve riflettere comportamento esecutivo realistico e condizioni reali di mercato.',
                                    'Le strategie devono essere scalabili e adatte al capitale live.',
                                ],
                            ],
                            [
                                'title' => 'Expert Advisors (EAs) e trading algoritmico',
                                'bullets' => [
                                    'Gli EAs sono consentiti.',
                                    'La duplicazione di EA di terze parti può entrare in conflitto con il risk management interno.',
                                    'Wolforix può limitare o negare l’allocazione dell’account in questi casi.',
                                    'L’attività della piattaforma deve restare entro limiti ragionevoli.',
                                    'Eccesso di ordini, modifiche o carico server può richiedere adeguamenti di strategia.',
                                ],
                            ],
                            [
                                'title' => 'Limiti server ed esecuzione',
                                'bullets' => [
                                    'Possono applicarsi limiti al numero massimo di ordini aperti contemporaneamente.',
                                    'Possono essere imposti limiti giornalieri di esecuzione.',
                                    'Una frequenza di trading eccessiva può attivare una revisione.',
                                    'Wolforix può richiedere aggiustamenti se la performance danneggia la stabilità della piattaforma.',
                                ],
                            ],
                            [
                                'title' => 'Policy su scalping e durata trade',
                                'bullets' => [
                                    'I trade chiusi in meno di 60 secondi sono severamente vietati se generano profitto.',
                                    'Questa attività è considerata non replicabile in condizioni reali e può indicare sfruttamento della latenza o esecuzione irrealistica.',
                                    'Lo scalping standard è consentito se la durata riflette reale esposizione al mercato.',
                                    'Wolforix può escludere tali trade dal calcolo dei profitti o adottare ulteriori azioni se ripetuti.',
                                ],
                            ],
                            [
                                'title' => 'Pratiche di trading vietate',
                                'paragraphs' => [
                                    'Se viene rilevata attività vietata, Wolforix può rimuovere o regolare posizioni, ricalcolare l’account, ridurre la leva, sospendere o chiudere l’account, o terminare la collaborazione con il trader.',
                                    'Se fai trading con intento genuino, edge chiaro e comportamento coerente conforme alle regole, Wolforix resta allineata con il tuo successo.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Hedging tra account e copy trading sono consentiti?',
                        'answer' => 'Hedging tra più account e copy trading non autorizzato sono severamente vietati in Wolforix.',
                        'answer_sections' => [
                            [
                                'title' => 'Che cos’è l’hedging?',
                                'paragraphs' => [
                                    'Hedging significa assumere posizioni opposte sullo stesso strumento o su strumenti correlati tra più account per ridurre artificialmente il rischio.',
                                    'Questo comportamento garantisce che un account generi profitto indipendentemente dalla direzione del mercato ed è considerato manipolazione del sistema, non trading genuino.',
                                ],
                            ],
                            [
                                'title' => 'Che cos’è il copy trading?',
                                'paragraphs' => [
                                    'Copy trading significa replicare trade tra più account, manualmente, tramite software, segnali o servizi di terze parti.',
                                ],
                                'bullets' => [
                                    'Copiare trade tra i propri account.',
                                    'Copiare trade tra utenti diversi.',
                                    'Usare gruppi di segnali, bot o automazioni per replicare trade.',
                                    'Trading coordinato progettato per aggirare le regole.',
                                ],
                            ],
                            [
                                'title' => 'Esempi di attività vietata',
                                'bullets' => [
                                    'Aprire posizioni long e short sullo stesso strumento su account diversi.',
                                    'Hedging tra account dello stesso utente.',
                                    'Hedging tra utenti coordinati.',
                                    'Hedging tra diverse firm o piattaforme.',
                                    'Tradare strumenti correlati in direzioni opposte tra account.',
                                    'Copiare o replicare trade tra account, manualmente o automaticamente.',
                                    'Usare trade copier software o servizi di segnali per replicare trade.',
                                ],
                            ],
                            [
                                'title' => 'Esempi',
                                'bullets' => [
                                    'Long EURUSD in un account e short EURUSD in un altro.',
                                    'Long SPX500 in un account e short NDX100 in un altro.',
                                    'Eseguire trade identici su più account nello stesso momento.',
                                    'Usare un bot o provider di segnali per replicare trade tra account.',
                                ],
                            ],
                            [
                                'title' => 'Cosa è consentito?',
                                'bullets' => [
                                    'Decisioni di trading indipendenti per account.',
                                    'Uso di strategie personali non condivise su più account.',
                                    'Corretta gestione del rischio in un singolo account.',
                                ],
                            ],
                            [
                                'title' => 'Chiarimento importante',
                                'bullets' => [
                                    'Aprire posizioni opposte nello stesso account può essere tecnicamente possibile in MT5, ma strategie progettate per aggirare le regole di rischio non sono consentite.',
                                    'L’automazione è consentita solo se riflette logica di trading indipendente, non replica di trade.',
                                    'Ogni trading deve riflettere decisioni reali e indipendenti ed esposizione reale al mercato.',
                                ],
                            ],
                            [
                                'title' => 'Rilevamento e monitoraggio',
                                'bullets' => [
                                    'Wolforix usa sistemi interni per rilevare pattern di hedging.',
                                    'Wolforix monitora la sincronizzazione dei trade.',
                                    'Wolforix monitora comportamenti di esecuzione identici tra account.',
                                    'Wolforix monitora attività di copy trading.',
                                ],
                            ],
                            [
                                'title' => 'Conseguenze',
                                'bullets' => [
                                    'Wolforix può rimuovere o regolare trade.',
                                    'Wolforix può ricalcolare il saldo account.',
                                    'Wolforix può squalificare l’account.',
                                    'Wolforix può limitare o bannare permanentemente l’utente.',
                                ],
                            ],
                            [
                                'title' => 'Perché è vietato?',
                                'bullets' => [
                                    'Hedging e copy trading falsano la reale abilità di trading.',
                                    'Hedging e copy trading eliminano la reale esposizione al rischio.',
                                    'Hedging e copy trading indeboliscono il processo di valutazione.',
                                    'Hedging e copy trading minacciano l’integrità della piattaforma.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'High-frequency trading (HFT) è consentito?',
                        'answer' => 'High-frequency trading (HFT) è severamente vietato in Wolforix.',
                        'answer_sections' => [
                            [
                                'title' => 'Che cos’è High-Frequency Trading?',
                                'paragraphs' => [
                                    'High-frequency trading (HFT) indica strategie automatizzate che eseguono molte operazioni in tempi estremamente brevi, spesso misurati in secondi o millisecondi.',
                                    'Queste strategie mirano tipicamente a sfruttare piccole inefficienze di prezzo tramite velocità e alto volume di ordini.',
                                ],
                            ],
                            [
                                'title' => 'Cosa è considerato HFT?',
                                'bullets' => [
                                    'Eseguire un alto volume di trade in periodi molto brevi.',
                                    'Inserimento e cancellazione rapida di ordini.',
                                    'Modifiche eccessive agli ordini.',
                                    'Pattern di esecuzione algoritmica ultra-veloci.',
                                    'Comportamento di trading che crea carico anomalo sulla piattaforma.',
                                ],
                            ],
                            [
                                'title' => 'Chiarimento importante',
                                'bullets' => [
                                    'Trading algoritmico ed EAs sono consentiti.',
                                    'Le strategie devono operare con frequenza normale e comportamento esecutivo realistico.',
                                    'Non è consentito alcun sistema progettato principalmente per sfruttare la velocità invece dell’analisi di mercato.',
                                ],
                            ],
                            [
                                'title' => 'Perché HFT è vietato?',
                                'paragraphs' => [
                                    'Wolforix è progettata per valutare abilità e consistenza, non sfruttamento del sistema basato sulla velocità.',
                                ],
                                'bullets' => [
                                    'HFT può degradare le prestazioni della piattaforma.',
                                    'HFT può creare instabilità di esecuzione.',
                                    'HFT può influenzare la coerenza dei prezzi.',
                                    'HFT può impattare l’ambiente di trading degli altri utenti.',
                                ],
                            ],
                            [
                                'title' => 'Rilevamento e monitoraggio',
                                'bullets' => [
                                    'Wolforix monitora la frequenza dei trade.',
                                    'Wolforix monitora il volume degli ordini.',
                                    'Wolforix monitora i pattern di esecuzione.',
                                    'Wolforix monitora l’impatto sul carico server.',
                                ],
                            ],
                            [
                                'title' => 'Conseguenze',
                                'bullets' => [
                                    'Può essere emesso un avviso.',
                                    'I profitti generati da HFT possono essere rimossi.',
                                    'L’account può essere limitato o chiuso.',
                                    'Violazioni ripetute possono portare a ban permanente.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Duration abuse, grid trading e strategie martingale sono consentiti?',
                        'answer' => 'Wolforix vieta severamente strategie che sfruttano strutture di rischio o creano profili di performance irrealistici, inclusi duration abuse, grid trading e sistemi martingale.',
                        'answer_sections' => [
                            [
                                'title' => 'Abuso di durata',
                                'paragraphs' => [
                                    'Duration abuse significa aprire e chiudere sistematicamente trade in modo da aggirare l’esposizione al rischio o le regole di trading previste, senza reale partecipazione al mercato.',
                                    'Tutti i trade devono riflettere reale esposizione e intenzione di mercato, non manipolazione delle regole.',
                                ],
                                'bullets' => [
                                    'Aprire e chiudere ripetutamente trade intorno alla soglia minima di durata, ad esempio appena sopra 60 secondi.',
                                    'Eseguire trade senza reale intento di mercato, solo per soddisfare requisiti di regola.',
                                    'Timing artificiale dei trade progettato per aggirare restrizioni.',
                                ],
                            ],
                            [
                                'title' => 'Trading a griglia',
                                'paragraphs' => [
                                    'Grid trading implica piazzare più ordini pendenti o attivi a intervalli di prezzo fissi, spesso senza chiara logica di stop-loss.',
                                ],
                                'bullets' => [
                                    'Sistemi grid senza adeguato controllo del rischio non sono consentiti.',
                                    'Order stacking ad alta densità non è consentito.',
                                    'Strategie basate su oscillazione del prezzo senza rischio definito non sono consentite.',
                                ],
                            ],
                            [
                                'title' => 'Strategie Martingale',
                                'paragraphs' => [
                                    'Le strategie martingale aumentano la dimensione della posizione dopo perdite per recuperare perdite precedenti con un singolo trade vincente.',
                                ],
                                'bullets' => [
                                    'Raddoppiare o aumentare lotti dopo perdite non è consentito.',
                                    'Position sizing di recupero senza limiti di rischio non è consentito.',
                                    'Strategie che creano esposizione esponenziale al rischio non sono consentite.',
                                ],
                            ],
                            [
                                'title' => 'Cosa è consentito?',
                                'bullets' => [
                                    'Strategie strutturate con rischio definito per trade.',
                                    'Position sizing logico.',
                                    'Esposizione coerente e controllata.',
                                    'Uso di stop-loss e corretta gestione del rischio.',
                                ],
                            ],
                            [
                                'title' => 'Rilevamento e monitoraggio',
                                'bullets' => [
                                    'Wolforix monitora pattern di durata dei trade.',
                                    'Wolforix monitora comportamento di position sizing.',
                                    'Wolforix monitora distribuzione degli ordini.',
                                    'Wolforix monitora pattern di escalation del rischio.',
                                ],
                            ],
                            [
                                'title' => 'Conseguenze',
                                'bullets' => [
                                    'Wolforix può rimuovere o regolare trade.',
                                    'Wolforix può ricalcolare il saldo account.',
                                    'Wolforix può limitare l’attività di trading.',
                                    'Wolforix può sospendere o chiudere l’account.',
                                ],
                            ],
                            [
                                'title' => 'Perché è vietato?',
                                'bullets' => [
                                    'Queste strategie distorcono la reale performance di trading.',
                                    'Queste strategie eliminano corretta esposizione al rischio.',
                                    'Queste strategie creano curve equity instabili.',
                                    'Queste strategie non sono sostenibili in condizioni reali di mercato.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Payout',
                'items' => [
                    [
                        'question' => 'Ogni quanto vengono elaborati i payout?',
                        'answer_paragraphs' => [
                            'Le commissioni vengono pagate su richiesta e sono soggette a revisione e approvazione da parte del Wolforix Partner Success Team. Il primo payout diventa idoneo dopo 21 giorni, con payout successivi disponibili in cicli ricorrenti di 14 giorni.',
                            'Una volta completato il periodo di ciclo richiesto, i payout vengono elaborati entro 24 ore.',
                            'Per richiedere un payout, invia un’email a support@wolforix.com una volta raggiunta la soglia minima di prelievo di $100.',
                        ],
                    ],
                    [
                        'question' => 'Quali metodi di payout sono disponibili?',
                        'answer' => 'Wolforix supporta metodi di payout sicuri ed efficienti per prelievi approvati.',
                        'answer_sections' => [
                            [
                                'title' => 'Opzioni disponibili',
                                'bullets' => [
                                    'Bonifico bancario (tramite infrastruttura Stripe, in base alla regione)',
                                    'PayPal',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'I metodi di payout possono variare in base alla località.',
                                    'Tutti i prelievi sono soggetti a revisione account e controlli compliance.',
                                    'I tempi di elaborazione possono variare in base a provider e regione.',
                                ],
                            ],
                            [
                                'title' => 'Tempo di elaborazione',
                                'bullets' => [
                                    'Le richieste sono generalmente revisionate entro 1–3 giorni lavorativi.',
                                    'Una volta approvati, i fondi vengono processati poco dopo.',
                                ],
                            ],
                            [
                                'title' => 'Note aggiuntive',
                                'bullets' => [
                                    'Il metodo di payout deve corrispondere all’identità del titolare dell’account.',
                                    'Wolforix si riserva il diritto di richiedere verifica prima di processare prelievi.',
                                    'Le commissioni possono variare in base al metodo selezionato.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Come viene calcolato il mio payout?',
                        'answer' => 'I payout dipendono dal profit split assegnato al modello e alla dimensione dell’account, dai tempi specifici del modello, dal rispetto della regola di consistenza dove applicabile e dai limiti interni. L’importo idoneo può essere inferiore ai profitti totali se i limiti giornalieri vengono superati.',
                    ],
                    [
                        'question' => 'Gli account funded scalano?',
                        'answer' => 'Gli account funded 2-Step possono scalare del +25% di capitale ogni 3 mesi se profittevoli. Gli account funded 1-Step al momento non includono questa regola.',
                    ],
                    [
                        'question' => 'Come funziona il piano di scaling dell’account?',
                        'answer_paragraphs' => [
                            'Wolforix applica un sistema di scaling dinamico agli account funded basato sulla performance di trading.',
                            'Man mano che l’account cresce, la dimensione massima consentita delle posizioni aumenta progressivamente, permettendo maggiore esposizione quando viene dimostrata consistenza.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Come funziona il sistema',
                                'bullets' => [
                                    'Lo scaling si basa sui profitti simulati.',
                                    'Quando i profitti aumentano, aumenta la capacità di trading.',
                                    'Se la performance cala, i limiti possono essere adeguati.',
                                ],
                            ],
                            [
                                'title' => 'Frequenza aggiornamenti',
                                'bullets' => [
                                    'Gli aggiornamenti di scaling vengono applicati alla fine di ogni giornata di trading.',
                                    'Le modifiche non vengono applicate in tempo reale durante la giornata.',
                                ],
                            ],
                            [
                                'title' => 'Struttura di scaling',
                                'paragraphs' => [
                                    'Il modello premia consistenza, gestione del rischio e performance sostenibile.',
                                    'La tua esposizione massima evolve quando l’account dimostra stabilità nel tempo.',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'Lo scaling non è lineare e può variare in base alla performance dell’account.',
                                    'Sovraesposizione senza performance sufficiente può portare a restrizioni.',
                                    'Il sistema dà priorità alla consistenza di lungo periodo rispetto ai guadagni di breve periodo.',
                                ],
                            ],
                            [
                                'title' => 'Aggirare il sistema di scaling',
                                'paragraphs' => [
                                    'Qualsiasi tentativo di aggirare o manipolare il sistema di scaling è severamente vietato.',
                                ],
                                'bullets' => [
                                    'Suddividere trade per superare limiti.',
                                    'Usare ingressi multipli per aumentare artificialmente l’esposizione.',
                                    'Sfruttare comportamento esecutivo o meccaniche della piattaforma.',
                                ],
                            ],
                            [
                                'title' => 'Conseguenze',
                                'bullets' => [
                                    'Wolforix può regolare o rimuovere trade.',
                                    'Wolforix può ricalcolare il saldo account.',
                                    'Wolforix può limitare condizioni di trading.',
                                    'Wolforix può sospendere o chiudere l’account.',
                                ],
                            ],
                            [
                                'title' => 'Riepilogo',
                                'bullets' => [
                                    'Performance in aumento, capacità di trading in aumento.',
                                    'Performance in calo, i limiti possono adeguarsi.',
                                    'Scaling aggiornato quotidianamente.',
                                    'La consistenza è richiesta.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Fatturazione',
                'items' => [
                    [
                        'question' => 'Quali metodi di pagamento sono accettati?',
                        'answer' => 'Wolforix accetta pagamenti online sicuri tramite provider affidabili. Tutti i pagamenti sono elaborati in modo sicuro tramite Stripe e PayPal, garantendo transazioni rapide e affidabili.',
                        'answer_sections' => [
                            [
                                'title' => 'Metodi disponibili',
                                'bullets' => [
                                    'Carte di credito e debito (Visa, Mastercard, American Express)',
                                    'PayPal',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'I pagamenti sono confermati immediatamente dopo l’approvazione.',
                                    'Tutte le transazioni sono criptate ed elaborate in modo sicuro.',
                                    'La disponibilità di alcuni metodi può variare in base alla località.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Account / Dashboard',
                'items' => [
                    [
                        'question' => 'Come vedo profitto e saldo?',
                        'answer' => 'La dashboard mostra profitto totale, profitto giornaliero e saldo account in tempo reale.',
                    ],
                    [
                        'question' => 'Cosa succede se mi avvicino al limite di consistenza?',
                        'answer' => 'Apparirà un avviso in dashboard: “⚠ Ti stai avvicinando al limite della regola di consistenza. I profitti devono essere distribuiti su più giorni di trading per essere idonei al payout.” Inoltre, può essere inviato un alert email automatico se ti avvicini alla soglia critica.',
                    ],
                    [
                        'question' => 'Come posso richiedere un payout?',
                        'answer' => 'Il pulsante per richiedere payout è nella dashboard. Gli account funded 1-Step devono soddisfare la regola di consistenza obbligatoria prima che il profitto diventi idoneo.',
                    ],
                    [
                        'question' => 'Ho superato con successo, cosa devo fare ora?',
                        'answer_paragraphs' => [
                            'Ciò che accade dopo dipende dal fatto che tu stia partecipando a Wolforix Step-1 Instant o a Wolforix Step-Pro, perché ogni programma segue una struttura leggermente diversa. Tuttavia, entrambi includono una fase di verifica.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Wolforix Step-1 Instant',
                                'paragraphs' => [
                                    'Dopo aver superato tutti gli Obiettivi di Trading nel tuo account Step-1 Instant, riceverai una notifica nella dashboard che conferma il raggiungimento degli obiettivi e la revisione dell’account.',
                                    'Il processo di revisione richiede in genere 1-2 giorni lavorativi. Una volta verificati i risultati, riceverai accesso alla fase di verifica.',
                                ],
                            ],
                            [
                                'title' => 'Fase di verifica',
                                'paragraphs' => [
                                    'Dopo aver superato tutti gli Obiettivi di Trading nella fase di verifica, il tuo account sarà nuovamente esaminato.',
                                    'Una volta verificati i risultati, sono richiesti i seguenti passaggi:',
                                ],
                                'bullets' => [
                                    'Completa la verifica della tua identità (KYC/KYB) nella tua area cliente',
                                    'Firma l’Accordo Account Wolforix',
                                    'Una volta completati correttamente tutti i passaggi, verrà emesso il tuo account Wolforix funded.',
                                ],
                            ],
                            [
                                'title' => 'Wolforix Step-Pro',
                                'paragraphs' => [
                                    'Fase 1',
                                    'Dopo aver superato tutti gli Obiettivi di Trading nella Fase 1, riceverai una notifica che conferma il tuo successo. A questo punto non è richiesto ulteriore trading e il tuo account sarà esaminato.',
                                    'Il processo di revisione richiede in genere 1-2 giorni lavorativi. Una volta verificato, riceverai accesso alla fase successiva.',
                                ],
                            ],
                            [
                                'title' => 'Fase di verifica',
                                'paragraphs' => [
                                    'Dopo aver completato con successo tutti gli Obiettivi di Trading nella fase di verifica, il tuo account passerà alla revisione finale.',
                                    'Una volta verificati i risultati, sono richiesti i seguenti passaggi:',
                                ],
                                'bullets' => [
                                    'Completa la verifica della tua identità (KYC/KYB) nella tua area cliente',
                                    'Firma l’Accordo Account Wolforix',
                                    'Una volta completati correttamente tutti i passaggi, verrà emesso il tuo account Wolforix funded.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Account Trial',
                'items' => [
                    [
                        'question' => 'Come avvio il mio Account Trial Wolforix?',
                        'answer_paragraphs' => [
                            'Dopo esserti registrato per un Account Trial Wolforix, riceverai un’email con le istruzioni.',
                            'Completa la registrazione demo presso IC Markets tramite https://www.icmarkets.eu/de/open-trading-account/demo.',
                            'Dopo aver inviato i tuoi dati, riceverai le credenziali di accesso via email. Con queste credenziali potrai accedere al tuo account demo e iniziare la prova gratuita.',
                            'Se hai bisogno di assistenza, contattaci a support@wolforix.com.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Supporto / Contatti',
                'items' => [
                    [
                        'question' => 'Come contatto il supporto?',
                        'answer' => 'Tutte le richieste di supporto sono gestite via email o tramite sistema ticket nella dashboard.',
                    ],
                    [
                        'question' => 'Avete un numero di telefono?',
                        'answer' => 'No, Wolforix Ltd. non fornisce supporto telefonico. Tutte le comunicazioni sono documentate via email o ticket per ragioni di compliance e sicurezza.',
                    ],
                ],
            ],
            [
                'title' => 'Legale / Compliance',
                'items' => [
                    [
                        'question' => 'Devo verificare la mia identità?',
                        'answer' => 'Sì, la verifica dell’identità (KYC) può essere richiesta prima di processare payout per rispettare le norme antiriciclaggio.',
                    ],
                    [
                        'question' => 'Quali Paesi sono soggetti a restrizioni?',
                        'answer_paragraphs' => [
                            'Wolforix non fornisce servizi a persone o entità residenti in Paesi soggetti a sanzioni internazionali o restrizioni regolamentari.',
                            'Questo include, tra gli altri, Iran, Corea del Nord, Siria, Sudan, Cuba, Russia e Venezuela.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Idoneità',
                                'bullets' => [
                                    'Usando i servizi Wolforix, confermi di non risiedere in una giurisdizione soggetta a restrizioni.',
                                    'Confermi di non essere soggetto ad alcuna sanzione applicabile.',
                                    'Confermi di poter partecipare legalmente secondo le leggi del tuo Paese.',
                                ],
                            ],
                            [
                                'title' => 'Conformità',
                                'paragraphs' => [
                                    'Wolforix rispetta normative internazionali, incluse politiche antiriciclaggio (AML) e contrasto al finanziamento del terrorismo (CTF).',
                                    'L’accesso ai servizi può essere limitato in base a Paese di residenza, nazionalità, limiti dei provider di pagamento come Stripe o PayPal, o valutazione interna del rischio.',
                                ],
                            ],
                            [
                                'title' => 'Avviso importante',
                                'bullets' => [
                                    'Wolforix si riserva il diritto di limitare o negare l’accesso a qualsiasi utente a sua esclusiva discrezione.',
                                    'Wolforix può richiedere verifica identità (KYC) in qualsiasi momento.',
                                    'Wolforix può aggiornare la lista delle giurisdizioni soggette a restrizioni senza preavviso.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Esistono regole contro frodi o abusi?',
                        'answer' => 'Qualsiasi tentativo di manipolare il sistema, sfruttare falle o compiere attività fraudolente comporterà la chiusura dell’account e potrà essere segnalato alle autorità.',
                    ],
                ],
            ],
        ],
    ],
    'search' => [
        'title' => 'Cerca nel sito',
        'description' => 'Cerca piani, policy, pagine di supporto e risposte FAQ.',
        'placeholder' => 'Cerca pagine, FAQ e policy...',
        'empty' => 'Nessun contenuto corrispondente trovato.',
        'close' => 'Chiudi ricerca',
        'featured_title' => 'Destinazioni popolari',
        'results_one' => 'risultato',
        'results_many' => 'risultati',
        'section_labels' => [
            'page' => 'Pagina',
            'faq' => 'FAQ',
            'policy' => 'Policy',
            'support' => 'Supporto',
        ],
    ],
    'contact' => [
        'eyebrow' => 'Contatta Wolforix',
        'title' => 'Supporto, live chat e aiuto vocale FAQ in un solo posto.',
        'description' => 'Contatta il team di supporto via email, avvia una live chat supportata dall’email o usa l’assistente vocale FAQ per ottenere risposte rapide prima o dopo aver iniziato una challenge.',
        'primary_action' => 'Invia email al supporto',
        'secondary_action' => 'Apri FAQ',
        'email_title' => 'Supporto email',
        'email_copy' => 'Invia direttamente al supporto Wolforix le domande su billing, account o regole.',
        'email_response' => 'Le risposte arrivano di solito durante gli orari di lavoro: :hours.',
        'email_button' => 'Invia email',
        'live_chat_title' => 'Live chat',
        'live_chat_copy' => 'Usa il launcher live chat qui sotto. Se il team è offline, il tuo messaggio viene inviato alla coda email del supporto, così nulla va perso.',
        'live_chat_note' => 'Le risposte potrebbero richiedere più tempo fuori dagli orari lavorativi.',
        'live_chat_label' => 'Di cosa hai bisogno?',
        'live_chat_placeholder' => 'Scrivi la tua domanda e la prepareremo per la casella del supporto...',
        'live_chat_status' => 'Il tuo messaggio live chat si aprirà nel compositore email del supporto.',
        'live_chat_empty' => 'Scrivi un messaggio prima di avviare la live chat.',
        'live_chat_button' => 'Avvia live chat',
        'live_chat_subject' => 'Richiesta supporto live chat',
        'voice_title' => 'Parla con Wolfi',
        'voice_copy' => 'Il tuo assistente di trading è pronto ad aiutarti.',
        'voice_online' => 'Online',
        'voice_state_idle' => 'Tocca per parlare',
        'voice_state_listening' => 'In ascolto',
        'voice_state_speaking' => 'Wolfi sta parlando',
        'voice_state_rendering' => 'Wolfi sta pensando',
        'voice_ready' => 'Wolfi è pronto.',
        'voice_listening' => 'In ascolto... tocca di nuovo per fermare.',
        'voice_unsupported' => 'L’input vocale non è supportato in questo browser. Puoi comunque scrivere una domanda.',
        'voice_no_match' => 'Non sono del tutto sicuro di aver capito bene. Prova a riformulare oppure apri le FAQ complete.',
        'voice_intro_title' => 'Parla con Wolfi',
        'voice_intro_message' => 'Il tuo assistente di trading è pronto ad aiutarti.',
        'voice_intro_blocked' => 'Wolfi si è aperto, ma il browser ha bloccato la riproduzione audio immediata. Tocca Riproduci risposta per ascoltare la replica.',
        'voice_clarify_title' => 'Voglio assicurarmi di rispondere alla domanda giusta.',
        'voice_clarify_intro' => 'Potrei stare confondendo la tua domanda. Prova invece uno di questi: :suggestions',
        'voice_support_fallback' => 'Posso aiutarti con regole, payout, piani e domande generali sulla piattaforma. Per billing o assistenza specifica sull’account, contatta :email.',
        'voice_trial_fallback' => 'Usa Prova Gratuita per aprire il flusso demo Wolforix. Gli utenti esistenti possono inserire la stessa email e password e Wolforix li porterà nella dashboard trial oppure creerà il trial se non è ancora stato avviato.',
        'voice_plan_fallback' => 'Wolforix offre attualmente i modelli 1-Step Instant e 2-Step Pro nelle dimensioni 5K, 10K, 25K, 50K e 100K. Scegli il modello che si adatta alla tua tolleranza al rischio, poi usa Ottieni il piano per continuare.',
        'voice_payout_fallback' => 'Le commissioni vengono pagate su richiesta e sono soggette a revisione e approvazione da parte del Wolforix Partner Success Team. Il primo payout diventa idoneo dopo :first_payout_days giorni, con payout successivi disponibili in cicli ricorrenti di :payout_cycle_days giorni. Una volta completato il ciclo richiesto, i payout vengono elaborati entro 24 ore. Per richiedere un payout, invia un’email a support@wolforix.com una volta raggiunta la soglia minima di prelievo di $100.',
        'voice_max_drawdown_fallback' => 'Se raggiungi il limite massimo di drawdown, la challenge viene fallita e l’account può essere bloccato o disattivato. Le posizioni aperte potrebbero dover essere chiuse, e la dashboard mostrerà lo stato di fallimento e il motivo.',
        'voice_rules_fallback' => '1-Step usa un target del 10%, perdita giornaliera max del 4% e perdita totale max dell’8%. 2-Step usa target del 10% e poi 5%, perdita giornaliera max del 5%, perdita totale max del 10% e minimo 3 giorni di trading per fase.',
        'voice_checkout_fallback' => 'Clicca Ottieni il piano sulla challenge selezionata, poi accedi o crea il tuo account prima del checkout. Dopo l’autenticazione, Wolforix ti riporta al piano corretto.',
        'voice_discount_fallback' => 'Usa il popup di lancio e clicca Ottieni sconto per attivare l’offerta del 20% per la sessione corrente. Se la ignori, i prezzi regolari restano visibili e lo sconto non viene applicato.',
        'voice_general_fallback' => 'Sono più utile su piani Wolforix, accesso demo gratuito, payout, regole, login, checkout e guida MT5. Se la tua domanda è più ampia, posso comunque aiutarti brevemente quando possibile o riportarti alla pagina Wolforix giusta.',
        'voice_input_label' => 'Fai una domanda',
        'voice_input_placeholder' => 'Esempio: Quando posso richiedere il mio primo payout?',
        'voice_suggestions_label' => 'Prompt suggeriti',
        'voice_suggestions_copy' => 'Inizia con una domanda rapida e Wolfi guiderà la conversazione da lì.',
        'voice_submit' => 'Ottieni risposta',
        'voice_button' => 'Parla',
        'voice_play_button' => 'Riproduci risposta',
        'voice_stop_play_button' => 'Ferma audio',
        'voice_play_requires_answer' => 'Ottieni prima una risposta, poi usa Riproduci risposta.',
        'voice_generating_audio' => 'Sto preparando la voce di Wolfi...',
        'voice_external_fallback' => 'La voce premium non è disponibile in questo momento. Uso invece la voce del browser.',
        'voice_audio_unavailable' => 'La riproduzione vocale non è disponibile in questo momento. Riprova tra poco.',
        'voice_speaking' => 'Wolfi sta parlando... tocca Ferma audio per silenziare subito.',
        'voice_stop_button' => 'Ferma',
        'voice_stopped' => 'Microfono fermato.',
        'voice_no_speech' => 'Nessuna voce rilevata. Riprova.',
        'voice_mic_blocked' => 'L’accesso al microfono è stato bloccato. Controlla i permessi del browser e riprova.',
        'voice_audio_capture' => 'Nessun microfono funzionante trovato per l’input vocale.',
        'voice_permission_checking' => 'Controllo accesso al microfono...',
        'voice_secure_context' => 'L’input vocale richiede un contesto browser sicuro come HTTPS o localhost.',
        'voice_answer_title' => 'Risposta di Wolfi',
        'voice_empty' => 'Chiedi di payout, regole, scaling, supporto o idoneità account.',
        'voice_ai_notice' => 'Le risposte vocali sono generate dall’AI.',
        'voice_open_faq' => 'Apri FAQ completa',
    ],
    'security' => [
        'meta_title' => 'Sicurezza',
        'eyebrow' => 'Fiducia e sicurezza',
        'title' => 'Pratiche di sicurezza progettate per proteggere la piattaforma, il tuo account e l’integrità operativa.',
        'description' => 'Wolforix sta costruendo il proprio posizionamento sicurezza attorno a controlli pratici per accesso, monitoraggio, supervisione del rischio e gestione dei dati. L’allineamento ISO/IEC 27001 è in corso e in questa fase non viene rivendicata alcuna certificazione.',
        'badge' => 'Allineamento ISO/IEC 27001 in corso',
        'note' => 'Questa pagina descrive le pratiche di sicurezza attuali e la direzione della roadmap, pensate per rafforzare la fiducia mentre il programma complessivo continua a maturare.',
        'sections' => [
            [
                'title' => 'Sicurezza',
                'description' => 'I controlli su account e piattaforma sono progettati per ridurre accessi non autorizzati e proteggere i flussi di autenticazione.',
                'items' => [
                    'Il supporto alla two-factor authentication è incluso nei controlli di accesso all’account.',
                    'Traffico sensibile e segreti sono protetti tramite crittografia e gestione controllata.',
                    'Le misure di protezione account includono accesso controllato, pratiche credenziali solide e salvaguardie di sessione.',
                ],
            ],
            [
                'title' => 'Risk management',
                'description' => 'Il rischio operativo viene gestito tramite salvaguardie stratificate, monitoraggio e processi di revisione.',
                'items' => [
                    'Diritti di accesso e cambi operativi vengono riesaminati tramite workflow controllati.',
                    'Monitoraggio e alerting aiutano a individuare rapidamente attività insolite e problemi di servizio.',
                    'I processi di risposta sono progettati per contenere i problemi, supportare la revisione e migliorare la resilienza.',
                ],
            ],
            [
                'title' => 'Protezione dei dati',
                'description' => 'La gestione dei dati è strutturata attorno a protezione, accesso limitato e pratiche responsabili di conservazione.',
                'items' => [
                    'I dati sono protetti in transito usando standard moderni di crittografia.',
                    'L’accesso alle informazioni sensibili è limitato a personale e sistemi autorizzati.',
                    'Pratiche di storage, retention e gestione sono progettate per supportare riservatezza e integrità.',
                ],
            ],
            [
                'title' => 'Roadmap',
                'description' => 'La maturità della sicurezza continua ad ampliarsi mentre piattaforma e controlli interni evolvono.',
                'items' => [
                    'L’allineamento ISO/IEC 27001 è attualmente in corso.',
                    'Policy, documentazione e copertura dei controlli continuano a essere formalizzate e riesaminate.',
                    'Monitoraggio, governance degli accessi e pratiche di miglioramento continuo continueranno a espandersi nel tempo.',
                ],
            ],
        ],
    ],
    'legal' => [
        'eyebrow' => 'Legale Wolforix',
        'quick_links' => 'Tutte le pagine legali',
        'overview_title' => 'Panoramica policy',
        'overview_copy' => 'Queste pagine organizzano il testo approvato dal cliente per la Milestone 1 in schermate dedicate e leggibili, invece di collocare lunghi testi legali direttamente nel footer.',
        'link_labels' => [
            'terms' => 'Termini e condizioni',
            'risk_disclosure' => 'Informativa sul rischio',
            'payout_policy' => 'Policy payout',
            'refund_policy' => 'Policy rimborsi',
            'privacy_policy' => 'Privacy Policy',
            'aml_kyc_policy' => 'Policy AML e KYC',
            'company_information' => 'Informazioni societarie',
        ],
        'pages' => [
            'terms' => [
                'title' => 'Termini e condizioni',
                'intro' => 'Wolforix Ltd. opera come piattaforma educativa e di valutazione nel proprietary trading. Accedendo ai nostri servizi, accetti i seguenti termini.',
                'sections' => [
                    [
                        'title' => 'Natura dei servizi',
                        'paragraphs' => [
                            'Tutte le attività di trading si svolgono in un ambiente simulato. Nessun capitale reale viene allocato agli utenti e non vengono forniti servizi di investimento.',
                        ],
                    ],
                    [
                        'title' => 'Idoneità',
                        'paragraphs' => [
                            'Gli utenti devono avere almeno 18 anni e rispettare tutte le leggi applicabili nella propria giurisdizione.',
                        ],
                    ],
                    [
                        'title' => 'Programma di valutazione',
                        'paragraphs' => [
                            'Gli utenti partecipano a una valutazione di trading pensata per misurare le competenze operative. Il completamento con successo non costituisce impiego né allocazione di capitale.',
                        ],
                    ],
                    [
                        'title' => 'Regole di trading',
                        'paragraphs' => [
                            'Gli utenti devono rispettare tutte le regole di trading, inclusi drawdown massimo, limiti di perdita giornaliera e requisiti di consistenza.',
                        ],
                    ],
                    [
                        'title' => 'Strumenti consentiti e standard strategici',
                        'paragraphs' => [
                            'Wolforix consente discretionary trading, algorithmic trading ed Expert Advisors (EA), purché la strategia sia legittima, rifletta condizioni di mercato reali, sia coerente con una sana gestione del rischio e resti adatta all’allocazione di capitale live.',
                            'I CFD attualmente evidenziati per l’ambiente di valutazione includono EUR/USD, USD/JPY e Gold (XAU/USD) come principale mercato commodities.',
                        ],
                    ],
                    [
                        'title' => 'Regola di consistenza',
                        'paragraphs' => [
                            'Per qualificarsi ai payout, non più del 40% dei profitti totali può essere generato in un singolo giorno di trading. Se il limite viene superato, è richiesta ulteriore attività di trading.',
                        ],
                    ],
                    [
                        'title' => 'Regola sul news trading',
                        'paragraphs' => [
                            'È vietato aprire o chiudere operazioni 5 minuti prima e 5 minuti dopo un evento news ad alto impatto. Questa restrizione si applica sia agli ordini a mercato sia agli ordini pendenti, incluse le attivazioni di stop-loss o take-profit. Puoi mantenere posizioni esistenti durante l’evento, ma non puoi aprire né chiudere trade in questa finestra.',
                        ],
                    ],
                    [
                        'title' => 'Regola sullo scalping',
                        'bullets' => [
                            'I trade chiusi in meno di 60 secondi sono severamente vietati se generano profitto.',
                            'Tale attività è considerata non replicabile in condizioni di mercato reali e può indicare sfruttamento della latenza o esecuzione irrealistica.',
                            'Lo scalping standard è consentito se la durata del trade riflette una reale esposizione al mercato.',
                            'Wolforix può escludere tali trade dal calcolo dei profitti o adottare ulteriori azioni in caso di ripetizione.',
                        ],
                    ],
                    [
                        'title' => 'Pratiche vietate',
                        'paragraphs' => [
                            'Qualsiasi forma di abuso, arbitrage exploitation, latency exploitation o manipolazione dell’ambiente di trading comporterà una revisione dell’account e potrà portare a restrizione o chiusura dello stesso.',
                        ],
                        'bullets' => [
                            'La duplicazione di EA di terze parti può entrare in conflitto con il risk management interno e può portare a un’allocazione limitata.',
                            'Possono applicarsi limiti al numero massimo di ordini aperti contemporaneamente e ai limiti giornalieri di esecuzione.',
                            'Un eccesso di ordini, modifiche o carico server può attivare una revisione della strategia.',
                            'Wolforix può rimuovere o regolare posizioni, riequilibrare l’account, ridurre la leva, sospendere o terminare l’account oppure interrompere la collaborazione con il trader se viene rilevata attività vietata.',
                        ],
                    ],
                    [
                        'title' => 'Limitazione di responsabilità',
                        'paragraphs' => [
                            'Wolforix Ltd. non sarà responsabile per perdite, danni o impossibilità di utilizzare la piattaforma.',
                        ],
                    ],
                ],
            ],
            'risk_disclosure' => [
                'title' => 'Informativa sul rischio',
                'intro' => 'Il trading sui mercati finanziari comporta rischi significativi. Tutte le attività su questa piattaforma si svolgono in un ambiente simulato esclusivamente per finalità educative.',
                'sections' => [
                    [
                        'title' => 'Ambiente di trading simulato',
                        'paragraphs' => [
                            'Tutte le attività di trading fornite da Wolforix avvengono in un ambiente simulato usando fondi virtuali. Questi fondi sono fittizi, non hanno valore monetario e non possono essere ritirati, trasferiti o usati nel mercato reale.',
                            'Eventuali funded account mostrati fanno parte di un programma di valutazione simulato. Non rappresentano capitale reale e nessun trade viene eseguito nei mercati finanziari live.',
                        ],
                    ],
                    [
                        'title' => 'Nessun servizio di investimento',
                        'paragraphs' => [
                            'Wolforix Ltd. non fornisce consulenza finanziaria, gestione di portafoglio, servizi di brokeraggio o custodia di fondi cliente.',
                            'Nulla su questo sito costituisce consulenza finanziaria, raccomandazione di investimento o offerta di acquisto o vendita di strumenti finanziari.',
                        ],
                    ],
                    [
                        'title' => 'Performance e payout',
                        'paragraphs' => [
                            'Le performance passate non garantiscono risultati futuri. I risultati ipotetici hanno limiti intrinseci e possono differire dalle condizioni di mercato reali a causa di fattori come liquidità, slippage e ritardi di esecuzione.',
                            'Qualsiasi payout, testimonianza o esempio di performance mostrato è puramente illustrativo e non garantisce risultati futuri.',
                        ],
                    ],
                    [
                        'title' => 'Giurisdizioni limitate e responsabilità',
                        'paragraphs' => [
                            'I nostri servizi non sono disponibili nelle giurisdizioni in cui il loro uso violerebbe leggi o regolamenti locali.',
                            'Wolforix non sarà ritenuta responsabile per perdite dirette o indirette derivanti dall’uso della piattaforma, dei servizi o delle informazioni fornite.',
                        ],
                    ],
                ],
            ],
            'payout_policy' => [
                'title' => 'Policy payout',
                'intro' => 'Il primo payout può essere richiesto dopo 21 giorni. I payout successivi possono essere richiesti ogni 14 giorni.',
                'highlight' => [
                    'title' => 'Elaborazione payout',
                    'items' => [
                        'Pagamenti entro 24 ore dopo l’approvazione',
                    ],
                    'note' => 'Una volta approvati, i payout vengono elaborati entro 24 ore.',
                ],
                'sections' => [
                    [
                        'title' => 'Idoneità al payout',
                        'paragraphs' => [
                            'Gli account funded possono richiedere il primo payout dopo 21 giorni. Successivamente, i payout possono essere richiesti ogni 14 giorni.',
                            'Gli account funded 2-Step possono anche scalare del +25% di capitale ogni 3 mesi se profittevoli.',
                            'Gli account funded 1-Step seguono lo stesso ritmo payout di 14 giorni ma richiedono il rispetto obbligatorio della regola di consistenza prima dell’approvazione payout.',
                        ],
                    ],
                    [
                        'title' => 'Requisiti di idoneità',
                        'bullets' => [
                            'Devono essere raggiunti i giorni minimi di trading della fase attiva.',
                            'Gli account funded 1-Step devono soddisfare la regola di consistenza obbligatoria.',
                            'Non devono esserci violazioni di regole sull’account.',
                        ],
                    ],
                    [
                        'title' => 'Revisione e approvazione',
                        'paragraphs' => [
                            'Wolforix Ltd. si riserva il diritto di rivedere tutta l’attività di trading prima di approvare i payout.',
                        ],
                    ],
                ],
            ],
            'refund_policy' => [
                'title' => 'Policy rimborsi',
                'intro' => 'Tutti gli acquisti sono definitivi e non rimborsabili una volta ottenuto l’accesso alla trading challenge.',
                'sections' => [
                    [
                        'title' => 'Regola generale',
                        'paragraphs' => [
                            'Tutti gli acquisti sono definitivi e non rimborsabili una volta ottenuto l’accesso alla trading challenge.',
                        ],
                    ],
                    [
                        'title' => 'Eccezioni limitate',
                        'paragraphs' => [
                            'I rimborsi possono essere emessi solo in caso di errori tecnici o pagamenti duplicati.',
                        ],
                    ],
                ],
            ],
            'privacy_policy' => [
                'title' => 'Privacy Policy',
                'intro' => 'Wolforix Ltd. raccoglie e tratta dati personali in conformità con le leggi applicabili sulla protezione dei dati.',
                'sections' => [
                    [
                        'title' => 'Uso dei dati',
                        'paragraphs' => [
                            'I dati degli utenti sono utilizzati per gestione account, verifica e finalità di compliance.',
                        ],
                    ],
                    [
                        'title' => 'Condivisione dei dati',
                        'paragraphs' => [
                            'Non vendiamo né condividiamo dati personali con terze parti senza consenso.',
                        ],
                    ],
                ],
            ],
            'aml_kyc_policy' => [
                'title' => 'Policy AML e KYC',
                'intro' => 'Per rispettare le normative antiriciclaggio, agli utenti può essere richiesto di verificare la propria identità prima di ricevere payout.',
                'sections' => [
                    [
                        'title' => 'Requisito di verifica',
                        'paragraphs' => [
                            'Per rispettare le normative antiriciclaggio, agli utenti può essere richiesto di verificare la propria identità prima di ricevere payout.',
                        ],
                    ],
                    [
                        'title' => 'Richieste documentali',
                        'paragraphs' => [
                            'Wolforix Ltd. si riserva il diritto di richiedere documentazione in qualsiasi momento.',
                        ],
                    ],
                    [
                        'title' => 'Mancata conformità',
                        'paragraphs' => [
                            'La mancata conformità può comportare la sospensione dell’account.',
                        ],
                    ],
                ],
            ],
            'company_information' => [
                'title' => 'Informazioni societarie',
                'intro' => 'Wolforix Ltd. è una società incorporata nel Regno Unito, con sede legale in Suite RA01, 195-197 Wood Street, London, E17 3NU.',
                'sections' => [
                    [
                        'title' => 'Informazioni societarie',
                        'paragraphs' => [
                            'Wolforix Ltd. è una società incorporata nel Regno Unito, con sede legale in Suite RA01, 195-197 Wood Street, London, E17 3NU.',
                        ],
                    ],
                    [
                        'title' => 'Natura dei servizi',
                        'paragraphs' => [
                            'Wolforix opera come società di valutazione e formazione nel proprietary trading. Non siamo un broker, un istituto finanziario, una società di investimento né un custodian.',
                            'Non accettiamo depositi, non gestiamo fondi cliente e non eseguiamo trade per conto degli utenti.',
                        ],
                    ],
                    [
                        'title' => 'Avviso normativo',
                        'paragraphs' => [
                            'Wolforix opera al di fuori dell’ambito delle autorità di regolamentazione finanziaria, poiché non fornisce servizi di brokeraggio o investimento.',
                            'Gli utenti sono responsabili di verificare la conformità alle leggi locali prima di usare i nostri servizi.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'footer' => [
        'disclaimer_title' => 'Ambiente simulato',
        'legal_copy' => [
            'Wolforix Ltd. è una società registrata nel Regno Unito (Company Number: 17111904), con sede legale in Suite RA01, 195-197 Wood Street, London, E17 3NU. Wolforix opera come piattaforma educativa e di valutazione nel proprietary trading.',
            'Tutti i servizi forniti da Wolforix si svolgono esclusivamente in un ambiente di trading simulato usando fondi virtuali. Questi fondi non hanno valore reale, non sono prelevabili e non rappresentano capitale reale. Wolforix non è un broker, non è un istituto finanziario e non fornisce servizi di investimento, consulenza finanziaria o gestione patrimoniale.',
            'Nulla su questa piattaforma costituisce consulenza finanziaria o offerta di acquisto o vendita di strumenti finanziari. I risultati ottenuti in ambienti simulati non garantiscono risultati futuri nei mercati reali e possono differire in modo significativo dagli esiti di trading reali.',
            'Qualsiasi performance mostrata, inclusi i payout, è puramente illustrativa e soggetta alle condizioni specifiche del programma. Tutti i payout sono soggetti a verifica, inclusi controlli interni di sicurezza, misure antifrode e procedure di verifica identità.',
            'Wolforix si riserva il diritto di richiedere documentazione aggiuntiva, rivedere account, regolare risultati, negare payout, annullare profitti o sospendere e/o terminare account in caso di violazione dei propri termini o rilevazione di attività irregolare.',
            'I nostri servizi non sono disponibili nelle giurisdizioni in cui il loro uso violerebbe leggi o regolamenti applicabili. È responsabilità dell’utente assicurarsi della conformità alle leggi locali.',
            'Possiamo condividere informazioni con fornitori terzi strettamente necessari al funzionamento della piattaforma, inclusi servizi di pagamento, provider infrastrutturali o servizi di verifica, in conformità con le leggi applicabili sulla protezione dei dati.',
            'Wolforix non garantisce disponibilità continua o ininterrotta dei propri servizi e non fornisce garanzie espresse o implicite. Wolforix non sarà responsabile per perdite dirette, indirette o consequenziali derivanti dall’uso della piattaforma.',
            'Wolforix si riserva il diritto di modificare questi termini e queste policy in qualsiasi momento.',
            'Usando questo sito, accetti i nostri Terms and Conditions, Privacy Policy, Payout Policy, Refund Policy e tutti i documenti legali correlati.',
        ],
        'legal_title' => 'Legale e policy',
        'security_title' => 'Fiducia e sicurezza',
        'security_line' => 'Sicurezza allineata agli standard ISO/IEC 27001 (in corso)',
        'security_link' => 'Vedi sicurezza',
        'operations_title' => 'Operazioni',
        'operations_copy' => 'Il supporto è gestito via email e successivamente tramite ticketing in dashboard. I prelievi manuali restano soggetti a revisione amministrativa e l’approvazione payout dipende dal rispetto delle regole.',
        'contact_title' => 'Contatti e supporto',
        'contact_copy' => 'Ti serve aiuto diretto prima di acquistare? Contatta il supporto oppure apri Wolfi per ottenere rapidamente guida su regole e piattaforma.',
        'payments' => [
            'eyebrow' => 'Checkout affidabile',
            'title' => 'Metodi di pagamento progettati per dare fiducia',
            'description' => 'Canali di checkout riconosciuti e brand di pagamento familiari rendono l’ultimo passo più rapido, sicuro e premium.',
            'cards_label' => 'Carte principali',
            'protected_label' => 'Flusso ordine protetto',
        ],
        'community' => [
            'eyebrow' => 'Accesso community',
            'title' => 'Wolforix Community Access',
            'description' => 'Access structured market insights, analysis and updates in real time',
            'channels' => [
                'youtube' => [
                    'description' => 'Guarda aggiornamenti di piattaforma, contenuti educativi e video pensati per i trader.',
                    'cta' => 'Apri YouTube',
                ],
                'instagram' => [
                    'description' => 'Segui aggiornamenti visuali, highlight e contenuti community in formato breve.',
                    'cta' => 'Apri Instagram',
                ],
                'telegram' => [
                    'description' => 'Unisciti al canale diretto per annunci, note di mercato e aggiornamenti community.',
                    'cta' => 'Apri Telegram',
                ],
            ],
        ],
        'positioning_bullets' => [
            'Payout rapidi. Zero complicazioni',
            'Costruita diversamente dalle prop firm tradizionali',
            'Progettata per trader disciplinati — non per gambler',
        ],
        'simulated_notice' => 'Qualsiasi funded account mostrato in questa interfaccia fa parte di un programma di valutazione simulato. Nessun capitale reale viene tradato nei mercati finanziari live.',
        'company_location' => 'Wolforix Ltd. | Suite RA01, 195-197 Wood Street, London, E17 3NU',
        'copyright' => 'Tutti i diritti riservati.',
        'back_to_top' => 'Torna su',
        'view_full_legal_information' => 'Vedi informazioni legali complete',
        'quick_navigation_eyebrow' => 'Navigazione rapida',
        'quick_navigation' => 'Apri navigazione principale',
        'contact_short' => 'Contatti',
    ],
    'cookie' => [
        'title' => 'Avviso cookie',
        'message' => 'Usiamo cookie per migliorare la tua esperienza e supportare le funzionalità essenziali del sito.',
        'accept' => 'Accetta',
        'learn_more' => 'Scopri di più',
    ],
    'fixed_disclaimer' => [
        'label' => 'Avviso ambiente simulato',
        'text' => 'Wolforix opera in un ambiente di trading simulato. Consulta FAQ e payout policy prima di acquistare una challenge.',
        'faq_link' => 'FAQ',
        'policy_link' => 'Policy payout',
        'close_label' => 'Chiudi avviso',
    ],
    'dashboard' => [
        'preview_title' => 'Dashboard di Trading',
        'preview_subtitle' => 'Account, payout e progressione in un solo spazio.',
        'nav' => [
            'wolfi_hub' => 'Wolfi Hub',
        ],
        'wolfi_hub_page' => [
            'title' => 'Wolfi Hub',
            'subtitle' => 'Supporto basato sull’account, guida piattaforma e contesto Wolfi senza occupare il workspace di trading.',
            'empty_title' => 'Wolfi sta preparando il tuo workspace',
            'empty_copy' => 'Quando il contesto della dashboard sarà caricato, Wolfi Hub spiegherà pagina, stato account, regole, payout e supporto.',
        ],
        'mt5' => [
            'title' => 'Sync live MT5',
            'heading' => 'Sync MT5 e accesso account',
            'copy' => 'Wolforix ora organizza il flusso trader su dati account MT5, freschezza del sync e dettagli di accesso sicuri.',
        ],
        'wolfi' => [
            'entry_eyebrow' => 'Wolfi supporta il tuo',
            'entry_title' => 'workspace di trading',
            'entry_copy' => 'Pronto a supportare il tuo prossimo passo su regole, metriche, tempistiche payout e contesto MT5.',
            'entry_hint' => 'La dashboard principale resta focalizzata sui dati account; l’assistente completo vive in Wolfi Hub.',
            'open_hub' => 'Apri Wolfi – Il tuo profilo',
            'fallbacks' => ['dashboard_workspace' => 'Workspace dashboard'],
            'welcome' => [
                'title' => 'Briefing account personalizzato',
                'account_message' => 'Sto leggendo il tuo account :plan in :page per spiegare stato, regole, payout e dati MT5 in modo semplice.',
                'account_bullets' => [
                    'status' => 'Stato attuale: :status con :progress di progresso verso il target.',
                    'balance' => 'Balance :balance, equity :equity e P&L flottante :pnl.',
                    'trading_days' => 'Progresso giorni di trading: :days per la fase attiva.',
                ],
                'empty_message' => 'Posso spiegare :page, regole challenge, payout e passi di supporto mentre Wolforix attende un account MT5 attivo su questo profilo.',
                'empty_bullets' => [
                    'Usa Wolfi Hub per l’assistente completo con contesto account.',
                    'La dashboard principale mantiene prima workspace trading e dati account.',
                    'Quando arrivano dati sync MT5, Wolfi aggiunge qui spiegazioni personalizzate.',
                ],
            ],
            'stat_labels' => [
                'status' => 'Stato',
                'balance' => 'Balance',
                'equity' => 'Equity',
                'page' => 'Pagina',
                'rules' => 'Regole',
                'support' => 'Supporto',
            ],
            'stat_values' => ['structured' => 'Strutturato', 'ready' => 'Pronto'],
            'assistant' => [
                'eyebrow' => 'Wolfi supporta il tuo',
                'title' => 'workspace di trading',
                'description' => 'Pronto a supportare il tuo prossimo passo con guida attenta a regole e metriche dentro Wolfi Hub.',
                'sources_title' => 'Risposta live',
                'sources_copy' => 'Attento a regole e metriche, pronto per una futura riproduzione vocale.',
                'status_idle' => 'Pronto a supportare il prossimo passo',
                'status_thinking' => 'Wolfi sta controllando il contesto account',
                'input_placeholder' => 'Chiedi di account MT5, regole, payout, metriche o supporto...',
                'submit_label' => 'Chiedi a Wolfi',
                'input_help' => 'Wolfi usa pagina corrente e account selezionato quando i dati sono disponibili.',
            ],
            'pillars' => [
                ['title' => 'Attento alle regole', 'description' => 'Guida basata sulle regole della piattaforma.'],
                ['title' => 'Attento alle metriche', 'description' => 'Insight che seguono ciò che conta davvero.'],
                ['title' => 'Tempistiche payout', 'description' => 'Resta in linea con il calendario dei payout.'],
                ['title' => 'Sempre pronto', 'description' => 'Ottieni supporto immediato quando ne hai bisogno.'],
            ],
            'quick_actions' => [
                ['key' => 'dashboard', 'label' => 'Spiega la mia dashboard', 'prompt' => 'Spiega la mia dashboard'],
                ['key' => 'rules', 'label' => 'Quali sono le regole?', 'prompt' => 'Quali sono le regole del challenge?'],
                ['key' => 'metrics', 'label' => 'Spiega le mie metriche', 'prompt' => 'Spiega le mie metriche'],
                ['key' => 'payouts', 'label' => 'Come funzionano i payout?', 'prompt' => 'Come funzionano i payout?'],
                ['key' => 'consistency', 'label' => 'Cos’è la regola di consistenza?', 'prompt' => 'Cos’è la regola di consistenza?'],
            ],
            'smart_insights' => [
                'title' => 'Smart Insights',
                'description' => 'Wolfi monitora il contesto live dell’account e segnala ciò che merita attenzione.',
            ],
            'pages' => [
                'dashboard.wolfi' => [
                    'title' => 'Wolfi Hub',
                    'summary' => 'Usa questa pagina per l’assistente Wolfi completo, spiegazioni account, Smart Insights, supporto e guida piattaforma.',
                    'sections' => [
                        ['title' => 'Briefing personale', 'description' => 'Wolfi spiega stato account, dati MT5, regole, progresso e contesto payout.'],
                        ['title' => 'Prompt rapidi', 'description' => 'Le azioni rapide aiutano a chiedere dashboard, regole, metriche, payout e consistenza.'],
                        ['title' => 'Contesto supporto', 'description' => 'Wolfi può indirizzare verso billing, supporto, navigazione o prossimo passo operativo.'],
                    ],
                ],
            ],
            'insights' => [
                'risk_alert' => ['label' => 'Allerta rischio', 'daily_message' => 'L’uso della perdita giornaliera è alto. Proteggi l’account prima di un nuovo setup.', 'max_message' => 'L’uso del max drawdown è alto. Riduci il rischio.', 'meta' => 'Daily :daily% · Max :max%', 'prompt' => 'Spiega il mio rischio drawdown e margine restante'],
                'profit_progress' => ['label' => 'Progresso profitto', 'message' => 'Sei vicino al target. Proteggi i guadagni e completa le regole.', 'meta' => ':progress% del target', 'prompt' => 'Spiega cosa manca per passare questa fase'],
                'consistency_warning' => ['label' => 'Avviso consistenza', 'message' => 'Un giorno concentra molto profitto. Distribuisci i guadagni prima del payout.', 'meta' => 'Ratio miglior giorno :ratio%', 'prompt' => 'Spiega il mio stato di consistenza'],
                'payout_readiness' => ['label' => 'Preparazione payout', 'message' => 'La finestra payout sembra aperta. Verifica le regole e prepara la richiesta.', 'meta' => 'Account funded', 'prompt' => 'Spiega la mia preparazione payout'],
            ],
        ],
        'settings' => [
            'preferences_copy' => 'La dashboard è già locale-aware, quindi il cambio lingua resta coerente man mano che vengono aggiunte altre lingue supportate.',
        ],
    ],
]);
