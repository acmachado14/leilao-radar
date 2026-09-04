<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UnsubscribeAlertsController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user->alertPreferences()->update(['notify_email' => false]);

        return redirect()
            ->route('catalog')
            ->with('success', 'Alertas por e-mail desativados. Você pode religá-los em Preferências.');
    }
}
