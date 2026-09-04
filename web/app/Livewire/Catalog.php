<?php

namespace App\Livewire;

use Livewire\Component;

class Catalog extends Component
{
    public function render()
    {
        return view('livewire.catalog')->layout('layouts.app', [
            'title' => 'Leilão Radar',
            'fullBleed' => true,
            'metaDescription' => 'Ofertas de leilão Sodré e Palácio vs tabela FIPE. Cadastre-se e receba alertas no e-mail.',
        ]);
    }
}
