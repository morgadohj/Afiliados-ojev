<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAffiliateRequest;
use App\Models\Affiliate;
use App\Services\Documents\PrivateDocumentStorage;
use App\Services\Ine\TesseractIneExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AffiliationController extends Controller
{
    public function create(TesseractIneExtractor $extractor): Response
    {
        return Inertia::render('affiliation/create', [
            'ocrAvailable' => $extractor->available(),
        ]);
    }

    public function extractIne(Request $request, TesseractIneExtractor $extractor): JsonResponse
    {
        $validated = $request->validate([
            'ine_front' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'ine_back' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        if (! $extractor->available()) {
            return response()->json([
                'message' => 'El motor OCR local no está disponible. Puedes continuar capturando los datos manualmente.',
                'fields' => [],
                'warnings' => ['OCR no disponible en este entorno.'],
            ], 503);
        }

        $result = $extractor->extract(
            $validated['ine_front'],
            $validated['ine_back'] ?? null,
        );

        return response()->json([
            'message' => count($result['fields']).' datos sugeridos a partir de la INE.',
            ...$result,
        ]);
    }

    public function store(
        StoreAffiliateRequest $request,
        PrivateDocumentStorage $documents,
    ): JsonResponse {
        $validated = $request->validated();

        $affiliate = DB::transaction(function () use ($validated, $request, $documents): Affiliate {
            $affiliate = Affiliate::create([
                ...collect($validated)->except([
                    'profile_photo',
                    'ine_front',
                    'ine_back',
                    'consent',
                    'ocr_metadata',
                ])->all(),
                'profile_photo_path' => $request->file('profile_photo')
                    ? $documents->storeEncrypted($request->file('profile_photo'), 'affiliates/profile')
                    : null,
                'ine_front_path' => $documents->storeEncrypted(
                    $request->file('ine_front'),
                    'affiliates/ine',
                ),
                'ine_back_path' => $documents->storeEncrypted(
                    $request->file('ine_back'),
                    'affiliates/ine',
                ),
                'consent_accepted_at' => now(),
                'ocr_metadata' => $request->filled('ocr_metadata')
                    ? json_decode($request->string('ocr_metadata')->toString(), true)
                    : null,
            ]);

            $affiliate->update([
                'folio' => sprintf('OJEV-%s-%06d', now()->format('Y'), $affiliate->id),
            ]);

            return $affiliate->refresh();
        });

        return response()->json([
            'message' => 'Tu solicitud de afiliación fue recibida.',
            'folio' => $affiliate->folio,
        ], 201);
    }
}
