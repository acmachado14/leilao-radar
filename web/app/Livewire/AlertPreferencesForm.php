<?php

namespace App\Livewire;

use App\Models\AlertPreference;
use App\Models\Lot;
use App\Services\Alerts\AlertDispatcher;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AlertPreferencesForm extends Component
{
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
        $preference = Auth::user()->alertPreference ?? Auth::user()->alertPreference()->create(AlertPreference::defaults());
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

    public function save(): void
    {
        $this->validate([
            'search' => 'nullable|string|max:120',
            'marcas' => 'array',
            'fontes' => 'array',
            'fipe_matches' => 'required|array|min:1',
            'monta' => 'array',
            'min_desconto' => 'integer|min:-50|max:80',
            'exclude_grande' => 'boolean',
            'max_days_until' => 'nullable|integer|min:1|max:60',
            'notify_email' => 'boolean',
            'notify_whatsapp' => 'boolean',
        ]);

        if ($this->notify_whatsapp && ! filled(Auth::user()->phone)) {
            $this->addError('notify_whatsapp', 'Cadastre um telefone em Minha conta para ativar o WhatsApp.');

            return;
        }

        Auth::user()->alertPreference()->update([
            'search' => $this->search,
            'marcas' => $this->marcas,
            'fontes' => $this->fontes,
            'fipe_matches' => $this->fipe_matches,
            'monta' => $this->monta,
            'min_desconto' => $this->min_desconto / 100,
            'exclude_grande' => $this->exclude_grande,
            'max_days_until' => $this->max_days_until,
            'notify_email' => $this->notify_email,
            'notify_whatsapp' => $this->notify_whatsapp,
        ]);

        session()->flash('success', 'Preferências salvas. O próximo digest usa esses filtros.');
    }

    public function render(AlertDispatcher $dispatcher)
    {
        $preference = Auth::user()->alertPreference;
        $preview = $preference ? $dispatcher->preview(Auth::user(), $preference) : collect();
        $marcasDisponiveis = Lot::query()
            ->whereNotNull('marca')
            ->distinct()
            ->orderBy('marca')
            ->pluck('marca');

        return view('livewire.alert-preferences', [
            'preview' => $preview,
            'marcasDisponiveis' => $marcasDisponiveis,
            'whatsappReady' => (bool) config('radar.whatsapp.enabled'),
        ])->layout('layouts.app', [
            'title' => 'Alertas — Leilão Radar',
        ]);
    }
}
