<?php

namespace App\Livewire\Admin;

use App\Models\AdminActivityLog;
use Illuminate\Support\Facades\File;
use Livewire\Component;
use Livewire\WithPagination;

class Logs extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.logs', [
            'activity' => AdminActivityLog::query()
                ->with(['actor', 'subject'])
                ->orderByDesc('created_at')
                ->paginate(30),
            'appLogLines' => $this->tailAppLog(),
        ])->layout('layouts.app', [
            'title' => 'Logs — Leilão Radar',
        ]);
    }

    /**
     * @return list<string>
     */
    private function tailAppLog(): array
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path)) {
            $daily = collect(File::glob(storage_path('logs/laravel-*.log')))->sort()->last();
            $path = is_string($daily) ? $daily : null;
        }

        if (! is_string($path) || ! is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        if ($size === false || $size === 0) {
            return [];
        }

        $start = max(0, $size - 120000);
        $contents = @file_get_contents($path, false, null, $start);
        if (! is_string($contents) || $contents === '') {
            return [];
        }

        $lines = preg_split('/\R/', $contents) ?: [];
        $lines = array_values(array_filter($lines, fn ($line) => trim($line) !== ''));

        return array_slice($lines, -120);
    }
}
