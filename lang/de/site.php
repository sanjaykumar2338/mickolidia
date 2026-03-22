<?php

return [
    'meta' => [
        'brand' => 'Wolforix',
        'default_title' => 'Wolforix Prop-Firm-Plattform',
        'description' => 'Meilenstein-1-Grundlage für die Wolforix Prop-Firm-Plattform mit dunklem Premium-Branding, mehrsprachigen öffentlichen Seiten, Rechtsstruktur und Dashboard-Vorschau.',
    ],

    'languages' => [
        'en' => 'Englisch',
        'de' => 'Deutsch',
        'es' => 'Spanisch',
    ],

    'locale' => [
        'current_label' => 'Sprache',
        'menu_title' => 'Sprache auswählen',
        'future_label' => 'Bereit für weitere Sprachen',
    ],

    'public_layout' => [
        'preview_badge' => 'Meilenstein-1-Grundlage',
        'simulated_notice' => 'Trade Fearlessly. Win Real.',
    ],

    'nav' => [
        'home' => 'Startseite',
        'plans' => 'Pläne',
        'faq' => 'FAQ',
        'legal' => 'Rechtliches',
        'dashboard_preview' => 'Dashboard-Vorschau',
        'login' => 'Login',
    ],

    'home' => [
        'eyebrow' => 'Wolforix Prop-Evaluation',
        'title' => 'Beweise es. Werde finanziert. Skaliere schnell.',
        'description' => 'Beweise deine Strategie. Lass dich finanzieren. Skaliere deine Performance.',
        'primary_cta' => 'Challenge starten',
        'secondary_cta' => 'Dashboard-Vorschau öffnen',
        'days' => 'Tage',
        'badges' => [
            'EN / DE / ES bereit',
            'Launch-Preise aktiv',
            '14-Tage-Auszahlungsmodelle bereit',
        ],
        'hero_panel' => [
            'title' => 'Zentrale Regelstruktur',
            'caption' => 'Gold bleibt auf wichtige Markenmomente, Aktionen und kritische Kontosignale beschränkt, damit die Oberfläche professionell und nicht wie ein Poster wirkt.',
            'status' => 'Zuerst cTrader',
            'items' => [
                '1-Step: 10 % Ziel, 4 % maximaler Tagesverlust und 8 % maximaler Gesamtverlust.',
                '2-Step Phase 1: 10 % Ziel, 5 % Tagesverlust, 10 % Gesamtverlust und 1:100 Hebel.',
                '2-Step Phase 2: 5 % Ziel mit unbegrenzten Handelstagen und denselben 5 % / 10 % Risikogrenzen.',
                'Funded-Regeln spiegeln jetzt 80 % Gewinnbeteiligung, 14-Tage-Auszahlungen und 2-Step-Skalierung fuer profitable Konten wider.',
            ],
        ],
        'image_caption' => 'Branding-Integration',
        'image_copy' => 'Die gelieferten Kunden-Grafiken bestimmen die goldene Markenidentität, während die Plattform dunkel, zurückhaltend und SaaS-orientiert bleibt.',
        'feature_cards' => [
            '80 % Gewinnbeteiligung',
            'Bis zu $100K simuliertes Kapital',
            '14-Tage-Auszahlungszyklus',
            'Keine Zeitlimits - Evaluationsphase',
        ],
        'challenge_selector' => [
            'currency_label' => 'Waehrung',
            'type_label' => 'Challenge-Typ',
            'size_label' => 'Kontogröße',
            'insight_title' => 'Modellueberblick',
            'entry_fee' => 'Launch-Preis',
            'current_price' => 'Launch-Preis',
            'original_price' => 'Regulaerer Preis',
            'discount_badge' => '20 % RABATT - Limitierte Launch-Aktion',
            'discount_urgency' => 'Launch-Rabatt - Nur fuer kurze Zeit',
            'start_button' => 'Challenge starten',
            'review_policy' => 'Auszahlungsrichtlinie ansehen',
            'faq_link' => 'FAQ lesen',
            'unlimited' => 'Unbegrenzt',
            'highlights' => [
                '20 % Launch-Rabatt aktiv',
                '14-Tage-Auszahlungszyklus',
                'Unbegrenzte Evaluationsdauer',
            ],
            'currencies' => [
                'USD' => 'US-Dollar',
                'EUR' => 'Euro',
                'GBP' => 'Britisches Pfund',
            ],
            'phase_titles' => [
                'single_phase' => 'Einzelphase',
                'phase_1' => 'Phase 1',
                'phase_2' => 'Phase 2',
                'funded' => 'Funded-Konto',
            ],
            'metrics' => [
                'profit_target' => 'Gewinnziel',
                'profit_share' => 'Gewinnbeteiligung',
                'daily_loss' => 'Max. Tagesverlust',
                'total_loss' => 'Max. Gesamtverlust',
                'minimum_days' => 'Min. Handelstage',
                'first_withdrawal' => 'Erste Auszahlung',
                'max_trading_days' => 'Max. Handelstage',
                'leverage' => 'Hebel',
                'payout_cycle' => 'Auszahlungszyklus',
                'scaling' => 'Skalierung',
                'consistency_rule' => 'Konsistenzregel',
            ],
            'value_templates' => [
                'days' => ':days Tage',
                'after_days' => 'Nach :days Tagen',
                'scaling' => '+:percent % Kapital alle :months Monate bei Profitabilitaet',
            ],
            'consistency_required' => 'Obligatorisch',
            'types' => [
                'one_step' => [
                    'label' => '1-Step Challenge',
                    'description' => 'Einphasige Evaluation mit engeren Verlustgrenzen und einer obligatorischen Konsistenzregel auf dem Funded-Konto.',
                    'note_title' => '1-Step Funded-Modell',
                    'note_body' => '1-Step Funded-Konten zahlen alle 14 Tage mit 80 % Gewinnbeteiligung aus. Die Konsistenzregel bleibt vor jeder Auszahlung obligatorisch.',
                ],
                'two_step' => [
                    'label' => '2-Step Challenge',
                    'description' => 'Zwei Evaluationsphasen mit 1:100 Hebel in Phase 1, 14-Tage-Auszahlungen und Skalierung fuer profitable Funded-Konten.',
                    'note_title' => '2-Step Funded-Modell',
                    'note_body' => '2-Step Funded-Konten zahlen alle 14 Tage aus, erlauben die erste Auszahlung nach 14 Tagen und skalieren bei Profitabilitaet alle 3 Monate um +25 % Kapital.',
                ],
            ],
        ],
        'plans' => [
            'eyebrow' => 'Challenge-Pläne',
            'title' => 'Finale Challenge-Modelle, Launch-Preise und Funded-Regeln.',
            'description' => 'Wechsle zwischen 1-Step- und 2-Step-Kontogroessen, um Evaluationsregeln, Auszahlungszeitpunkte, Skalierung und das aktuelle Launch-Angebot zu pruefen.',
            'badge' => 'Finale launch-bereite Challenge-Modelle',
            'entry_fee' => 'Teilnahmegebühr',
            'profit_target' => 'Gewinnziel',
            'daily_loss' => 'Tagesverlust',
            'max_loss' => 'Gesamtverlust',
            'steps' => 'Schritte',
            'profit_share' => 'Gewinnbeteiligung',
            'first_payout' => 'Erste Auszahlung',
            'minimum_days' => 'Mindesthandelstage',
        ],
        'foundation' => [
            'eyebrow' => 'Plattform-Richtung',
            'title' => 'Gebaut für Vertrauen, Regeltransparenz und spätere Automatisierung.',
            'description' => 'Dieser erste Meilenstein konzentriert sich auf die Struktur vor Live-Integrationen: saubere Navigation, mehrsprachige Inhalte, sichtbare Richtlinien und ein Dashboard, das operativ statt werblich wirkt.',
            'cards' => [
                [
                    'title' => 'Zuerst simulierte Evaluation',
                    'description' => 'Die öffentlichen Texte folgen dem Kundendisclaimer: Wolforix ist ein Unternehmen für proprietäre Trading-Evaluation und Ausbildung, kein Broker oder Investmentunternehmen.',
                ],
                [
                    'title' => 'Auszahlungs-Schutzmechanismen früh sichtbar',
                    'description' => 'Der 14-Tage-Auszahlungszyklus, die 1-Step-Konsistenzpflicht, die erste 2-Step-Auszahlung nach 14 Tagen und die +25-%-Skalierung alle 3 Monate sind bereits auf Website und im Dashboard sichtbar.',
                ],
                [
                    'title' => 'Von Anfang an mehrsprachig',
                    'description' => 'Englisch ist Standard, während Deutsch und Spanisch von Beginn an strukturiert sind, damit später weitere Sprachen wie Hindi, Italienisch und Portugiesisch sauber ergänzt werden können.',
                ],
            ],
        ],
        'workflow' => [
            'eyebrow' => 'Umfang von Meilenstein 1',
            'title' => 'Was dieses Fundament bereits abdeckt',
            'items' => [
                [
                    'title' => 'Öffentlicher Website-Kern',
                    'description' => 'Landingpage, gebrandeter Bereich für Challenge-Pläne, Footer-/Rechtsstruktur und ein FAQ-Erlebnis mit den vom Kunden gelieferten Fragen und Auszahlungsformulierungen.',
                ],
                [
                    'title' => 'Dashboard-Grundlage',
                    'description' => 'Sidebar-Navigation, Kopfbereich, Kennzahlenkarten, Auszahlungs-Platzhalter, Einstellungs-Platzhalter und das Warnbanner zur Konsistenzregel sind mit Mock-Daten verdrahtet.',
                ],
                [
                    'title' => 'Backend bereit für Erweiterung',
                    'description' => 'Locale-Middleware, wiederverwendbare Blade-Layouts, MySQL-fähige Schema-Grundlagen und Seed-Daten für Pläne bereiten das Projekt auf Logik und Integrationen in Meilenstein 2 vor.',
                ],
            ],
        ],
    ],

    'launch_popup' => [
        'title' => '20 % RABATT - Begrenztes Angebot auf alle Plaene',
        'description' => 'Launch-Preise sind jetzt fuer jede Wolforix-Challenge aktiv. Pruefe das Modell, das zu deiner Strategie passt, und sichere dir das limitierte Angebot, solange es verfuegbar ist.',
        'primary_action' => 'Launch-Plaene ansehen',
        'secondary_action' => 'Spaeter',
        'close' => 'Launch-Angebot schliessen',
    ],

    'auth' => [
        'eyebrow' => 'Login-Platzhalter',
        'title' => 'Login folgt in Kuerze',
        'description' => 'Authentifizierung gehoert noch nicht zu dieser Verfeinerungsrunde, aber Route und Header-Button sind jetzt fuer den naechsten Umsetzungsschritt vorbereitet.',
        'notice' => 'Nutze bis dahin die Dashboard-Vorschau fuer das UI-Review. Echte Konto-Authentifizierung, sichere Sitzungen und Passwort-Flows werden in einem spaeteren Meilenstein angebunden.',
        'primary_action' => 'Zur Startseite',
    ],

    'checkout' => [
        'eyebrow' => 'Checkout-Grundlage',
        'title' => 'Kauf-UI-Platzhalter mit verpflichtender rechtlicher Bestätigung.',
        'description' => 'Diese Vorschau validiert wichtige Käuferdaten und die verpflichtende Checkbox vor der Zahlung. Live-Stripe- und PayPal-Verarbeitung bleiben in Meilenstein 1 bewusst außerhalb des Umfangs.',
        'supporting_title' => 'Vor der Zahlung',
        'supporting_copy' => 'Die folgende Formulierung entspricht exakt der Kundenvorgabe und wird in dieser Checkout-Grundlage von Meilenstein 1 als Pflichtbestätigung erzwungen.',
        'kyc_notice' => 'Die Kundendatenfelder sind für Registrierung, Kaufprüfung und spätere KYC-/Auszahlungs-Compliance vorbereitet.',
        'helper_points' => [
            'Die Validierung ist aktiv für Name, E-Mail, Straße, Stadt, Postleitzahl, Land, Planauswahl und die verpflichtende Einverständnis-Checkbox.',
            'Stripe wird als Platzhalter dargestellt, damit die Zahlungslogik später ohne Neugestaltung sauber angebunden werden kann.',
            'Der Zustimmungstext stellt ausdrücklich klar, dass es sich um eine simulierte Trading-Evaluation handelt.',
        ],
        'client_data_title' => 'Kundendaten / Registrierungsdetails',
        'full_name' => 'Vollständiger Name',
        'email' => 'E-Mail-Adresse',
        'street_address' => 'Straße und Hausnummer',
        'city' => 'Stadt',
        'postal_code' => 'Postleitzahl',
        'country' => 'Land',
        'select_country' => 'Land auswählen',
        'plan' => 'Challenge-Plan',
        'select_plan' => 'Plan auswählen',
        'platform' => 'Plattform',
        'platform_value' => 'cTrader in Phase 1, MT4/MT5 später',
        'agreement' => 'Ich stimme den Allgemeinen Geschäftsbedingungen zu und verstehe, dass dies eine simulierte Trading-Evaluation ist.',
        'submit' => 'Checkout-Platzhalter validieren',
        'stub_notice' => 'Checkout-Platzhalter validiert. Die Zahlungsabwicklung wird bewusst auf einen späteren Meilenstein verschoben.',
        'buttons' => [
            'stripe' => 'Stripe-Verbindungs-Platzhalter',
            'paypal' => 'Optionaler PayPal-Platzhalter',
        ],
        'validation' => [
            'accept_terms' => 'Sie müssen der Vereinbarung zur simulierten Evaluation zustimmen, bevor Sie fortfahren.',
        ],
    ],

    'faq' => [
        'eyebrow' => 'Häufig gestellte Fragen',
        'title' => 'Durchsuchbare Antworten auf Basis des Kundenbriefings.',
        'description' => 'Fragen, Compliance-Formulierungen, Auszahlungsregeln und Dashboard-Verhalten sind direkt aus dem lokal gespeicherten Kundenchat strukturiert.',
        'search_label' => 'Suche',
        'search_placeholder' => 'FAQs durchsuchen...',
        'no_results' => 'Für diese Suche wurden keine FAQ-Einträge gefunden.',
        'sections' => [
            [
                'title' => 'Allgemein',
                'items' => [
                    [
                        'question' => 'Was ist Wolforix?',
                        'answer' => 'Wolforix Ltd. ist ein Unternehmen für proprietäre Trading-Evaluation und Ausbildung. Alle Handelsaktivitäten werden in einer simulierten Umgebung zu Ausbildungszwecken durchgeführt.',
                    ],
                    [
                        'question' => 'Handelt es sich um echtes Geld oder simuliertes Trading?',
                        'answer' => 'Alle Konten arbeiten in einer simulierten Handelsumgebung. Es werden keine echten Gelder an Nutzer zugewiesen.',
                    ],
                    [
                        'question' => 'Wer kann teilnehmen?',
                        'answer' => 'Nutzer müssen mindestens 18 Jahre alt sein und alle geltenden Gesetze ihrer Gerichtsbarkeit einhalten.',
                    ],
                ],
            ],
            [
                'title' => 'Trading-Regeln',
                'items' => [
                    [
                        'question' => 'Was ist die Konsistenzregel?',
                        'answer' => 'Nicht mehr als 40 % des Gesamtgewinns dürfen an einem einzigen Handelstag erzielt werden. Gewinne müssen auf mehrere Handelstage verteilt sein, um für eine Auszahlung berechtigt zu sein.',
                    ],
                    [
                        'question' => 'Wie wird das tägliche Gewinnlimit berechnet?',
                        'answer' => 'Das System vergleicht den heutigen Gewinn mit dem gesamten Kontogewinn. Überschreitet der heutige Gewinn 40 %, wird eine Dashboard-Warnung ausgelöst und die Auszahlungsberechtigung kann beeinflusst werden.',
                    ],
                    [
                        'question' => 'Was sind maximale Drawdowns?',
                        'answer' => 'Jedes Konto hat definierte Drawdown-Grenzen. Das Überschreiten dieser Grenzen kann zur Disqualifikation aus dem Evaluationsprogramm führen.',
                    ],
                ],
            ],
            [
                'title' => 'Auszahlungen',
                'items' => [
                    [
                        'question' => 'Wie oft werden Auszahlungen verarbeitet?',
                        'answer' => 'Funded-Konten werden alle 14 Tage mit einem Hoechstbetrag pro Zyklus geprueft. Verbleibende auszahlungsfaehige Betraege werden in den folgenden Zyklen bearbeitet. 2-Step Funded-Konten koennen die erste Auszahlung nach 14 Tagen anfordern.',
                    ],
                    [
                        'question' => 'Wie wird meine Auszahlung berechnet?',
                        'answer' => 'Auszahlungen haengen von der 80-%-Gewinnbeteiligung, modellspezifischen Auszahlungszeiten, der Einhaltung der Konsistenzregel, falls anwendbar, und internen Auszahlungslimits ab. Der auszahlungsfaehige Betrag kann niedriger sein als der Gesamtgewinn, wenn Tagesgrenzen ueberschritten wurden.',
                    ],
                    [
                        'question' => 'Skalieren Funded-Konten?',
                        'answer' => '2-Step Funded-Konten koennen alle 3 Monate bei Profitabilitaet um +25 % Kapital skaliert werden. 1-Step Konten enthalten diese Skalierungsregel derzeit nicht.',
                    ],
                ],
            ],
            [
                'title' => 'Konten / Dashboard',
                'items' => [
                    [
                        'question' => 'Wie sehe ich meinen Gewinn und Kontostand?',
                        'answer' => 'Ihr Dashboard zeigt Gesamtgewinn, Tagesgewinn und Kontostand in Echtzeit an.',
                    ],
                    [
                        'question' => 'Was passiert, wenn ich mich dem Konsistenzlimit nähere?',
                        'answer' => 'Eine Dashboard-Warnung erscheint: „⚠ Sie nähern sich dem Limit der Konsistenzregel. Gewinne müssen auf mehrere Handelstage verteilt sein, um für eine Auszahlung berechtigt zu sein.“ Zusätzlich kann eine automatische E-Mail-Warnung versendet werden, wenn Sie sich dem kritischen Schwellenwert nähern.',
                    ],
                    [
                        'question' => 'Wie kann ich eine Auszahlung anfordern?',
                        'answer' => 'Die Auszahlungsanforderung befindet sich in Ihrem Dashboard. 1-Step Funded-Konten muessen die obligatorische Konsistenzregel erfuellen, bevor Gewinn auszahlungsberechtigt wird.',
                    ],
                ],
            ],
            [
                'title' => 'Support / Kontakt',
                'items' => [
                    [
                        'question' => 'Wie kontaktiere ich den Support?',
                        'answer' => 'Alle Supportanfragen werden per E-Mail oder Ticketsystem innerhalb Ihres Dashboards bearbeitet.',
                    ],
                    [
                        'question' => 'Gibt es eine Telefonnummer?',
                        'answer' => 'Nein, Wolforix Ltd. bietet keinen Telefonsupport an. Sämtliche Kommunikation wird aus Compliance- und Sicherheitsgründen per E-Mail oder Tickets dokumentiert.',
                    ],
                ],
            ],
            [
                'title' => 'Recht / Compliance',
                'items' => [
                    [
                        'question' => 'Muss ich meine Identität verifizieren?',
                        'answer' => 'Ja, eine Identitätsprüfung (KYC) kann vor der Auszahlung erforderlich sein, um die Vorschriften zur Bekämpfung von Geldwäsche einzuhalten.',
                    ],
                    [
                        'question' => 'Gibt es Regeln gegen Betrug oder Missbrauch?',
                        'answer' => 'Jeder Versuch, das System zu manipulieren, Schlupflöcher auszunutzen oder betrügerische Aktivitäten zu begehen, führt zur Kontokündigung und kann den Behörden gemeldet werden.',
                    ],
                ],
            ],
        ],
    ],

    'legal' => [
        'eyebrow' => 'Wolforix Rechtliches',
        'quick_links' => 'Alle Rechtsseiten',
        'overview_title' => 'Richtlinienübersicht',
        'overview_copy' => 'Diese Seiten formatieren die vom Kunden freigegebenen Texte aus Meilenstein 1 in gut lesbare Einzelseiten, statt lange Rechtstexte direkt im Footer zu platzieren.',
        'link_labels' => [
            'terms' => 'Allgemeine Geschäftsbedingungen',
            'risk_disclosure' => 'Risikohinweis',
            'payout_policy' => 'Auszahlungsrichtlinie',
            'refund_policy' => 'Rückerstattungsrichtlinie',
            'privacy_policy' => 'Datenschutzrichtlinie',
            'aml_kyc_policy' => 'AML- & KYC-Richtlinie',
            'company_information' => 'Unternehmensinformationen',
        ],
        'pages' => [
            'terms' => [
                'title' => 'Allgemeine Geschäftsbedingungen',
                'intro' => 'Wolforix Ltd. betreibt eine Plattform für proprietäre Trading-Evaluation und Ausbildung. Durch den Zugriff auf unsere Dienste stimmen Sie den folgenden Bedingungen zu.',
                'sections' => [
                    [
                        'title' => 'Art der Dienstleistungen',
                        'paragraphs' => [
                            'Alle Handelsaktivitäten werden in einer simulierten Umgebung durchgeführt. Es werden keine echten Gelder an Nutzer zugewiesen und keine Investmentdienstleistungen erbracht.',
                        ],
                    ],
                    [
                        'title' => 'Teilnahmeberechtigung',
                        'paragraphs' => [
                            'Nutzer müssen mindestens 18 Jahre alt sein und alle geltenden Gesetze in ihrer Gerichtsbarkeit einhalten.',
                        ],
                    ],
                    [
                        'title' => 'Evaluationsprogramm',
                        'paragraphs' => [
                            'Nutzer nehmen an einer Trading-Evaluation teil, die Handelsfähigkeiten bewerten soll. Ein erfolgreicher Abschluss stellt weder eine Anstellung noch eine Investmentzuweisung dar.',
                        ],
                    ],
                    [
                        'title' => 'Trading-Regeln',
                        'paragraphs' => [
                            'Nutzer müssen alle Trading-Regeln einhalten, einschließlich maximalem Drawdown, täglichen Verlustgrenzen und Konsistenzanforderungen.',
                        ],
                    ],
                    [
                        'title' => 'Konsistenzregel',
                        'paragraphs' => [
                            'Um für Auszahlungen berechtigt zu sein, dürfen nicht mehr als 40 % des Gesamtgewinns an einem einzigen Handelstag erzielt werden. Bei Überschreitung ist zusätzliche Handelsaktivität erforderlich.',
                        ],
                    ],
                    [
                        'title' => 'Verbotene Praktiken',
                        'paragraphs' => [
                            'Jede Form von Missbrauch, Arbitrage-Ausnutzung oder Manipulation der Handelsumgebung führt zur Kontokündigung.',
                        ],
                    ],
                    [
                        'title' => 'Haftungsbeschränkung',
                        'paragraphs' => [
                            'Wolforix Ltd. haftet nicht für Verluste, Schäden oder die Unfähigkeit, die Plattform zu nutzen.',
                        ],
                    ],
                ],
            ],
            'risk_disclosure' => [
                'title' => 'Risikohinweis',
                'intro' => 'Der Handel an den Finanzmärkten ist mit erheblichen Risiken verbunden. Alle Aktivitäten auf dieser Plattform werden ausschließlich zu Ausbildungszwecken in einer simulierten Umgebung durchgeführt.',
                'sections' => [
                    [
                        'title' => 'Simulierte Handelsumgebung',
                        'paragraphs' => [
                            'Alle von Wolforix bereitgestellten Handelsaktivitäten finden in einer simulierten Umgebung mit virtuellen Geldern statt. Diese Mittel sind fiktiv, haben keinen Geldwert und können nicht abgehoben, übertragen oder für echten Markthandel genutzt werden.',
                            'Alle dargestellten Funded Accounts sind Teil eines simulierten Evaluationsprogramms. Sie stellen kein reales Kapital dar und es werden keine Trades an Live-Finanzmärkten ausgeführt.',
                        ],
                    ],
                    [
                        'title' => 'Keine Investmentdienstleistungen',
                        'paragraphs' => [
                            'Wolforix Ltd. bietet weder Anlageberatung noch Portfolioverwaltung, Brokerage-Services oder Verwahrung von Kundengeldern an.',
                            'Nichts auf dieser Website stellt eine Finanzberatung, eine Anlageempfehlung oder ein Angebot zum Kauf oder Verkauf eines Finanzinstruments dar.',
                        ],
                    ],
                    [
                        'title' => 'Performance & Auszahlungen',
                        'paragraphs' => [
                            'Vergangene Ergebnisse garantieren keine zukünftigen Resultate. Hypothetische Ergebnisse haben inhärente Einschränkungen und können sich aufgrund von Faktoren wie Liquidität, Slippage und Ausführungsverzögerungen von realen Marktbedingungen unterscheiden.',
                            'Alle dargestellten Auszahlungen, Erfahrungsberichte oder Performance-Beispiele dienen nur zur Veranschaulichung und garantieren keine zukünftigen Ergebnisse.',
                        ],
                    ],
                    [
                        'title' => 'Eingeschränkte Gerichtsbarkeiten & Haftung',
                        'paragraphs' => [
                            'Unsere Dienstleistungen sind in Gerichtsbarkeiten nicht verfügbar, in denen ihre Nutzung gegen lokale Gesetze oder Vorschriften verstoßen würde.',
                            'Wolforix haftet nicht für direkte oder indirekte Verluste, die aus der Nutzung der Plattform, der Dienstleistungen oder der bereitgestellten Informationen entstehen.',
                        ],
                    ],
                ],
            ],
            'payout_policy' => [
                'title' => 'Auszahlungsrichtlinie',
                'intro' => 'Auszahlungen werden alle 14 Tage mit einem Hoechstbetrag pro Zyklus verarbeitet. Verbleibende auszahlungsfaehige Betraege werden in den folgenden Zyklen bearbeitet.',
                'sections' => [
                    [
                        'title' => 'Auszahlungsberechtigung',
                        'paragraphs' => [
                            'Auszahlungen werden alle 14 Tage mit einem Hoechstbetrag pro Zyklus verarbeitet. Verbleibende auszahlungsfaehige Betraege werden in den folgenden Zyklen bearbeitet.',
                            '2-Step Funded-Konten koennen die erste Auszahlung nach 14 Tagen anfordern und bei Profitabilitaet alle 3 Monate um +25 % Kapital skaliert werden.',
                            '1-Step Funded-Konten arbeiten im selben 14-Tage-Rhythmus, verlangen jedoch vor jeder Auszahlung die obligatorische Einhaltung der Konsistenzregel.',
                        ],
                    ],
                    [
                        'title' => 'Voraussetzungen',
                        'bullets' => [
                            'Die Mindesthandelstage der aktiven Phase muessen erfuellt sein.',
                            '1-Step Funded-Konten muessen die obligatorische Konsistenzregel erfuellen.',
                            'Es duerfen keine Regelverstoesse auf dem Konto vorliegen.',
                        ],
                    ],
                    [
                        'title' => 'Prüfung & Genehmigung',
                        'paragraphs' => [
                            'Wolforix Ltd. behält sich das Recht vor, vor der Genehmigung von Auszahlungen alle Handelsaktivitäten zu überprüfen.',
                        ],
                    ],
                ],
            ],
            'refund_policy' => [
                'title' => 'Rückerstattungsrichtlinie',
                'intro' => 'Alle Käufe sind endgültig und nicht erstattungsfähig, sobald auf die Trading-Challenge zugegriffen wurde.',
                'sections' => [
                    [
                        'title' => 'Grundregel',
                        'paragraphs' => [
                            'Alle Käufe sind endgültig und nicht erstattungsfähig, sobald auf die Trading-Challenge zugegriffen wurde.',
                        ],
                    ],
                    [
                        'title' => 'Begrenzte Ausnahmen',
                        'paragraphs' => [
                            'Rückerstattungen können nur bei technischen Fehlern oder Doppelzahlungen gewährt werden.',
                        ],
                    ],
                ],
            ],
            'privacy_policy' => [
                'title' => 'Datenschutzrichtlinie',
                'intro' => 'Wolforix Ltd. erhebt und verarbeitet personenbezogene Daten gemäß den geltenden Datenschutzgesetzen.',
                'sections' => [
                    [
                        'title' => 'Datennutzung',
                        'paragraphs' => [
                            'Nutzerdaten werden für Kontoverwaltung, Verifizierung und Compliance-Zwecke verwendet.',
                        ],
                    ],
                    [
                        'title' => 'Datenweitergabe',
                        'paragraphs' => [
                            'Wir verkaufen oder teilen personenbezogene Daten nicht ohne Zustimmung mit Dritten.',
                        ],
                    ],
                ],
            ],
            'aml_kyc_policy' => [
                'title' => 'AML- & KYC-Richtlinie',
                'intro' => 'Zur Einhaltung der Vorschriften zur Bekämpfung von Geldwäsche kann von Nutzern verlangt werden, ihre Identität vor einer Auszahlung zu verifizieren.',
                'sections' => [
                    [
                        'title' => 'Verifizierungsanforderung',
                        'paragraphs' => [
                            'Zur Einhaltung der Vorschriften zur Bekämpfung von Geldwäsche kann von Nutzern verlangt werden, ihre Identität vor einer Auszahlung zu verifizieren.',
                        ],
                    ],
                    [
                        'title' => 'Dokumentenanforderungen',
                        'paragraphs' => [
                            'Wolforix Ltd. behält sich das Recht vor, jederzeit Dokumente anzufordern.',
                        ],
                    ],
                    [
                        'title' => 'Nichteinhaltung',
                        'paragraphs' => [
                            'Die Nichtbeachtung kann zur Sperrung des Kontos führen.',
                        ],
                    ],
                ],
            ],
            'company_information' => [
                'title' => 'Unternehmensinformationen',
                'intro' => 'Wolforix Ltd. ist ein im Vereinigten Koenigreich eingetragenes Unternehmen mit eingetragenem Sitz in Suite RA01, 195-197 Wood Street, London, E17 3NU.',
                'sections' => [
                    [
                        'title' => 'Unternehmensinformationen',
                        'paragraphs' => [
                            'Wolforix Ltd. ist ein im Vereinigten Koenigreich eingetragenes Unternehmen mit eingetragenem Sitz in Suite RA01, 195-197 Wood Street, London, E17 3NU.',
                        ],
                    ],
                    [
                        'title' => 'Art der Dienstleistungen',
                        'paragraphs' => [
                            'Wolforix arbeitet als Unternehmen für proprietäre Trading-Evaluation und Ausbildung. Wir sind weder Broker noch Finanzinstitut, Investmentfirma oder Verwahrer.',
                            'Wir nehmen keine Einlagen an, verwalten keine Kundengelder und führen keine Trades im Namen von Nutzern aus.',
                        ],
                    ],
                    [
                        'title' => 'Regulatorischer Hinweis',
                        'paragraphs' => [
                            'Wolforix agiert außerhalb des Zuständigkeitsbereichs von Finanzaufsichtsbehörden, da keine Brokerage- oder Investmentdienstleistungen erbracht werden.',
                            'Nutzer sind selbst dafür verantwortlich, die Einhaltung lokaler Gesetze vor der Nutzung unserer Dienste sicherzustellen.',
                        ],
                    ],
                ],
            ],
        ],
    ],

    'footer' => [
        'disclaimer_title' => 'Simulierte Umgebung',
        'company_title' => 'Footer- und Rechtsstruktur',
        'summary' => 'Wolforix Ltd. arbeitet als Unternehmen für proprietäre Trading-Evaluation und Ausbildung. Alle Handelsaktivitäten finden in einer simulierten Umgebung mit virtuellen Geldern statt und stellen keine Brokerage- oder Investmentdienstleistungen dar.',
        'service_copy' => 'Wolforix nimmt keine Einlagen an, verwaltet keine Kundengelder und führt keine Trades im Namen von Nutzern aus. Gebühren beziehen sich ausschließlich auf Softwarezugang, Evaluationsservices und Ausbildungstools.',
        'legal_title' => 'Rechtliches & Richtlinien',
        'operations_title' => 'Betrieb',
        'operations_copy' => 'Support wird per E-Mail und später über Dashboard-Tickets abgewickelt. Manuelle Auszahlungen bleiben administrativ geprüft, und die Genehmigung hängt von der Einhaltung der Regeln ab.',
        'simulated_notice' => 'Jedes in dieser Oberfläche angezeigte Funded Account ist Teil eines simulierten Evaluationsprogramms. Es wird kein echtes Kapital an Live-Finanzmärkten gehandelt.',
        'company_location' => 'Wolforix Ltd. | Suite RA01, 195-197 Wood Street, London, E17 3NU',
        'copyright' => 'Alle Rechte vorbehalten.',
    ],

    'fixed_disclaimer' => [
        'label' => 'Hinweis zur simulierten Umgebung',
        'text' => 'Wolforix arbeitet in einer simulierten Trading-Umgebung. Bitte lesen Sie vor dem Kauf einer Challenge die FAQ und die Auszahlungsrichtlinie.',
        'faq_link' => 'FAQ',
        'policy_link' => 'Auszahlungsrichtlinie',
        'close_label' => 'Hinweis schließen',
    ],

    'dashboard' => [
        'preview_title' => 'Dashboard-Grundlage',
        'preview_subtitle' => 'Nur Mock-Daten. Das Layout ist für Live-Challenge-Sync, Auszahlungslogik und spätere Plattform-Integrationen vorbereitet.',
        'sidebar_label' => 'Trader-Arbeitsbereich',
        'simulated_badge' => 'Ansicht eines simulierten Evaluationskontos',
        'status_badge' => 'Intervall-Sync-Vorschau',
        'nav' => [
            'overview' => 'Übersicht',
            'accounts' => 'Konten',
            'payouts' => 'Auszahlungen',
            'settings' => 'Einstellungen',
        ],
        'cards' => [
            'balance' => 'Kontostand',
            'total_profit' => 'Gesamtgewinn',
            'today_profit' => 'Heutiger Gewinn',
            'drawdown' => 'Drawdown',
        ],
        'card_hints' => [
            'balance' => 'Mock-Wert des aktuellen Kontostands für das ausgewählte Evaluationskonto.',
            'total_profit' => 'Gesamter simulierter Gewinn, der aktuell auf dem Konto erfasst wird.',
            'today_profit' => 'Das heutige Ergebnis wird für die Warnung zur Konsistenzregel verwendet.',
            'drawdown' => 'Visueller Platzhalter für tägliche und gesamte Risikoüberwachung.',
        ],
        'account' => [
            'stage' => 'Phase 1',
            'status' => 'Aktiv',
            'next_sync' => 'Mock-Sync alle 5 Minuten',
            'review_status' => 'In Prüfung',
            'review_stage' => 'Funded-Pruefung',
        ],
        'consistency' => [
            'title' => 'Warnung zur Konsistenzregel',
            'message' => '⚠ Sie nähern sich dem Limit der Konsistenzregel. Gewinne müssen auf mehrere Handelstage verteilt sein, um für eine Auszahlung berechtigt zu sein.',
            'meta' => [
                'today_profit' => 'Heutiger Gewinn',
                'limit' => 'Konsistenzlimit',
                'usage' => 'Limit-Auslastung',
            ],
        ],
        'labels' => [
            'reference' => 'Konto-Referenz',
            'platform' => 'Plattform',
            'stage' => 'Phase',
            'next_sync' => 'Nächster Sync',
            'target' => 'Gewinnziel',
            'daily_loss' => 'Tagesverlust',
            'max_loss' => 'Gesamtverlust',
            'min_days' => 'Mindesthandelstage',
            'cycle' => 'Auszahlungszyklus',
            'eligible_profit' => 'Auszahlungsfähiger Gewinn',
            'progress' => 'Fortschritt',
        ],
        'overview' => [
            'snapshot_title' => 'Konten-Snapshot',
            'snapshot_copy' => 'Dieser Bereich ist für zukünftige cTrader-, MT4- und MT5-Sync-Verbindungen vorbereitet. Meilenstein 1 verwendet bewusst nur Demo-Werte.',
            'rules_title' => 'Regelstruktur',
            'rules_copy' => 'Die Vorschau unten ist am finalen 2-Step-Launch-Modell ausgerichtet, damit Ziele, Risikogrenzen, Hebel und Auszahlungszeitpunkt nahe an den Kontokennzahlen sichtbar bleiben.',
            'payout_title' => 'Auszahlungsbereich',
            'payout_copy' => 'Die Auszahlungsvorschau spiegelt jetzt 14-Tage-Zyklen, die erste 2-Step-Auszahlung nach 14 Tagen, 1-Step-Konsistenzanforderungen und die +25-%-Skalierung alle 3 Monate bei Profitabilitaet wider.',
            'settings_title' => 'Profil & Einstellungen',
            'settings_copy' => 'Ein Profil-Platzhalter hält das Dashboard bereit für Spracheinstellungen, KYC und Kontosicherheitsfunktionen.',
        ],
        'accounts_page' => [
            'title' => 'Trading-Konten',
            'subtitle' => 'Mock-Kontokarten für zukünftigen Live-Sync und Verwaltung des Evaluationsstatus.',
        ],
        'payouts_page' => [
            'title' => 'Auszahlungen',
            'subtitle' => 'Berechtigungs-Kommunikation im Einklang mit der Auszahlungsrichtlinie und dem Konsistenzregel-Briefing des Kunden.',
        ],
        'settings_page' => [
            'title' => 'Einstellungen',
            'subtitle' => 'Profil-, Lokalisierungs- und Compliance-Struktur vorbereitet für künftige Benutzerverwaltung.',
        ],
        'payouts' => [
            'next_window' => 'Nächstes Auszahlungsfenster',
            'next_window_value' => 'Naechste 14-Tage-Pruefung in 3 Tagen',
            'cycle_note' => 'Auszahlungen werden alle 14 Tage mit einem Hoechstbetrag pro Zyklus verarbeitet. Verbleibende auszahlungsfaehige Betraege werden in den folgenden Zyklen bearbeitet.',
            'placeholder_status' => 'Platzhalter für manuelle Prüfung',
            'queue_title' => 'Vorschau der Auszahlungswarteschlange',
            'queue_copy' => '14-Tage-Auszahlungszyklen, der Zeitpunkt der ersten 2-Step-Auszahlung, 1-Step-Konsistenzanforderungen und interne Pruefungen werden hier ohne Live-Auszahlungs-Engine dargestellt.',
            'progressive_note' => '2-Step Funded-Konten koennen bei Profitabilitaet alle 3 Monate um +25 % Kapital skaliert werden. 1-Step Funded-Konten behalten die obligatorische Konsistenzregel vor der Auszahlungsfreigabe.',
            'requirements_title' => 'Berechtigungs-Checkliste',
            'requirements' => [
                'Die Mindesthandelstage der aktiven Phase muessen erfuellt sein.',
                '1-Step Funded-Konten muessen die obligatorische Konsistenzregel erfuellen, bevor Gewinn auszahlungsberechtigt wird.',
                '2-Step Funded-Konten koennen die erste Auszahlung nach 14 Tagen anfordern.',
                'Es duerfen keine Regelverstoesse auf dem Konto vorliegen und alle Auszahlungsanfragen bleiben einer internen Handelspruefung unterworfen.',
            ],
            'cta' => 'Platzhalter für Auszahlungsanfrage',
        ],
        'settings' => [
            'profile_title' => 'Profil-Platzhalter',
            'language_label' => 'Bevorzugte Sprache',
            'timezone_label' => 'Zeitzone',
            'preferences_title' => 'Sprache & Lokalisierung',
            'preferences_copy' => 'Das Dashboard ist bereits locale-fähig, sodass Englisch, Deutsch und Spanisch sauber wechseln können, während spätere Sprachen leicht ergänzt werden.',
            'security_title' => 'Compliance- & Sicherheits-Platzhalter',
            'security_copy' => 'KYC-Anfragen, AML-Kontrollen, Audit-Logging und der Umgang mit sensiblen Zugangsdaten bleiben für spätere Meilensteine reserviert.',
            'save' => 'In späterem Meilenstein speichern',
        ],
    ],
];
