<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppDownloadController extends Controller
{
    public function android(Request $request): BinaryFileResponse
    {
        $path = public_path('downloads/verifyradar.apk');

        if (! File::isFile($path)) {
            abort(404, 'O APK do VerifyRadar ainda não está disponível. Tente de novo em alguns minutos.');
        }

        return response()->download(
            $path,
            'verifyradar.apk',
            ['Content-Type' => 'application/vnd.android.package-archive'],
        );
    }
}
