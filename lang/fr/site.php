<?php

$en = require __DIR__.'/../en/site.php';

return array_replace_recursive($en, [
    'meta' => [
        'default_title' => 'Plateforme Prop Firm Wolforix',
        'description' => 'Base premium pour la plateforme prop firm Wolforix avec site public multilingue, structure juridique, espace d’essai gratuit et accès authentifié au dashboard.',
    ],
    'languages' => [
        'en' => 'Anglais',
        'de' => 'Allemand',
        'es' => 'Espagnol',
        'fr' => 'Français',
        'hi' => 'Hindi',
        'it' => 'Italien',
        'pt' => 'Portugais',
    ],
    'locale' => [
        'current_label' => 'Langue',
        'menu_title' => 'Choisir la langue',
        'future_label' => 'Prêt pour d’autres langues',
    ],
    'public_layout' => [
        'preview_badge' => 'Aperçu plateforme',
        'simulated_notice' => 'Trade Fearlessly. Win Real.',
    ],
    'ai_assistant' => [
        'name' => 'Wolfi',
        'eyebrow' => 'Assistant Wolfi',
        'title' => 'WOLFI',
        'description' => 'Laissez Wolfi vous guider. Votre assistant expert pour les règles, les payouts, l’accès MT5 et la bonne prochaine étape sur la plateforme.',
        'home_headline' => 'Laissez Wolfi vous guider.',
        'home_description' => 'Votre assistant expert pour les règles, les payouts, l’accès MT5 et la bonne prochaine étape sur la plateforme.',
        'multi_language' => 'Disponible 24/7',
        'start_chat' => 'Parlez avec Wolfi',
        'floating_label' => 'Ouvrir Wolfi',
        'floating_cta' => 'Demandez à Wolfi',
        'floating_aria' => 'Ouvrir Wolfi, l’assistant IA',
        'close_aria' => 'Fermer Wolfi',
        'preview_label' => 'Aperçu de Wolfi',
        'preview_title' => 'Posez vos questions à Wolfi avant d’acheter',
        'preview_badge' => '24/7',
        'preview_copy' => 'Ouvrez l’assistant pour obtenir rapidement des réponses sur les règles, le news trading, les limites de drawdown, le payout et le challenge qui vous convient.',
        'visual_title' => 'Toujours actif. Toujours prêt.',
        'visual_copy' => 'Utilisez Wolfi comme guide visible pour les règles, les payouts, l’accès MT5 et les questions de plateforme.',
        'visual_response_label' => 'Réponse en direct',
        'visual_response_preview' => 'Votre drawdown maximal est de 5 % sur ce compte.',
        'visual_response_hint' => 'Règles et guidage compte en temps réel.',
        'visual_cta_hint' => 'Demandez à Wolfi avant votre prochain trade.',
        'visual_alt' => 'Illustration lumineuse de la mascotte Wolfi',
        'home_visual_alt' => 'Aperçu validé de la homepage Wolfi affiché dans un format mobile',
        'example_questions' => [
            'Puis-je trader pendant les news ?',
            'À quelle fréquence les payouts sont-ils traités ?',
            'Quel plan est le mieux pour moi ?',
        ],
    ],
    'nav' => [
        'home' => 'Accueil',
        'about' => 'À Propos',
        'about_us' => 'À Propos de Nous',
        'security' => 'Sécurité',
        'contact' => 'Contactez-nous',
        'plans' => 'Plans',
        'faq' => 'FAQ',
        'news' => 'NEWS',
        'legal' => 'Légal',
        'dashboard' => 'Dashboard',
        'dashboard_preview' => 'Dashboard',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'search' => 'Rechercher',
        'search_aria' => 'Rechercher sur le site',
        'menu_open' => 'Ouvrir le menu',
        'menu_close' => 'Fermer le menu',
    ],
    'home' => [
        'eyebrow' => 'Prop Trading Moderne',
        'title' => 'Obtenez un compte funded. Soyez payé. Sans limite de temps.',
        'description' => 'Réussissez le challenge. Accédez à des comptes funded. Retirez rapidement.',
        'mobile_title' => [
            'line_1' => 'Obtenez un compte funded.',
            'line_2' => 'Soyez payé.',
            'line_3' => 'Sans limite de temps.',
        ],
        'mobile_description' => [
            'line_1' => 'Réussissez le challenge. Accédez à des comptes funded.',
            'line_2' => 'Retirez rapidement.',
        ],
        'primary_cta' => 'Commencer le Challenge',
        'free_trial_cta' => 'Essai Gratuit',
        'free_trial_caption' => 'Aucun risque. Aucune carte bancaire.',
        'secondary_cta' => 'Ouvrir le dashboard',
        'days' => 'jours',
        'badges' => [
            'Infrastructure sécurisée',
            'Trading rewards en 24 h',
            'Market Pulse (News live)',
            'Assistant IA Wolfi',
        ],
        'feature_cards' => [
            'Accès instantané au funding',
            'Payouts rapides',
            'Scaling +25 % de capital',
            'Jusqu’à 90 % de profit split',
        ],
        'trust' => [
            'eyebrow' => 'Confiance / Sécurité',
            'title' => 'Le positionnement sécurité visible dès la première visite.',
            'description' => 'Wolforix renforce la confiance autour d’une infrastructure sécurisée, de contrôles de risque, du monitoring et d’un alignement ISO/IEC 27001 en cours.',
            'cta' => 'Voir la sécurité',
            'items' => [
                [
                    'title' => 'Infrastructure sécurisée',
                    'description' => 'Hébergement protégé et accès opérationnel contrôlé sur les systèmes clés.',
                ],
                [
                    'title' => 'Contrôle avancé du risque',
                    'description' => 'Contrôles préventifs et circuits de revue pour réduire le risque opérationnel.',
                ],
                [
                    'title' => 'Monitoring en temps réel',
                    'description' => 'Visibilité continue sur l’activité de la plateforme, les événements et la santé du service.',
                ],
                [
                    'title' => 'Aligné ISO/IEC 27001',
                    'description' => 'La feuille de route d’alignement est en cours sans aucune revendication de certification.',
                ],
            ],
            'support_items' => [
                'Contrôles de protection des données avec accès limité et gestion contrôlée.',
                'La feuille de route sécurité et l’amélioration continue restent actives.',
            ],
        ],
        'hero_visual' => [
            'label' => 'Aperçu du trading desk',
            'platform' => 'Workspace d’exécution sombre',
            'card_title' => 'Axé graphiques. Règles visibles. Pensé comme un funded.',
            'card_copy' => 'La homepage s’ouvre désormais avec un visuel de trading propre afin que la marque ressemble davantage à un vrai workspace de prop firm qu’à une carte promotionnelle décorative.',
            'image_alt' => 'Interface de trading type laptop avec graphique et panneaux de marché',
        ],
        'plans' => [
            'eyebrow' => 'Plans Challenge',
            'title' => 'Tradez plus de 1 000 instruments sur MT5',
            'description' => 'Tous les instruments disponibles sur votre compte MT5 sont entièrement pris en charge et automatiquement suivis.',
            'platform_label' => 'Plateforme',
            'platform_value' => 'MT5',
            'badge' => 'Modèles prêts pour le lancement',
        ],
        'global_reach' => [
            'eyebrow' => 'Présence mondiale',
            'title_prefix' => 'Wolforix accompagne des traders dans plus de',
            'title_suffix' => 'pays. Un seul standard.',
            'description' => 'Wolforix connecte des traders du monde entier sous une infrastructure unifiée : rapide, précise et pensée pour la performance.',
            'image_alt' => 'Visuel du réseau mondial de traders Wolforix',
            'visual_label' => 'Couverture mondiale',
            'visual_status' => 'Expansion active',
            'visual_card_label' => 'Flux connecté',
            'visual_card_title' => 'Une plateforme, une communauté de trading mondiale.',
            'visual_card_copy' => 'Pensé pour les traders qui évoluent entre sessions, régions et cycles de marché sans perdre en vitesse, en clarté ni en qualité d’exécution.',
            'highlights' => [
                [
                    'title' => 'Accès multi-régions',
                    'description' => 'De l’Europe à l’Amérique latine, à l’Asie et au Moyen-Orient.',
                ],
                [
                    'title' => 'Expérience unifiée',
                    'description' => 'Le même flow de challenge, la même structure de payouts et la même direction support partout.',
                ],
                [
                    'title' => 'Pensé pour évoluer',
                    'description' => 'Conçu pour une audience plus large sans perdre une sensation premium.',
                ],
            ],
        ],
        'market_pulse' => [
            'eyebrow' => 'Direction de la plateforme',
            'title' => 'Market Pulse',
            'description' => 'Des insights en temps réel pour trader plus intelligemment et réagir plus vite.',
            'cta' => 'Ouvrir les news marché',
            'view_all' => 'Voir le calendrier complet',
            'preview_label' => 'Accès aux news en direct',
            'preview_copy' => 'Consultez les prochains événements macro, les évolutions de prévisions et les niveaux d’impact avant votre prochain trade.',
            'source_caption' => 'Source : :source. Horaires affichés en :timezone (:abbr).',
            'empty' => 'Market Pulse prépare la prochaine mise à jour en direct. Ouvrez le calendrier complet pour charger les derniers événements.',
            'cards' => [
                [
                    'title' => 'Événements à fort impact',
                    'description' => 'Suivez les publications les plus susceptibles de faire bouger la volatilité, les spreads et le risque court terme.',
                ],
                [
                    'title' => 'Focus multi-devises',
                    'description' => 'Surveillez USD, EUR, GBP, JPY et d’autres devises clés depuis un seul flux macro live.',
                ],
                [
                    'title' => 'Filtres rapides',
                    'description' => 'Accédez au calendrier complet pour filtrer par impact, devise et période en quelques secondes.',
                ],
            ],
        ],
        'challenge_selector' => [
            'currency_label' => 'Devise',
            'type_label' => 'Type de challenge',
            'size_label' => 'Taille du compte',
            'insight_title' => 'Aperçu du modèle',
            'entry_fee' => 'Prix de lancement',
            'current_price' => 'Prix de lancement',
            'original_price' => 'Prix standard',
            'discount_badge' => '20 % OFF - Offre de lancement limitée',
            'discount_urgency' => 'Remise de lancement - Offre limitée dans le temps',
            'best_value' => 'Meilleure valeur',
            'start_button' => 'Obtenir le plan',
            'review_policy' => 'Voir la politique de payout',
            'faq_link' => 'Lire la FAQ',
            'unlimited' => 'Illimité',
            'highlights' => [
                'Tarif de lancement -20 % actif',
                'Premier retrait après 21 jours',
                'Durée d’évaluation illimitée',
            ],
            'currencies' => [
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP',
            ],
            'phase_titles' => [
                'single_phase' => 'Phase Unique',
                'phase_1' => 'Phase 1',
                'phase_2' => 'Phase 2',
                'funded' => 'Compte Funded',
            ],
            'metrics' => [
                'profit_target' => 'Objectif de profit',
                'profit_share' => 'Part de profit',
                'profit_share_upgrade' => 'Upgrade du split',
                'daily_loss' => 'Perte journalière max',
                'total_loss' => 'Perte totale max',
                'minimum_days' => 'Jours de trading min',
                'first_withdrawal' => 'Premier retrait',
                'max_trading_days' => 'Jours de trading max',
                'leverage' => 'Levier',
                'payout_cycle' => 'Cycle de payout',
                'scaling' => 'Scaling',
                'consistency_rule' => 'Règle de cohérence',
            ],
            'value_templates' => [
                'profit_split_upgrade' => ':percent % après :payouts payouts consécutifs',
            ],
            'consistency_required' => 'Obligatoire',
            'types' => [
                'one_step' => [
                    'label' => '1-Step Instant',
                    'description' => 'Réussissez en une seule étape. Accédez plus vite à un compte funded. Aucun délai. Aucune deuxième phase.',
                    'note_title' => 'Modèle funded 1-Step Instant',
                    'note_body' => 'Règles plus strictes, contrôle du risque plus serré et accès direct à un compte funded avec cohérence obligatoire. Moins d’étapes. Des standards plus élevés. Des résultats plus rapides.',
                ],
                'two_step' => [
                    'label' => '2-Step Pro',
                    'description' => 'Risque plus faible. Potentiel de scaling plus élevé. Conçu pour la régularité et la croissance à long terme.',
                    'note_title' => 'Modèle funded 2-Step Pro',
                    'note_body' => 'Évaluation en deux phases avec levier 1:100 en phase 1, premier payout après 21 jours, payouts tous les 14 jours ensuite et système de scaling pour les comptes funded rentables. Construisez votre régularité. Scalez agressivement.',
                ],
            ],
        ],
        'foundation' => [
            'eyebrow' => 'Direction de la plateforme',
            'title' => 'Pensée pour la confiance, la visibilité des règles et l’automatisation future.',
            'description' => 'Ce premier jalon se concentre sur la structure nécessaire avant les intégrations live : navigation claire, contenu multilingue, visibilité des politiques et dashboard au rendu réellement opérationnel.',
            'cards' => [
                [
                    'title' => 'Évaluation simulée en priorité',
                    'description' => 'Le wording public suit le disclaimer client : Wolforix fonctionne comme une société d’évaluation et d’éducation au trading propriétaire, et non comme un broker ou une société d’investissement.',
                ],
                [
                    'title' => 'Protections payout visibles très tôt',
                    'description' => 'Le premier payout après 21 jours, le cycle de payout de 14 jours ensuite, le traitement sous 24 heures après approbation, la cohérence obligatoire en 1-Step et le scaling 2-Step tous les 3 mois sont déjà visibles sur le site et le dashboard.',
                ],
                [
                    'title' => 'Multilingue dès le départ',
                    'description' => 'L anglais reste la langue par defaut, tandis que la plateforme est structuree pour ajouter proprement d autres langues sans casser l experience principale.',
                ],
            ],
        ],
        'about' => [
            'eyebrow' => 'À propos de Wolforix',
            'title' => 'Une nouvelle génération de prop firms construite autour de l’accès, de la discipline et de la performance.',
            'intro' => 'Wolforix est une société de trading propriétaire représentant une nouvelle génération de prop firms, pensée pour libérer le potentiel des traders engagés dans un environnement juste, accessible et orienté performance.',
            'mission_label' => 'Notre mission',
            'mission' => 'Identifier, former et financer les traders prêts à performer.',
            'pillars' => [
                'Évaluation structurée',
                'Accès à l’essai gratuit',
                'Financement guidé par la performance',
            ],
            'blocks' => [
                [
                    'title' => 'Pourquoi nous existons',
                    'description' => 'Nous pensons que le talent seul ne suffit pas. Pour de nombreux traders disciplinés qui possèdent déjà la constance et l’état d’esprit adéquat, le véritable obstacle reste l’accès au capital.',
                ],
                [
                    'title' => 'Comment les traders progressent',
                    'description' => 'Grâce à un système d’évaluation structuré et à des opportunités d’essai gratuit, les traders peuvent développer leur processus, gagner en expérience et démontrer leur constance dans un environnement contrôlé avant de gérer un capital funded.',
                ],
                [
                    'title' => 'Ce qui soutient Wolforix',
                    'description' => 'Wolforix s’appuie sur une équipe de diplômés en économie disposant de plusieurs années d’expérience dans les marchés financiers, le trading et l’investissement. La société est construite comme un écosystème transparent, juste et orienté performance.',
                ],
            ],
            'closing_label' => 'Ce que nous construisons',
            'closing' => 'Nous ne faisons pas que financer des traders. Nous formons des professionnels disciplinés et constants capables d’atteindre un succès financier durable.',
        ],
        'news' => [
            'eyebrow' => 'Calendrier macro de risque',
            'title' => 'Calendrier des actualités économiques',
            'description' => 'Suivez les prochaines publications macroéconomiques afin de mieux gérer votre exposition pendant les sessions sensibles à la volatilité.',
            'warning_title' => 'Avertissement volatilité',
            'warning_copy' => 'Les annonces à fort impact peuvent augmenter la volatilité et modifier les conditions de trading. Surveillez les événements programmés dans votre gestion du risque.',
            'data_source_label' => 'Source du calendrier',
            'mode_demo' => 'Mode démo du calendrier',
            'mode_live' => 'Mode live du calendrier',
            'demo_notice' => 'Le calendrier fonctionne actuellement en mode démo avec des événements réalistes jusqu’à la configuration d’identifiants API sous licence.',
            'live_notice' => 'Le calendrier utilise actuellement le fournisseur API sous licence configuré.',
            'timezone_badge' => 'Affichage :timezone (:abbr)',
            'range_caption' => 'Affichage des événements de :from à :to',
            'filters' => [
                'impact' => 'Impact',
                'currency' => 'Devise',
                'range' => 'Période',
                'high_only' => 'Fort impact uniquement',
                'apply' => 'Appliquer les filtres',
                'reset' => 'Réinitialiser',
                'all_impacts' => 'Tous les impacts',
                'all_currencies' => 'Toutes les devises',
                'range_options' => [
                    'today' => 'Aujourd’hui',
                    'this_week' => 'Cette semaine',
                    'next_week' => 'Semaine prochaine',
                ],
            ],
            'table' => [
                'time' => 'Heure',
                'currency' => 'Devise',
                'impact' => 'Impact',
                'event' => 'Événement',
                'forecast' => 'Prévision',
                'previous' => 'Précédent',
                'empty' => 'Aucun événement du calendrier économique ne correspond aux filtres sélectionnés.',
            ],
            'impact' => [
                'high' => 'Fort',
                'medium' => 'Moyen',
                'low' => 'Faible',
            ],
            'sources' => [
                'title' => 'Sources de données',
                'copy' => 'Le mode actuel du calendrier, l’architecture du fournisseur live et les sites de référence marché sont affichés ici par transparence.',
                'current_demo' => 'Source démo actuelle',
                'current_live' => 'Source live actuelle',
                'provider' => 'Architecture fournisseur configurée',
                'reference' => 'Référence uniquement',
                'legal_notice' => 'Les sites de référence sont listés uniquement pour la veille marché. Wolforix ne scrape pas, ne reflète pas et n’iframe pas les calendriers tiers.',
            ],
        ],
    ],
    'launch_popup' => [
        'title' => '20 % OFF - L’accès de lancement se termine bientôt',
        'description' => 'Activez votre remise de lancement de 20 % avant la fin de l’offre.',
        'secondary_copy' => 'Les places sont limitées. Une fois remplies, les prix augmentent.',
        'promo_label' => 'Code promo',
        'auto_apply_notice' => 'La remise ne s’active que si vous choisissez Obtenir la remise. Si vous ignorez l’offre, le tarif standard reste affiché.',
        'copy_code' => 'Copier le code',
        'code_copied' => 'Code copié',
        'primary_action' => 'Obtenir la remise',
        'secondary_action' => 'Ignorer',
        'benefits' => [
            '20 % économisés immédiatement',
            'Opportunité de capital réel',
            'Activation pendant la session',
        ],
        'close' => 'Fermer l’offre de lancement',
    ],
    'faq' => [
        'eyebrow' => 'Questions fréquentes',
        'title' => 'Tout ce dont vous avez besoin. Instantanément.',
        'description' => 'Toutes vos règles de trading, paiements et informations de compte — claires et accessibles.',
        'search_label' => 'Recherche',
        'search_placeholder' => 'Rechercher dans la FAQ...',
        'no_results' => 'Aucun élément de FAQ ne correspond à cette recherche.',
        'sections' => [
            [
                'title' => 'Général',
                'items' => [
                    [
                        'question' => 'Qu’est-ce que Wolforix ?',
                        'answer' => 'Wolforix Ltd. est une société d’évaluation et d’éducation en proprietary trading. Toutes les activités de trading sont réalisées dans un environnement simulé à des fins éducatives.',
                    ],
                    [
                        'question' => 'S’agit-il d’argent réel ou de trading simulé ?',
                        'answer' => 'Tous les comptes fonctionnent dans un environnement de trading simulé. Aucun capital réel n’est attribué aux utilisateurs.',
                    ],
                    [
                        'question' => 'Qui peut participer ?',
                        'answer' => 'Les utilisateurs doivent avoir au moins 18 ans et respecter toutes les lois applicables dans leur juridiction.',
                    ],
                ],
            ],
            [
                'title' => 'Plateforme',
                'items' => [
                    [
                        'question' => 'Quelle plateforme Wolforix utilise-t-il ?',
                        'answer' => 'Wolforix utilise MetaTrader 5 (MT5).',
                    ],
                    [
                        'question' => 'Comment me connecter à MT5 ?',
                        'answer_sections' => [
                            [
                                'title' => 'Mobile',
                                'bullets' => [
                                    '1. Téléchargez MetaTrader 5.',
                                    '2. Allez dans "Manage Accounts".',
                                    '3. Appuyez sur "+".',
                                    '4. Sélectionnez "Login to an existing account".',
                                    '5. Recherchez : MetaQuotes-Demo.',
                                    '6. Entrez vos identifiants.',
                                ],
                            ],
                            [
                                'title' => 'Desktop',
                                'bullets' => [
                                    '1. Ouvrez MT5.',
                                    '2. File -> Login to Trade Account.',
                                    '3. Entrez vos informations de connexion.',
                                    '4. Server: MetaQuotes-Demo.',
                                ],
                            ],
                            [
                                'title' => 'Important',
                                'bullets' => [
                                    'Wolforix n’utilise pas son propre broker.',
                                    'Nous utilisons le serveur MetaQuotes-Demo.',
                                    'Votre compte est lié à Wolforix.',
                                    'Toute l’activité est synchronisée avec votre dashboard.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Instruments négociables',
                'items' => [
                    [
                        'question' => 'Que puis-je trader ?',
                        'answer' => 'Wolforix donne accès à une large gamme d’instruments CFD disponibles dans MT5.',
                        'answer_sections' => [
                            [
                                'title' => 'Forex',
                                'bullets' => [
                                    'EURUSD, GBPUSD, USDJPY, USDCHF, USDCAD',
                                    'AUDUSD, NZDUSD, EURJPY, GBPJPY, EURGBP et plus',
                                ],
                            ],
                            [
                                'title' => 'Indices',
                                'bullets' => [
                                    'SPX500, NDX100, US30',
                                    'GER30, UK100, FRA40',
                                    'JP225 et autres',
                                ],
                            ],
                            [
                                'title' => 'Matières premières',
                                'bullets' => [
                                    'XAUUSD (Or)',
                                    'XAGUSD (Argent)',
                                    'XPTUSD (Platine)',
                                    'UKOUSD (Brent)',
                                    'USOUSD (Crude Oil)',
                                ],
                            ],
                            [
                                'title' => 'Cryptomonnaies',
                                'bullets' => [
                                    'BTCUSD, ETHUSD, XRPUSD',
                                    'ADAUSD, LTCUSD, XLMUSD',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Comment voir tous les instruments ?',
                        'answer_sections' => [
                            [
                                'title' => 'MT5 Market Watch',
                                'bullets' => [
                                    '1. Ouvrez MT5.',
                                    '2. Allez dans Market Watch.',
                                    '3. Faites un clic droit.',
                                    '4. Sélectionnez "Show All".',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Règles de trading',
                'items' => [
                    [
                        'question' => 'Qu’est-ce que la règle de cohérence ?',
                        'answer' => 'Pas plus de 40 % des profits totaux ne peuvent être générés en une seule journée de trading. Les profits doivent être répartis sur plusieurs jours pour être éligibles au payout.',
                    ],
                    [
                        'question' => 'Comment la limite de profit quotidien est-elle calculée ?',
                        'answer' => 'Le système compare le profit du jour au profit total du compte. Si le profit du jour dépasse 40 %, une alerte dashboard sera déclenchée et votre éligibilité au payout peut être affectée.',
                    ],
                    [
                        'question' => 'Que sont les drawdowns maximums ?',
                        'answer' => 'Chaque compte possède des limites de drawdown définies. Les dépasser peut entraîner la disqualification du compte du programme d’évaluation.',
                    ],
                    [
                        'question' => 'Puis-je trader pendant les news à fort impact ?',
                        'answer' => 'Il est interdit d’ouvrir ou de clôturer des trades 5 minutes avant et 5 minutes après une news à fort impact. Cette restriction s’applique aux ordres au marché et aux ordres en attente, y compris les déclenchements de stop-loss ou take-profit. Vous pouvez conserver des positions existantes pendant l’événement, mais vous ne pouvez pas ouvrir ni fermer de trades dans cette fenêtre.',
                    ],
                    [
                        'question' => 'Quels horaires de trading sont autorisés ?',
                        'answer' => 'Wolforix autorise le trading pendant les heures de marché standard selon l’instrument tradé.',
                        'answer_sections' => [
                            [
                                'title' => 'Règle générale',
                                'bullets' => [
                                    'Le trading est disponible 24 heures sur 24, 5 jours par semaine, du lundi au vendredi, selon les sessions mondiales.',
                                    'La disponibilité peut varier selon l’instrument, notamment Forex, indices, crypto et autres CFD.',
                                ],
                            ],
                            [
                                'title' => 'Maintien des positions',
                                'bullets' => [
                                    'Les positions peuvent être conservées intraday ou overnight, sauf restriction spécifique du compte.',
                                    'Les traders sont responsables de gérer leur exposition pendant les périodes de faible liquidité.',
                                ],
                            ],
                            [
                                'title' => 'Fermetures de marché',
                                'bullets' => [
                                    'Le trading n’est pas disponible le week-end.',
                                    'Certains instruments peuvent avoir des pauses quotidiennes ou des fenêtres de maintenance.',
                                    'Les jours fériés, les horaires peuvent être réduits ou modifiés.',
                                ],
                            ],
                            [
                                'title' => 'Important',
                                'bullets' => [
                                    'Les traders doivent connaître les horaires de session et les conditions de liquidité.',
                                    'Wolforix n’est pas responsable des pertes causées par le trading en périodes illiquides ou volatiles.',
                                ],
                            ],
                            [
                                'title' => 'Restrictions',
                                'bullets' => [
                                    'Les restrictions liées aux news (±5 minutes) restent applicables.',
                                    'Toutes les autres règles de trading restent en vigueur quel que soit l’horaire.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Quelles stratégies de trading sont autorisées ?',
                        'answer_paragraphs' => [
                            'Wolforix autorise le trading discrétionnaire, le trading algorithmique et les Expert Advisors (EAs), tant que les stratégies sont légitimes, reflètent les conditions réelles du marché, respectent une gestion du risque solide et n’impliquent pas de pratiques interdites.',
                            'Les stratégies doivent être réplicables dans des conditions réelles de marché et capables de produire des résultats live cohérents.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Conditions de trading',
                                'bullets' => [
                                    'Le Stop Loss n’est pas obligatoire, mais le contrôle du risque est fortement recommandé.',
                                    'Le trading doit refléter un comportement d’exécution réaliste et des conditions de marché réelles.',
                                    'Les stratégies doivent être scalables et adaptées au capital live.',
                                ],
                            ],
                            [
                                'title' => 'Expert Advisors (EAs) & trading algorithmique',
                                'bullets' => [
                                    'Les EAs sont autorisés.',
                                    'La duplication d’EAs tiers peut entrer en conflit avec la gestion interne du risque.',
                                    'Wolforix peut limiter ou refuser l’allocation de compte dans ces cas.',
                                    'L’activité de plateforme doit rester dans des limites raisonnables.',
                                    'Un excès d’ordres, de modifications ou de charge serveur peut nécessiter des ajustements de stratégie.',
                                ],
                            ],
                            [
                                'title' => 'Limites serveur & exécution',
                                'bullets' => [
                                    'Des limites d’ordres ouverts simultanément peuvent s’appliquer.',
                                    'Des limites d’exécution quotidiennes peuvent être imposées.',
                                    'Une fréquence excessive de trading peut déclencher une révision.',
                                    'Wolforix peut demander des ajustements si la performance nuit à la stabilité de la plateforme.',
                                ],
                            ],
                            [
                                'title' => 'Politique de scalping & durée des trades',
                                'bullets' => [
                                    'Les trades clôturés en moins de 60 secondes sont strictement interdits s’ils génèrent un profit.',
                                    'Cette activité est considérée comme non réplicable en conditions réelles et peut indiquer une exploitation de latence ou une exécution irréaliste.',
                                    'Le scalping standard est autorisé si la durée du trade reflète une réelle exposition au marché.',
                                    'Wolforix peut exclure ces trades du calcul des profits ou prendre d’autres mesures en cas de répétition.',
                                ],
                            ],
                            [
                                'title' => 'Pratiques de trading interdites',
                                'paragraphs' => [
                                    'Si une activité interdite est détectée, Wolforix peut supprimer ou ajuster des positions, recalculer le compte, réduire le levier, suspendre ou fermer le compte, ou mettre fin à la coopération avec le trader.',
                                    'Si vous tradez avec une intention réelle, un avantage clair et un comportement conforme aux règles, Wolforix reste aligné avec votre réussite.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Le hedging entre comptes et le copy trading sont-ils autorisés ?',
                        'answer' => 'Le hedging entre plusieurs comptes et le copy trading non autorisé sont strictement interdits chez Wolforix.',
                        'answer_sections' => [
                            [
                                'title' => 'Qu’est-ce que le hedging ?',
                                'paragraphs' => [
                                    'Le hedging consiste à prendre des positions opposées sur le même instrument ou des instruments corrélés entre plusieurs comptes afin de réduire artificiellement le risque.',
                                    'Ce comportement garantit qu’un compte profite quelle que soit la direction du marché et est considéré comme une manipulation du système, non comme du trading réel.',
                                ],
                            ],
                            [
                                'title' => 'Qu’est-ce que le copy trading ?',
                                'paragraphs' => [
                                    'Le copy trading consiste à répliquer des trades entre plusieurs comptes, manuellement, via logiciel, signaux ou services tiers.',
                                ],
                                'bullets' => [
                                    'Copier des trades entre vos propres comptes.',
                                    'Copier des trades entre différents utilisateurs.',
                                    'Utiliser des groupes de signaux, bots ou automatisations pour mirrorer des trades.',
                                    'Trading coordonné conçu pour contourner les règles.',
                                ],
                            ],
                            [
                                'title' => 'Exemples d’activité interdite',
                                'bullets' => [
                                    'Ouvrir des positions long et short sur le même instrument entre différents comptes.',
                                    'Hedging entre comptes appartenant au même utilisateur.',
                                    'Hedging entre utilisateurs coordonnés.',
                                    'Hedging entre différentes firms ou plateformes.',
                                    'Trader des instruments corrélés en sens opposé entre comptes.',
                                    'Copier ou mirrorer des trades entre comptes, manuellement ou automatiquement.',
                                    'Utiliser un logiciel trade copier ou des services de signaux pour répliquer des trades.',
                                ],
                            ],
                            [
                                'title' => 'Exemples',
                                'bullets' => [
                                    'Long EURUSD sur un compte et short EURUSD sur un autre.',
                                    'Long SPX500 sur un compte et short NDX100 sur un autre.',
                                    'Exécuter des trades identiques sur plusieurs comptes au même moment.',
                                    'Utiliser un bot ou fournisseur de signaux pour répliquer des trades entre comptes.',
                                ],
                            ],
                            [
                                'title' => 'Ce qui est autorisé',
                                'bullets' => [
                                    'Décisions de trading indépendantes par compte.',
                                    'Utilisation de stratégies personnelles non partagées sur plusieurs comptes.',
                                    'Gestion du risque appropriée dans un seul compte.',
                                ],
                            ],
                            [
                                'title' => 'Clarification importante',
                                'bullets' => [
                                    'Ouvrir des positions opposées dans le même compte peut être techniquement possible dans MT5, mais les stratégies conçues pour contourner les règles de risque ne sont pas autorisées.',
                                    'L’automatisation est autorisée uniquement si elle reflète une logique de trading indépendante, pas une réplication de trades.',
                                    'Tout trading doit refléter une prise de décision réelle et indépendante ainsi qu’une exposition de marché.',
                                ],
                            ],
                            [
                                'title' => 'Détection & monitoring',
                                'bullets' => [
                                    'Wolforix utilise des systèmes internes pour détecter les patterns de hedging.',
                                    'Wolforix surveille la synchronisation des trades.',
                                    'Wolforix surveille les comportements d’exécution identiques entre comptes.',
                                    'Wolforix surveille l’activité de copy trading.',
                                ],
                            ],
                            [
                                'title' => 'Conséquences',
                                'bullets' => [
                                    'Wolforix peut supprimer ou ajuster des trades.',
                                    'Wolforix peut recalculer le solde du compte.',
                                    'Wolforix peut disqualifier le compte.',
                                    'Wolforix peut restreindre ou bannir définitivement l’utilisateur.',
                                ],
                            ],
                            [
                                'title' => 'Pourquoi est-ce interdit ?',
                                'bullets' => [
                                    'Le hedging et le copy trading faussent la compétence réelle de trading.',
                                    'Le hedging et le copy trading éliminent l’exposition réelle au risque.',
                                    'Le hedging et le copy trading affaiblissent le processus d’évaluation.',
                                    'Le hedging et le copy trading menacent l’intégrité de la plateforme.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Le high-frequency trading (HFT) est-il autorisé ?',
                        'answer' => 'Le high-frequency trading (HFT) est strictement interdit chez Wolforix.',
                        'answer_sections' => [
                            [
                                'title' => 'Qu’est-ce que le high-frequency trading ?',
                                'paragraphs' => [
                                    'Le high-frequency trading (HFT) désigne des stratégies automatisées qui exécutent un grand nombre de trades sur des périodes extrêmement courtes, souvent en secondes ou millisecondes.',
                                    'Ces stratégies cherchent généralement à exploiter de petites inefficiences de prix grâce à la vitesse et au volume élevé d’ordres.',
                                ],
                            ],
                            [
                                'title' => 'Qu’est-ce qui est considéré comme HFT ?',
                                'bullets' => [
                                    'Exécuter un volume élevé de trades sur de très courtes périodes.',
                                    'Placement et annulation rapides d’ordres.',
                                    'Modifications excessives d’ordres.',
                                    'Patterns d’exécution algorithmique ultra-rapides.',
                                    'Comportement de trading imposant une charge anormale à la plateforme.',
                                ],
                            ],
                            [
                                'title' => 'Clarification importante',
                                'bullets' => [
                                    'Le trading algorithmique et les EAs sont autorisés.',
                                    'Les stratégies doivent fonctionner avec une fréquence normale et un comportement d’exécution réaliste.',
                                    'Tout système principalement conçu pour exploiter la vitesse plutôt que l’analyse de marché n’est pas autorisé.',
                                ],
                            ],
                            [
                                'title' => 'Pourquoi le HFT est-il interdit ?',
                                'paragraphs' => [
                                    'Wolforix est conçu pour évaluer la compétence et la cohérence, pas l’exploitation de systèmes basée sur la vitesse.',
                                ],
                                'bullets' => [
                                    'Le HFT peut dégrader les performances de la plateforme.',
                                    'Le HFT peut créer une instabilité d’exécution.',
                                    'Le HFT peut affecter la cohérence des prix.',
                                    'Le HFT peut impacter l’environnement de trading des autres utilisateurs.',
                                ],
                            ],
                            [
                                'title' => 'Détection & monitoring',
                                'bullets' => [
                                    'Wolforix surveille la fréquence des trades.',
                                    'Wolforix surveille le volume d’ordres.',
                                    'Wolforix surveille les patterns d’exécution.',
                                    'Wolforix surveille l’impact sur la charge serveur.',
                                ],
                            ],
                            [
                                'title' => 'Conséquences',
                                'bullets' => [
                                    'Un avertissement peut être émis.',
                                    'Les profits générés par le HFT peuvent être supprimés.',
                                    'Le compte peut être restreint ou fermé.',
                                    'Les violations répétées peuvent entraîner un bannissement définitif.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Le duration abuse, le grid trading et les stratégies martingale sont-ils autorisés ?',
                        'answer' => 'Wolforix interdit strictement les stratégies qui exploitent les structures de risque ou créent des profils de performance irréalistes, notamment le duration abuse, le grid trading et les systèmes martingale.',
                        'answer_sections' => [
                            [
                                'title' => 'Duration Abuse',
                                'paragraphs' => [
                                    'Le duration abuse consiste à ouvrir et fermer systématiquement des trades d’une manière qui contourne l’exposition au risque prévue ou les règles de trading, sans réelle participation au marché.',
                                    'Tous les trades doivent refléter une exposition et une intention réelles de marché, pas une manipulation des règles.',
                                ],
                                'bullets' => [
                                    'Ouvrir et fermer répétitivement des trades autour du seuil minimum de durée, par exemple juste au-dessus de 60 secondes.',
                                    'Exécuter des trades sans intention réelle de marché, uniquement pour satisfaire des règles.',
                                    'Timing artificiel des trades conçu pour contourner les restrictions.',
                                ],
                            ],
                            [
                                'title' => 'Grid Trading',
                                'paragraphs' => [
                                    'Le grid trading consiste à placer plusieurs ordres en attente ou actifs à intervalles de prix fixes, souvent sans logique claire de stop-loss.',
                                ],
                                'bullets' => [
                                    'Les systèmes grid sans contrôle du risque approprié ne sont pas autorisés.',
                                    'L’empilement dense d’ordres n’est pas autorisé.',
                                    'Les stratégies qui reposent sur l’oscillation des prix sans risque défini ne sont pas autorisées.',
                                ],
                            ],
                            [
                                'title' => 'Stratégies Martingale',
                                'paragraphs' => [
                                    'Les stratégies martingale augmentent la taille de position après des pertes pour récupérer les pertes précédentes avec un seul trade gagnant.',
                                ],
                                'bullets' => [
                                    'Doubler ou augmenter les lots après des pertes n’est pas autorisé.',
                                    'Le sizing de récupération sans limites de risque n’est pas autorisé.',
                                    'Les stratégies qui créent une exposition exponentielle au risque ne sont pas autorisées.',
                                ],
                            ],
                            [
                                'title' => 'Ce qui est autorisé',
                                'bullets' => [
                                    'Stratégies structurées avec risque défini par trade.',
                                    'Taille de position logique.',
                                    'Exposition cohérente et contrôlée.',
                                    'Utilisation de stop-loss et gestion du risque appropriée.',
                                ],
                            ],
                            [
                                'title' => 'Détection & monitoring',
                                'bullets' => [
                                    'Wolforix surveille les patterns de durée des trades.',
                                    'Wolforix surveille le comportement de sizing des positions.',
                                    'Wolforix surveille la distribution des ordres.',
                                    'Wolforix surveille les patterns d’escalade du risque.',
                                ],
                            ],
                            [
                                'title' => 'Conséquences',
                                'bullets' => [
                                    'Wolforix peut supprimer ou ajuster des trades.',
                                    'Wolforix peut recalculer le solde du compte.',
                                    'Wolforix peut restreindre l’activité de trading.',
                                    'Wolforix peut suspendre ou fermer le compte.',
                                ],
                            ],
                            [
                                'title' => 'Pourquoi est-ce interdit ?',
                                'bullets' => [
                                    'Ces stratégies déforment la performance réelle de trading.',
                                    'Ces stratégies éliminent une exposition au risque correcte.',
                                    'Ces stratégies créent des courbes d’equity instables.',
                                    'Ces stratégies ne sont pas durables dans des conditions réelles de marché.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Payouts',
                'items' => [
                    [
                        'question' => 'À quelle fréquence les payouts sont-ils traités ?',
                        'answer' => 'Les commissions sont payées sur demande et sont soumises à l’examen et à l’approbation de la Wolforix Partner Success Team. Le premier payout devient éligible après 21 jours, puis les payouts suivants sont disponibles selon des cycles récurrents de 14 jours. Une fois la période de cycle requise terminée, les payouts sont traités sous 24 heures. Pour demander un payout, envoyez un email à support@wolforix.com une fois le seuil minimum de retrait de $100 atteint.',
                    ],
                    [
                        'question' => 'Quels moyens de payout sont disponibles ?',
                        'answer' => 'Wolforix prend en charge des méthodes de payout sûres et efficaces pour les retraits approuvés.',
                        'answer_sections' => [
                            [
                                'title' => 'Options disponibles',
                                'bullets' => [
                                    'Virement bancaire (via l’infrastructure Stripe, selon la région)',
                                    'PayPal',
                                ],
                            ],
                            [
                                'title' => 'Important',
                                'bullets' => [
                                    'Les méthodes de payout peuvent varier selon votre localisation.',
                                    'Tous les retraits sont soumis à une revue de compte et à des contrôles de conformité.',
                                    'Les délais de traitement peuvent varier selon le fournisseur et la région.',
                                ],
                            ],
                            [
                                'title' => 'Délai de traitement',
                                'bullets' => [
                                    'Les demandes sont généralement examinées sous 1–3 jours ouvrés.',
                                    'Une fois approuvés, les fonds sont traités peu après.',
                                ],
                            ],
                            [
                                'title' => 'Notes supplémentaires',
                                'bullets' => [
                                    'La méthode de payout doit correspondre à l’identité du titulaire du compte.',
                                    'Wolforix se réserve le droit de demander une vérification avant de traiter les retraits.',
                                    'Les frais peuvent varier selon la méthode choisie.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Comment mon payout est-il calculé ?',
                        'answer' => 'Les payouts dépendent du profit split attribué à votre modèle et taille de compte, du calendrier de payout du modèle, du respect de la règle de cohérence le cas échéant et des limites internes. Le montant éligible peut être inférieur aux profits totaux si les limites quotidiennes sont dépassées.',
                    ],
                    [
                        'question' => 'Les comptes funded scalent-ils ?',
                        'answer' => 'Les comptes funded 2-Step peuvent scaler de +25 % de capital tous les 3 mois s’ils sont profitables. Les comptes funded 1-Step n’incluent pas actuellement cette règle.',
                    ],
                    [
                        'question' => 'Comment fonctionne le plan de scaling du compte ?',
                        'answer_paragraphs' => [
                            'Wolforix applique un système de scaling dynamique aux comptes funded en fonction de la performance de trading.',
                            'À mesure que votre compte progresse, votre taille de position maximale autorisée augmente progressivement, permettant une exposition plus élevée lorsque la cohérence est démontrée.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Fonctionnement du système',
                                'bullets' => [
                                    'Le scaling est basé sur vos profits simulés.',
                                    'Lorsque les profits augmentent, votre capacité de trading augmente.',
                                    'Si la performance baisse, les limites peuvent être ajustées.',
                                ],
                            ],
                            [
                                'title' => 'Fréquence de mise à jour',
                                'bullets' => [
                                    'Les mises à jour de scaling sont appliquées à la fin de chaque journée de trading.',
                                    'Les changements ne sont pas appliqués en temps réel pendant la journée.',
                                ],
                            ],
                            [
                                'title' => 'Structure de scaling',
                                'paragraphs' => [
                                    'Le modèle récompense la cohérence, la gestion du risque et la performance durable.',
                                    'Votre exposition maximale évolue lorsque le compte démontre de la stabilité dans le temps.',
                                ],
                            ],
                            [
                                'title' => 'Important',
                                'bullets' => [
                                    'Le scaling n’est pas linéaire et peut varier selon la performance du compte.',
                                    'Une surexposition sans performance suffisante peut entraîner des restrictions.',
                                    'Le système privilégie la cohérence long terme plutôt que les gains court terme.',
                                ],
                            ],
                            [
                                'title' => 'Contournement du système',
                                'paragraphs' => [
                                    'Toute tentative de contourner ou manipuler le système de scaling est strictement interdite.',
                                ],
                                'bullets' => [
                                    'Diviser les trades pour dépasser les limites.',
                                    'Utiliser plusieurs entrées pour augmenter artificiellement l’exposition.',
                                    'Exploiter le comportement d’exécution ou les mécaniques de plateforme.',
                                ],
                            ],
                            [
                                'title' => 'Conséquences',
                                'bullets' => [
                                    'Wolforix peut ajuster ou supprimer des trades.',
                                    'Wolforix peut recalculer le solde du compte.',
                                    'Wolforix peut restreindre les conditions de trading.',
                                    'Wolforix peut suspendre ou fermer le compte.',
                                ],
                            ],
                            [
                                'title' => 'Résumé',
                                'bullets' => [
                                    'Performance en hausse, capacité de trading en hausse.',
                                    'Performance en baisse, les limites peuvent s’ajuster.',
                                    'Scaling mis à jour quotidiennement.',
                                    'La cohérence est requise.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Facturation',
                'items' => [
                    [
                        'question' => 'Quels moyens de paiement sont acceptés ?',
                        'answer' => 'Wolforix accepte les paiements en ligne sécurisés via des fournisseurs de confiance. Tous les paiements sont traités de façon sécurisée via Stripe et PayPal, garantissant des transactions rapides et fiables.',
                        'answer_sections' => [
                            [
                                'title' => 'Moyens disponibles',
                                'bullets' => [
                                    'Cartes de crédit et débit (Visa, Mastercard, American Express)',
                                    'PayPal',
                                ],
                            ],
                            [
                                'title' => 'Important',
                                'bullets' => [
                                    'Les paiements sont confirmés instantanément après approbation.',
                                    'Toutes les transactions sont chiffrées et traitées de manière sécurisée.',
                                    'La disponibilité de certains moyens peut varier selon votre localisation.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Comptes / Dashboard',
                'items' => [
                    [
                        'question' => 'Comment voir mon profit et mon solde ?',
                        'answer' => 'Votre dashboard affiche le profit total, le profit journalier et le solde du compte en temps réel.',
                    ],
                    [
                        'question' => 'Que se passe-t-il si j’approche de la limite de cohérence ?',
                        'answer' => 'Une alerte dashboard apparaîtra : “⚠ Vous approchez de la limite de la règle de cohérence. Les profits doivent être répartis sur plusieurs jours de trading pour être éligibles au payout.” Une alerte email automatique peut aussi être envoyée si vous approchez du seuil critique.',
                    ],
                    [
                        'question' => 'Comment demander un payout ?',
                        'answer' => 'Le bouton de demande de payout se trouve dans votre dashboard. Les comptes funded 1-Step doivent respecter la règle de cohérence obligatoire avant que le profit ne devienne éligible.',
                    ],
                    [
                        'question' => 'I have successfully passed, what should I do now?',
                        'answer_paragraphs' => [
                            'What happens next depends on whether you are participating in the Wolforix Step-1 Instant or Wolforix Step-Pro, as each program follows a slightly different structure. However, both include a Verification phase.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Wolforix Step-1 Instant',
                                'paragraphs' => [
                                    'After successfully passing all Trading Objectives in your Step-1 Instant account, you will receive a notification in your dashboard confirming that your objectives have been met and your account is under review.',
                                    'The review process typically takes 1-2 business days. Once your results are verified, you will receive access to the Verification phase.',
                                ],
                            ],
                            [
                                'title' => 'Verification Phase',
                                'paragraphs' => [
                                    'After passing all Trading Objectives in the Verification phase, your account will again be reviewed.',
                                    'Once your results are verified, the following steps are required:',
                                ],
                                'bullets' => [
                                    'Complete your identity verification (KYC/KYB) in your client area',
                                    'Sign the Wolforix Account Agreement',
                                    'Once all steps are successfully completed, your funded Wolforix account will be issued.',
                                ],
                            ],
                            [
                                'title' => 'Wolforix Step-Pro',
                                'paragraphs' => [
                                    'Phase 1',
                                    'After passing all Trading Objectives in Phase 1, you will receive a notification confirming your success. At this point, no further trading is required, and your account will be reviewed.',
                                    'The review process typically takes 1-2 business days. Once verified, you will receive access to the next phase.',
                                ],
                            ],
                            [
                                'title' => 'Verification Phase',
                                'paragraphs' => [
                                    'After successfully completing all Trading Objectives in the Verification phase, your account will be set for final review.',
                                    'Once your results are verified, the following steps are required:',
                                ],
                                'bullets' => [
                                    'Complete your identity verification (KYC/KYB) in your client area',
                                    'Sign the Wolforix Account Agreement',
                                    'Once all steps are successfully completed, your funded Wolforix account will be issued.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Support / Contact',
                'items' => [
                    [
                        'question' => 'Comment contacter le support ?',
                        'answer' => 'Toutes les demandes de support sont traitées par email ou via le système de tickets dans votre dashboard.',
                    ],
                    [
                        'question' => 'Avez-vous un numéro de téléphone ?',
                        'answer' => 'Non, Wolforix Ltd. ne fournit pas de support téléphonique. Toutes les communications sont documentées par email ou ticket pour des raisons de conformité et de sécurité.',
                    ],
                ],
            ],
            [
                'title' => 'Légal / Conformité',
                'items' => [
                    [
                        'question' => 'Dois-je vérifier mon identité ?',
                        'answer' => 'Oui, une vérification d’identité (KYC) peut être requise avant le traitement des payouts afin de respecter les règles anti-blanchiment.',
                    ],
                    [
                        'question' => 'Quels pays sont restreints ?',
                        'answer_paragraphs' => [
                            'Wolforix ne fournit pas de services aux personnes ou entités résidant dans des pays soumis à des sanctions internationales ou restrictions réglementaires.',
                            'Cela inclut notamment l’Iran, la Corée du Nord, la Syrie, le Soudan, Cuba, la Russie et le Venezuela.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Éligibilité',
                                'bullets' => [
                                    'En utilisant les services Wolforix, vous confirmez ne pas résider dans une juridiction restreinte.',
                                    'Vous confirmez ne pas être soumis à des sanctions applicables.',
                                    'Vous confirmez être légalement autorisé à participer selon les lois de votre pays.',
                                ],
                            ],
                            [
                                'title' => 'Conformité',
                                'paragraphs' => [
                                    'Wolforix respecte les réglementations internationales, notamment les politiques anti-blanchiment (AML) et de lutte contre le financement du terrorisme (CTF).',
                                    'L’accès aux services peut être restreint selon le pays de résidence, la nationalité, les limites de fournisseurs de paiement comme Stripe ou PayPal, ou l’évaluation interne du risque.',
                                ],
                            ],
                            [
                                'title' => 'Avis important',
                                'bullets' => [
                                    'Wolforix se réserve le droit de restreindre ou refuser l’accès à tout utilisateur à sa seule discrétion.',
                                    'Wolforix peut demander une vérification d’identité (KYC) à tout moment.',
                                    'Wolforix peut mettre à jour la liste des juridictions restreintes sans préavis.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Existe-t-il des règles contre la fraude ou l’abus ?',
                        'answer' => 'Toute tentative de manipuler le système, exploiter des failles ou commettre une activité frauduleuse entraînera la fermeture du compte et pourra être signalée aux autorités.',
                    ],
                ],
            ],
        ],
    ],
    'search' => [
        'title' => 'Rechercher sur le site',
        'description' => 'Recherchez des plans, politiques, pages support et réponses FAQ.',
        'placeholder' => 'Rechercher pages, FAQ et politiques...',
        'empty' => 'Aucun contenu correspondant trouvé.',
        'close' => 'Fermer la recherche',
        'featured_title' => 'Destinations populaires',
        'results_one' => 'résultat',
        'results_many' => 'résultats',
        'section_labels' => [
            'page' => 'Page',
            'faq' => 'FAQ',
            'policy' => 'Politique',
            'support' => 'Support',
        ],
    ],
    'contact' => [
        'eyebrow' => 'Contacter Wolforix',
        'title' => 'Support, live chat et aide vocale FAQ réunis au même endroit.',
        'description' => 'Contactez le support par email, lancez un live chat relié à l’email ou utilisez l’assistant vocal basé sur la FAQ pour obtenir des réponses rapides avant ou après votre challenge.',
        'primary_action' => 'Envoyer un email',
        'secondary_action' => 'Ouvrir la FAQ',
        'email_title' => 'Support email',
        'email_copy' => 'Envoyez vos questions de facturation, de compte ou de règles directement au support Wolforix.',
        'email_response' => 'Les réponses arrivent généralement pendant les horaires de support : :hours.',
        'email_button' => 'Envoyer un email',
        'live_chat_title' => 'Live chat',
        'live_chat_copy' => 'Utilisez le lanceur de live chat ci-dessous. Si l’équipe est hors ligne, votre message est envoyé dans la file email du support.',
        'live_chat_note' => 'Les réponses peuvent prendre plus de temps en dehors des heures d’ouverture.',
        'live_chat_label' => 'De quoi avez-vous besoin ?',
        'live_chat_placeholder' => 'Écrivez votre question et nous la préparerons pour la boîte de support...',
        'live_chat_status' => 'Votre message de live chat s’ouvrira dans l’email du support.',
        'live_chat_empty' => 'Écrivez d’abord un message avant de démarrer le live chat.',
        'live_chat_button' => 'Démarrer le live chat',
        'live_chat_subject' => 'Demande support depuis le live chat',
        'voice_title' => 'Parlez avec Wolfi',
        'voice_copy' => 'Votre assistant de trading est prêt à vous aider.',
        'voice_online' => 'En ligne',
        'voice_state_idle' => 'Touchez pour parler',
        'voice_state_listening' => 'Écoute',
        'voice_state_speaking' => 'Wolfi parle',
        'voice_state_rendering' => 'Wolfi réfléchit',
        'voice_ready' => 'Wolfi est prêt.',
        'voice_listening' => 'Écoute en cours... appuyez à nouveau pour arrêter.',
        'voice_unsupported' => 'La saisie vocale n’est pas prise en charge dans ce navigateur. Vous pouvez quand même taper votre question.',
        'voice_no_match' => 'Je ne suis pas encore totalement sûr d’avoir bien compris. Reformulez votre question ou ouvrez la FAQ complète.',
        'voice_intro_title' => 'Parlez avec Wolfi',
        'voice_intro_message' => 'Votre assistant de trading est prêt à vous aider.',
        'voice_intro_blocked' => 'Wolfi s’est ouvert, mais votre navigateur a bloqué la lecture audio immédiate. Touchez Lire la réponse pour l’entendre.',
        'voice_clarify_title' => 'Je veux m’assurer de répondre à la bonne question.',
        'voice_clarify_intro' => 'Je mélange peut-être votre demande. Essayez plutôt l’une de ces options : :suggestions',
        'voice_support_fallback' => 'Je peux vous aider sur les règles, les payouts, les plans et les questions générales de plateforme. Pour la facturation ou l’aide spécifique au compte, contactez :email.',
        'voice_trial_fallback' => 'Utilisez Free Trial pour ouvrir le parcours démo Wolforix. Les utilisateurs existants peuvent saisir le même email et mot de passe, et Wolforix les redirigera vers le dashboard d’essai ou créera l’essai s’il n’a pas encore commencé.',
        'voice_plan_fallback' => 'Wolforix propose actuellement les modèles 1-Step Instant et 2-Step Pro en tailles 5K, 10K, 25K, 50K et 100K. Choisissez le modèle adapté à votre risque, puis utilisez Obtenir le plan pour continuer.',
        'voice_payout_fallback' => 'Les commissions sont payées sur demande et soumises à l’examen et à l’approbation de la Wolforix Partner Success Team. Le premier payout devient éligible après :first_payout_days jours, puis les payouts suivants sont disponibles selon des cycles récurrents de :payout_cycle_days jours. Une fois le cycle requis terminé, les payouts sont traités sous 24 heures. Pour demander un payout, envoyez un email à support@wolforix.com une fois le seuil minimum de retrait de $100 atteint.',
        'voice_max_drawdown_fallback' => 'Si vous atteignez la limite de drawdown maximum, le challenge échoue et le compte peut être bloqué ou désactivé. Les positions ouvertes peuvent devoir être clôturées, et le dashboard affichera le statut d’échec et la raison.',
        'voice_rules_fallback' => 'Le 1-Step utilise un objectif de 10 %, une perte journalière maximale de 4 % et une perte totale maximale de 8 %. Le 2-Step utilise des objectifs de 10 % puis 5 %, avec 5 % de perte journalière maximale, 10 % de perte totale maximale et un minimum de 3 jours tradés par phase.',
        'voice_checkout_fallback' => 'Cliquez sur Obtenir le plan dans le challenge choisi, puis connectez-vous ou créez votre compte avant le checkout. Wolforix vous ramène ensuite vers le bon plan.',
        'voice_discount_fallback' => 'Ouvrez le popup de lancement et cliquez sur Obtenir la remise pour activer l’offre de 20 % pendant la session en cours. Si vous l’ignorez, le tarif standard reste affiché et la remise ne s’applique pas.',
        'voice_general_fallback' => 'Je suis surtout utile pour les plans Wolforix, l’accès démo, les payouts, les règles, la connexion, le checkout et l’orientation MT5. Si votre question est plus large, je peux quand même aider brièvement ou vous rediriger vers la bonne page Wolforix.',
        'voice_input_label' => 'Poser une question',
        'voice_input_placeholder' => 'Exemple : Quand puis-je demander mon premier payout ?',
        'voice_suggestions_label' => 'Questions suggérées',
        'voice_suggestions_copy' => 'Commencez par une question rapide et Wolfi guidera la suite de l’échange.',
        'voice_submit' => 'Obtenir une réponse',
        'voice_button' => 'Parler',
        'voice_play_button' => 'Lire la réponse',
        'voice_stop_play_button' => 'Arrêter l’audio',
        'voice_play_requires_answer' => 'Obtenez d’abord une réponse, puis utilisez Lire la réponse.',
        'voice_generating_audio' => 'Préparation de la voix de Wolfi...',
        'voice_external_fallback' => 'La voix premium est indisponible pour le moment. La voix du navigateur sera utilisée à la place.',
        'voice_audio_unavailable' => 'La lecture vocale est indisponible pour le moment. Veuillez réessayer dans un instant.',
        'voice_speaking' => 'Wolfi parle... touchez Arrêter l’audio pour le couper immédiatement.',
        'voice_stop_button' => 'Arrêter',
        'voice_stopped' => 'Microphone arrêté.',
        'voice_no_speech' => 'Aucune voix détectée. Veuillez réessayer.',
        'voice_mic_blocked' => 'L’accès au microphone a été bloqué. Vérifiez les permissions du navigateur puis réessayez.',
        'voice_audio_capture' => 'Aucun microphone fonctionnel n’a été détecté pour la saisie vocale.',
        'voice_permission_checking' => 'Vérification de l’accès au microphone...',
        'voice_secure_context' => 'La saisie vocale nécessite un contexte navigateur sécurisé comme HTTPS ou localhost.',
        'voice_answer_title' => 'Réponse de Wolfi',
        'voice_empty' => 'Posez une question sur les payouts, les règles, le scaling, le support ou l’éligibilité du compte.',
        'voice_ai_notice' => 'Les réponses vocales sont générées par IA.',
        'voice_open_faq' => 'Ouvrir la FAQ complète',
    ],
    'security' => [
        'meta_title' => 'Sécurité',
        'eyebrow' => 'Confiance & Sécurité',
        'title' => 'Des pratiques de sécurité pensées pour protéger la plateforme, votre compte et l’intégrité opérationnelle.',
        'description' => 'Wolforix construit son positionnement sécurité autour de contrôles concrets d’accès, de monitoring, de supervision du risque et de gestion des données. L’alignement ISO/IEC 27001 est en cours et aucune certification n’est revendiquée à ce stade.',
        'badge' => 'Alignement ISO/IEC 27001 en cours',
        'note' => 'Cette page décrit les pratiques de sécurité actuelles et la direction de la feuille de route afin de renforcer la confiance pendant que le programme continue de mûrir.',
        'sections' => [
            [
                'title' => 'Sécurité',
                'description' => 'Les contrôles de compte et de plateforme sont conçus pour réduire les accès non autorisés et protéger les flux d’authentification.',
                'items' => [
                    'La prise en charge de l’authentification à deux facteurs fait partie des contrôles d’accès au compte.',
                    'Le trafic sensible et les secrets sont protégés par chiffrement et par une gestion contrôlée.',
                    'La protection du compte inclut accès contrôlé, bonnes pratiques d’identifiants et garde-fous de session.',
                ],
            ],
            [
                'title' => 'Gestion des Risques',
                'description' => 'Le risque opérationnel est géré via des sauvegardes multicouches, du monitoring et des processus de revue.',
                'items' => [
                    'Les droits d’accès et les changements opérationnels sont revus dans des workflows contrôlés.',
                    'Le monitoring et les alertes aident à détecter rapidement activité inhabituelle et incidents de service.',
                    'Les processus de réponse visent à contenir les incidents, soutenir la revue et améliorer la résilience.',
                ],
            ],
            [
                'title' => 'Protection des Données',
                'description' => 'La gestion des données s’organise autour de la protection, de l’accès limité et de pratiques de conservation responsables.',
                'items' => [
                    'Les données sont protégées en transit grâce à des standards modernes de chiffrement.',
                    'L’accès aux informations sensibles est limité aux personnes et systèmes autorisés.',
                    'Le stockage, la conservation et la manipulation sont pensés pour soutenir confidentialité et intégrité.',
                ],
            ],
            [
                'title' => 'Feuille de Route',
                'description' => 'La maturité sécurité continue de s’étendre à mesure que la plateforme et les contrôles internes évoluent.',
                'items' => [
                    'L’alignement ISO/IEC 27001 est actuellement en cours.',
                    'Les politiques, la documentation et la couverture des contrôles continuent d’être formalisées et revues.',
                    'Le monitoring, la gouvernance des accès et l’amélioration continue continueront de se renforcer dans le temps.',
                ],
            ],
        ],
    ],
    'admin' => [
        'meta_title' => 'Wolforix Admin',
        'eyebrow' => 'Admin interne',
        'header_label' => 'Zone de gestion des clients',
        'back_to_site' => 'Retour au site',
        'logout' => 'Se déconnecter',
        'login' => [
            'eyebrow' => 'Accès protégé',
            'title' => 'Connexion Admin',
            'description' => 'Utilisez les identifiants admin internes configurés pour accéder aux fiches clients, aux paiements et aux métriques de challenge depuis l’espace admin Wolforix.',
            'username' => 'Nom d’utilisateur',
            'password' => 'Mot de passe',
            'submit' => 'Ouvrir l’admin',
            'invalid' => 'Les identifiants admin sont invalides.',
            'logged_out' => 'Vous avez été déconnecté de l’admin.',
        ],
        'clients' => [
            'title' => 'Clients',
            'description' => 'Suivez les utilisateurs enregistrés, les commandes payées, les données de facturation, les prestataires de paiement et l’avancement d’activation depuis un seul tableau admin interne.',
            'status_hint' => ':count clients chargés',
            'empty' => 'Aucun client enregistré pour le moment.',
            'activation_success' => ':name est maintenant actif et prêt pour les métriques MT5 sous :reference.',
            'activation_error' => 'L’activation n’a pas pu être finalisée : :message',
        ],
        'table' => [
            'full_name' => 'Nom complet',
            'email' => 'Email',
            'country' => 'Pays',
            'plan_selected' => 'Plan sélectionné',
            'payment_amount' => 'Montant payé',
            'payment_provider' => 'Prestataire',
            'payment_status' => 'Statut du paiement',
            'order_date' => 'Date de commande',
            'account_status' => 'Statut du compte',
            'metrics' => 'Métriques',
            'view_metrics' => 'Voir les métriques',
            'actions' => 'Actions',
            'activate_account' => 'Activer le compte',
            'no_action' => 'Aucune action',
        ],
        'client_show' => [
            'title' => 'Métriques client',
            'eyebrow' => 'Détail client',
            'description' => 'Consultez les données commerciales du client, l’état du compte de trading lié et les derniers détails de synchronisation depuis un seul écran interne.',
            'back' => 'Retour aux clients',
            'client_summary' => 'Résumé du client',
            'metrics_overview' => 'Vue d’ensemble des métriques',
            'placeholder_note' => 'Les métriques proviennent du dernier compte de trading lié et de la couche locale de synchronisation.',
            'account_snapshot' => 'Dernier snapshot du compte',
            'billing_summary' => 'Résumé de facturation',
            'provider_references' => 'Références prestataire',
        ],
        'metrics' => [
            'profit' => 'Profit',
            'daily_loss' => 'Perte journalière',
            'max_drawdown' => 'Drawdown max',
            'trading_days' => 'Jours de trading',
            'current_status' => 'Statut actuel',
        ],
        'account' => [
            'reference' => 'Référence du compte',
            'platform' => 'Plateforme',
            'stage' => 'Étape',
            'balance' => 'Solde',
        ],
    ],
    'checkout' => [
        'page_description' => 'Wolforix crée d’abord une vraie commande, puis lance Stripe ou PayPal avec un pricing calculé côté serveur et le même flux de fulfillment sécurisé.',
        'redirect_note' => 'La homepage redirige maintenant vers une page de checkout dédiée où les informations de facturation, le choix du provider et le paiement Stripe ou PayPal sont traités en toute sécurité.',
        'promo_code_title' => 'Code promo',
        'promo_code_label' => 'Code promo',
        'promo_code_placeholder' => 'Saisissez votre code promo',
        'promo_code_badge' => 'Accès lancement 20 %',
        'promo_code_help' => 'Saisissez un code promo puis cliquez sur Appliquer pour mettre à jour instantanément le total du checkout.',
        'promo_code_apply' => 'Appliquer',
        'promo_code_applied' => 'Code appliqué',
        'promo_code_applied_copy' => 'Le code de lancement a été appliqué automatiquement et la remise de 20 % est déjà reflétée dans le total ci-dessus.',
        'promo_code_feedback' => [
            'success' => 'Code appliqué avec succès',
            'invalid' => 'Code invalide/expiré',
        ],
        'payment_methods_title' => 'Choisissez votre moyen de paiement',
        'payment_methods_subtitle' => 'Checkout sécurisé • Activation instantanée du compte',
        'trust_message' => 'Vos données sont protégées au moyen de pratiques de sécurité standard du secteur alignées sur l’ISO/IEC 27001.',
        'provider_available' => 'Disponible',
        'provider_coming_soon' => 'Bientôt',
        'provider_recommended' => 'Recommandé',
        'payment_method_points' => [
            'Paiement sécurisé',
            'Activation instantanée du compte',
            'Aucun frais caché',
        ],
        'buttons' => [
            'stripe' => 'Payez par carte via Stripe avec le même flux sécurisé de commande et de fulfillment.',
            'paypal' => 'Payez avec PayPal via redirection d’approbation et capture sécurisée côté serveur.',
        ],
        'providers' => [
            'stripe' => [
                'label' => 'Stripe',
                'description' => 'Checkout carte sécurisé avec Stripe et confirmation de commande via webhook.',
                'summary' => 'Payez en toute sécurité par carte',
                'supporting' => 'Confirmation rapide et instantanée',
                'cta' => 'Payer par carte',
            ],
            'paypal' => [
                'label' => 'PayPal',
                'description' => 'Flux PayPal sécurisé avec approbation externe et capture côté serveur, relié au même parcours de commande Wolforix.',
                'summary' => 'Payez avec votre compte PayPal',
                'supporting' => 'Sécurisé et reconnu',
                'cta' => 'Payer avec PayPal',
            ],
        ],
        'validation' => [
            'promo_code' => 'Code invalide/expiré',
        ],
    ],
    'footer' => [
        'disclaimer_title' => 'Environnement simulé',
        'legal_copy' => [
            'Wolforix Ltd. est une société enregistrée au Royaume-Uni (Company Number: 17111904), avec son siège social à Suite RA01, 195-197 Wood Street, London, E17 3NU. Wolforix exerce comme plateforme d’évaluation et d’éducation au trading propriétaire.',
            'Tous les services fournis par Wolforix sont proposés exclusivement dans un environnement de trading simulé utilisant des fonds virtuels. Ces fonds n’ont aucune valeur réelle, ne sont pas retirables et ne représentent pas un capital réel. Wolforix n’est ni un broker ni une institution financière et ne fournit pas de services d’investissement, de conseil financier ou de gestion d’actifs.',
            'Rien sur cette plateforme ne constitue un conseil en investissement ni une offre d’achat ou de vente d’instruments financiers. Les résultats obtenus dans des environnements simulés ne garantissent pas les résultats futurs sur les marchés réels et peuvent différer sensiblement des résultats de trading réels.',
            'Toute performance affichée, y compris les payouts, est purement illustrative et soumise aux conditions spécifiques du programme. Tous les payouts sont soumis à vérification, y compris des contrôles internes de sécurité, des mesures de prévention de la fraude et des procédures de vérification d’identité.',
            'Wolforix se réserve le droit de demander des documents complémentaires, de revoir des comptes, d’ajuster des résultats, de refuser des payouts, d’annuler des profits ou de suspendre et/ou résilier des comptes en cas de violation de ses conditions ou de détection d’une activité irrégulière.',
            'Nos services ne sont pas disponibles dans les juridictions où leur utilisation violerait les lois ou réglementations applicables. Il appartient à l’utilisateur de s’assurer du respect des lois locales.',
            'Nous pouvons partager des informations avec des prestataires tiers strictement nécessaires au fonctionnement de la plateforme, notamment des services de paiement, des fournisseurs d’infrastructure ou des services de vérification, conformément aux lois applicables en matière de protection des données.',
            'Wolforix ne garantit pas une disponibilité continue ou ininterrompue de ses services et ne fournit aucune garantie expresse ou implicite. Wolforix ne pourra être tenue responsable d’aucune perte directe, indirecte ou consécutive résultant de l’utilisation de la plateforme.',
            'Wolforix se réserve le droit de modifier à tout moment ces conditions et politiques.',
            'En utilisant ce site web, vous acceptez nos Conditions Générales, notre Politique de Confidentialité, notre Politique de Payout, notre Politique de Remboursement et tous les documents juridiques associés.',
        ],
        'legal_title' => 'Juridique & Politiques',
        'security_title' => 'Confiance & Sécurité',
        'security_line' => 'Sécurité alignée sur les standards ISO/IEC 27001 (en cours)',
        'security_link' => 'Voir la sécurité',
        'operations_title' => 'Opérations',
        'operations_copy' => 'Le support est géré par email puis, plus tard, via des tickets dans le dashboard. Les retraits manuels restent vérifiés par l’admin et l’approbation dépend du respect des règles.',
        'contact_title' => 'Contact & Support',
        'contact_copy' => 'Besoin d’aide directe avant d’acheter ? Contactez le support ou ouvrez Wolfi pour obtenir rapidement des réponses sur les règles et la plateforme.',
        'payments' => [
            'eyebrow' => 'Checkout de confiance',
            'title' => 'Des moyens de paiement qui rassurent',
            'description' => 'Des rails de paiement reconnus et des marques familières rendent la dernière étape plus rapide, plus sûre et plus premium.',
            'cards_label' => 'Cartes principales',
            'protected_label' => 'Flux de commande protégé',
        ],
        'community' => [
            'eyebrow' => 'Accès communauté',
            'title' => 'Wolforix Community Access',
            'description' => 'Access structured market insights, analysis and updates in real time',
            'channels' => [
                'youtube' => [
                    'description' => 'Regardez les mises à jour plateforme, le contenu éducatif et les vidéos pensées pour les traders.',
                    'cta' => 'Ouvrir YouTube',
                ],
                'instagram' => [
                    'description' => 'Suivez les updates visuelles, les highlights et le contenu court de la communauté.',
                    'cta' => 'Ouvrir Instagram',
                ],
                'telegram' => [
                    'description' => 'Rejoignez le canal direct pour les annonces, notes de marché et nouvelles de la communauté.',
                    'cta' => 'Ouvrir Telegram',
                ],
            ],
        ],
        'positioning_bullets' => [
            'Payouts rapides. Zéro blabla',
            'Conçu différemment des prop firms traditionnelles',
            'Pensé pour les traders disciplinés — pas les joueurs',
        ],
        'simulated_notice' => 'Tout funded account affiché dans cette interface fait partie d’un programme d’évaluation simulé. Aucun capital réel n’est tradé sur les marchés financiers en direct.',
        'company_location' => 'Wolforix Ltd. | Suite RA01, 195-197 Wood Street, London, E17 3NU',
        'copyright' => 'Tous droits réservés.',
        'back_to_top' => 'Retour en haut',
        'view_full_legal_information' => 'Voir toutes les informations légales',
        'quick_navigation_eyebrow' => 'Navigation rapide',
        'quick_navigation' => 'Ouvrir la navigation principale',
        'contact_short' => 'Contact',
    ],
    'cookie' => [
        'title' => 'Avis cookies',
        'message' => 'Nous utilisons des cookies pour améliorer votre expérience et assurer les fonctionnalités essentielles du site.',
        'accept' => 'Accepter',
        'learn_more' => 'En savoir plus',
    ],
    'legal' => [
        'eyebrow' => 'Juridique Wolforix',
        'quick_links' => 'Toutes les pages légales',
        'overview_title' => 'Vue d’ensemble des politiques',
        'overview_copy' => 'Ces pages présentent les textes validés par le client dans des écrans lisibles plutôt que dans un long footer.',
        'link_labels' => [
            'terms' => 'Conditions Générales',
            'risk_disclosure' => 'Divulgation des Risques',
            'payout_policy' => 'Politique de Payout',
            'refund_policy' => 'Politique de Remboursement',
            'privacy_policy' => 'Politique de Confidentialité',
            'aml_kyc_policy' => 'Politique AML & KYC',
            'company_information' => 'Informations Société',
        ],
    ],
    'trial' => [
        'eyebrow' => 'Essai Gratuit',
        'register' => [
            'title' => 'Démarrer votre essai gratuit',
            'description' => 'Inscrivez-vous avec votre email et votre mot de passe pour accéder à un compte d’essai démo Wolforix séparé des challenges payants.',
            'what_you_get_title' => 'Inclus immédiatement',
            'balance_line' => 'Solde démo : :amount',
            'take_profit_line' => 'Objectif take profit : :percent%',
            'minimum_days_line' => 'Jours de trading minimum : :days',
            'markets_line' => 'Marchés disponibles : :markets',
            'restrictions_line' => 'Démo uniquement. Aucun retrait. Non compté comme un challenge.',
            'email' => 'Email',
            'password' => 'Mot de passe',
            'password_placeholder' => '8 caractères minimum',
            'submit' => 'Créer le compte d’essai',
            'success' => 'Votre compte d’essai gratuit est prêt.',
            'existing_account_error' => 'Cet email possède déjà un compte Wolforix. Entrez ici le bon mot de passe pour continuer vers le Free Trial.',
        ],
        'dashboard' => [
            'title' => 'Dashboard d’Essai',
            'description' => 'Suivez l’état actuel de votre compte d’essai avant de passer à une évaluation payante.',
            'banner_title' => 'Ceci est un compte d’essai.',
            'banner_copy' => 'Ce compte est entièrement démo et n’affecte ni les payouts ni l’éligibilité aux challenges financés.',
            'passed_title' => 'Vous avez réussi le modèle free trial.',
            'passed_copy' => 'Très bon travail. Vous avez atteint l’objectif du trial dans le cadre des règles affichées. La prochaine étape est de passer sur un Simulation Account avec une évaluation structurée.',
            'passed_button' => 'Voir les plans de simulation',
            'ended_title' => 'Votre essai est terminé.',
            'ended_copy' => 'Ce compte démo n’est plus actif car les règles affichées du trial ont été enfreintes. Lancez un nouvel essai pour continuer à pratiquer.',
            'retry_button' => 'Relancer l’essai',
            'restrictions_title' => 'Restrictions de l’essai',
            'restrictions' => [
                'Aucun retrait',
                'Non compté comme un Challenge',
                'Environnement démo uniquement',
            ],
            'markets_title' => 'Marchés disponibles',
            'rules_title' => 'Règles affichées',
            'rule_labels' => [
                'starting_balance' => 'Solde de départ',
                'daily_limit' => 'Limite de drawdown journalier',
                'take_profit' => 'Take Profit',
                'max_limit' => 'Limite de drawdown max',
                'minimum_days' => 'Jours de trading minimum',
                'status' => 'Statut actuel',
            ],
            'metrics' => [
                'balance' => 'Solde',
                'equity' => 'Équité',
                'daily_drawdown' => 'Drawdown journalier',
                'max_drawdown' => 'Drawdown max',
                'profit_loss' => 'Profit / Perte',
            ],
        ],
        'statuses' => [
            'active' => 'Actif',
            'passed' => 'Réussi',
            'ended' => 'Terminé',
        ],
    ],
    'auth' => [
        'eyebrow' => 'Accès sécurisé',
        'title' => 'Connectez-vous ou créez votre compte pour continuer.',
        'description' => 'Le paiement d’un challenge payant nécessite désormais une authentification afin que la commande, le résultat du paiement et le challenge acheté restent liés au bon compte utilisateur.',
        'notice' => 'Le challenge, la taille de compte et la devise sélectionnés seront conservés après la connexion ou l’inscription, puis vous serez renvoyé directement vers le checkout.',
        'home_action' => 'Retour à l’accueil',
        'dashboard_action' => 'Dashboard',
        'login' => [
            'title' => 'Connexion',
            'copy' => 'Utilisez les identifiants de votre compte Wolforix existant pour continuer vers le checkout sécurisé.',
            'email' => 'Email',
            'password' => 'Mot de passe',
            'forgot_password' => 'Mot de passe oublié ?',
            'remember' => 'Rester connecté sur cet appareil',
            'submit' => 'Se connecter',
            'invalid' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
            'social_divider' => 'ou continuer avec',
            'social_google' => 'Continuer avec Google',
            'social_facebook' => 'Continuer avec Facebook',
            'social_apple' => 'Continuer avec Apple',
            'social_unavailable_badge' => 'Setup',
            'social_setup_notice' => 'La connexion sociale apparaît automatiquement dès que les identifiants provider sont configurés dans l’environnement.',
            'social_unavailable_error' => 'Ce provider de connexion sociale n’est pas encore configuré.',
            'social_failed' => 'La connexion sociale n’a pas pu être finalisée. Réessayez ou utilisez la connexion par email.',
            'social_cancelled' => 'La connexion sociale a été annulée avant la fin.',
            'social_state_invalid' => 'La session de connexion sociale a expiré. Réessayez.',
        ],
        'register' => [
            'title' => 'Créer un compte',
            'copy' => 'Nouveau sur Wolforix ? Créez d’abord votre compte, puis complétez vos informations de facturation avant de choisir votre moyen de paiement au checkout.',
            'name' => 'Nom complet',
            'email' => 'Email',
            'password' => 'Mot de passe',
            'password_confirmation' => 'Confirmer le mot de passe',
            'submit' => 'Créer le compte',
        ],
        'passwords' => [
            'request' => [
                'title' => 'Réinitialiser votre mot de passe',
                'copy' => 'Saisissez l’email lié à votre compte Wolforix et nous vous enverrons un lien sécurisé de réinitialisation.',
                'email' => 'Email',
                'submit' => 'Envoyer le lien',
                'back_to_login' => 'Retour à la connexion',
            ],
            'reset' => [
                'title' => 'Créer un nouveau mot de passe',
                'copy' => 'Choisissez un nouveau mot de passe pour votre compte Wolforix.',
                'password' => 'Nouveau mot de passe',
                'password_confirmation' => 'Confirmer le nouveau mot de passe',
                'submit' => 'Mettre à jour le mot de passe',
            ],
            'status' => [
                'sent' => 'Nous vous avons envoyé le lien de réinitialisation par email.',
                'user' => 'Aucun utilisateur n’a été trouvé avec cette adresse email.',
                'throttled' => 'Veuillez patienter avant de réessayer.',
                'token' => 'Ce lien de réinitialisation est invalide ou expiré.',
                'reset' => 'Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.',
            ],
        ],
    ],
    'dashboard' => [
        'preview_title' => 'Dashboard de Trading',
        'preview_subtitle' => 'Comptes, payouts et progression au même endroit.',
        'nav' => [
            'wolfi_hub' => 'Wolfi Hub',
        ],
        'wolfi_hub_page' => [
            'title' => 'Wolfi Hub',
            'subtitle' => 'Support lié au compte, guide de plateforme et contexte Wolfi sans encombrer l’espace de trading.',
            'empty_title' => 'Wolfi prépare votre espace',
            'empty_copy' => 'Quand le contexte du dashboard sera chargé, Wolfi Hub expliquera la page, le statut du compte, les règles, les payouts et les options de support.',
        ],
        'mt5' => [
            'title' => 'Sync live MT5',
            'heading' => 'Synchronisation MT5 et accès au compte',
            'copy' => 'Wolforix organise maintenant le flux trader autour des données de compte MT5, de la fraîcheur de synchronisation et des accès sécurisés.',
        ],
        'wolfi' => [
            'entry_eyebrow' => 'Wolfi soutient votre',
            'entry_title' => 'espace de trading',
            'entry_copy' => 'Prêt à soutenir votre prochaine étape sur les règles, les métriques, le timing payout et le contexte MT5.',
            'entry_hint' => 'Le dashboard principal garde les données de compte en priorité ; l’assistant complet vit dans Wolfi Hub.',
            'open_hub' => 'Ouvrir Wolfi – Votre profil',
            'fallbacks' => [
                'dashboard_workspace' => 'Espace dashboard',
            ],
            'welcome' => [
                'title' => 'Explication personnalisée du compte',
                'account_message' => 'Je lis votre compte :plan dans :page afin d’expliquer le statut, les règles, les payouts et les données MT5 clairement.',
                'account_bullets' => [
                    'status' => 'Statut actuel : :status avec :progress de progression vers l’objectif.',
                    'balance' => 'Le solde est :balance, l’equity :equity et le P&L flottant :pnl.',
                    'trading_days' => 'La progression des jours de trading est :days pour la phase active.',
                ],
                'empty_message' => 'Je peux expliquer :page, les règles de challenge, les payouts et les étapes de support pendant que Wolforix attend un compte MT5 actif sur ce profil.',
                'empty_bullets' => [
                    'Utilisez Wolfi Hub pour l’assistant complet avec contexte de compte.',
                    'Le dashboard principal garde l’espace de trading et les données de compte en premier.',
                    'Quand les données MT5 arrivent, Wolfi ajoute ici des explications personnalisées.',
                ],
            ],
            'stat_labels' => [
                'status' => 'Statut',
                'balance' => 'Solde',
                'equity' => 'Equity',
                'page' => 'Page',
                'rules' => 'Règles',
                'support' => 'Support',
            ],
            'stat_values' => [
                'structured' => 'Structuré',
                'ready' => 'Prêt',
            ],
            'assistant' => [
                'eyebrow' => 'Wolfi soutient votre',
                'title' => 'espace de trading',
                'description' => 'Prêt à soutenir votre prochaine étape avec une aide consciente des règles et des métriques dans Wolfi Hub.',
                'sources_title' => 'Réponse en direct',
                'sources_copy' => 'Conscient des règles, conscient des métriques et prêt pour une future lecture vocale.',
                'status_idle' => 'Prêt à soutenir votre prochaine étape',
                'status_thinking' => 'Wolfi vérifie le contexte du compte',
                'input_placeholder' => 'Question sur votre compte MT5, les règles, payouts, métriques ou support...',
                'submit_label' => 'Demander à Wolfi',
                'input_help' => 'Wolfi utilise la page actuelle et le compte sélectionné lorsque ces données sont disponibles.',
            ],
            'pillars' => [
                ['title' => 'Conscient des règles', 'description' => 'Guidage basé sur les règles de la plateforme.'],
                ['title' => 'Conscient des métriques', 'description' => 'Des insights qui suivent ce qui compte vraiment.'],
                ['title' => 'Timing payout', 'description' => 'Restez aligné avec le calendrier des payouts.'],
                ['title' => 'Toujours prêt', 'description' => 'Obtenez une aide instantanée quand vous en avez besoin.'],
            ],
            'quick_actions' => [
                ['key' => 'dashboard', 'label' => 'Expliquer mon dashboard', 'prompt' => 'Expliquer mon dashboard'],
                ['key' => 'rules', 'label' => 'Quelles sont les règles ?', 'prompt' => 'Quelles sont les règles du challenge ?'],
                ['key' => 'metrics', 'label' => 'Expliquer mes métriques', 'prompt' => 'Expliquer mes métriques'],
                ['key' => 'payouts', 'label' => 'Comment fonctionnent les payouts ?', 'prompt' => 'Comment fonctionnent les payouts ?'],
                ['key' => 'consistency', 'label' => 'Quelle est la règle de consistance ?', 'prompt' => 'Quelle est la règle de consistance ?'],
            ],
            'smart_insights' => [
                'title' => 'Smart Insights',
                'description' => 'Wolfi surveille le contexte live du compte et met en avant les signaux importants avant même votre question.',
            ],
            'pages' => [
                'dashboard.wolfi' => [
                    'title' => 'Wolfi Hub',
                    'summary' => 'Utilisez cette page pour l’assistant Wolfi complet, les explications liées au compte, Smart Insights, le support et le guide plateforme.',
                    'sections' => [
                        ['title' => 'Briefing personnel', 'description' => 'Wolfi explique le statut du compte, les données MT5, les règles, le progrès et le contexte payout.'],
                        ['title' => 'Questions rapides', 'description' => 'Les actions rapides aident à demander le dashboard, les règles, métriques, payouts et la consistance.'],
                        ['title' => 'Contexte support', 'description' => 'Wolfi peut orienter vers facturation, support, navigation ou prochaine étape opérationnelle.'],
                    ],
                ],
            ],
            'insights' => [
                'risk_alert' => ['label' => 'Alerte risque', 'daily_message' => 'L’utilisation de perte quotidienne devient élevée. Protégez le compte avant un nouveau setup.', 'max_message' => 'L’utilisation du drawdown max devient élevée. Réduisez le risque.', 'meta' => 'Jour :daily% · Max :max%', 'prompt' => 'Explique mon risque de drawdown et la marge restante'],
                'profit_progress' => ['label' => 'Progression profit', 'message' => 'Vous êtes proche de l’objectif. Protégez les gains et complétez les règles.', 'meta' => ':progress% de l’objectif', 'prompt' => 'Explique ce qu’il reste pour réussir cette phase'],
                'consistency_warning' => ['label' => 'Alerte consistance', 'message' => 'Un jour porte une grande part du profit. Répartissez les gains avant payout.', 'meta' => 'Ratio meilleur jour :ratio%', 'prompt' => 'Explique mon statut de consistance'],
                'payout_readiness' => ['label' => 'Préparation payout', 'message' => 'La fenêtre payout semble ouverte. Confirmez les règles et préparez la demande.', 'meta' => 'Compte funded', 'prompt' => 'Explique ma préparation payout'],
            ],
        ],
        'settings' => [
            'preferences_copy' => 'Le dashboard est deja adapte aux locales afin que le changement de langue reste coherent a mesure que de nouvelles langues prises en charge sont ajoutees.',
        ],
    ],
]);
