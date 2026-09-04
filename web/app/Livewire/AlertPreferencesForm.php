<?php

namespace App\Livewire;

use App\Models\AlertPreference;
use App\Models\Lot;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AlertPreferencesForm extends Component
{
    public ?string $editingId = null;

    public string $name = '';

    public string $search = '';

    /** @var list<string> */
    public array $marcas = [];

    /** @var list<string> */
    public array $fontes = [];

    /** @var list<string> */
    public array $fipe_matches = [];

    /** @var list<string> */
    public array $monta = [];

    public int $min_desconto = 0;

    public bool $exclude_grande = true;

    public ?int $max_days_until = 14;

    public bool $notify_email = true;

    public bool $notify_whatsapp = false;

    public function mount(): void
    {
        $user = Auth::user();
        $first = $user->alertPreferences->first();
        $this->notify_email = (bool) ($first?->notify_email ?? true);
        $this->notify_whatsapp = (bool) ($first?->notify_whatsapp ?? false);

        if ($first) {
            $this->fillFromPreference($first);
        } else {
            $this->resetForm();
        }
    }

    public function edit(string $id): void
    {
        $preference = $this->ownedPreference($id);
        $this->fillFromPreference($preference);
        $this->resetValidation();
    }

    public function createNew(): void
    {
        $this->resetForm();
        $this->resetValidation();
    }

    public function delete(string $id): void
    {
        $this->ownedPreference($id)->delete();

        $next = Auth::user()->alertPreferences()->first();
        if ($next) {
            $this->fillFromPreference($next);
        } else {
            $this->resetForm();
        }

        session()->flash('success', 'Preferência removida.');
    }

    public function saveChannels(): void
    {
        $this->validate([
            'notify_email' => 'boolean',
            'notify_whatsapp' => 'boolean',
        ]);

        if ($this->notify_whatsapp && ! filled(Auth::user()->phone)) {
            $this->addError('notify_whatsapp', 'Cadastre um telefone em Minha conta para ativar o WhatsApp.');

            return;
        }

        Auth::user()->alertPreferences()->update([
            'notify_email' => $this->notify_email,
            'notify_whatsapp' => $this->notify_whatsapp,
        ]);

        session()->flash('success', 'Canais de alerta atualizados.');
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'nullable|string|max:80',
            'search' => 'nullable|string|max:120',
            'marcas' => 'array',
            'fontes' => 'array',
            'fipe_matches' => 'required|array|min:1',
            'monta' => 'array',
            'min_desconto' => 'integer|min:-50|max:80',
            'exclude_grande' => 'boolean',
            'max_days_until' => 'nullable|integer|min:1|max:60',
        ]);

        $user = Auth::user();
        $payload = [
            'name' => trim($this->name),
            'search' => trim($this->search),
            'marcas' => $this->marcas,
            'fontes' => $this->fontes,
            'fipe_matches' => $this->fipe_matches,
            'monta' => $this->monta,
            'min_desconto' => $this->min_desconto / 100,
            'exclude_grande' => $this->exclude_grande,
            'max_days_until' => $this->max_days_until,
            'notify_email' => $this->notify_email,
            'notify_whatsapp' => $this->notify_whatsapp,
        ];

        if ($this->editingId) {
            $this->ownedPreference($this->editingId)->update($payload);
            session()->flash('success', 'Preferência atualizada. O próximo digest usa esses filtros.');

            return;
        }

        $max = (int) config('radar.max_preferences', 12);
        if ($user->alertPreferences()->count() >= $max) {
            $this->addError('search', "Limite de {$max} preferências por conta.");

            return;
        }

        $created = $user->alertPreferences()->create($payload);
        $this->editingId = $created->id;
        session()->flash('success', 'Preferência criada. Você pode cadastrar outro modelo quando quiser.');
    }

    public function render(AlertDispatcher $dispatcher)
    {
        $user = Auth::user();
        $preferences = $user->alertPreferences;
        $editing = $this->editingId
            ? $preferences->firstWhere('id', $this->editingId)
            : null;
        $preview = $editing
            ? $dispatcher->preview($user, $editing)
            : collect();

        $marcasDisponiveis = Lot::query()
            ->whereNotNull('marca')
            ->distinct()
            ->orderBy('marca')
            ->pluck('marca');

        return view('livewire.alert-preferences', [
            'preferences' => $preferences,
            'preview' => $preview,
            'marcasDisponiveis' => $marcasDisponiveis,
            'whatsappReady' => (bool) config('radar.whatsapp.enabled'),
            'maxPreferences' => (int) config('radar.max_preferences', 12),
        ])->layout('layouts.app', [
            'title' => 'Alertas — Leilão Radar',
        ]);
    }

    private function fillFromPreference(AlertPreference $preference): void
    {
        $this->editingId = $preference->id;
        $this->name = (string) $preference->name;
        $this->search = (string) $preference->search;
        $this->marcas = $preference->marcas ?? [];
        $this->fontes = $preference->fontes ?? ['sodre', 'palacio'];
        $this->fipe_matches = $preference->fipe_matches ?? ['exact', 'closest', 'failed'];
        $this->monta = $preference->monta ?? ['sem_sinistro', 'pequena', 'media'];
        $this->min_desconto = (int) round(((float) $preference->min_desconto) * 100);
        $this->exclude_grande = (bool) $preference->exclude_grande;
        $this->max_days_until = $preference->max_days_until;
        $this->notify_email = (bool) $preference->notify_email;
        $this->notify_whatsapp = (bool) $preference->notify_whatsapp;
    }

    private function resetForm(): void
    {
        $defaults = AlertPreference::defaults();
        $this->editingId = null;
        $this->name = '';
        $this->search = '';
        $this->marcas = [];
        $this->fontes = $defaults['fontes'];
        $this->fipe_matches = $defaults['fipe_matches'];
        $this->monta = $defaults['monta'];
        $this->min_desconto = 0;
        $this->exclude_grande = true;
        $this->max_days_until = 14;
    }

    private function ownedPreference(string $id): AlertPreference
    {
        return Auth::user()->alertPreferences()->whereKey($id)->firstOrFail();
    }
}
