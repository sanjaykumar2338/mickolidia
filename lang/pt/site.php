<?php

$en = require __DIR__.'/../en/site.php';

return array_replace_recursive($en, [
    'meta' => [
        'default_title' => 'Plataforma Prop Firm Wolforix',
        'description' => 'Wolforix is a modern prop trading platform built for disciplined traders. Access evaluation accounts, track performance, manage payouts, and trade with clear rules, secure infrastructure, and scalable capital opportunities.',
        'image' => 'trading123.png',
    ],
    'languages' => [
        'en' => 'Inglês',
        'de' => 'Alemão',
        'es' => 'Espanhol',
        'fr' => 'Francês',
        'hi' => 'Hindi',
        'it' => 'Italiano',
        'pt' => 'Português',
    ],
    'locale' => [
        'current_label' => 'Idioma',
        'menu_title' => 'Selecionar idioma',
        'future_label' => 'Pronto para mais idiomas',
    ],
    'public_layout' => [
        'preview_badge' => 'Pré-visualização da plataforma',
        'simulated_notice' => 'Negocie sem medo. Ganhe de verdade.',
    ],
    'ai_assistant' => [
        'name' => 'Wolfi',
        'eyebrow' => 'Assistente Wolfi',
        'title' => 'WOLFI',
        'description' => 'Deixe o Wolfi guiá-lo. O seu assistente especialista para regras, payouts, acesso MT5 e o próximo passo certo na plataforma.',
        'home_headline' => 'Deixe o Wolfi guiá-lo.',
        'home_description' => 'O seu assistente especialista para regras, payouts, acesso MT5 e o próximo passo certo na plataforma.',
        'multi_language' => 'Disponível 24/7',
        'start_chat' => 'Fale com Wolfi',
        'floating_label' => 'Abrir Wolfi',
        'floating_cta' => 'Pergunte ao Wolfi',
        'floating_aria' => 'Abrir Wolfi, o seu assistente AI',
        'close_aria' => 'Fechar Wolfi',
        'preview_label' => 'Pré-visualização do Wolfi',
        'preview_title' => 'Pergunte ao Wolfi antes de comprar',
        'preview_badge' => '24/7',
        'preview_copy' => 'Abra o assistente para obter clareza imediata sobre regras, trading em notícias, limites de drawdown, tempos de payout e qual challenge combina melhor com o seu perfil.',
        'visual_title' => 'Sempre ativo. Sempre pronto.',
        'visual_copy' => 'Use o Wolfi como guia visível para regras, payouts, acesso MT5 e perguntas da plataforma.',
        'visual_response_label' => 'Resposta ao vivo',
        'visual_response_preview' => 'O seu drawdown máximo nesta conta é de 5%.',
        'visual_response_hint' => 'Regras e orientação da conta em tempo real.',
        'visual_cta_hint' => 'Pergunte ao Wolfi antes da próxima operação.',
        'visual_alt' => 'Ilustração luminosa da mascote Wolfi',
        'home_visual_alt' => 'Pré-visualização aprovada da homepage do Wolfi apresentada num formato mobile',
        'example_questions' => [
            'Posso fazer trading durante notícias?',
            'Com que frequência são processados os payouts?',
            'Qual é o melhor plano para mim?',
        ],
    ],
    'nav' => [
        'home' => 'Início',
        'about' => 'Sobre nós',
        'about_us' => 'Sobre nós',
        'security' => 'Segurança',
        'contact' => 'Contacte-nos',
        'plans' => 'Planos',
        'faq' => 'FAQ',
        'news' => 'NEWS',
        'legal' => 'Legal',
        'dashboard' => 'Dashboard',
        'dashboard_preview' => 'Dashboard',
        'login' => 'Entrar',
        'logout' => 'Sair',
        'search' => 'Pesquisar',
        'search_aria' => 'Pesquisar no site',
        'menu_open' => 'Abrir menu',
        'menu_close' => 'Fechar menu',
    ],
    'home' => [
        'eyebrow' => 'Prop Trading Moderno',
        'title' => 'Obtenha uma conta funded. Receba payouts. Sem limite de tempo.',
        'description' => 'Passe a challenge. Aceda a contas funded. Levante rapidamente.',
        'mobile_title' => [
            'line_1' => 'Obtenha uma conta funded.',
            'line_2' => 'Receba payouts.',
            'line_3' => 'Sem limite de tempo.',
        ],
        'mobile_description' => [
            'line_1' => 'Passe a challenge. Aceda a contas funded.',
            'line_2' => 'Levante rapidamente.',
        ],
        'primary_cta' => 'Iniciar challenge',
        'free_trial_cta' => 'Teste Gratuito',
        'free_trial_caption' => 'Sem risco. Sem cartão.',
        'secondary_cta' => 'Abrir dashboard',
        'days' => 'dias',
        'badges' => [
            'Infraestrutura segura',
            'Trading rewards em 24h',
            'Market Pulse (news ao vivo)',
            'Assistente AI Wolfi',
        ],
        'feature_cards' => [
            'Acesso rápido ao funding',
            'Payouts rápidos',
            'Scaling de capital +25%',
            'Até 90% de profit split',
        ],
        'hero_visual' => [
            'label' => 'Pré-visualização da mesa de trading',
            'platform' => 'Workspace dark de execução',
            'card_title' => 'Focado em gráficos. Regras visíveis. Feito para parecer funded.',
            'card_copy' => 'A homepage passa agora a abrir com uma visual de trading limpa que se aproxima mais de um verdadeiro workspace de prop firm do que de um cartão promocional decorativo.',
            'image_alt' => 'Interface de trading em estilo laptop com gráfico e painéis de mercado',
        ],
        'plans' => [
            'eyebrow' => 'Planos challenge',
            'title' => 'Negocie mais de 1.000 instrumentos no MT5',
            'description' => 'Todos os instrumentos disponíveis na sua conta MT5 são totalmente suportados e acompanhados automaticamente.',
            'platform_label' => 'Plataforma',
            'platform_value' => 'MT5',
            'badge' => 'Modelos finais prontos para lançamento',
            'entry_fee' => 'Taxa de entrada',
            'profit_target' => 'Meta de lucro',
            'daily_loss' => 'Perda diária',
            'max_loss' => 'Perda total',
            'steps' => 'Etapas',
            'profit_share' => 'Profit split',
            'first_payout' => 'Primeiro levantamento',
            'minimum_days' => 'Dias mínimos de trading',
        ],
        'trust' => [
            'eyebrow' => 'Confiança / Segurança',
            'title' => 'Segurança visível desde o primeiro momento',
            'description' => 'A Wolforix reforça a confiança com uma infraestrutura robusta, controlos de risco avançados, monitorização contínua e um processo ativo de alinhamento com a norma ISO/IEC 27001.',
            'cta' => 'Ver segurança',
            'items' => [
                [
                    'title' => 'Infraestrutura segura',
                    'description' => 'Alojamento protegido e acessos operacionais estritamente controlados nos sistemas centrais.',
                ],
                [
                    'title' => 'Controlo de risco avançado',
                    'description' => 'Controlos preventivos e fluxos de revisão concebidos para minimizar o risco operacional.',
                ],
                [
                    'title' => 'Monitorização em tempo real',
                    'description' => 'Visibilidade contínua da atividade da plataforma, eventos críticos e estado do serviço.',
                ],
                [
                    'title' => 'Alinhamento ISO/IEC 27001',
                    'description' => 'O processo de implementação está em curso e faz parte do nosso compromisso com as normas internacionais de segurança.',
                ],
            ],
            'support_items' => [
                'Controlos de proteção de dados com acesso limitado e tratamento controlado.',
                'O roadmap de segurança e o trabalho de melhoria contínua continuam ativos.',
            ],
        ],
        'global_reach' => [
            'eyebrow' => 'Alcance global',
            'title_prefix' => 'A impulsionar traders em',
            'title_suffix' => 'países. Um só padrão.',
            'description' => 'A Wolforix liga traders em todo o mundo sob uma infraestrutura unificada: rápida, precisa e construída para performance.',
            'image_alt' => 'Visual da rede global de traders Wolforix',
            'visual_label' => 'Cobertura global',
            'visual_status' => 'Expansão live',
            'visual_card_label' => 'Fluxo ligado',
            'visual_card_title' => 'Uma plataforma, uma comunidade global de trading.',
            'visual_card_copy' => 'Construída para traders que se movem entre sessões, regiões e ciclos de mercado sem perder velocidade, clareza ou foco de execução.',
            'highlights' => [
                [
                    'title' => 'Acesso multi-região',
                    'description' => 'Da Europa à América Latina, Ásia e Médio Oriente.',
                ],
                [
                    'title' => 'Experiência unificada',
                    'description' => 'O mesmo fluxo de challenge, estrutura de payout e direção de suporte em todo o lado.',
                ],
                [
                    'title' => 'Feita para escalar',
                    'description' => 'Pensada para um público mais amplo de traders sem perder uma sensação premium.',
                ],
            ],
        ],
        'market_pulse' => [
            'eyebrow' => 'Direção da plataforma',
            'title' => 'Market Pulse',
            'description' => 'Insights em tempo real para negociar melhor e reagir mais depressa.',
            'cta' => 'Abrir notícias de mercado ao vivo',
            'view_all' => 'Ver calendário completo',
            'preview_label' => 'Acesso a notícias ao vivo',
            'preview_copy' => 'Veja os próximos eventos macro, mudanças de previsão e níveis de impacto antes da sua próxima operação.',
            'source_caption' => 'Fonte: :source. Horas apresentadas em :timezone (:abbr).',
            'empty' => 'O Market Pulse está a preparar as próximas atualizações ao vivo. Abra o calendário completo para ver os eventos mais recentes.',
            'cards' => [
                [
                    'title' => 'Eventos de alto impacto',
                    'description' => 'Acompanhe as publicações com maior probabilidade de mover volatilidade, spreads e condições de risco de curto prazo.',
                ],
                [
                    'title' => 'Foco multi-moeda',
                    'description' => 'Siga USD, EUR, GBP, JPY e outras moedas-chave num único feed macro ao vivo.',
                ],
                [
                    'title' => 'Filtragem rápida de eventos',
                    'description' => 'Entre no calendário completo para ordenar por impacto, moeda e intervalo temporal em segundos.',
                ],
            ],
        ],
        'challenge_selector' => [
            'currency_label' => 'Moeda',
            'type_label' => 'Tipo de challenge',
            'size_label' => 'Tamanho da conta',
            'insight_title' => 'Visão geral do modelo',
            'entry_fee' => 'Taxa de entrada',
            'current_price' => 'Preço atual',
            'original_price' => 'Preço normal',
            'discount_badge' => '20% OFF - Oferta de lançamento limitada',
            'discount_urgency' => 'Desconto de lançamento - Tempo limitado',
            'best_value' => 'Melhor valor',
            'start_button' => 'Obter plano',
            'review_policy' => 'Ver política de payout',
            'faq_link' => 'Ler FAQ',
            'unlimited' => 'Ilimitado',
            'highlights' => [
                'Preço de lançamento com 20% ativo',
                'Primeiro levantamento após 21 dias',
                'Duração de avaliação ilimitada',
            ],
            'currencies' => [
                'USD' => 'USD',
                'EUR' => 'EUR',
                'GBP' => 'GBP',
            ],
            'phase_titles' => [
                'single_phase' => 'Fase única',
                'phase_1' => 'Fase 1',
                'phase_2' => 'Fase 2',
                'funded' => 'Conta funded',
            ],
            'metrics' => [
                'profit_target' => 'Meta de lucro',
                'profit_share' => 'Profit split',
                'profit_share_upgrade' => 'Upgrade do split',
                'daily_loss' => 'Perda diária máxima',
                'total_loss' => 'Perda total máxima',
                'minimum_days' => 'Dias mínimos de trading',
                'first_withdrawal' => 'Primeiro levantamento',
                'max_trading_days' => 'Dias máximos de trading',
                'leverage' => 'Alavancagem',
                'payout_cycle' => 'Ciclo de payout',
                'scaling' => 'Scaling',
                'consistency_rule' => 'Regra de consistência',
            ],
            'value_templates' => [
                'days' => ':days dias',
                'after_days' => 'Depois de :days dias',
                'scaling' => '+:percent% de capital a cada :months meses se for lucrativa',
                'profit_split_upgrade' => ':percent% após :payouts payouts consecutivos',
            ],
            'consistency_required' => 'Obrigatória',
            'types' => [
                'one_step' => [
                    'label' => '1-Step Instant',
                    'description' => 'Passe numa única etapa. Fique funded mais depressa. Sem atrasos. Sem segunda fase.',
                    'note_title' => 'Modelo funded 1-Step Instant',
                    'note_body' => 'Regras mais apertadas, controlo de risco mais rigoroso e acesso direto a uma conta funded com consistência obrigatória. Menos etapas. Mais exigência. Resultados mais rápidos.',
                ],
                'two_step' => [
                    'label' => '2-Step Pro',
                    'description' => 'Menor risco. Maior potencial de scaling. Criado para consistência e crescimento de longo prazo.',
                    'note_title' => 'Modelo funded 2-Step Pro',
                    'note_body' => 'Avaliação em duas fases com alavancagem 1:100 na Fase 1, primeiro levantamento após 21 dias, payouts a cada 14 dias depois disso e sistema de scaling para contas funded lucrativas. Construa consistência. Escale com agressividade.',
                ],
            ],
        ],
        'about' => [
            'eyebrow' => 'Sobre a Wolforix',
            'title' => 'Uma nova geração de prop firm construída em torno de acesso, disciplina e performance.',
            'intro' => 'A Wolforix é uma proprietary trading firm que representa uma nova geração de prop firms criada para desbloquear o potencial de traders comprometidos num ambiente justo, acessível e orientado para a performance.',
            'mission_label' => 'A nossa missão',
            'mission' => 'Identificar, formar e financiar traders prontos para performar.',
            'pillars' => [
                'Avaliação estruturada',
                'Acesso a teste gratuito',
                'Funding orientado para a performance',
            ],
            'blocks' => [
                [
                    'title' => 'Porque existimos',
                    'description' => 'Acreditamos que o talento por si só não basta. O acesso ao capital continua a ser a verdadeira barreira para muitos traders disciplinados que já têm a consistência e a mentalidade necessárias para ter sucesso.',
                ],
                [
                    'title' => 'Como os traders evoluem',
                    'description' => 'Através de um sistema de avaliação estruturado e oportunidades de teste gratuito, os traders podem aperfeiçoar o seu processo, ganhar experiência e provar consistência num ambiente controlado antes de gerir capital funded.',
                ],
                [
                    'title' => 'O que sustenta a Wolforix',
                    'description' => 'A Wolforix é apoiada por licenciados em economia com anos de experiência nos mercados financeiros, trading e investimentos. A empresa está a ser construída como um ecossistema transparente, justo e orientado para a performance.',
                ],
            ],
            'closing_label' => 'O que construímos',
            'closing' => 'Não financiamos apenas traders. Construímos profissionais disciplinados e consistentes, capazes de alcançar sucesso financeiro a longo prazo.',
        ],
    ],
    'news' => [
        'eyebrow' => 'Calendário de risco macro',
        'title' => 'Calendário económico',
        'description' => 'Acompanhe as próximas publicações macro e económicas para gerir a exposição em sessões de trading sensíveis à volatilidade.',
        'warning_title' => 'Aviso de volatilidade',
        'warning_copy' => 'As news de alto impacto podem aumentar a volatilidade e afetar as condições de trading. Monitorize os eventos agendados como parte do seu processo de gestão de risco.',
        'data_source_label' => 'Fonte do calendário',
        'mode_demo' => 'Modo de calendário demo',
        'mode_live' => 'Modo de calendário live',
        'demo_notice' => 'O calendário está atualmente em modo demo com eventos de exemplo realistas até serem configuradas credenciais API licenciadas.',
        'live_notice' => 'O calendário está atualmente a usar o fornecedor API licenciado configurado.',
        'timezone_badge' => 'Visualização :timezone (:abbr)',
        'range_caption' => 'Eventos mostrados de :from até :to',
        'filters' => [
            'impact' => 'Impacto',
            'currency' => 'Moeda',
            'range' => 'Intervalo de datas',
            'high_only' => 'Só alto impacto',
            'apply' => 'Aplicar filtros',
            'reset' => 'Repor',
            'all_impacts' => 'Todos os impactos',
            'all_currencies' => 'Todas as moedas',
            'range_options' => [
                'today' => 'Hoje',
                'this_week' => 'Esta semana',
                'next_week' => 'Próxima semana',
            ],
        ],
        'table' => [
            'time' => 'Hora',
            'currency' => 'Moeda',
            'impact' => 'Impacto',
            'event' => 'Nome do evento',
            'forecast' => 'Previsão',
            'previous' => 'Anterior',
            'empty' => 'Nenhum evento do calendário económico corresponde aos filtros selecionados.',
        ],
        'impact' => [
            'high' => 'Alto',
            'medium' => 'Médio',
            'low' => 'Baixo',
        ],
        'sources' => [
            'title' => 'Fontes de dados',
            'copy' => 'Para transparência, o modo atual do calendário, a arquitetura do fornecedor live e os sites de referência do mercado estão listados abaixo.',
            'current_demo' => 'Fonte demo atual',
            'current_live' => 'Fonte live atual',
            'provider' => 'Arquitetura de fornecedor configurada',
            'reference' => 'Apenas referência',
            'legal_notice' => 'Os sites de referência são listados apenas para consciência do trader. A Wolforix não faz scraping, mirroring ou iframe de sites de calendário de terceiros.',
        ],
    ],
    'launch_popup' => [
        'title' => '20% OFF - Acesso de lançamento a terminar',
        'description' => 'Ative o seu desconto de lançamento de 20% antes que a oferta desapareça.',
        'secondary_copy' => 'Há vagas limitadas disponíveis. Quando forem preenchidas, os preços sobem.',
        'promo_label' => 'Código promocional',
        'auto_apply_notice' => 'O desconto só é ativado se escolher Obter desconto. Se ignorar, os preços regulares permanecem visíveis.',
        'copy_code' => 'Copiar código',
        'code_copied' => 'Código copiado',
        'primary_action' => 'Obter desconto',
        'secondary_action' => 'Ignorar',
        'benefits' => [
            '20% poupado instantaneamente',
            'Oportunidade sobre capital real',
            'Ativação baseada na sessão',
        ],
        'close' => 'Fechar oferta de lançamento',
    ],
    'auth' => [
        'eyebrow' => 'Acesso seguro',
        'title' => 'Entre ou crie a sua conta para continuar.',
        'description' => 'O checkout de challenges pagas exige agora autenticação para que encomendas, resultados de pagamento e challenges compradas permaneçam ligadas à conta certa do utilizador.',
        'notice' => 'A challenge e a moeda selecionadas serão preservadas após login ou registo e voltará diretamente ao checkout.',
        'home_action' => 'Voltar ao início',
        'dashboard_action' => 'Dashboard',
        'email_placeholder' => 'o.seu@email.com',
        'login' => [
            'title' => 'Entrar',
            'copy' => 'Use as credenciais da sua conta Wolforix existente para continuar para o checkout seguro.',
            'email' => 'Email',
            'password' => 'Palavra-passe',
            'forgot_password' => 'Esqueceu-se da palavra-passe?',
            'remember' => 'Manter sessão iniciada neste dispositivo',
            'submit' => 'Entrar',
            'invalid' => 'Estas credenciais não correspondem aos nossos registos.',
            'social_divider' => 'ou continue com',
            'social_google' => 'Continuar com Google',
            'social_facebook' => 'Continuar com Facebook',
            'social_apple' => 'Continuar com Apple',
            'social_unavailable_badge' => 'Setup',
            'social_setup_notice' => 'O social sign-in aparece automaticamente quando as credenciais do fornecedor são configuradas no ambiente.',
            'social_unavailable_error' => 'Este fornecedor de social sign-in ainda não está configurado.',
            'social_failed' => 'Não foi possível concluir o social sign-in. Tente novamente ou use o login por email.',
            'social_cancelled' => 'O social sign-in foi cancelado antes da conclusão.',
            'social_state_invalid' => 'A sessão de social sign-in expirou. Tente novamente.',
        ],
        'register' => [
            'title' => 'Criar conta',
            'copy' => 'Novo na Wolforix? Crie primeiro a sua conta, depois complete os dados de faturação e continue com o método de pagamento escolhido no checkout.',
            'name' => 'Nome completo',
            'email' => 'Email',
            'password' => 'Palavra-passe',
            'password_confirmation' => 'Confirmar palavra-passe',
            'submit' => 'Criar conta',
        ],
        'passwords' => [
            'request' => [
                'title' => 'Repor a sua palavra-passe',
                'copy' => 'Introduza o email associado à sua conta Wolforix e enviaremos um link seguro de reposição.',
                'email' => 'Email',
                'submit' => 'Enviar link de reposição',
                'back_to_login' => 'Voltar ao login',
            ],
            'reset' => [
                'title' => 'Criar uma nova palavra-passe',
                'copy' => 'Escolha uma nova palavra-passe para a sua conta Wolforix.',
                'password' => 'Nova palavra-passe',
                'password_confirmation' => 'Confirmar nova palavra-passe',
                'submit' => 'Atualizar palavra-passe',
            ],
            'status' => [
                'sent' => 'Enviámos por email o link para repor a sua palavra-passe.',
                'user' => 'Não conseguimos encontrar um utilizador com esse endereço de email.',
                'throttled' => 'Aguarde antes de tentar novamente.',
                'token' => 'Este link de reposição é inválido ou expirou.',
                'reset' => 'A sua palavra-passe foi reposta. Já pode entrar.',
                'mailer' => 'Não foi possível enviar o email de reposição neste momento. Contacte o suporte ou tente novamente mais tarde.',
            ],
        ],
    ],
    'trial' => [
        'eyebrow' => 'Teste gratuito',
        'register' => [
            'title' => 'Inicie o seu teste gratuito',
            'description' => 'Registe-se com email e palavra-passe para aceder a uma conta de teste Wolforix apenas demo, com a mesma lógica de execução apresentada e a mesma visibilidade de regras do ambiente principal de challenge.',
            'what_you_get_title' => 'Incluído de imediato',
            'take_profit_line' => 'Objetivo de take profit: :percent%',
            'minimum_days_line' => 'Dias mínimos de trading: :days',
            'restrictions_line' => 'Apenas demo. Sem levantamentos. Não conta como challenge.',
            'email' => 'Email',
            'password' => 'Palavra-passe',
            'password_placeholder' => 'Mínimo 8 caracteres',
            'submit' => 'Criar conta de teste gratuito',
            'recover_password' => 'Recuperar palavra-passe',
            'success' => 'A sua conta de teste gratuito está pronta.',
            'existing_account_error' => 'Este email já tem uma conta Wolforix. Introduza aqui a palavra-passe correta para continuar no Teste Gratuito.',
        ],
        'setup' => [
            'title' => 'Configure a sua conta trial',
            'description' => 'Abra a sua conta demo MT5 da IC Markets, instale o conector MT5 da Wolforix e introduza os dados do conector dentro do MetaTrader 5.',
            'process_label' => 'Processo do trial',
            'steps' => [
                [
                    'title' => 'Passo 1: Crie a sua conta gratuita',
                    'body' => 'Registe-se com email e palavra-passe para iniciar o seu teste gratuito.',
                ],
                [
                    'title' => 'Passo 2: Abra a sua conta demo',
                    'body' => 'Abra a sua conta demo MT5 com a IC Markets. Será redirecionado para o site deles.',
                ],
                [
                    'title' => 'Passo 3: Instale o conector MT5',
                    'body' => 'Descarregue o conector MT5 da Wolforix e arraste o Expert Advisor para um gráfico dentro do MetaTrader 5.',
                ],
                [
                    'title' => 'Passo 4: Introduza os dados do conector',
                    'body' => 'Cole a Base URL, Account Reference e Secret Token do dashboard nos inputs do EA. O dashboard atualiza automaticamente após o primeiro sync.',
                ],
            ],
            'step_two_label' => 'Passo 2',
            'open_demo_title' => 'Abra a sua conta demo',
            'open_demo_copy' => 'Clique no botão abaixo para abrir a sua conta demo MT5 com a IC Markets. Será redirecionado para o site deles.',
            'important_items' => [
                'Use o mesmo email que utilizou para se registar.',
                'Selecione MT5 como plataforma.',
                'Guarde os seus dados de login.',
            ],
            'open_demo_button' => 'Abrir conta demo',
            'step_three_label' => 'Passo 3',
            'continue_button' => 'Ir para o dashboard trial',
            'continue_success' => 'Os dados do conector estão prontos. Instale o EA no MT5 e o dashboard será atualizado automaticamente após o primeiro sync.',
            'help_title' => 'Precisa de ajuda?',
            'help_copy' => 'Se tiver algum problema ao criar a sua conta demo, contacte a nossa equipa de suporte em',
        ],
        'dashboard' => [
            'title' => 'Dashboard de teste',
            'description' => 'Monitorize o estado atual da sua conta demo gratuita antes de avançar para uma avaliação paga.',
            'banner_title' => 'Esta é uma conta de teste.',
            'banner_copy' => 'A conta usa apenas condições demo e está separada de challenges pagas, payouts e elegibilidade para contas funded.',
            'passed_title' => 'Concluiu o modelo de teste gratuito.',
            'passed_copy' => 'Bom trabalho. Atingiu o objetivo do teste dentro das regras apresentadas. O passo seguinte é passar para uma Simulation Account para continuar sob um modelo de avaliação estruturado.',
            'passed_button' => 'Ver planos simulation',
            'ended_title' => 'O seu teste terminou.',
            'ended_copy' => 'Esta conta demo já não está ativa porque as regras de teste apresentadas foram violadas. Inicie um novo teste para continuar a praticar com a mesma lógica de regras.',
            'retry_button' => 'Repetir teste',
            'restrictions_title' => 'Restrições do teste',
            'restrictions' => [
                'Sem levantamentos',
                'Não conta como challenge',
                'Apenas ambiente demo',
            ],
            'rules_title' => 'Lógica de regras apresentada',
            'rule_labels' => [
                'starting_balance' => 'Saldo inicial',
                'daily_limit' => 'Limite de drawdown diário',
                'take_profit' => 'Take profit',
                'max_limit' => 'Limite máximo de drawdown',
                'minimum_days' => 'Dias mínimos de trading',
                'status' => 'Estado atual',
            ],
            'metrics' => [
                'balance' => 'Saldo',
                'equity' => 'Equity',
                'daily_drawdown' => 'Drawdown diário',
                'max_drawdown' => 'Drawdown máximo',
                'profit_loss' => 'Lucro / Perda',
            ],
        ],
        'milestones' => [
            'three' => 'Está a ter um bom desempenho.',
            'five' => 'Está a ter um bom desempenho.',
        ],
        'encouragement_subject' => 'Continue e melhore a sua performance.',
        'retry' => [
            'success' => 'Foi criada uma nova conta de teste.',
        ],
        'statuses' => [
            'active' => 'Ativo',
            'passed' => 'Aprovado',
            'ended' => 'Terminado',
        ],
    ],
    'checkout' => [
        'meta_title' => 'Checkout',
        'eyebrow' => 'Checkout seguro',
        'title' => 'Passe da seleção do plano para o verdadeiro fluxo de pagamento.',
        'description' => 'A seleção da challenge permanece na homepage, enquanto os dados de faturação, a escolha do fornecedor, a criação da encomenda e o pagamento baseado em redirecionamento são tratados numa página checkout dedicada.',
        'page_title' => 'Conclua a sua encomenda challenge',
        'page_description' => 'A Wolforix cria primeiro um registo real da encomenda e depois inicia o checkout Stripe ou PayPal usando preços do lado do servidor e o mesmo fluxo protegido de fulfillment.',
        'secure_badge' => 'Preços do lado do servidor ativos',
        'order_summary' => 'Resumo da encomenda',
        'supporting_title' => 'O que acontece a seguir',
        'supporting_copy' => 'A challenge selecionada é primeiro convertida numa encomenda interna e, depois, o fornecedor escolhido trata o pagamento externamente. O estado da encomenda, novas tentativas e futuras adições de gateways usarão todos o mesmo fluxo interno de compra.',
        'kyc_notice' => 'Os dados de faturação são guardados com a encomenda e preparados para futuro registo, revisão de compliance e verificações de conta relacionadas com payout.',
        'helper_points' => [
            'O preço do lado do servidor é a fonte de verdade, por isso os montantes do lado do cliente nunca são considerados fiáveis durante o checkout.',
            'As encomendas permanecem disponíveis para nova tentativa se o pagamento for cancelado ou falhar.',
            'O texto de consentimento indica explicitamente que a challenge é uma avaliação de trading simulado.',
        ],
        'current_selection' => 'Seleção atual',
        'redirect_note' => 'A homepage encaminha agora diretamente para uma página checkout dedicada, onde dados de faturação, escolha do fornecedor de pagamento e checkout seguro Stripe ou PayPal são tratados com segurança no servidor.',
        'promo_code_title' => 'Código promocional',
        'promo_code_label' => 'Código promocional',
        'promo_code_placeholder' => 'Introduza o seu código promocional',
        'promo_code_badge' => 'Acesso de lançamento 20%',
        'promo_code_help' => 'Introduza um código promocional e clique em Aplicar para atualizar de imediato o total do checkout.',
        'promo_code_apply' => 'Aplicar',
        'promo_code_applied' => 'Código aplicado',
        'promo_code_applied_copy' => 'O código de lançamento foi aplicado automaticamente e a oferta de lançamento de 20% já está refletida no total acima.',
        'promo_code_feedback' => [
            'success' => 'Código aplicado com sucesso',
            'invalid' => 'Código inválido / expirado',
        ],
        'billing_title' => 'Informação de faturação',
        'payment_methods_title' => 'Escolha o seu método de pagamento',
        'payment_methods_subtitle' => 'Checkout seguro • Ativação imediata da conta',
        'client_data_title' => 'Dados do cliente / detalhes de registo',
        'full_name' => 'Nome completo',
        'email' => 'Endereço de email',
        'street_address' => 'Morada',
        'city' => 'Cidade',
        'postal_code' => 'Código postal',
        'country' => 'País',
        'select_country' => 'Selecionar país',
        'plan' => 'Plano challenge',
        'select_plan' => 'Selecionar um plano',
        'platform' => 'Plataforma',
        'platform_value' => 'MT5',
        'agreement' => 'O checkout exige aceitação dos Terms & Conditions, confirmação do seu país atual de residência e aceitação da Cancellation and Refund Policy.',
        'confirmation_title' => 'Confirmações obrigatórias',
        'confirmations' => [
            'terms_and_residency_html' => 'Declaro que li e concordo com os <a href=":terms_url" target="_blank" rel="noopener noreferrer" class="font-semibold text-white underline underline-offset-4">Terms & Conditions</a> e declaro que o país de residência indicado na encomenda acima é o meu país atual de residência.',
            'refund_policy_html' => 'Declaro que li e concordo com a <a href=":refund_url" target="_blank" rel="noopener noreferrer" class="font-semibold text-white underline underline-offset-4">Cancellation and Refund Policy</a>.',
        ],
        'submit' => 'Continuar para checkout seguro',
        'provider_available' => 'Disponível',
        'provider_coming_soon' => 'Em breve',
        'provider_recommended' => 'Recomendado',
        'back_to_plans' => 'Voltar aos planos',
        'trust_message' => 'Os seus dados estão protegidos através de práticas de segurança standard da indústria alinhadas com ISO/IEC 27001.',
        'payment_method_points' => [
            'Pagamento seguro',
            'Ativação imediata da conta',
            'Sem taxas ocultas',
        ],
        'buttons' => [
            'stripe' => 'Pague com cartão através do Stripe usando o mesmo fluxo protegido de encomenda e fulfillment.',
            'paypal' => 'Pague com PayPal usando approval redirect e captura de encomenda no servidor.',
        ],
        'providers' => [
            'stripe' => [
                'label' => 'Stripe',
                'description' => 'Checkout externo seguro com cartão e confirmação de encomenda baseada em webhook.',
                'summary' => 'Pague em segurança com cartão',
                'supporting' => 'Confirmação rápida e imediata',
                'cta' => 'Pagar com cartão',
            ],
            'paypal' => [
                'label' => 'PayPal',
                'description' => 'Fluxo seguro de aprovação PayPal com captura no servidor e o mesmo percurso de fulfillment da Wolforix.',
                'summary' => 'Pague usando a sua conta PayPal',
                'supporting' => 'Seguro e de confiança',
                'cta' => 'Pagar com PayPal',
            ],
        ],
        'success' => [
            'eyebrow' => 'Pagamento com sucesso',
            'title' => 'Encomenda challenge confirmada',
            'description' => 'O seu pagamento foi confirmado e a challenge comprada foi ligada ao registo da sua encomenda Wolforix.',
            'pending_description' => 'O checkout regressou com sucesso. Estamos a finalizar a confirmação com o fornecedor de pagamento e manteremos esta encomenda ligada até à conclusão.',
            'plan' => 'Plano comprado',
            'amount' => 'Montante pago',
            'provider' => 'Fornecedor de pagamento',
            'order_number' => 'Número da encomenda',
            'next_steps' => 'Passo seguinte: a sua challenge paga está guardada separadamente do fluxo de teste gratuito e pronta para ativação e futura ligação ao dashboard.',
            'open_dashboard' => 'Abrir dashboard',
            'back_home' => 'Voltar ao início',
        ],
        'cancel' => [
            'eyebrow' => 'Checkout cancelado',
            'title' => 'A sua encomenda continua guardada',
            'description' => 'O pagamento foi cancelado antes da conclusão. O registo da encomenda continua disponível para que possa tentar novamente sem perder a challenge selecionada nem os dados de faturação.',
            'order_number' => 'Número da encomenda',
            'plan' => 'Plano selecionado',
            'amount' => 'Montante em dívida',
            'retry' => 'Tentar pagamento novamente',
            'back_to_plans' => 'Voltar aos planos',
        ],
        'errors' => [
            'provider_unavailable' => 'O fornecedor de pagamento selecionado não conseguiu iniciar uma sessão de checkout. Verifique as credenciais do fornecedor e tente novamente.',
        ],
        'validation' => [
            'accept_terms_and_residency' => 'Tem de aceitar os Terms & Conditions e confirmar o seu país atual de residência antes de continuar.',
            'accept_refund_policy' => 'Tem de aceitar a Cancellation and Refund Policy antes de continuar.',
            'promo_code' => 'Código inválido / expirado',
        ],
    ],
    'faq' => [
        'eyebrow' => 'Perguntas frequentes',
        'title' => 'Tudo o que você precisa. Instantaneamente.',
        'description' => 'Todas as suas regras de trading, pagamentos e informações da conta — claras e acessíveis.',
        'search_label' => 'Pesquisar',
        'search_placeholder' => 'Pesquisar nas FAQ...',
        'no_results' => 'Nenhum item FAQ corresponde a essa pesquisa.',
        'sections' => [
            [
                'title' => 'Geral',
                'items' => [
                    [
                        'question' => 'O que é a Wolforix?',
                        'answer' => 'A Wolforix Ltd. é uma empresa de avaliação e formação em proprietary trading. Todas as atividades de trading decorrem num ambiente simulado para fins educativos.',
                    ],
                    [
                        'question' => 'Isto é dinheiro real ou trading simulado?',
                        'answer' => 'Todas as contas operam num ambiente de trading simulado. Nenhum capital real é atribuído aos utilizadores.',
                    ],
                    [
                        'question' => 'Quem pode participar?',
                        'answer' => 'Os utilizadores devem ter pelo menos 18 anos e cumprir todas as leis aplicáveis na sua jurisdição.',
                    ],
                ],
            ],
            [
                'title' => 'Plataforma',
                'items' => [
                    [
                        'question' => 'Que plataforma usa a Wolforix?',
                        'answer' => 'A Wolforix usa MetaTrader 5 (MT5).',
                    ],
                    [
                        'question' => 'Como inicio sessão no MT5?',
                        'answer_sections' => [
                            [
                                'title' => 'Aplicação móvel',
                                'bullets' => [
                                    '1. Descarregue o MetaTrader 5.',
                                    '2. Vá a "Manage Accounts".',
                                    '3. Toque em "+".',
                                    '4. Selecione "Login to an existing account".',
                                    '5. Pesquise: MetaQuotes-Demo.',
                                    '6. Introduza as suas credenciais.',
                                ],
                            ],
                            [
                                'title' => 'Plataforma desktop',
                                'bullets' => [
                                    '1. Abra o MT5.',
                                    '2. Ficheiro -> Iniciar sessão na conta de trading.',
                                    '3. Introduza os seus dados de login.',
                                    '4. Servidor: MetaQuotes-Demo.',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'A Wolforix não usa broker próprio.',
                                    'Usamos o servidor MetaQuotes-Demo.',
                                    'A sua conta está ligada à Wolforix.',
                                    'Toda a atividade é sincronizada com o seu dashboard.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Instrumentos negociáveis',
                'items' => [
                    [
                        'question' => 'O que posso negociar?',
                        'answer' => 'A Wolforix dá acesso a uma ampla gama de instrumentos CFD disponíveis no MT5.',
                        'answer_sections' => [
                            [
                                'title' => 'Forex',
                                'bullets' => [
                                    'EURUSD, GBPUSD, USDJPY, USDCHF, USDCAD',
                                    'AUDUSD, NZDUSD, EURJPY, GBPJPY, EURGBP e mais',
                                ],
                            ],
                            [
                                'title' => 'Índices',
                                'bullets' => [
                                    'SPX500, NDX100, US30',
                                    'GER30, UK100, FRA40',
                                    'JP225 e outros',
                                ],
                            ],
                            [
                                'title' => 'Matérias-primas',
                                'bullets' => [
                                    'XAUUSD (Ouro)',
                                    'XAGUSD (Prata)',
                                    'XPTUSD (Platina)',
                                    'UKOUSD (Brent)',
                                    'USOUSD (Crude Oil)',
                                ],
                            ],
                            [
                                'title' => 'Criptomoedas',
                                'bullets' => [
                                    'BTCUSD, ETHUSD, XRPUSD',
                                    'ADAUSD, LTCUSD, XLMUSD',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Como posso ver todos os instrumentos?',
                        'answer_sections' => [
                            [
                                'title' => 'Observação de mercado MT5',
                                'bullets' => [
                                    '1. Abra o MT5.',
                                    '2. Vá a Market Watch.',
                                    '3. Clique com o botão direito.',
                                    '4. Selecione "Show All".',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Regras de trading',
                'items' => [
                    [
                        'question' => 'O que é a regra de consistência?',
                        'answer' => 'Não mais de 40% dos lucros totais podem ser gerados num único dia de trading. Os lucros devem estar distribuídos por vários dias para serem elegíveis para payout.',
                    ],
                    [
                        'question' => 'Como é calculado o limite de lucro diário?',
                        'answer' => 'O sistema compara o lucro de hoje com o lucro total da conta. Se o lucro de hoje exceder 40%, será apresentado um aviso no dashboard e a elegibilidade para payout pode ser afetada.',
                    ],
                    [
                        'question' => 'O que são drawdowns máximos?',
                        'answer' => 'Cada conta tem limites de drawdown definidos. Ultrapassar esses limites pode resultar na desqualificação da conta do programa de avaliação.',
                    ],
                    [
                        'question' => 'Posso negociar durante notícias de alto impacto?',
                        'answer' => 'É proibido abrir ou fechar trades 5 minutos antes e 5 minutos depois de uma notícia de alto impacto. Esta restrição aplica-se a ordens de mercado e pendentes, incluindo ativações de stop-loss ou take-profit. Pode manter posições existentes durante o evento, mas não pode abrir nem fechar trades nessa janela.',
                    ],
                    [
                        'question' => 'Que horários de trading são permitidos?',
                        'answer' => 'A Wolforix permite trading durante o horário normal de mercado, dependendo do instrumento negociado.',
                        'answer_sections' => [
                            [
                                'title' => 'Regra geral',
                                'bullets' => [
                                    'O trading está disponível 24 horas por dia, 5 dias por semana, de segunda a sexta-feira, de acordo com as sessões globais.',
                                    'A disponibilidade pode variar por instrumento, incluindo Forex, índices, cripto e outros CFDs.',
                                ],
                            ],
                            [
                                'title' => 'Manutenção de posições',
                                'bullets' => [
                                    'As posições podem ser mantidas intraday ou overnight, salvo restrições específicas da conta.',
                                    'Os traders são responsáveis por gerir a exposição durante períodos de baixa liquidez.',
                                ],
                            ],
                            [
                                'title' => 'Fechos de mercado',
                                'bullets' => [
                                    'O trading não está disponível aos fins de semana.',
                                    'Alguns instrumentos podem ter pausas diárias ou janelas de manutenção.',
                                    'Em feriados, os horários de mercado podem ser reduzidos ou alterados.',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'Os traders devem conhecer os horários das sessões e as condições de liquidez.',
                                    'A Wolforix não é responsável por perdas causadas por trading em períodos ilíquidos ou voláteis.',
                                ],
                            ],
                            [
                                'title' => 'Restrições',
                                'bullets' => [
                                    'As restrições relacionadas com notícias (±5 minutos) continuam a aplicar-se.',
                                    'Todas as outras regras de trading permanecem em vigor independentemente do horário.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Que estratégias de trading são permitidas?',
                        'answer_paragraphs' => [
                            'A Wolforix permite trading discricionário, trading algorítmico e Expert Advisors (EAs), desde que as estratégias sejam legítimas, reflitam condições reais de mercado, respeitem gestão de risco sólida e não incluam práticas proibidas.',
                            'As estratégias devem ser replicáveis em condições reais de mercado e capazes de produzir resultados live consistentes.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Condições de trading',
                                'bullets' => [
                                    'Stop Loss não é obrigatório, mas o controlo de risco é fortemente recomendado.',
                                    'O trading deve refletir comportamento de execução realista e condições reais de mercado.',
                                    'As estratégias devem ser escaláveis e adequadas a capital live.',
                                ],
                            ],
                            [
                                'title' => 'Expert Advisors (EAs) e trading algorítmico',
                                'bullets' => [
                                    'Os EAs são permitidos.',
                                    'A duplicação de EAs de terceiros pode entrar em conflito com a gestão interna de risco.',
                                    'A Wolforix pode limitar ou recusar a alocação da conta nesses casos.',
                                    'A atividade da plataforma deve permanecer dentro de limites razoáveis.',
                                    'Excesso de ordens, modificações ou carga do servidor pode exigir ajustes de estratégia.',
                                ],
                            ],
                            [
                                'title' => 'Limites de servidor e execução',
                                'bullets' => [
                                    'Podem aplicar-se limites ao número máximo de ordens abertas em simultâneo.',
                                    'Podem ser aplicados limites diários de execução.',
                                    'Frequência de trading excessiva pode ativar revisão.',
                                    'A Wolforix pode pedir ajustes se a performance prejudicar a estabilidade da plataforma.',
                                ],
                            ],
                            [
                                'title' => 'Política de scalping e duração do trade',
                                'bullets' => [
                                    'Trades fechados em menos de 60 segundos são estritamente proibidos se resultarem em lucro.',
                                    'Essa atividade é considerada não replicável em condições reais e pode indicar exploração de latência ou execução irrealista.',
                                    'O scalping standard é permitido se a duração refletir exposição real ao mercado.',
                                    'A Wolforix pode excluir esses trades do cálculo de lucros ou tomar medidas adicionais se se repetirem.',
                                ],
                            ],
                            [
                                'title' => 'Práticas de trading proibidas',
                                'paragraphs' => [
                                    'Se for detetada atividade proibida, a Wolforix pode remover ou ajustar posições, recalcular a conta, reduzir alavancagem, suspender ou fechar a conta, ou terminar a cooperação com o trader.',
                                    'Se negociar com intenção genuína, vantagem clara e comportamento consistente com as regras, a Wolforix permanece alinhada com o seu sucesso.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Hedging entre contas e copy trading são permitidos?',
                        'answer' => 'Hedging entre múltiplas contas e copy trading não autorizado são estritamente proibidos na Wolforix.',
                        'answer_sections' => [
                            [
                                'title' => 'O que é Hedging?',
                                'paragraphs' => [
                                    'Hedging significa assumir posições opostas no mesmo instrumento ou em instrumentos correlacionados entre várias contas para reduzir artificialmente o risco.',
                                    'Este comportamento garante que uma conta lucre independentemente da direção do mercado e é considerado manipulação do sistema, não trading genuíno.',
                                ],
                            ],
                            [
                                'title' => 'O que é Copy Trading?',
                                'paragraphs' => [
                                    'Copy trading significa replicar trades entre várias contas, manualmente, por software, sinais ou serviços terceiros.',
                                ],
                                'bullets' => [
                                    'Copiar trades entre as suas próprias contas.',
                                    'Copiar trades entre utilizadores diferentes.',
                                    'Usar grupos de sinais, bots ou automação para espelhar trades.',
                                    'Trading coordenado concebido para contornar regras.',
                                ],
                            ],
                            [
                                'title' => 'Exemplos de atividade proibida',
                                'bullets' => [
                                    'Abrir posições long e short no mesmo instrumento em contas diferentes.',
                                    'Hedging entre contas do mesmo utilizador.',
                                    'Hedging entre utilizadores coordenados.',
                                    'Hedging entre diferentes firms ou plataformas.',
                                    'Negociar instrumentos correlacionados em direções opostas entre contas.',
                                    'Copiar ou espelhar trades entre contas, manual ou automaticamente.',
                                    'Usar software trade copier ou serviços de sinais para replicar trades.',
                                ],
                            ],
                            [
                                'title' => 'Exemplos',
                                'bullets' => [
                                    'Long EURUSD numa conta e short EURUSD noutra.',
                                    'Long SPX500 numa conta e short NDX100 noutra.',
                                    'Executar trades idênticos em várias contas ao mesmo tempo.',
                                    'Usar um bot ou fornecedor de sinais para replicar trades entre contas.',
                                ],
                            ],
                            [
                                'title' => 'O que é permitido?',
                                'bullets' => [
                                    'Decisões de trading independentes por conta.',
                                    'Uso de estratégias pessoais não partilhadas entre várias contas.',
                                    'Gestão de risco adequada dentro de uma única conta.',
                                ],
                            ],
                            [
                                'title' => 'Clarificação importante',
                                'bullets' => [
                                    'Abrir posições opostas dentro da mesma conta pode ser tecnicamente possível no MT5, mas estratégias concebidas para contornar regras de risco não são permitidas.',
                                    'Automação é permitida apenas se refletir lógica de trading independente, não replicação de trades.',
                                    'Todo o trading deve refletir decisão real e independente e exposição real ao mercado.',
                                ],
                            ],
                            [
                                'title' => 'Deteção e monitorização',
                                'bullets' => [
                                    'A Wolforix usa sistemas internos para detetar padrões de hedging.',
                                    'A Wolforix monitoriza sincronização de trades.',
                                    'A Wolforix monitoriza comportamento de execução idêntico entre contas.',
                                    'A Wolforix monitoriza atividade de copy trading.',
                                ],
                            ],
                            [
                                'title' => 'Consequências',
                                'bullets' => [
                                    'A Wolforix pode remover ou ajustar trades.',
                                    'A Wolforix pode recalcular o saldo da conta.',
                                    'A Wolforix pode desqualificar a conta.',
                                    'A Wolforix pode restringir ou banir permanentemente o utilizador.',
                                ],
                            ],
                            [
                                'title' => 'Porque é proibido?',
                                'bullets' => [
                                    'Hedging e copy trading distorcem a verdadeira habilidade de trading.',
                                    'Hedging e copy trading eliminam exposição real ao risco.',
                                    'Hedging e copy trading comprometem o processo de avaliação.',
                                    'Hedging e copy trading ameaçam a integridade da plataforma.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'High-frequency trading (HFT) é permitido?',
                        'answer' => 'High-frequency trading (HFT) é estritamente proibido na Wolforix.',
                        'answer_sections' => [
                            [
                                'title' => 'O que é High-Frequency Trading?',
                                'paragraphs' => [
                                    'High-frequency trading (HFT) refere-se a estratégias automatizadas que executam muitas operações em intervalos extremamente curtos, muitas vezes medidos em segundos ou milissegundos.',
                                    'Estas estratégias são geralmente concebidas para explorar pequenas ineficiências de preço usando velocidade e alto volume de ordens.',
                                ],
                            ],
                            [
                                'title' => 'O que é considerado HFT?',
                                'bullets' => [
                                    'Executar um alto volume de trades em períodos muito curtos.',
                                    'Colocação e cancelamento rápido de ordens.',
                                    'Modificações excessivas de ordens.',
                                    'Padrões de execução algorítmica ultra-rápidos.',
                                    'Comportamento de trading que coloca carga anormal na plataforma.',
                                ],
                            ],
                            [
                                'title' => 'Clarificação importante',
                                'bullets' => [
                                    'Trading algorítmico e EAs são permitidos.',
                                    'As estratégias devem operar com frequência normal e comportamento de execução realista.',
                                    'Qualquer sistema concebido principalmente para exploração de velocidade em vez de análise de mercado não é permitido.',
                                ],
                            ],
                            [
                                'title' => 'Porque é proibido HFT?',
                                'paragraphs' => [
                                    'A Wolforix foi concebida para avaliar habilidade e consistência, não exploração de sistemas baseada em velocidade.',
                                ],
                                'bullets' => [
                                    'HFT pode degradar a performance da plataforma.',
                                    'HFT pode criar instabilidade de execução.',
                                    'HFT pode afetar a consistência de preços.',
                                    'HFT pode impactar o ambiente de trading de outros utilizadores.',
                                ],
                            ],
                            [
                                'title' => 'Deteção e monitorização',
                                'bullets' => [
                                    'A Wolforix monitoriza frequência de trades.',
                                    'A Wolforix monitoriza volume de ordens.',
                                    'A Wolforix monitoriza padrões de execução.',
                                    'A Wolforix monitoriza impacto na carga do servidor.',
                                ],
                            ],
                            [
                                'title' => 'Consequências',
                                'bullets' => [
                                    'Pode ser emitido um aviso.',
                                    'Lucros gerados por HFT podem ser removidos.',
                                    'A conta pode ser restringida ou fechada.',
                                    'Violações repetidas podem resultar em ban permanente.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Duration abuse, grid trading e estratégias martingale são permitidos?',
                        'answer' => 'A Wolforix proíbe estritamente estratégias que exploram estruturas de risco ou criam perfis de performance irrealistas, incluindo duration abuse, grid trading e sistemas martingale.',
                        'answer_sections' => [
                            [
                                'title' => 'Abuso de duração',
                                'paragraphs' => [
                                    'Duration abuse refere-se a abrir e fechar trades sistematicamente de forma a contornar a exposição de risco prevista ou regras de trading, sem participação real no mercado.',
                                    'Todos os trades devem refletir exposição e intenção reais de mercado, não manipulação de regras.',
                                ],
                                'bullets' => [
                                    'Abrir e fechar repetidamente trades perto do limite mínimo de duração, por exemplo pouco acima de 60 segundos.',
                                    'Executar trades sem intenção real de mercado, apenas para cumprir requisitos de regras.',
                                    'Timing artificial de trades concebido para contornar restrições.',
                                ],
                            ],
                            [
                                'title' => 'Trading em grelha',
                                'paragraphs' => [
                                    'Grid trading envolve colocar várias ordens pendentes ou ativas em intervalos fixos de preço, muitas vezes sem lógica clara de stop-loss.',
                                ],
                                'bullets' => [
                                    'Sistemas grid sem controlo adequado de risco não são permitidos.',
                                    'Order stacking de alta densidade não é permitido.',
                                    'Estratégias baseadas em oscilação de preço sem risco definido não são permitidas.',
                                ],
                            ],
                            [
                                'title' => 'Estratégias Martingale',
                                'paragraphs' => [
                                    'Estratégias martingale aumentam o tamanho da posição após perdas para recuperar perdas anteriores com um único trade vencedor.',
                                ],
                                'bullets' => [
                                    'Duplicar ou aumentar lotes após perdas não é permitido.',
                                    'Position sizing de recuperação sem limites de risco não é permitido.',
                                    'Estratégias que criam exposição exponencial ao risco não são permitidas.',
                                ],
                            ],
                            [
                                'title' => 'O que é permitido?',
                                'bullets' => [
                                    'Estratégias estruturadas com risco definido por trade.',
                                    'Position sizing lógico.',
                                    'Exposição consistente e controlada.',
                                    'Uso de stop-loss e gestão de risco adequada.',
                                ],
                            ],
                            [
                                'title' => 'Deteção e monitorização',
                                'bullets' => [
                                    'A Wolforix monitoriza padrões de duração dos trades.',
                                    'A Wolforix monitoriza comportamento de position sizing.',
                                    'A Wolforix monitoriza distribuição de ordens.',
                                    'A Wolforix monitoriza padrões de escalada de risco.',
                                ],
                            ],
                            [
                                'title' => 'Consequências',
                                'bullets' => [
                                    'A Wolforix pode remover ou ajustar trades.',
                                    'A Wolforix pode recalcular o saldo da conta.',
                                    'A Wolforix pode restringir atividade de trading.',
                                    'A Wolforix pode suspender ou fechar a conta.',
                                ],
                            ],
                            [
                                'title' => 'Porque é proibido?',
                                'bullets' => [
                                    'Estas estratégias distorcem a performance real de trading.',
                                    'Estas estratégias eliminam exposição adequada ao risco.',
                                    'Estas estratégias criam curvas de equity instáveis.',
                                    'Estas estratégias não são sustentáveis em condições reais de mercado.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Levantamentos',
                'items' => [
                    [
                        'question' => 'Com que frequência são processados os payouts?',
                        'answer_paragraphs' => [
                            'As comissões são pagas mediante pedido e estão sujeitas a revisão e aprovação pela Wolforix Partner Success Team. O primeiro payout torna-se elegível após 21 dias, com payouts seguintes disponíveis em ciclos recorrentes de 14 dias.',
                            'Assim que o período de ciclo obrigatório for concluído, os payouts são processados em até 24 horas.',
                            'Para pedir um payout, envie um email para support@wolforix.com assim que o limite mínimo de levantamento de $100 for atingido.',
                        ],
                    ],
                    [
                        'question' => 'Que métodos de payout estão disponíveis?',
                        'answer' => 'A Wolforix suporta métodos de payout seguros e eficientes para levantamentos aprovados.',
                        'answer_sections' => [
                            [
                                'title' => 'Opções disponíveis',
                                'bullets' => [
                                    'Transferência bancária (via infraestrutura Stripe, dependendo da região)',
                                    'PayPal',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'Os métodos de payout podem variar conforme a sua localização.',
                                    'Todos os levantamentos estão sujeitos a revisão da conta e verificações de compliance.',
                                    'Os tempos de processamento podem variar por fornecedor e região.',
                                ],
                            ],
                            [
                                'title' => 'Tempo de processamento',
                                'bullets' => [
                                    'Os pedidos são normalmente revistos em 1–3 dias úteis.',
                                    'Após aprovação, os fundos são processados pouco depois.',
                                ],
                            ],
                            [
                                'title' => 'Notas adicionais',
                                'bullets' => [
                                    'O método de payout deve corresponder à identidade do titular da conta.',
                                    'A Wolforix reserva-se o direito de solicitar verificação antes de processar levantamentos.',
                                    'As taxas podem variar conforme o método selecionado.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Como é calculado o meu payout?',
                        'answer' => 'Os payouts dependem do profit split atribuído ao modelo e tamanho da conta, calendário específico, cumprimento da regra de consistência quando aplicável e limites internos. O montante elegível pode ser inferior ao lucro total se os limites diários forem ultrapassados.',
                    ],
                    [
                        'question' => 'As contas funded escalam?',
                        'answer' => 'As contas funded 2-Step podem escalar +25% de capital a cada 3 meses se forem lucrativas. As contas funded 1-Step não incluem atualmente essa regra.',
                    ],
                    [
                        'question' => 'Como funciona o plano de scaling da conta?',
                        'answer_paragraphs' => [
                            'A Wolforix aplica um sistema dinâmico de scaling a contas funded baseado na performance de trading.',
                            'À medida que a conta cresce, o tamanho máximo permitido da posição aumenta progressivamente, permitindo maior exposição quando a consistência é demonstrada.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Como funciona o sistema',
                                'bullets' => [
                                    'O scaling baseia-se nos seus lucros simulados.',
                                    'À medida que os lucros aumentam, a capacidade de trading aumenta.',
                                    'Se a performance diminuir, os limites podem ser ajustados.',
                                ],
                            ],
                            [
                                'title' => 'Frequência de atualização',
                                'bullets' => [
                                    'As atualizações de scaling são aplicadas no fim de cada dia de trading.',
                                    'As alterações não são aplicadas em tempo real durante o dia.',
                                ],
                            ],
                            [
                                'title' => 'Estrutura de scaling',
                                'paragraphs' => [
                                    'O modelo recompensa consistência, gestão de risco e performance sustentável.',
                                    'A sua exposição máxima evolui à medida que a conta demonstra estabilidade ao longo do tempo.',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'O scaling não é linear e pode variar conforme a performance da conta.',
                                    'Sobreexposição sem performance suficiente pode resultar em restrições.',
                                    'O sistema prioriza consistência de longo prazo em vez de ganhos de curto prazo.',
                                ],
                            ],
                            [
                                'title' => 'Contornar o sistema de scaling',
                                'paragraphs' => [
                                    'Qualquer tentativa de contornar ou manipular o sistema de scaling é estritamente proibida.',
                                ],
                                'bullets' => [
                                    'Dividir trades para exceder limites.',
                                    'Usar múltiplas entradas para aumentar artificialmente a exposição.',
                                    'Explorar comportamento de execução ou mecânicas da plataforma.',
                                ],
                            ],
                            [
                                'title' => 'Consequências',
                                'bullets' => [
                                    'A Wolforix pode ajustar ou remover trades.',
                                    'A Wolforix pode recalcular o saldo da conta.',
                                    'A Wolforix pode restringir condições de trading.',
                                    'A Wolforix pode suspender ou fechar a conta.',
                                ],
                            ],
                            [
                                'title' => 'Resumo',
                                'bullets' => [
                                    'Performance aumenta, capacidade de trading aumenta.',
                                    'Performance diminui, limites podem ajustar.',
                                    'Scaling atualizado diariamente.',
                                    'Consistência é obrigatória.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Faturação',
                'items' => [
                    [
                        'question' => 'Que métodos de pagamento são aceites?',
                        'answer' => 'A Wolforix aceita pagamentos online seguros através de fornecedores de confiança. Todos os pagamentos são processados com segurança via Stripe e PayPal, garantindo transações rápidas e fiáveis.',
                        'answer_sections' => [
                            [
                                'title' => 'Métodos disponíveis',
                                'bullets' => [
                                    'Cartões de crédito e débito (Visa, Mastercard, American Express)',
                                    'PayPal',
                                ],
                            ],
                            [
                                'title' => 'Importante',
                                'bullets' => [
                                    'Os pagamentos são confirmados instantaneamente após aprovação.',
                                    'Todas as transações são encriptadas e processadas com segurança.',
                                    'A disponibilidade de certos métodos pode variar conforme a localização.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Conta / Dashboard',
                'items' => [
                    [
                        'question' => 'Como vejo o meu lucro e saldo?',
                        'answer' => 'O dashboard mostra lucro total, lucro diário e saldo da conta em tempo real.',
                    ],
                    [
                        'question' => 'O que acontece se me aproximar do limite de consistência?',
                        'answer' => 'Será apresentado um aviso no dashboard: “⚠ Está a aproximar-se do limite da regra de consistência. Os lucros devem ser distribuídos por vários dias de trading para serem elegíveis para payout.” Também pode ser enviado um alerta automático por email se se aproximar do limiar crítico.',
                    ],
                    [
                        'question' => 'Como posso pedir um payout?',
                        'answer' => 'O botão de pedido de payout está no dashboard. As contas funded 1-Step devem cumprir a regra de consistência obrigatória antes de o lucro se tornar elegível.',
                    ],
                    [
                        'question' => 'Passei com sucesso, o que devo fazer agora?',
                        'answer_paragraphs' => [
                            'O que acontece a seguir depende de estar a participar no Wolforix Step-1 Instant ou no Wolforix Step-Pro, pois cada programa segue uma estrutura ligeiramente diferente. No entanto, ambos incluem uma fase de verificação.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Wolforix Step-1 Instant',
                                'paragraphs' => [
                                    'Depois de cumprir com sucesso todos os Objetivos de Trading na sua conta Step-1 Instant, receberá uma notificação no dashboard a confirmar que os objetivos foram cumpridos e que a sua conta está em revisão.',
                                    'O processo de revisão demora normalmente 1 a 2 dias úteis. Assim que os seus resultados forem verificados, receberá acesso à fase de verificação.',
                                ],
                            ],
                            [
                                'title' => 'Fase de verificação',
                                'paragraphs' => [
                                    'Depois de cumprir todos os Objetivos de Trading na fase de verificação, a sua conta será novamente revista.',
                                    'Assim que os seus resultados forem verificados, são necessários os seguintes passos:',
                                ],
                                'bullets' => [
                                    'Concluir a verificação de identidade (KYC/KYB) na sua área de cliente',
                                    'Assinar o Acordo de Conta Wolforix',
                                    'Assim que todos os passos forem concluídos com sucesso, a sua conta Wolforix funded será emitida.',
                                ],
                            ],
                            [
                                'title' => 'Wolforix Step-Pro',
                                'paragraphs' => [
                                    'Fase 1',
                                    'Depois de cumprir todos os Objetivos de Trading na Fase 1, receberá uma notificação a confirmar o seu sucesso. Neste momento, não é necessário mais trading e a sua conta será revista.',
                                    'O processo de revisão demora normalmente 1 a 2 dias úteis. Assim que for verificada, receberá acesso à fase seguinte.',
                                ],
                            ],
                            [
                                'title' => 'Fase de verificação',
                                'paragraphs' => [
                                    'Depois de concluir com sucesso todos os Objetivos de Trading na fase de verificação, a sua conta será encaminhada para revisão final.',
                                    'Assim que os seus resultados forem verificados, são necessários os seguintes passos:',
                                ],
                                'bullets' => [
                                    'Concluir a verificação de identidade (KYC/KYB) na sua área de cliente',
                                    'Assinar o Acordo de Conta Wolforix',
                                    'Assim que todos os passos forem concluídos com sucesso, a sua conta Wolforix funded será emitida.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Conta Trial',
                'items' => [
                    [
                        'question' => 'Como funciona?',
                        'answer_paragraphs' => [
                            'Passo 1: Crie a sua conta gratuita Wolforix com email e palavra-passe.',
                            'Passo 2: Abra a sua conta demo MT5 com a IC Markets usando o botão no ecrã de configuração do trial. Será redirecionado para https://www.icmarkets.eu/de/open-trading-account/demo.',
                            'Importante: use o mesmo email que utilizou na Wolforix, selecione MT5 como plataforma e guarde os seus dados de login.',
                            'Passo 3: Descarregue o conector MT5 da Wolforix no dashboard trial e instale o EA no MetaTrader 5.',
                            'Passo 4: Arraste o conector para um gráfico, introduza a Base URL, Account Reference e Secret Token do dashboard, e clique em OK.',
                            'Passo 5: A sua conta liga automaticamente quando o EA envia a primeira atualização.',
                            'Se tiver algum problema ao criar a sua conta demo, contacte support@wolforix.com.',
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Suporte / Contacto',
                'items' => [
                    [
                        'question' => 'Como contacto o suporte?',
                        'answer' => 'Todos os pedidos de suporte são tratados por email ou sistema de tickets dentro do dashboard.',
                    ],
                    [
                        'question' => 'Têm número de telefone?',
                        'answer' => 'Não, a Wolforix Ltd. não fornece suporte telefónico. Todas as comunicações são documentadas por email ou ticket por razões de compliance e segurança.',
                    ],
                ],
            ],
            [
                'title' => 'Legal / Conformidade',
                'items' => [
                    [
                        'question' => 'Preciso de verificar a minha identidade?',
                        'answer' => 'Sim, a verificação de identidade (KYC) pode ser exigida antes de processar payouts para cumprir regras anti-branqueamento de capitais.',
                    ],
                    [
                        'question' => 'Que países estão restritos?',
                        'answer_paragraphs' => [
                            'A Wolforix não fornece serviços a pessoas ou entidades residentes em países sujeitos a sanções internacionais ou restrições regulatórias.',
                            'Os países atualmente restritos são Irão, Coreia do Norte, Síria, Sudão, Cuba, Rússia e Venezuela.',
                        ],
                        'answer_sections' => [
                            [
                                'title' => 'Elegibilidade',
                                'bullets' => [
                                    'Ao usar os serviços Wolforix, confirma que não reside numa jurisdição restrita.',
                                    'Confirma que não está sujeito a quaisquer sanções aplicáveis.',
                                    'Confirma que está legalmente autorizado a participar segundo as leis do seu país.',
                                ],
                            ],
                            [
                                'title' => 'Conformidade',
                                'paragraphs' => [
                                    'A Wolforix cumpre regulamentos internacionais, incluindo políticas anti-branqueamento de capitais (AML) e combate ao financiamento do terrorismo (CTF).',
                                    'O acesso aos serviços pode ser restringido por país de residência, nacionalidade, limitações de fornecedores de pagamento como Stripe ou PayPal, ou avaliação interna de risco.',
                                ],
                            ],
                            [
                                'title' => 'Aviso importante',
                                'bullets' => [
                                    'A Wolforix reserva-se o direito de restringir ou negar acesso a qualquer utilizador a seu exclusivo critério.',
                                    'A Wolforix pode solicitar verificação de identidade (KYC) a qualquer momento.',
                                    'A Wolforix pode atualizar a lista de jurisdições restritas sem aviso prévio.',
                                ],
                            ],
                        ],
                    ],
                    [
                        'question' => 'Existem regras contra fraude ou abuso?',
                        'answer' => 'Qualquer tentativa de manipular o sistema, explorar falhas ou cometer atividade fraudulenta resultará no encerramento da conta e poderá ser reportada às autoridades.',
                    ],
                ],
            ],
        ],
    ],
    'search' => [
        'title' => 'Pesquisar no site',
        'description' => 'Pesquise planos, políticas, páginas de suporte e respostas FAQ.',
        'placeholder' => 'Pesquisar páginas, FAQ e políticas...',
        'empty' => 'Nenhum conteúdo correspondente encontrado.',
        'close' => 'Fechar pesquisa',
        'featured_title' => 'Destinos populares',
        'results_one' => 'resultado',
        'results_many' => 'resultados',
        'section_labels' => [
            'page' => 'Página',
            'faq' => 'FAQ',
            'policy' => 'Política',
            'support' => 'Suporte',
        ],
    ],
    'contact' => [
        'eyebrow' => 'Contacte a Wolforix',
        'title' => 'Suporte, live chat e ajuda por voz FAQ num só lugar.',
        'description' => 'Fale com a equipa de suporte por email, inicie um live chat apoiado por email ou use o assistente de voz FAQ para obter respostas rápidas antes ou depois de iniciar uma challenge.',
        'primary_action' => 'Email para suporte',
        'secondary_action' => 'Abrir FAQ',
        'email_title' => 'Suporte por email',
        'email_copy' => 'Envie questões de billing, conta ou regras diretamente para a equipa de suporte da Wolforix.',
        'email_response' => 'As respostas costumam chegar durante o horário comercial em :hours.',
        'email_button' => 'Enviar email',
        'live_chat_title' => 'Live chat',
        'live_chat_copy' => 'Use o launcher de live chat abaixo. Se a equipa estiver offline, a sua mensagem segue para a fila de suporte por email para que nada se perca.',
        'live_chat_note' => 'As respostas podem demorar mais fora do horário comercial.',
        'live_chat_label' => 'Em que precisa de ajuda?',
        'live_chat_placeholder' => 'Escreva a sua pergunta e vamos prepará-la para a caixa de entrada do suporte...',
        'live_chat_status' => 'A sua mensagem de live chat será aberta no compositor de email do suporte.',
        'live_chat_empty' => 'Escreva uma mensagem antes de iniciar o live chat.',
        'live_chat_button' => 'Iniciar live chat',
        'live_chat_subject' => 'Pedido de suporte por live chat',
        'voice_title' => 'Fale com Wolfi',
        'voice_copy' => 'O seu assistente de trading está pronto para ajudar.',
        'voice_online' => 'Online',
        'voice_state_idle' => 'Toque para falar',
        'voice_state_listening' => 'Ouvindo',
        'voice_state_speaking' => 'Wolfi está falando',
        'voice_state_rendering' => 'Wolfi está pensando',
        'voice_ready' => 'O Wolfi está pronto.',
        'voice_listening' => 'A ouvir... toque novamente para parar.',
        'voice_unsupported' => 'A entrada por voz não é suportada neste browser. Ainda pode escrever a sua pergunta.',
        'voice_no_match' => 'Não tenho total confiança de que entendi bem. Tente reformular ou abra o FAQ completo.',
        'voice_intro_title' => 'Fale com Wolfi',
        'voice_intro_message' => 'O seu assistente de trading está pronto para ajudar.',
        'voice_intro_blocked' => 'O Wolfi abriu, mas o browser bloqueou a reprodução imediata do áudio. Toque em Reproduzir resposta para ouvir.',
        'voice_clarify_title' => 'Quero garantir que respondo à pergunta certa.',
        'voice_clarify_intro' => 'Posso estar a confundir a sua pergunta. Em vez disso, experimente uma destas: :suggestions',
        'voice_support_fallback' => 'Posso ajudar com regras, payouts, planos e questões gerais da plataforma. Para billing ou ajuda específica da conta, contacte :email.',
        'voice_trial_fallback' => 'Registe-se primeiro para a Conta Trial na Wolforix. A Wolforix envia um email com instruções, conclui o registo demo da IC Markets, a IC Markets envia as credenciais demo e depois pode iniciar o teste gratuito.',
        'voice_plan_fallback' => 'A Wolforix oferece atualmente os modelos 1-Step Instant e 2-Step Pro nos tamanhos 5K, 10K, 25K, 50K e 100K. Escolha o modelo que combina com a sua tolerância ao risco e depois use Obter plano para continuar.',
        'voice_payout_fallback' => 'As comissões são pagas mediante pedido e estão sujeitas a revisão e aprovação pela Wolforix Partner Success Team. O primeiro payout torna-se elegível após :first_payout_days dias, com payouts seguintes disponíveis em ciclos recorrentes de :payout_cycle_days dias. Assim que o ciclo obrigatório for concluído, os payouts são processados em até 24 horas. Para pedir um payout, envie um email para support@wolforix.com assim que o limite mínimo de levantamento de $100 for atingido.',
        'voice_max_drawdown_fallback' => 'Se atingir o limite máximo de drawdown, o challenge falha e a conta pode ser bloqueada ou desativada. As posições abertas podem ter de ser fechadas, e o dashboard mostrará o estado de falha e o motivo.',
        'voice_rules_fallback' => 'O 1-Step usa meta de 10%, perda diária máxima de 4% e perda total máxima de 8%. O 2-Step usa metas de 10% e depois 5%, perda diária máxima de 5%, perda total máxima de 10% e mínimo de 3 dias de trading por fase.',
        'voice_checkout_fallback' => 'Clique em Obter plano na challenge selecionada e depois entre ou crie a sua conta antes do checkout. Após a autenticação, a Wolforix devolve-o ao plano correto.',
        'voice_discount_fallback' => 'Use o popup de lançamento e clique em Obter desconto para ativar a oferta de 20% na sessão atual. Se a ignorar, o preço normal continua visível e o desconto não é aplicado.',
        'voice_general_fallback' => 'Sou melhor com planos Wolforix, acesso demo gratuito, payouts, regras, login, checkout e orientação MT5. Se a sua pergunta for mais ampla, ainda assim posso ajudar brevemente quando possível ou encaminhá-lo para a página certa da Wolforix.',
        'voice_input_label' => 'Faça uma pergunta',
        'voice_input_placeholder' => 'Exemplo: Quando posso pedir o meu primeiro payout?',
        'voice_suggestions_label' => 'Prompts sugeridos',
        'voice_suggestions_copy' => 'Comece com uma pergunta rápida e o Wolfi guiará a conversa a partir daí.',
        'voice_submit' => 'Obter resposta',
        'voice_button' => 'Falar',
        'voice_play_button' => 'Reproduzir resposta',
        'voice_stop_play_button' => 'Parar áudio',
        'voice_play_requires_answer' => 'Obtenha primeiro uma resposta e depois use Reproduzir resposta.',
        'voice_generating_audio' => 'A preparar a voz do Wolfi...',
        'voice_external_fallback' => 'A voz premium não está disponível agora. A usar a voz do browser.',
        'voice_audio_unavailable' => 'A reprodução por voz não está disponível neste momento. Tente novamente em breve.',
        'voice_speaking' => 'O Wolfi está a falar... toque em Parar áudio para silenciar de imediato.',
        'voice_stop_button' => 'Parar',
        'voice_stopped' => 'Microfone parado.',
        'voice_no_speech' => 'Não foi detetada fala. Tente novamente.',
        'voice_mic_blocked' => 'O acesso ao microfone foi bloqueado. Verifique as permissões do browser e tente de novo.',
        'voice_audio_capture' => 'Não foi encontrado nenhum microfone funcional para entrada de voz.',
        'voice_permission_checking' => 'A verificar acesso ao microfone...',
        'voice_secure_context' => 'A entrada por voz requer um contexto seguro do browser, como HTTPS ou localhost.',
        'voice_answer_title' => 'Resposta do Wolfi',
        'voice_empty' => 'Pergunte sobre payouts, regras, scaling, suporte ou elegibilidade da conta.',
        'voice_ai_notice' => 'As respostas de voz são geradas por AI.',
        'voice_open_faq' => 'Abrir FAQ completa',
    ],
    'security' => [
        'meta_title' => 'Segurança',
        'eyebrow' => 'Confiança e segurança',
        'title' => 'Práticas de segurança concebidas para proteger a plataforma, a sua conta e a integridade operacional.',
        'description' => 'A Wolforix está a construir o seu posicionamento de segurança em torno de controlos práticos de acesso, monitorização, supervisão de risco e tratamento de dados. O alinhamento ISO/IEC 27001 está em curso e nesta fase não é reivindicada qualquer certificação.',
        'badge' => 'Alinhamento ISO/IEC 27001 em curso',
        'note' => 'Esta página descreve as práticas de segurança atuais e a direção do roadmap, destinadas a reforçar a confiança enquanto o programa global continua a amadurecer.',
        'sections' => [
            [
                'title' => 'Segurança',
                'description' => 'Os controlos de conta e de plataforma são desenhados para reduzir acessos não autorizados e proteger os fluxos de autenticação.',
                'items' => [
                    'O suporte a autenticação de dois fatores está incluído nos controlos de acesso à conta.',
                    'Tráfego sensível e segredos são protegidos com encriptação e tratamento controlado.',
                    'As medidas de proteção da conta incluem acesso controlado, boas práticas de credenciais e salvaguardas de sessão.',
                ],
            ],
            [
                'title' => 'Gestão de risco',
                'description' => 'O risco operacional é gerido através de salvaguardas em camadas, monitorização e processos de revisão.',
                'items' => [
                    'Direitos de acesso e alterações operacionais são revistos através de workflows controlados.',
                    'Monitorização e alertas ajudam a identificar rapidamente atividade incomum e problemas de serviço.',
                    'Os processos de resposta são concebidos para conter incidentes, apoiar a revisão e melhorar a resiliência.',
                ],
            ],
            [
                'title' => 'Proteção de dados',
                'description' => 'O tratamento de dados é estruturado em torno de proteção, acesso limitado e práticas responsáveis de retenção.',
                'items' => [
                    'Os dados são protegidos em trânsito usando padrões modernos de encriptação.',
                    'O acesso a informação sensível é limitado a pessoal e sistemas autorizados.',
                    'As práticas de armazenamento, retenção e tratamento são concebidas para apoiar confidencialidade e integridade.',
                ],
            ],
            [
                'title' => 'Roadmap',
                'description' => 'A maturidade de segurança continua a expandir-se à medida que a plataforma e os controlos internos evoluem.',
                'items' => [
                    'O alinhamento ISO/IEC 27001 está atualmente em curso.',
                    'Políticas, documentação e cobertura de controlos continuam a ser formalizadas e revistas.',
                    'Monitorização, governação de acessos e práticas de melhoria contínua continuarão a expandir-se ao longo do tempo.',
                ],
            ],
        ],
    ],
    'legal' => [
        'eyebrow' => 'Legal Wolforix',
        'quick_links' => 'Todas as páginas legais',
        'overview_title' => 'Visão geral das políticas',
        'overview_copy' => 'Estas páginas organizam o texto aprovado pelo cliente para a Milestone 1 em ecrãs dedicados e legíveis, em vez de colocar longos blocos legais diretamente no footer.',
        'link_labels' => [
            'terms' => 'Termos e condições',
            'risk_disclosure' => 'Divulgação de risco',
            'payout_policy' => 'Política de payout',
            'refund_policy' => 'Política de reembolso',
            'privacy_policy' => 'Política de privacidade',
            'aml_kyc_policy' => 'Política AML e KYC',
            'company_information' => 'Informação da empresa',
        ],
        'pages' => [
            'terms' => [
                'title' => 'Termos e condições',
                'intro' => 'A Wolforix Ltd. opera como plataforma educativa e de avaliação em proprietary trading. Ao aceder aos nossos serviços, concorda com os seguintes termos.',
                'sections' => [
                    [
                        'title' => 'Natureza dos serviços',
                        'paragraphs' => [
                            'Todas as atividades de trading decorrem num ambiente simulado. Nenhum capital real é atribuído aos utilizadores e não são prestados serviços de investimento.',
                        ],
                    ],
                    [
                        'title' => 'Elegibilidade',
                        'paragraphs' => [
                            'Os utilizadores devem ter pelo menos 18 anos e cumprir todas as leis aplicáveis na sua jurisdição.',
                        ],
                    ],
                    [
                        'title' => 'Programa de avaliação',
                        'paragraphs' => [
                            'Os utilizadores participam numa avaliação de trading concebida para medir competências operacionais. A conclusão com sucesso não constitui emprego nem atribuição de capital.',
                        ],
                    ],
                    [
                        'title' => 'Regras de trading',
                        'paragraphs' => [
                            'Os utilizadores devem cumprir todas as regras de trading, incluindo drawdown máximo, limites de perda diária e requisitos de consistência.',
                        ],
                    ],
                    [
                        'title' => 'Instrumentos permitidos e padrões de estratégia',
                        'paragraphs' => [
                            'A Wolforix permite discretionary trading, algorithmic trading e Expert Advisors (EA), desde que a estratégia seja legítima, reflita condições reais de mercado, esteja alinhada com uma gestão de risco sólida e se mantenha adequada à alocação de capital live.',
                            'Os CFD atualmente destacados para o ambiente de avaliação incluem EUR/USD, USD/JPY e Gold (XAU/USD) como principal mercado de commodities.',
                        ],
                    ],
                    [
                        'title' => 'Regra de consistência',
                        'paragraphs' => [
                            'Para qualificar para payouts, não mais do que 40% dos lucros totais podem ser gerados num único dia de trading. Se esse limite for ultrapassado, será necessária atividade adicional de trading.',
                        ],
                    ],
                    [
                        'title' => 'Regra de news trading',
                        'paragraphs' => [
                            'É proibido abrir ou fechar operações 5 minutos antes e 5 minutos depois de um evento news de alto impacto. Esta restrição aplica-se tanto a ordens de mercado como a ordens pendentes, incluindo ativações de stop-loss ou take-profit. Pode manter posições existentes durante o evento, mas não pode abrir nem fechar trades nessa janela temporal.',
                        ],
                    ],
                    [
                        'title' => 'Regra de scalping',
                        'bullets' => [
                            'Os trades fechados em menos de 60 segundos são estritamente proibidos se resultarem em lucro.',
                            'Essa atividade é considerada não replicável em condições reais de mercado e pode indicar exploração de latência ou execução irrealista.',
                            'O scalping standard é permitido se a duração do trade refletir exposição genuína ao mercado.',
                            'A Wolforix pode excluir esses trades do cálculo de lucros ou tomar medidas adicionais se o comportamento se repetir.',
                        ],
                    ],
                    [
                        'title' => 'Práticas proibidas',
                        'paragraphs' => [
                            'Qualquer forma de abuso, arbitrage exploitation, latency exploitation ou manipulação do ambiente de trading dará origem a revisão da conta e poderá levar a restrição ou encerramento da mesma.',
                        ],
                        'bullets' => [
                            'A duplicação de EA de terceiros pode entrar em conflito com a gestão de risco interna e pode resultar em alocação limitada.',
                            'Podem aplicar-se limites ao número máximo de ordens abertas em simultâneo e à execução diária.',
                            'Excesso de ordens, modificações ou sobrecarga do servidor pode ativar uma revisão da estratégia.',
                            'A Wolforix pode remover ou ajustar posições, reequilibrar a conta, reduzir a alavancagem, suspender ou terminar a conta, ou terminar a cooperação com o trader se for detetada atividade proibida.',
                        ],
                    ],
                    [
                        'title' => 'Limitação de responsabilidade',
                        'paragraphs' => [
                            'A Wolforix Ltd. não será responsável por quaisquer perdas, danos ou incapacidade de usar a plataforma.',
                        ],
                    ],
                ],
            ],
            'risk_disclosure' => [
                'title' => 'Divulgação de risco',
                'intro' => 'O trading nos mercados financeiros envolve risco significativo. Todas as atividades nesta plataforma decorrem num ambiente simulado exclusivamente para fins educativos.',
                'sections' => [
                    [
                        'title' => 'Ambiente de trading simulado',
                        'paragraphs' => [
                            'Todas as atividades de trading disponibilizadas pela Wolforix ocorrem num ambiente simulado usando fundos virtuais. Esses fundos são fictícios, não têm valor monetário e não podem ser levantados, transferidos ou utilizados em mercados reais.',
                            'Quaisquer funded accounts apresentados fazem parte de um programa de avaliação simulado. Não representam capital real e nenhum trade é executado em mercados financeiros live.',
                        ],
                    ],
                    [
                        'title' => 'Sem serviços de investimento',
                        'paragraphs' => [
                            'A Wolforix Ltd. não presta aconselhamento financeiro, gestão de portefólio, serviços de corretagem ou custódia de fundos de clientes.',
                            'Nada neste website constitui aconselhamento financeiro, recomendação de investimento ou oferta de compra ou venda de qualquer instrumento financeiro.',
                        ],
                    ],
                    [
                        'title' => 'Performance e payouts',
                        'paragraphs' => [
                            'A performance passada não garante resultados futuros. Resultados hipotéticos têm limitações inerentes e podem diferir das condições reais de mercado devido a fatores como liquidez, slippage e atrasos de execução.',
                            'Quaisquer payouts, testemunhos ou exemplos de performance apresentados são meramente ilustrativos e não garantem resultados futuros.',
                        ],
                    ],
                    [
                        'title' => 'Jurisdições restritas e responsabilidade',
                        'paragraphs' => [
                            'Os nossos serviços não estão disponíveis em jurisdições onde a sua utilização viole leis ou regulamentos locais.',
                            'A Wolforix não será responsabilizada por quaisquer perdas diretas ou indiretas resultantes da utilização da plataforma, dos serviços ou da informação fornecida.',
                        ],
                    ],
                ],
            ],
            'payout_policy' => [
                'title' => 'Política de payout',
                'intro' => 'O primeiro payout pode ser pedido após 21 dias. Os payouts seguintes podem ser pedidos a cada 14 dias.',
                'highlight' => [
                    'title' => 'Processamento de payout',
                    'items' => [
                        'Pagamentos em até 24 horas após aprovação',
                    ],
                    'note' => 'Após aprovação, os payouts são processados em até 24 horas.',
                ],
                'sections' => [
                    [
                        'title' => 'Elegibilidade para payout',
                        'paragraphs' => [
                            'As contas funded podem pedir o primeiro payout após 21 dias. Depois disso, os payouts seguintes podem ser pedidos a cada 14 dias.',
                            'As contas funded 2-Step também podem escalar +25% de capital a cada 3 meses se forem lucrativas.',
                            'As contas funded 1-Step seguem o mesmo ritmo de payout de 14 dias, mas exigem o cumprimento obrigatório da regra de consistência antes da aprovação do payout.',
                        ],
                    ],
                    [
                        'title' => 'Requisitos de elegibilidade',
                        'bullets' => [
                            'Os dias mínimos de trading da fase ativa têm de ser cumpridos.',
                            'As contas funded 1-Step têm de cumprir a regra de consistência obrigatória.',
                            'Não podem existir violações de regras na conta.',
                        ],
                    ],
                    [
                        'title' => 'Revisão e aprovação',
                        'paragraphs' => [
                            'A Wolforix Ltd. reserva-se o direito de rever toda a atividade de trading antes de aprovar payouts.',
                        ],
                    ],
                ],
            ],
            'refund_policy' => [
                'title' => 'Política de reembolso',
                'intro' => 'Todas as compras são finais e não reembolsáveis depois de a trading challenge ter sido acedida.',
                'sections' => [
                    [
                        'title' => 'Regra geral',
                        'paragraphs' => [
                            'Todas as compras são finais e não reembolsáveis depois de a trading challenge ter sido acedida.',
                        ],
                    ],
                    [
                        'title' => 'Exceções limitadas',
                        'paragraphs' => [
                            'Os reembolsos só podem ser emitidos em casos de erros técnicos ou pagamentos duplicados.',
                        ],
                    ],
                ],
            ],
            'privacy_policy' => [
                'title' => 'Política de privacidade',
                'intro' => 'A Wolforix Ltd. recolhe e trata dados pessoais em conformidade com as leis aplicáveis de proteção de dados.',
                'sections' => [
                    [
                        'title' => 'Uso de dados',
                        'paragraphs' => [
                            'Os dados dos utilizadores são usados para gestão de conta, verificação e fins de compliance.',
                        ],
                    ],
                    [
                        'title' => 'Partilha de dados',
                        'paragraphs' => [
                            'Não vendemos nem partilhamos dados pessoais com terceiros sem consentimento.',
                        ],
                    ],
                ],
            ],
            'aml_kyc_policy' => [
                'title' => 'Política AML e KYC',
                'intro' => 'Para cumprir as regras de combate ao branqueamento de capitais, poderá ser exigido aos utilizadores que verifiquem a sua identidade antes de receber payouts.',
                'sections' => [
                    [
                        'title' => 'Requisito de verificação',
                        'paragraphs' => [
                            'Para cumprir as regras de combate ao branqueamento de capitais, poderá ser exigido aos utilizadores que verifiquem a sua identidade antes de receber payouts.',
                        ],
                    ],
                    [
                        'title' => 'Pedidos de documentação',
                        'paragraphs' => [
                            'A Wolforix Ltd. reserva-se o direito de solicitar documentação em qualquer momento.',
                        ],
                    ],
                    [
                        'title' => 'Incumprimento',
                        'paragraphs' => [
                            'O incumprimento pode resultar na suspensão da conta.',
                        ],
                    ],
                ],
            ],
            'company_information' => [
                'title' => 'Informação da empresa',
                'intro' => 'A Wolforix Ltd. é uma empresa incorporada no Reino Unido, com sede registada em Suite RA01, 195-197 Wood Street, London, E17 3NU.',
                'sections' => [
                    [
                        'title' => 'Informação da empresa',
                        'paragraphs' => [
                            'A Wolforix Ltd. é uma empresa incorporada no Reino Unido, com sede registada em Suite RA01, 195-197 Wood Street, London, E17 3NU.',
                        ],
                    ],
                    [
                        'title' => 'Natureza dos serviços',
                        'paragraphs' => [
                            'A Wolforix opera como empresa de avaliação e formação em proprietary trading. Não somos broker, instituição financeira, empresa de investimento nem custodian.',
                            'Não aceitamos depósitos, não gerimos fundos de clientes e não executamos trades em nome dos utilizadores.',
                        ],
                    ],
                    [
                        'title' => 'Aviso regulamentar',
                        'paragraphs' => [
                            'A Wolforix opera fora do âmbito das autoridades de regulação financeira, uma vez que não presta serviços de corretagem nem de investimento.',
                            'Os utilizadores são responsáveis por garantir o cumprimento das leis locais antes de usar os nossos serviços.',
                        ],
                    ],
                ],
            ],
        ],
    ],
    'footer' => [
        'disclaimer_title' => 'Ambiente simulado',
        'legal_copy' => [
            'A Wolforix Ltd. é uma empresa registada no Reino Unido (Company Number: 17111904), com sede registada em Suite RA01, 195-197 Wood Street, London, E17 3NU. A Wolforix opera como plataforma de avaliação e formação em proprietary trading.',
            'Todos os serviços prestados pela Wolforix decorrem exclusivamente num ambiente de trading simulado usando fundos virtuais. Estes fundos não têm valor real, não podem ser levantados e não representam capital real. A Wolforix não é broker, não é instituição financeira e não presta serviços de investimento, aconselhamento financeiro ou gestão de ativos.',
            'Nada nesta plataforma constitui aconselhamento de investimento ou oferta de compra ou venda de instrumentos financeiros. Os resultados obtidos em ambientes simulados não garantem resultados futuros nos mercados reais e podem diferir significativamente dos resultados de trading reais.',
            'Qualquer performance apresentada, incluindo payouts, é meramente ilustrativa e está sujeita às condições específicas do programa. Todos os payouts estão sujeitos a verificação, incluindo controlos internos de segurança, medidas antifraude e procedimentos de verificação de identidade.',
            'A Wolforix reserva-se o direito de solicitar documentação adicional, rever contas, ajustar resultados, recusar payouts, cancelar lucros ou suspender e/ou encerrar contas em caso de violação dos seus termos ou deteção de atividade irregular.',
            'Os nossos serviços não estão disponíveis em jurisdições onde a sua utilização viole leis ou regulamentos aplicáveis. É responsabilidade do utilizador garantir o cumprimento da legislação local.',
            'Poderemos partilhar informação com fornecedores terceiros estritamente necessários ao funcionamento da plataforma, incluindo serviços de pagamento, fornecedores de infraestrutura ou serviços de verificação, em conformidade com as leis aplicáveis de proteção de dados.',
            'A Wolforix não garante disponibilidade contínua ou ininterrupta dos seus serviços e não fornece garantias expressas ou implícitas. A Wolforix não será responsável por quaisquer perdas diretas, indiretas ou consequenciais resultantes da utilização da plataforma.',
            'A Wolforix reserva-se o direito de alterar estes termos e políticas a qualquer momento.',
            'Ao utilizar este website, concorda com os nossos Terms and Conditions, Privacy Policy, Payout Policy, Refund Policy e todos os documentos legais relacionados.',
        ],
        'legal_title' => 'Legal e políticas',
        'security_title' => 'Confiança e segurança',
        'security_line' => 'Segurança alinhada com as normas ISO/IEC 27001 (em curso)',
        'security_link' => 'Ver segurança',
        'operations_title' => 'Operações',
        'operations_copy' => 'O suporte é tratado por email e, mais tarde, por ticketing no dashboard. Os levantamentos manuais continuam sujeitos a revisão administrativa e a aprovação do payout depende do cumprimento das regras.',
        'contact_title' => 'Contacto e suporte',
        'contact_copy' => 'Precisa de ajuda direta antes de comprar? Contacte a equipa de suporte ou abra o Wolfi para obter orientação rápida sobre regras e plataforma.',
        'payments' => [
            'eyebrow' => 'Checkout de confiança',
            'title' => 'Métodos de pagamento pensados para transmitir confiança',
            'description' => 'Canais de checkout reconhecidos e marcas de pagamento familiares tornam o passo final mais rápido, mais seguro e mais premium.',
            'cards_label' => 'Principais cartões',
            'protected_label' => 'Fluxo de encomenda protegido',
        ],
        'community' => [
            'eyebrow' => 'Acesso à comunidade',
            'title' => 'Wolforix Community Access',
            'description' => 'Access structured market insights, analysis and updates in real time',
            'channels' => [
                'youtube' => [
                    'description' => 'Veja atualizações da plataforma, conteúdo educativo e vídeos focados em traders.',
                    'cta' => 'Abrir YouTube',
                ],
                'instagram' => [
                    'description' => 'Siga atualizações visuais, destaques e conteúdo curto da comunidade.',
                    'cta' => 'Abrir Instagram',
                ],
                'telegram' => [
                    'description' => 'Junte-se ao canal direto para anúncios, notas de mercado e atualizações da comunidade.',
                    'cta' => 'Abrir Telegram',
                ],
            ],
        ],
        'positioning_bullets' => [
            'Payouts rápidos. Zero complicações',
            'Construída de forma diferente das prop firms tradicionais',
            'Desenhada para traders disciplinados — não para apostadores',
        ],
        'simulated_notice' => 'Qualquer funded account apresentado nesta interface faz parte de um programa de avaliação simulado. Nenhum capital real é negociado nos mercados financeiros live.',
        'company_location' => 'Wolforix Ltd. | Suite RA01, 195-197 Wood Street, London, E17 3NU',
        'copyright' => 'Todos os direitos reservados.',
        'back_to_top' => 'Voltar ao topo',
        'view_full_legal_information' => 'Ver informação legal completa',
        'quick_navigation_eyebrow' => 'Navegação rápida',
        'quick_navigation' => 'Abrir navegação principal',
        'contact_short' => 'Contacto',
    ],
    'cookie' => [
        'title' => 'Aviso de cookies',
        'message' => 'Usamos cookies para melhorar a sua experiência e suportar a funcionalidade essencial do site.',
        'accept' => 'Aceitar',
        'learn_more' => 'Saber mais',
    ],
    'fixed_disclaimer' => [
        'label' => 'Aviso de ambiente simulado',
        'text' => 'A Wolforix opera num ambiente de trading simulado. Reveja a FAQ e a política de payout antes de comprar uma challenge.',
        'faq_link' => 'FAQ',
        'policy_link' => 'Política de payout',
        'close_label' => 'Fechar aviso',
    ],
    'dashboard' => [
        'preview_title' => 'Dashboard de Trading',
        'preview_subtitle' => 'Contas, payouts e progresso num só espaço.',
        'nav' => [
            'wolfi_hub' => 'Wolfi Hub',
        ],
        'wolfi_hub_page' => [
            'title' => 'Wolfi Hub',
            'subtitle' => 'Suporte com contexto da conta, guia da plataforma e contexto Wolfi sem ocupar o workspace de trading.',
            'empty_title' => 'Wolfi está a preparar o seu workspace',
            'empty_copy' => 'Quando o contexto do dashboard carregar, o Wolfi Hub explicará a página atual, estado da conta, regras, payouts e suporte.',
        ],
        'mt5' => [
            'title' => 'Sync live MT5',
            'heading' => 'Sincronização MT5 e acesso à conta',
            'copy' => 'A Wolforix organiza agora o fluxo do trader em torno de dados MT5, frescura de sincronização e detalhes de acesso seguros.',
        ],
        'wolfi' => [
            'entry_eyebrow' => 'Wolfi apoia o seu',
            'entry_title' => 'workspace de trading',
            'entry_copy' => 'Pronto para apoiar o seu próximo passo em regras, métricas, timing de payout e contexto MT5.',
            'entry_hint' => 'O dashboard principal mantém dados de conta primeiro; o assistente completo vive no Wolfi Hub.',
            'open_hub' => 'Abrir Wolfi – Seu perfil',
            'fallbacks' => ['dashboard_workspace' => 'Workspace do dashboard'],
            'welcome' => [
                'title' => 'Resumo personalizado da conta',
                'account_message' => 'Estou a ler a sua conta :plan em :page para explicar estado, regras, payouts e dados MT5 em linguagem simples.',
                'account_bullets' => [
                    'status' => 'Estado atual: :status com :progress de progresso até ao objetivo.',
                    'balance' => 'Balance :balance, equity :equity e P&L flutuante :pnl.',
                    'trading_days' => 'Progresso de dias de trading: :days para a fase ativa.',
                ],
                'empty_message' => 'Posso explicar :page, regras da challenge, payouts e passos de suporte enquanto a Wolforix aguarda uma conta MT5 ativa neste perfil.',
                'empty_bullets' => [
                    'Use o Wolfi Hub para o assistente completo com contexto de conta.',
                    'O dashboard principal mantém o workspace de trading e dados de conta primeiro.',
                    'Quando chegarem dados MT5, o Wolfi adiciona aqui explicações personalizadas.',
                ],
            ],
            'stat_labels' => [
                'status' => 'Estado',
                'balance' => 'Balance',
                'equity' => 'Equity',
                'page' => 'Página',
                'rules' => 'Regras',
                'support' => 'Suporte',
            ],
            'stat_values' => ['structured' => 'Estruturado', 'ready' => 'Pronto'],
            'assistant' => [
                'eyebrow' => 'Wolfi apoia o seu',
                'title' => 'workspace de trading',
                'description' => 'Pronto para apoiar o seu próximo passo com orientação consciente de regras e métricas dentro do Wolfi Hub.',
                'sources_title' => 'Resposta ao vivo',
                'sources_copy' => 'Consciente das regras, consciente das métricas e pronto para futura reprodução de voz.',
                'status_idle' => 'Pronto para apoiar o próximo passo',
                'status_thinking' => 'Wolfi está a rever o contexto da conta',
                'input_placeholder' => 'Pergunte sobre a sua conta MT5, regras, payouts, métricas ou suporte...',
                'submit_label' => 'Perguntar ao Wolfi',
                'input_help' => 'Wolfi usa a página atual e a conta selecionada quando esses dados estão disponíveis.',
            ],
            'pillars' => [
                ['title' => 'Consciente das regras', 'description' => 'Orientação baseada nas regras da plataforma.'],
                ['title' => 'Consciente das métricas', 'description' => 'Insights que acompanham o que realmente importa.'],
                ['title' => 'Timing de payout', 'description' => 'Mantenha-se alinhado com o calendário de payouts.'],
                ['title' => 'Sempre pronto', 'description' => 'Receba apoio imediato quando precisar.'],
            ],
            'quick_actions' => [
                ['key' => 'dashboard', 'label' => 'Explicar o meu dashboard', 'prompt' => 'Explicar o meu dashboard'],
                ['key' => 'rules', 'label' => 'Quais são as regras?', 'prompt' => 'Quais são as regras da challenge?'],
                ['key' => 'metrics', 'label' => 'Explicar as minhas métricas', 'prompt' => 'Explicar as minhas métricas'],
                ['key' => 'payouts', 'label' => 'Como funcionam os payouts?', 'prompt' => 'Como funcionam os payouts?'],
                ['key' => 'consistency', 'label' => 'Qual é a regra de consistência?', 'prompt' => 'Qual é a regra de consistência?'],
            ],
            'smart_insights' => [
                'title' => 'Smart Insights',
                'description' => 'Wolfi acompanha o contexto live da conta e destaca sinais importantes antes mesmo de perguntar.',
            ],
            'pages' => [
                'dashboard.wolfi' => [
                    'title' => 'Wolfi Hub',
                    'summary' => 'Use esta página para o assistente Wolfi completo, explicações da conta, Smart Insights, suporte e guia da plataforma.',
                    'sections' => [
                        ['title' => 'Resumo pessoal', 'description' => 'Wolfi explica estado da conta, dados MT5, regras, progresso e contexto de payout.'],
                        ['title' => 'Prompts rápidos', 'description' => 'As ações rápidas ajudam a perguntar sobre dashboard, regras, métricas, payouts e consistência.'],
                        ['title' => 'Contexto de suporte', 'description' => 'Wolfi pode orientar para faturação, suporte, navegação ou próximo passo operacional.'],
                    ],
                ],
            ],
            'insights' => [
                'risk_alert' => ['label' => 'Alerta de risco', 'daily_message' => 'O uso da perda diária está elevado. Proteja a conta antes de novo setup.', 'max_message' => 'O uso do max drawdown está elevado. Reduza o risco.', 'meta' => 'Diário :daily% · Max :max%', 'prompt' => 'Explique o meu risco de drawdown e margem restante'],
                'profit_progress' => ['label' => 'Progresso de lucro', 'message' => 'Está perto do objetivo. Proteja ganhos e complete as regras.', 'meta' => ':progress% do objetivo', 'prompt' => 'Explique o que falta para passar esta fase'],
                'consistency_warning' => ['label' => 'Aviso de consistência', 'message' => 'Um dia concentra muito lucro. Distribua ganhos antes do payout.', 'meta' => 'Rácio do melhor dia :ratio%', 'prompt' => 'Explique o meu estado de consistência'],
                'payout_readiness' => ['label' => 'Readiness de payout', 'message' => 'A janela de payout parece aberta. Confirme regras e prepare o pedido.', 'meta' => 'Conta funded', 'prompt' => 'Explique a minha readiness de payout'],
            ],
        ],
        'settings' => [
            'preferences_copy' => 'O dashboard já é locale-aware, por isso a mudança de idioma mantém-se consistente à medida que são adicionados mais idiomas suportados.',
        ],
    ],
]);
