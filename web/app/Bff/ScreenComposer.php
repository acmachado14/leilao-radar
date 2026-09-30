<?php

namespace App\Bff;

use App\Models\AlertPreference;
use App\Models\Lot;
use App\Models\LotEvaluation;
use App\Models\User;
use App\Services\Billing\EntitlementResolver;
use App\Services\Billing\PlanQuota;
use App\Support\LegalCopy;
use App\Support\SearchYearExtractor;
use Illuminate\Http\Request;

class ScreenComposer
{
    public function __construct(
        private EntitlementResolver $entitlements,
        private PlanQuota $quota,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function compose(string $name, Request $request, ?User $user, array $params = []): ScreenDocument
    {
        $ipa = (string) $request->header('X-App-Version', '1.0.0');
        $min = (string) config('radar.ios.min_ipa_version', '1.0.0');
        if (version_compare($ipa, $min, '<')) {
            return $this->forceUpdate($min);
        }

        return match ($name) {
            'login' => $this->login(),
            'register' => $this->register(),
            'catalog' => $this->catalog($request, $user),
            'lot' => $this->lot($request, $user, (string) ($params['id'] ?? $request->query('id', ''))),
            'evaluation' => $this->evaluation($user, (string) ($params['id'] ?? $request->query('id', ''))),
            'alerts' => $this->alerts($user),
            'alerts_edit' => $this->alertsEdit($user, (string) ($params['id'] ?? $request->query('id', ''))),
            'account' => $this->account($user),
            'paywall' => $this->paywall($user),
            'my_lots' => $this->myLots($user),
            'terms' => $this->terms(),
            'privacy' => $this->privacy(),
            'shell' => $this->shell($user),
            default => $this->notFound($name),
        };
    }

    public function login(?string $error = null): ScreenDocument
    {
        $fields = [
            Node::make(ComponentType::HERO, [
                'kicker' => 'VerifyRadar',
                'title' => 'IA diz até quanto pagar no leilão',
                'subtitle' => 'Entre para ver ofertas, recortes e o teto de lance.',
            ]),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'email',
                'label' => 'E-mail',
                'keyboard' => 'email',
                'autoComplete' => 'username',
            ], id: 'login-email'),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'password',
                'label' => 'Senha',
                'secure' => true,
                'autoComplete' => 'password',
            ], id: 'login-password'),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Entrar',
                'style' => 'primary',
            ], onPress: [
                'type' => ActionType::SUBMIT,
                'action' => 'login',
            ]),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Criar conta',
                'style' => 'secondary',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'register']),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Esqueci a senha',
                'style' => 'plain',
            ], onPress: [
                'type' => ActionType::SUBMIT,
                'action' => 'forgot_password',
            ]),
        ];

        if ($error !== null) {
            array_unshift($fields, Node::make(ComponentType::BANNER, [
                'tone' => 'danger',
                'text' => $error,
            ]));
        }

        return new ScreenDocument('login', 'Entrar', $fields, refresh: null, tabs: [], meta: ['guest' => true]);
    }

    public function register(?string $error = null): ScreenDocument
    {
        $fields = [
            Node::make(ComponentType::HERO, [
                'kicker' => 'VerifyRadar',
                'title' => 'Criar conta grátis',
                'subtitle' => 'Catálogo completo e 3 análises de IA. Assinar um plano é opcional e só acontece depois, na App Store — nada de WhatsApp.',
            ]),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'name',
                'label' => 'Nome',
                'autoComplete' => 'name',
            ], id: 'register-name'),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'email',
                'label' => 'E-mail',
                'keyboard' => 'email',
                'autoComplete' => 'email',
            ], id: 'register-email'),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'password',
                'label' => 'Senha',
                'secure' => true,
                'autoComplete' => 'new-password',
            ], id: 'register-password'),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'password_confirmation',
                'label' => 'Confirmar senha',
                'secure' => true,
                'autoComplete' => 'new-password',
            ], id: 'register-password-confirmation'),
            Node::make(ComponentType::CHECKBOX, [
                'name' => 'terms_accepted',
                'label' => 'Li e aceito os termos de uso',
                'value' => false,
            ], id: 'register-terms'),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Ver termos de uso',
                'style' => 'plain',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'terms']),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Criar conta grátis',
                'style' => 'primary',
            ], onPress: [
                'type' => ActionType::SUBMIT,
                'action' => 'register',
            ]),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Já tenho conta',
                'style' => 'plain',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'login']),
        ];

        if ($error !== null) {
            array_unshift($fields, Node::make(ComponentType::BANNER, ['tone' => 'danger', 'text' => $error]));
        }

        return new ScreenDocument('register', 'Criar conta', $fields, refresh: null, tabs: [], meta: ['guest' => true]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function catalog(Request $request, ?User $user, array $filters = []): ScreenDocument
    {
        $input = $filters !== [] ? $filters : $request->query();
        $filter = CatalogFilter::from($input);
        $limit = (int) config('radar.bff.catalog_limit', 100);
        $lots = $filter->lots($limit);
        $cards = $lots->map(fn (Lot $lot) => $this->lotCard($lot))->all();
        $params = $filter->params();
        $extra = $filter->extraCount();
        $shown = $lots->count();
        $filteredTotal = $filter->filteredCount();
        $snapshotTotal = CatalogFilter::snapshotCount();
        $countLabel = sprintf(
            'Ofertas mais relevantes (exibindo %d de %d filtrados · %d no snapshot)',
            $shown,
            $filteredTotal,
            $snapshotTotal,
        );

        $searchBar = Node::make(ComponentType::SEARCH_BAR, [
            'name' => 'q',
            'placeholder' => 'Marca ou modelo',
            'value' => $filter->q,
        ], onPress: [
            'type' => ActionType::RELOAD_SCREEN,
            'screen' => 'catalog',
            'params' => $params,
        ]);
        $chipRow = Node::make(ComponentType::CHIP_ROW, [], [
            $this->filterChip('Sodré', in_array('sodre', $filter->fontes, true), $filter->toggle('fontes', 'sodre')->params()),
            $this->filterChip('Palácio', in_array('palacio', $filter->fontes, true), $filter->toggle('fontes', 'palacio')->params()),
            Node::make(ComponentType::FILTER_SHEET, [
                'label' => $extra > 0 ? 'Filtros · '.$extra : 'Filtros',
                'title' => 'Filtros',
                'count' => $extra,
                'params' => $params,
            ], $this->catalogFilterSheet($filter)),
        ]);

        $components = [];

        if ($cards === []) {
            $listChildren = [$searchBar, $chipRow];
            if ($user && $this->entitlements->canShowPaywall($user)) {
                $listChildren[] = Node::make(ComponentType::BANNER, [
                    'tone' => 'info',
                    'text' => 'Conta grátis com 3 análises de IA. Assinatura é opcional e cobrada pela Apple.',
                ]);
                $listChildren[] = Node::make(ComponentType::BUTTON, [
                    'label' => 'Ver planos na App Store',
                    'style' => 'plain',
                ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'paywall']);
            }
            $listChildren[] = Node::make(ComponentType::EMPTY_STATE, [
                'title' => 'Nenhuma oferta',
                'text' => 'Tente outra busca ou limpe os filtros.',
            ]);
            $components[] = Node::make(ComponentType::LIST, [], $listChildren);
        } else {
            $listChildren = [
                $searchBar,
                $chipRow,
            ];
            if ($user && $this->entitlements->canShowPaywall($user)) {
                $listChildren[] = Node::make(ComponentType::BANNER, [
                    'tone' => 'info',
                    'text' => 'Conta grátis com 3 análises de IA. Assinatura é opcional e cobrada pela Apple.',
                ]);
                $listChildren[] = Node::make(ComponentType::BUTTON, [
                    'label' => 'Ver planos na App Store',
                    'style' => 'plain',
                ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'paywall']);
            }
            $listChildren[] = Node::make(ComponentType::TEXT, [
                'value' => $countLabel,
                'tone' => 'muted',
            ]);
            $listChildren = [...$listChildren, ...$cards];
            $components[] = Node::make(ComponentType::LIST, [], $listChildren);
        }

        if ($extra > 0 || $filter->q !== '' || $filter->fontes != ['sodre', 'palacio']) {
            $components[] = Node::make(ComponentType::BUTTON, [
                'label' => 'Limpar filtros',
                'style' => 'plain',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'catalog']);
        }

        return new ScreenDocument(
            'catalog',
            'Ofertas',
            $components,
            tabs: $this->tabs('catalog'),
            meta: [
                'filter' => $params,
                ...($user ? ['entitlement' => $this->entitlements->snapshot($user)] : []),
            ],
        );
    }

    public function lot(Request $request, ?User $user, string $loteId): ScreenDocument
    {
        $lot = Lot::query()->find($loteId);
        if ($lot === null) {
            return $this->notFound('lot');
        }

        $interested = $user
            ? $user->lotInterests()->where('lote_id', $lot->lote_id)->exists()
            : false;

        $fonteLabel = $this->fonteLabel($lot->fonte);
        $photos = $this->lotPhotos($lot);

        $children = [
            Node::make(ComponentType::GALLERY, [
                'photos' => $photos,
                'alt' => $lot->titulo,
            ]),
            Node::make(ComponentType::TEXT, [
                'value' => $lot->titulo,
                'tone' => 'title',
            ]),
            Node::make(ComponentType::TEXT, [
                'value' => trim($lot->marca.' '.$lot->modelo.($lot->ano_mod ? ' · '.$lot->ano_mod : '')),
                'tone' => 'muted',
            ]),
            Node::make(ComponentType::GROUP, ['header' => 'Valores'], [
                $this->factRow('Lance atual', $this->brl($lot->lance_atual)),
                $this->factRow('Tabela FIPE', $this->brl($lot->fipe_preco)),
                $this->factRow('Desconto', $lot->desconto_label ?: 'FIPE N/A'),
                $this->factRow('Custo est. (+5%)', $this->brl($lot->custo_estimado_5pct)),
            ]),
            Node::make(ComponentType::GROUP, ['header' => 'Leilão'], [
                $this->factRow('Fonte', $fonteLabel),
                $this->factRow('Quando', $lot->auctionWhenLabel()),
                $this->factRow('Prazo', $this->daysLabel($lot)),
                $this->factRow('Pátio', $lot->patio ?: '—'),
                $this->factRow('Lote', (string) $lot->lote_id),
            ]),
            Node::make(ComponentType::GROUP, ['header' => 'Veículo'], [
                $this->factRow('Match FIPE', $this->fipeLabel($lot->fipe_match)),
                $this->factRow('Classificação', $this->montaLabel($lot->classificacao_monta)),
                $this->factRow('Sinistro', $lot->sinistro_label ?: ($lot->sinistro ?: '—')),
            ]),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Pedir parecer da IA',
                'style' => 'primary',
            ], onPress: [
                'type' => ActionType::SUBMIT,
                'action' => 'evaluate',
                'params' => ['id' => $lot->lote_id],
            ]),
            Node::make(ComponentType::BUTTON, [
                'label' => $interested ? 'Remover interesse' : 'Tenho interesse',
                'style' => 'secondary',
            ], onPress: [
                'type' => ActionType::SUBMIT,
                'action' => 'toggle_interest',
                'params' => ['id' => $lot->lote_id],
            ]),
        ];

        if (is_string($lot->url) && $lot->url !== '') {
            $children[] = Node::make(ComponentType::BUTTON, [
                'label' => 'Ver no '.$fonteLabel,
                'style' => 'secondary',
            ], onPress: [
                'type' => ActionType::OPEN_URL,
                'url' => $lot->url,
            ]);
        }

        return new ScreenDocument('lot', 'Lote', $children, tabs: $this->tabs('catalog'), meta: [
            'lote_id' => $lot->lote_id,
        ]);
    }

    public function evaluation(?User $user, string $loteId, ?string $error = null): ScreenDocument
    {
        if ($user === null) {
            return $this->login('Entre para ver o parecer.');
        }

        $lot = Lot::query()->find($loteId);
        if ($lot === null) {
            return $this->notFound('evaluation');
        }

        $evaluation = LotEvaluation::query()->find($loteId);
        $status = $evaluation?->status;
        $nodes = [
            Node::make(ComponentType::TEXT, [
                'value' => $lot->titulo,
                'tone' => 'title',
            ]),
        ];

        if ($error !== null) {
            $nodes[] = Node::make(ComponentType::BANNER, ['tone' => 'danger', 'text' => $error]);
            $nodes[] = Node::make(ComponentType::BUTTON, [
                'label' => 'Ver planos',
                'style' => 'primary',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'paywall']);
        } elseif ($evaluation === null || $status === 'pending') {
            $nodes[] = Node::make(ComponentType::AI_LOADING, [
                'title' => 'IA analisando o lote',
                'subtitle' => 'Lendo fotos, cruzando com a FIPE e calculando o teto de lance.',
                'steps' => [
                    'Lendo as fotos do pátio',
                    'Cruzando monta, sinistro e tabela',
                    'Fechando limite de lance com lucro',
                ],
            ]);
        } elseif ($status === 'failed') {
            $nodes[] = Node::make(ComponentType::BANNER, [
                'tone' => 'danger',
                'text' => $evaluation->error_message ?: 'Falha ao gerar avaliação.',
            ]);
            $nodes[] = Node::make(ComponentType::BUTTON, [
                'label' => 'Tentar de novo',
                'style' => 'primary',
            ], onPress: [
                'type' => ActionType::SUBMIT,
                'action' => 'evaluate',
                'params' => ['id' => $lot->lote_id],
            ]);
        } else {
            $public = $evaluation->toPublicArray();
            $nodes[] = Node::make(ComponentType::HERO, [
                'kicker' => 'Teto de lance',
                'title' => $public['max_bid_amount'] !== null
                    ? 'R$ '.number_format((float) $public['max_bid_amount'], 0, ',', '.')
                    : '—',
                'subtitle' => $public['summary'] ?: '',
            ]);
            $detailRows = [];
            if ($public['risk_score'] !== null) {
                $detailRows[] = $this->factRow('Risco', $public['risk_score'].'/10');
            }
            if ($public['estimated_resale'] !== null) {
                $detailRows[] = $this->factRow('Revenda est.', $this->brl($public['estimated_resale']));
            }
            if ($public['estimated_costs'] !== null) {
                $detailRows[] = $this->factRow('Custos est.', $this->brl($public['estimated_costs']));
            }
            if ($public['target_profit'] !== null) {
                $detailRows[] = $this->factRow('Lucro alvo', $this->brl($public['target_profit']));
            }
            if ($detailRows !== []) {
                $nodes[] = Node::make(ComponentType::GROUP, ['header' => 'Leitura da IA'], $detailRows);
            }
            if (is_string($public['pricing_rationale']) && $public['pricing_rationale'] !== '') {
                $nodes[] = Node::make(ComponentType::TEXT, [
                    'value' => $public['pricing_rationale'],
                    'tone' => 'muted',
                ]);
            }
            foreach ($public['patio_checks'] ?? [] as $index => $check) {
                $nodes[] = Node::make(ComponentType::TEXT, [
                    'value' => is_string($check) ? '• '.$check : json_encode($check),
                ], id: 'check-'.$index);
            }
            $nodes[] = Node::make(ComponentType::TEXT, [
                'value' => 'Parecer gerado por IA com base na FIPE. Não substitui vistoria nem garante lucro.',
                'tone' => 'muted',
            ]);
        }

        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Voltar ao lote',
            'style' => 'secondary',
        ], onPress: [
            'type' => ActionType::NAVIGATE,
            'screen' => 'lot',
            'params' => ['id' => $lot->lote_id],
        ]);

        $meta = [
            'lote_id' => $loteId,
            'status' => $error !== null ? 'error' : ($status ?: 'pending'),
        ];
        if ($error === null && ($evaluation === null || $status === 'pending')) {
            $meta['poll'] = [
                'screen' => 'evaluation',
                'params' => ['id' => $loteId],
                'interval_ms' => 2000,
            ];
        }

        return new ScreenDocument('evaluation', 'Parecer da IA', $nodes, tabs: $this->tabs('catalog'), meta: $meta);
    }

    public function alerts(?User $user, ?string $flash = null): ScreenDocument
    {
        if ($user === null) {
            return $this->login();
        }

        $preferences = $user->alertPreferences;
        $limit = $this->quota->alertsLimit($user);
        $nodes = [];
        if ($flash !== null) {
            $nodes[] = Node::make(ComponentType::BANNER, ['tone' => 'success', 'text' => $flash]);
        }

        $nodes[] = Node::make(ComponentType::TEXT, [
            'value' => $preferences->count().'/'.$limit.' recortes neste plano. Cada um é um modelo ou busca.',
            'tone' => 'muted',
        ]);

        if ($preferences->isEmpty()) {
            $nodes[] = Node::make(ComponentType::EMPTY_STATE, [
                'title' => 'Nenhum recorte ainda',
                'text' => 'Cadastre um por modelo. O e-mail da manhã usa esses filtros.',
            ]);
        }

        foreach ($preferences as $preference) {
            $detail = trim(($preference->search !== '' ? $preference->search : 'qualquer modelo')
                .($preference->yearLabel() ? ' · '.$preference->yearLabel() : '')
                .' · desconto ≥ '.(int) round(((float) $preference->min_desconto) * 100).'%');

            $nodes[] = Node::make(ComponentType::GROUP, [
                'header' => $preference->label(),
            ], [
                Node::make(ComponentType::ROW, [
                    'label' => 'Filtros',
                    'value' => $detail,
                ], id: 'pref-detail-'.$preference->id),
                Node::make(ComponentType::ROW, [
                    'label' => 'Editar',
                    'disclosure' => true,
                ], onPress: [
                    'type' => ActionType::NAVIGATE,
                    'screen' => 'alerts_edit',
                    'params' => ['id' => $preference->id],
                ], id: 'pref-edit-'.$preference->id),
                Node::make(ComponentType::ROW, [
                    'label' => 'Excluir recorte',
                ], onPress: [
                    'type' => ActionType::SUBMIT,
                    'action' => 'delete_alerts',
                    'params' => ['id' => $preference->id],
                    'confirm' => 'Remover este recorte?',
                ], id: 'pref-del-'.$preference->id),
            ], id: 'pref-'.$preference->id);
        }

        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Novo recorte',
            'style' => 'primary',
        ], onPress: [
            'type' => ActionType::NAVIGATE,
            'screen' => 'alerts_edit',
        ]);

        return new ScreenDocument('alerts', 'Alertas', $nodes, tabs: $this->tabs('alerts'));
    }

    public function alertsEdit(?User $user, string $id = '', ?string $error = null): ScreenDocument
    {
        if ($user === null) {
            return $this->login();
        }

        $editing = $id !== ''
            ? $user->alertPreferences()->whereKey($id)->first()
            : null;

        if ($id !== '' && $editing === null) {
            return $this->alerts($user, null);
        }

        $preference = $editing ?? new AlertPreference(AlertPreference::defaults());
        $checks = PreferenceInput::checkboxValues($preference->toArray());
        $nodes = [];

        if ($error !== null) {
            $nodes[] = Node::make(ComponentType::BANNER, ['tone' => 'danger', 'text' => $error]);
        }

        $nodes[] = Node::make(ComponentType::TEXT, [
            'value' => $editing ? 'Editar recorte' : 'Novo recorte',
            'tone' => 'title',
        ]);
        $nodes[] = Node::make(ComponentType::TEXT_FIELD, [
            'name' => 'name',
            'label' => 'Nome (opcional)',
            'value' => (string) $preference->name,
        ]);
        $nodes[] = Node::make(ComponentType::TEXT_FIELD, [
            'name' => 'search',
            'label' => 'Busca (marca / modelo)',
            'value' => (string) $preference->search,
        ]);
        $nodes[] = Node::make(ComponentType::STEPPER_YEAR, [
            'name' => 'ano_min',
            'label' => 'Ano de',
            'value' => $preference->ano_min,
            'min' => SearchYearExtractor::MIN_YEAR,
            'max' => SearchYearExtractor::MAX_YEAR,
        ]);
        $nodes[] = Node::make(ComponentType::STEPPER_YEAR, [
            'name' => 'ano_max',
            'label' => 'Ano até',
            'value' => $preference->ano_max,
            'min' => SearchYearExtractor::MIN_YEAR,
            'max' => SearchYearExtractor::MAX_YEAR,
        ]);
        $nodes[] = Node::make(ComponentType::TEXT, ['value' => 'Fonte', 'tone' => 'title']);
        $nodes = [...$nodes, ...$this->checkboxes([
            ['fonte_sodre', 'Sodré', $checks['fonte_sodre']],
            ['fonte_palacio', 'Palácio', $checks['fonte_palacio']],
        ])];
        $nodes[] = Node::make(ComponentType::TEXT, ['value' => 'Match FIPE', 'tone' => 'title']);
        $nodes = [...$nodes, ...$this->checkboxes([
            ['fipe_exact', 'Exato', $checks['fipe_exact']],
            ['fipe_closest', 'Mais próximo', $checks['fipe_closest']],
            ['fipe_failed', 'Sem match', $checks['fipe_failed']],
        ])];
        $nodes[] = Node::make(ComponentType::TEXT, ['value' => 'Classificação', 'tone' => 'title']);
        $nodes = [...$nodes, ...$this->checkboxes([
            ['monta_sem_sinistro', 'Sem sinistro', $checks['monta_sem_sinistro']],
            ['monta_pequena', 'Pequena', $checks['monta_pequena']],
            ['monta_media', 'Média', $checks['monta_media']],
            ['monta_outro', 'Outro', $checks['monta_outro']],
            ['exclude_grande', 'Excluir grande monta', $checks['exclude_grande']],
        ])];
        $nodes[] = Node::make(ComponentType::TEXT_FIELD, [
            'name' => 'min_desconto',
            'label' => 'Desconto mínimo vs FIPE (%)',
            'value' => (string) (int) round(((float) $preference->min_desconto) * 100),
            'keyboard' => 'number',
        ]);
        $nodes[] = Node::make(ComponentType::TEXT_FIELD, [
            'name' => 'marcas',
            'label' => 'Marcas (separe por vírgula, vazio = todas)',
            'value' => implode(', ', $preference->marcas ?? []),
        ]);
        $nodes[] = Node::make(ComponentType::TEXT_FIELD, [
            'name' => 'max_days_until',
            'label' => 'Prazo máximo (dias)',
            'value' => $preference->max_days_until !== null ? (string) $preference->max_days_until : '',
            'keyboard' => 'number',
        ]);
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => $editing ? 'Salvar recorte' : 'Adicionar recorte',
            'style' => 'primary',
        ], onPress: [
            'type' => ActionType::SUBMIT,
            'action' => 'save_alerts',
            'params' => $editing ? ['id' => $editing->id] : [],
        ]);
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Voltar aos recortes',
            'style' => 'plain',
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'alerts']);

        return new ScreenDocument(
            'alerts_edit',
            $editing ? 'Editar recorte' : 'Novo recorte',
            $nodes,
            tabs: $this->tabs('alerts'),
        );
    }

    public function account(?User $user, ?string $flash = null): ScreenDocument
    {
        if ($user === null) {
            return $this->login();
        }

        $entitlement = $this->entitlements->snapshot($user);
        $until = $user->subscription_until?->timezone('America/Sao_Paulo')?->format('d/m/Y');
        $nodes = [];
        if ($flash !== null) {
            $nodes[] = Node::make(ComponentType::BANNER, ['tone' => 'success', 'text' => $flash]);
        }

        $nodes[] = Node::make(ComponentType::HERO, [
            'kicker' => (string) ($entitlement['plan_name'] ?? 'VerifyRadar'),
            'title' => $user->name,
            'subtitle' => $user->email,
        ]);

        $planRows = [
            $this->factRow('Plano', (string) ($entitlement['plan_name'] ?? '—')),
            $this->factRow('Status', (string) ($entitlement['status_label'] ?? $user->subscriptionLabel())),
            $this->factRow(
                'Consultas',
                $entitlement['unlimited'] ? 'Ilimitadas' : ($entitlement['used'].'/'.$entitlement['limit'].' neste mês'),
            ),
        ];
        if ($until) {
            $planRows[] = $this->factRow('Válido até', $until);
        }
        $planRows[] = Node::make(ComponentType::ROW, [
            'label' => 'Ver planos',
            'disclosure' => true,
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'paywall']);

        $planGroup = ['header' => 'Assinatura'];
        if ($entitlement['source'] === 'web') {
            $planGroup['footer'] = 'Plano ativo pela web. Não é preciso assinar de novo na App Store.';
        }

        $nodes[] = Node::make(ComponentType::GROUP, $planGroup, $planRows);

        $sessionRows = [];
        $sessionRows[] = Node::make(ComponentType::ROW, [
            'label' => 'Restaurar compras',
            'disclosure' => true,
        ], onPress: ['type' => ActionType::RESTORE]);
        $sessionRows[] = Node::make(ComponentType::ROW, [
            'label' => 'Gerenciar assinatura Apple',
            'disclosure' => true,
        ], onPress: [
            'type' => ActionType::OPEN_URL,
            'url' => 'https://apps.apple.com/account/subscriptions',
        ]);
        $sessionRows[] = Node::make(ComponentType::ROW, [
            'label' => 'Termos de uso',
            'disclosure' => true,
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'terms']);
        $sessionRows[] = Node::make(ComponentType::ROW, [
            'label' => 'Privacidade',
            'disclosure' => true,
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'privacy']);
        $sessionRows[] = Node::make(ComponentType::ROW, [
            'label' => 'Sair',
        ], onPress: ['type' => ActionType::LOGOUT]);

        $nodes[] = Node::make(ComponentType::GROUP, ['header' => 'Conta'], $sessionRows);
        $nodes[] = Node::make(ComponentType::TEXT, [
            'value' => 'Apagar a conta remove login, recortes e interesses. A assinatura da Apple é cancelada em Ajustes.',
            'tone' => 'muted',
        ]);
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Apagar conta',
            'style' => 'plain',
        ], onPress: [
            'type' => ActionType::SUBMIT,
            'action' => 'delete_account',
            'confirm' => 'Apagar a conta de forma permanente? Esta ação não pode ser desfeita.',
            'destructive' => true,
        ]);

        return new ScreenDocument('account', 'Conta', $nodes, tabs: $this->tabs('account'), meta: [
            'entitlement' => $entitlement,
        ]);
    }

    public function paywall(?User $user): ScreenDocument
    {
        $radar = config('radar.plans.radar');
        $pro = config('radar.plans.radar_pro');
        $packages = config('radar.revenuecat.packages');
        $canBuy = $user !== null && $this->entitlements->canShowPaywall($user);
        $source = $user ? $this->entitlements->source($user) : null;

        $subtitle = 'A compra acontece na App Store. Nada de site ou WhatsApp neste app.';
        if ($user === null) {
            $subtitle = 'Entre para assinar pela App Store.';
        } elseif (! $canBuy && $source === 'web') {
            $subtitle = 'Seu plano já está ativo pelo site. Não cobramos de novo na App Store.';
        } elseif (! $canBuy) {
            $subtitle = 'Você já tem um plano pago neste iPhone.';
        } else {
            $subtitle = 'Conta grátis já está ativa. Assinar é opcional: 7 dias grátis pela Apple, depois o valor mensal. Cancele em Ajustes.';
        }

        $packagePayload = [
            [
                'id' => $packages['radar'],
                'title' => $radar['name'],
                'period' => 'Mensal',
                'price_hint' => $radar['price'],
                'intro' => '7 dias grátis, depois '.$radar['price'],
                'features' => $radar['features'],
                'purchasable' => $canBuy,
            ],
            [
                'id' => $packages['radar_pro'],
                'title' => $pro['name'],
                'period' => 'Mensal',
                'price_hint' => $pro['price'],
                'intro' => '7 dias grátis, depois '.$pro['price'],
                'features' => $pro['features'],
                'highlight' => true,
                'purchasable' => $canBuy,
            ],
        ];

        $nodes = [
            Node::make(ComponentType::HERO, [
                'kicker' => 'Assinatura App Store',
                'title' => $canBuy ? 'Escolha um plano' : 'Seus planos',
                'subtitle' => $subtitle,
            ]),
            Node::make(ComponentType::PAYWALL, ['packages' => $packagePayload]),
        ];

        if ($user === null) {
            $nodes[] = Node::make(ComponentType::BUTTON, [
                'label' => 'Entrar para assinar',
                'style' => 'primary',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'login']);
        } elseif ($canBuy) {
            $nodes[] = Node::make(ComponentType::BUTTON, [
                'label' => 'Continuar grátis',
                'style' => 'secondary',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'catalog']);
        }

        $nodes[] = Node::make(ComponentType::TEXT, [
            'value' => 'O pagamento é cobrado no Apple ID na confirmação da compra. A assinatura é mensal e renova automaticamente, a menos que seja cancelada pelo menos 24 horas antes do fim do período. A renovação é cobrada em até 24 horas antes do vencimento. Gerencie ou cancele em Ajustes → Apple ID → Assinaturas.',
            'tone' => 'muted',
        ]);
        $nodes[] = Node::make(ComponentType::TEXT, [
            'value' => 'Ao tocar em Assinar, aguarde o sheet da Apple — pode levar alguns segundos.',
            'tone' => 'muted',
        ]);

        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Termos de uso',
            'style' => 'plain',
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'terms']);
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Privacidade',
            'style' => 'plain',
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'privacy']);
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Termos padrão da Apple (EULA)',
            'style' => 'plain',
        ], onPress: [
            'type' => ActionType::OPEN_URL,
            'url' => 'https://www.apple.com/legal/internet-services/itunes/dev/stdeula/',
        ]);

        return new ScreenDocument('paywall', 'Planos', $nodes, tabs: $this->tabs('paywall'));
    }

    public function myLots(?User $user): ScreenDocument
    {
        if ($user === null) {
            return $this->login();
        }

        $ids = $user->lotInterests()->pluck('lote_id');
        $lots = Lot::query()->whereIn('lote_id', $ids)->orderByDesc('relevance_score')->get();
        $cards = $lots->map(fn (Lot $lot) => $this->lotCard($lot))->all();

        $nodes = $cards === []
            ? [Node::make(ComponentType::EMPTY_STATE, [
                'title' => 'Nenhum interesse ainda',
                'text' => 'Abra um lote e toque em Tenho interesse.',
            ])]
            : [Node::make(ComponentType::LIST, [], $cards)];

        return new ScreenDocument('my_lots', 'Meus lotes', $nodes, tabs: $this->tabs('my_lots'));
    }

    public function terms(): ScreenDocument
    {
        return $this->legalScreen('terms', 'Termos de uso', LegalCopy::termsParagraphs());
    }

    public function privacy(): ScreenDocument
    {
        return $this->legalScreen('privacy', 'Privacidade', LegalCopy::privacyParagraphs());
    }

    public function shell(?User $user): ScreenDocument
    {
        return new ScreenDocument(
            'shell',
            'VerifyRadar',
            [
                Node::make(ComponentType::HERO, [
                    'kicker' => 'VerifyRadar',
                    'title' => 'Radar de leilão',
                    'subtitle' => 'As telas vêm do servidor. Puxe para atualizar.',
                ]),
            ],
            tabs: $user ? $this->tabs('catalog') : [],
            meta: ['guest' => $user === null],
        );
    }

    public function forceUpdate(string $min): ScreenDocument
    {
        return new ScreenDocument('force_update', 'Atualize o app', [
            Node::make(ComponentType::FORCE_UPDATE, [
                'min_ipa_version' => $min,
                'text' => 'Esta versão do VerifyRadar não entende as telas novas. Atualize na App Store.',
            ]),
        ], refresh: null, tabs: []);
    }

    public function withToken(ScreenDocument $screen, string $token): ScreenDocument
    {
        $screen->token = $token;

        return $screen;
    }

    /**
     * @param  array<string, string>  $params
     */
    private function filterChip(string $label, bool $selected, array $params): Node
    {
        return Node::make(ComponentType::CHIP, [
            'label' => $label,
            'selected' => $selected,
        ], onPress: [
            'type' => ActionType::RELOAD_SCREEN,
            'screen' => 'catalog',
            'params' => $params,
        ]);
    }

    /**
     * @return list<Node>
     */
    private function catalogFilterSheet(CatalogFilter $filter): array
    {
        $percent = (int) round($filter->minDesconto * 100);

        return [
            Node::make(ComponentType::TEXT, ['value' => 'Match FIPE', 'tone' => 'title']),
            Node::make(ComponentType::CHIP_ROW, [], [
                $this->filterChip('Exato', in_array('exact', $filter->fipe, true), $filter->toggle('fipe', 'exact')->params()),
                $this->filterChip('Mais próximo', in_array('closest', $filter->fipe, true), $filter->toggle('fipe', 'closest')->params()),
                $this->filterChip('Sem match', in_array('failed', $filter->fipe, true), $filter->toggle('fipe', 'failed')->params()),
            ]),
            Node::make(ComponentType::TEXT, ['value' => 'Classificação', 'tone' => 'title']),
            Node::make(ComponentType::CHIP_ROW, [], [
                $this->filterChip('Sem sinistro', in_array('sem_sinistro', $filter->monta, true), $filter->toggle('monta', 'sem_sinistro')->params()),
                $this->filterChip('Pequena', in_array('pequena', $filter->monta, true), $filter->toggle('monta', 'pequena')->params()),
                $this->filterChip('Média', in_array('media', $filter->monta, true), $filter->toggle('monta', 'media')->params()),
                $this->filterChip('Outro', in_array('outro', $filter->monta, true), $filter->toggle('monta', 'outro')->params()),
            ]),
            Node::make(ComponentType::CHECKBOX, [
                'name' => 'exclude_grande',
                'label' => 'Excluir grande monta',
                'value' => $filter->excludeGrande,
            ]),
            Node::make(ComponentType::TEXT, ['value' => 'Desconto mínimo vs FIPE', 'tone' => 'title']),
            Node::make(ComponentType::CHIP_ROW, [], [
                $this->filterChip('Qualquer', $percent <= 0, $filter->withMinDescontoPercent(0)->params()),
                $this->filterChip('10%+', $percent === 10, $filter->withMinDescontoPercent(10)->params()),
                $this->filterChip('20%+', $percent === 20, $filter->withMinDescontoPercent(20)->params()),
                $this->filterChip('30%+', $percent === 30, $filter->withMinDescontoPercent(30)->params()),
            ]),
            Node::make(ComponentType::TEXT_FIELD, [
                'name' => 'marca',
                'label' => 'Marca',
                'value' => implode(', ', $filter->marcas),
            ]),
        ];
    }

    private function lotCard(Lot $lot): Node
    {
        $photos = $this->lotPhotos($lot);

        return Node::make(ComponentType::LOT_CARD, [
            'title' => $lot->titulo,
            'subtitle' => trim($lot->marca.' · '.$lot->modelo.($lot->ano_mod ? ' · '.$lot->ano_mod : '')),
            'year' => $lot->ano_mod,
            'photo' => $photos[0] ?? $lot->emailPhotoUrl(),
            'photo_count' => count($photos),
            'lance' => $this->brl($lot->lance_atual),
            'fipe' => $this->brl($lot->fipe_preco),
            'discount' => $lot->desconto_label ?: 'FIPE N/A',
            'fonte' => $this->fonteLabel($lot->fonte),
            'patio' => $lot->patio ?: '',
            'when' => $this->daysLabel($lot),
            'tags' => array_values(array_filter([
                $this->fonteLabel($lot->fonte),
                $lot->classificacao_monta ? $this->montaLabel($lot->classificacao_monta) : null,
                $lot->fipe_match ? $this->fipeLabel($lot->fipe_match) : null,
                $this->daysLabel($lot),
            ], fn (?string $tag) => is_string($tag) && $tag !== '' && $tag !== '—')),
        ], onPress: [
            'type' => ActionType::NAVIGATE,
            'screen' => 'lot',
            'params' => ['id' => $lot->lote_id],
        ], id: $lot->lote_id);
    }

    private function factRow(string $label, string $value): Node
    {
        return Node::make(ComponentType::ROW, [
            'label' => $label,
            'value' => $value,
        ]);
    }

    /**
     * @return list<string>
     */
    private function lotPhotos(Lot $lot): array
    {
        $photos = [];
        foreach (array_merge([$lot->foto_capa], $lot->fotos ?? []) as $url) {
            if (is_string($url) && $url !== '' && ! in_array($url, $photos, true)) {
                $photos[] = $url;
            }
        }

        return $photos !== [] ? $photos : [$lot->emailPhotoUrl()];
    }

    private function brl(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return 'R$ '.number_format((float) $value, 0, ',', '.');
    }

    private function fonteLabel(?string $fonte): string
    {
        return $fonte === 'palacio' ? 'Palácio' : 'Sodré';
    }

    private function montaLabel(?string $value): string
    {
        return match ($value) {
            'sem_sinistro' => 'Sem sinistro',
            'pequena' => 'Pequena monta',
            'media' => 'Média monta',
            'grande' => 'Grande monta',
            'outro' => 'Outro',
            default => $value ?: '—',
        };
    }

    private function fipeLabel(?string $value): string
    {
        return match ($value) {
            'exact' => 'FIPE exato',
            'closest' => 'FIPE próximo',
            'failed' => 'Sem FIPE',
            default => 'FIPE',
        };
    }

    private function daysLabel(Lot $lot): string
    {
        $days = $lot->daysUntilAuction();
        if ($days === null) {
            return 'Sem data';
        }
        if ($days < 0) {
            return 'Encerrado';
        }
        if ($days < 1) {
            return (string) round($days * 24).' h restantes';
        }

        return number_format($days, 1, ',', '.').' dias';
    }

    /**
     * @param  list<array{0: string, 1: string, 2: bool}>  $items
     * @return list<Node>
     */
    private function checkboxes(array $items): array
    {
        return array_map(
            fn (array $item) => Node::make(ComponentType::CHECKBOX, [
                'name' => $item[0],
                'label' => $item[1],
                'value' => $item[2],
            ]),
            $items,
        );
    }

    /**
     * @param  list<string>  $paragraphs
     */
    private function legalScreen(string $name, string $title, array $paragraphs): ScreenDocument
    {
        $nodes = [
            Node::make(ComponentType::TEXT, ['value' => $title, 'tone' => 'title']),
        ];
        foreach ($paragraphs as $index => $paragraph) {
            $nodes[] = Node::make(ComponentType::TEXT, [
                'value' => $paragraph,
                'tone' => $index === 0 ? 'muted' : 'body',
            ], id: $name.'-p-'.$index);
        }
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Falar com o suporte',
            'style' => 'plain',
        ], onPress: [
            'type' => ActionType::OPEN_URL,
            'url' => 'mailto:'.LegalCopy::CONTACT_EMAIL,
        ]);
        $nodes[] = Node::make(ComponentType::BUTTON, [
            'label' => 'Voltar',
            'style' => 'secondary',
        ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'account']);

        return new ScreenDocument($name, $title, $nodes, tabs: $this->tabs('account'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tabs(string $active): array
    {
        return [
            ['id' => 'catalog', 'label' => 'Ofertas', 'icon' => 'car-sport', 'screen' => 'catalog', 'active' => $active === 'catalog'],
            ['id' => 'my_lots', 'label' => 'Meus', 'icon' => 'heart', 'screen' => 'my_lots', 'active' => $active === 'my_lots'],
            ['id' => 'alerts', 'label' => 'Recortes', 'icon' => 'notifications', 'screen' => 'alerts', 'active' => $active === 'alerts'],
            ['id' => 'paywall', 'label' => 'Planos', 'icon' => 'diamond', 'screen' => 'paywall', 'active' => $active === 'paywall'],
            ['id' => 'account', 'label' => 'Conta', 'icon' => 'person-circle', 'screen' => 'account', 'active' => $active === 'account'],
        ];
    }

    private function notFound(string $name): ScreenDocument
    {
        return new ScreenDocument('not_found', 'Não encontrado', [
            Node::make(ComponentType::EMPTY_STATE, [
                'title' => 'Tela indisponível',
                'text' => 'O BFF não conhece "'.$name.'".',
            ]),
            Node::make(ComponentType::BUTTON, [
                'label' => 'Voltar às ofertas',
                'style' => 'primary',
            ], onPress: ['type' => ActionType::NAVIGATE, 'screen' => 'catalog']),
        ], tabs: $this->tabs('catalog'));
    }
}
