<?php

namespace App\Bff;

use App\Constants\LotEvaluationStatus;
use App\Jobs\EvaluateLotJob;
use App\Models\Lot;
use App\Models\LotEvaluation;
use App\Models\User;
use App\Services\Billing\PlanQuota;
use App\Services\Mobile\UserAuthenticator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActionDispatcher
{
    public function __construct(
        private ScreenComposer $screens,
        private UserAuthenticator $auth,
        private PlanQuota $quota,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $name, Request $request, ?User $user, array $payload = []): ScreenDocument
    {
        return match ($name) {
            'login' => $this->login($request, $payload),
            'register' => $this->register($request, $payload),
            'forgot_password' => $this->forgotPassword($payload),
            'logout' => $this->logout($user),
            'toggle_interest' => $this->toggleInterest($request, $user, $payload),
            'evaluate' => $this->evaluate($request, $user, $payload),
            'save_alerts' => $this->saveAlerts($user, $payload),
            'delete_alerts' => $this->deleteAlerts($user, $payload),
            'filter_catalog' => $this->filterCatalog($request, $user, $payload),
            'delete_account' => $this->deleteAccount($user),
            default => $this->screens->compose('catalog', $request, $user),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function login(Request $request, array $payload): ScreenDocument
    {
        try {
            $issued = $this->auth->login((string) ($payload['email'] ?? ''), (string) ($payload['password'] ?? ''));
        } catch (ValidationException $exception) {
            return $this->screens->login($exception->validator->errors()->first() ?: 'E-mail ou senha inválidos.');
        }

        return $this->screens->withToken(
            $this->screens->catalog($request, $issued['user']),
            $issued['token'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function register(Request $request, array $payload): ScreenDocument
    {
        $validator = validator($payload, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'terms_accepted' => 'accepted',
        ], [
            'name.required' => 'Informe seu nome.',
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Esse e-mail não parece válido.',
            'email.unique' => 'Não foi possível criar a conta. Confira os dados ou tente entrar.',
            'password.required' => 'Informe uma senha com pelo menos 8 caracteres.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'terms_accepted.accepted' => 'Aceite os termos de uso para criar a conta.',
        ]);

        if ($validator->fails()) {
            return $this->screens->register($validator->errors()->first());
        }

        $issued = $this->auth->register($validator->validated());

        return $this->screens->withToken(
            $this->screens->paywall($issued['user']),
            $issued['token'],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function forgotPassword(array $payload): ScreenDocument
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email !== '') {
            $this->auth->sendResetLink($email);
        }

        $screen = $this->screens->login();
        $screen->components = [
            Node::make(ComponentType::BANNER, [
                'tone' => 'success',
                'text' => 'Se o e-mail existir, enviamos o link para redefinir a senha.',
            ]),
            ...$screen->components,
        ];

        return $screen;
    }

    private function logout(?User $user): ScreenDocument
    {
        $user?->currentAccessToken()?->delete();

        return $this->screens->login();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function toggleInterest(Request $request, ?User $user, array $payload): ScreenDocument
    {
        if ($user === null) {
            return $this->screens->login('Entre para marcar interesse.');
        }

        $loteId = (string) ($payload['id'] ?? '');
        $lot = Lot::query()->find($loteId);
        if ($lot === null) {
            return $this->screens->compose('catalog', $request, $user);
        }

        $existing = $user->lotInterests()->where('lote_id', $lot->lote_id)->first();
        if ($existing) {
            $existing->delete();
        } else {
            $user->lotInterests()->create(['lote_id' => $lot->lote_id]);
        }

        return $this->screens->lot($request, $user->fresh(), $lot->lote_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function evaluate(Request $request, ?User $user, array $payload): ScreenDocument
    {
        if ($user === null) {
            return $this->screens->login('Entre para pedir o parecer.');
        }

        $loteId = (string) ($payload['id'] ?? '');
        $lot = Lot::query()->find($loteId);
        if ($lot === null) {
            return $this->screens->compose('catalog', $request, $user);
        }

        if (! $this->quota->canConsult($user, $lot->lote_id)) {
            $expired = ! $user->isAdmin() && ! $user->hasLiveSubscription();
            $message = $expired
                ? 'Seu período de teste acabou. Assine na App Store para continuar.'
                : 'Você usou as análises de IA deste mês. Suba de plano na App Store.';

            return $this->screens->evaluation($user, $lot->lote_id, $message);
        }

        $alreadyRequested = $user->lotEvaluationRequests()->where('lote_id', $lot->lote_id)->exists();
        $user->lotEvaluationRequests()->firstOrCreate(['lote_id' => $lot->lote_id]);

        $hash = LotEvaluation::sourceHashFor($lot);
        $evaluation = LotEvaluation::query()->find($lot->lote_id);

        if ($evaluation !== null && $evaluation->source_hash === $hash && $evaluation->status === LotEvaluationStatus::READY) {
            if (! $alreadyRequested || ! $this->quota->alreadyBilledThisPeriod($user, $lot->lote_id)) {
                $this->quota->record($user, $lot->lote_id, 'cache', false);
            }

            return $this->screens->evaluation($user, $lot->lote_id);
        }

        if ($evaluation !== null && $evaluation->source_hash === $hash && $evaluation->status === LotEvaluationStatus::PENDING) {
            if (! $alreadyRequested || ! $this->quota->alreadyBilledThisPeriod($user, $lot->lote_id)) {
                $this->quota->record($user, $lot->lote_id, 'api', true);
            }

            return $this->screens->evaluation($user, $lot->lote_id);
        }

        LotEvaluation::query()->updateOrCreate(
            ['lote_id' => $lot->lote_id],
            [
                'status' => LotEvaluationStatus::PENDING,
                'source_hash' => $hash,
                'risk_score' => null,
                'summary' => null,
                'flags' => null,
                'patio_checks' => null,
                'max_bid_amount' => null,
                'estimated_resale' => null,
                'estimated_costs' => null,
                'target_profit' => null,
                'pricing_rationale' => null,
                'model' => null,
                'error_message' => null,
            ],
        );

        $this->quota->record($user, $lot->lote_id, 'api', true);
        EvaluateLotJob::dispatch($lot->lote_id, $user->id);

        return $this->screens->evaluation($user, $lot->lote_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function filterCatalog(Request $request, ?User $user, array $payload): ScreenDocument
    {
        return $this->screens->catalog($request, $user, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function saveAlerts(?User $user, array $payload): ScreenDocument
    {
        if ($user === null) {
            return $this->screens->login();
        }

        $id = isset($payload['id']) && $payload['id'] !== '' ? (string) $payload['id'] : null;
        $preference = $id
            ? $user->alertPreferences()->whereKey($id)->first()
            : null;

        if ($id !== null && $preference === null) {
            return $this->screens->alerts($user, 'Esse recorte não existe mais.');
        }

        $body = PreferenceInput::hydrate($payload, $preference);
        $channels = $user->alertPreferences()->first();
        $body['notify_email'] = (bool) ($channels?->notify_email ?? true);
        $body['notify_whatsapp'] = (bool) ($channels?->notify_whatsapp ?? false);

        if ($preference) {
            $preference->update($body);

            return $this->screens->alerts($user->fresh(), 'Recorte atualizado. O e-mail da manhã já usa esses filtros.');
        }

        if ($user->alertPreferences()->count() >= $this->quota->alertsLimit($user)) {
            return $this->screens->alertsEdit($user, '', 'Limite de recortes deste plano. Suba de plano para cadastrar mais.');
        }

        $user->alertPreferences()->create($body);

        return $this->screens->alerts($user->fresh(), 'Recorte criado. Você pode cadastrar outro modelo quando quiser.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deleteAlerts(?User $user, array $payload): ScreenDocument
    {
        if ($user === null) {
            return $this->screens->login();
        }

        $id = (string) ($payload['id'] ?? '');
        $preference = $id !== '' ? $user->alertPreferences()->whereKey($id)->first() : null;
        $preference?->delete();

        return $this->screens->alerts($user->fresh(), 'Recorte removido.');
    }

    private function deleteAccount(?User $user): ScreenDocument
    {
        if ($user === null) {
            return $this->screens->login();
        }

        $user->tokens()->delete();
        $user->delete();

        $screen = $this->screens->login();
        $screen->components = [
            Node::make(ComponentType::BANNER, [
                'tone' => 'success',
                'text' => 'Conta excluída.',
            ]),
            ...$screen->components,
        ];

        return $screen;
    }
}
