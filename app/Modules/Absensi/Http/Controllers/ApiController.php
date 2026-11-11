<?php

namespace App\Modules\Absensi\Http\Controllers;

use App\Modules\Absensi\Exceptions\ApiException;
use App\Modules\Absensi\Models\Anak;
use App\Modules\Absensi\Models\Evaluasi;
use App\Modules\Absensi\Models\Kegiatan;
use App\Modules\Absensi\Services\AnakService;
use App\Modules\Absensi\Services\DasborService;
use App\Modules\Absensi\Services\EvaluasiService;
use App\Modules\Absensi\Services\JadwalService;
use App\Modules\Absensi\Services\PembinaanService;
use App\Modules\Absensi\Services\RaporService;
use App\Modules\Absensi\Services\RingkasanService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu endpoint JSON dengan kontrak `?action=` (parity dengan backend/api.php native).
 *
 * Perilaku yang dipertahankan sengaja:
 * - Envelope tidak seragam: list `anak`/`kegiatan`/`evaluasi` = array mentah.
 * - Hanya status 200/400/404/500 (tanpa 405/422).
 * - POST ke action GET tetap dilayani (native berperilaku demikian).
 * - Validasi global `mulai`/`sampai` dilakukan sebelum dispatch action.
 */
class ApiController
{
    public function __invoke(
        Request $request,
        AnakService $anakService,
        EvaluasiService $evaluasiService,
        JadwalService $jadwalService,
        RingkasanService $ringkasanService,
        RaporService $raporService,
        DasborService $dasborService,
        PembinaanService $pembinaanService,
    ): Response {
        try {
            // Native: semarak_init_schema() dijalankan pada setiap request.
            $pembinaanService->luluskanAngkatanSelesai();

            $this->validateGlobalRange($request, $jadwalService);

            $action = (string) ($request->query('action') ?? $request->request->get('action') ?? 'dashboard');

            if ($request->isMethod('POST')) {
                $write = $this->handleWrite(
                    $action,
                    $request,
                    $anakService,
                    $evaluasiService,
                    $jadwalService,
                    $raporService,
                );

                if ($write !== null) {
                    return $write;
                }
            }

            $read = $this->handleRead($action, $request, $jadwalService, $ringkasanService, $raporService, $dasborService);

            if ($read !== null) {
                return $read;
            }

            throw ApiException::notFound();
        } catch (ApiException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], $exception->status);
        } catch (Throwable $exception) {
            Log::error('absensi.api.error', [
                'action' => $request->query('action'),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }

    /**
     * Native: validasi format & urutan `mulai`/`sampai` untuk semua action.
     */
    private function validateGlobalRange(Request $request, JadwalService $jadwalService): void
    {
        $start = (string) ($request->query('mulai') ?? '');
        $end = (string) ($request->query('sampai') ?? '');

        if ($start !== '' && ! $jadwalService->isValidDate($start)) {
            throw new ApiException('Format tanggal tidak valid.');
        }

        if ($end !== '' && ! $jadwalService->isValidDate($end)) {
            throw new ApiException('Format tanggal tidak valid.');
        }

        if ($start !== '' && $end !== '' && $start > $end) {
            throw new ApiException('Tanggal mulai tidak boleh melewati tanggal selesai.');
        }
    }

    /**
     * @return Response|null null bila action bukan action tulis (agar bisa jatuh ke handler baca).
     */
    private function handleWrite(
        string $action,
        Request $request,
        AnakService $anakService,
        EvaluasiService $evaluasiService,
        JadwalService $jadwalService,
        RaporService $raporService,
    ): ?Response {
        $payload = $this->payload($request);

        switch ($action) {
            case 'save_evaluasi':
                return $this->json($evaluasiService->save($payload));

            case 'save_evaluasi_batch':
                if (! isset($payload['items']) || ! is_array($payload['items'])) {
                    throw new ApiException('Format data penilaian tidak valid.');
                }

                return $this->json($evaluasiService->saveBatch($payload['items']));

            case 'settings_save':
                return $this->json($jadwalService->save($payload));

            case 'report_notes_save':
                return $this->json($raporService->saveNotes($payload));

            case 'anak_store':
                return $this->json($anakService->store($payload));

            case 'anak_update':
                $id = trim((string) ($payload['id'] ?? ''));

                if ($id === '') {
                    throw new ApiException('ID anak wajib diisi saat update.');
                }

                return $this->json($anakService->update($id, $payload));

            case 'anak_delete':
                $id = trim((string) ($request->query('id') ?? $payload['id'] ?? ''));

                if ($id === '') {
                    throw new ApiException('ID anak wajib diisi.');
                }

                return $this->json($anakService->delete($id));
        }

        return null;
    }

    /**
     * @return Response|null null bila action tidak dikenal (pemanggil melempar 404).
     */
    private function handleRead(
        string $action,
        Request $request,
        JadwalService $jadwalService,
        RingkasanService $ringkasanService,
        RaporService $raporService,
        DasborService $dasborService,
    ): ?Response {
        switch ($action) {
            case 'dashboard':
                return $this->json($dasborService->dashboard($request->query()));

            case 'anak':
            case 'anak_list':
                return $this->json(Anak::query()->orderBy('nama')->get()->toArray());

            case 'anak_detail':
                $id = (string) ($request->query('id') ?? '');
                $anak = $id === '' ? null : Anak::query()->find($id);

                return $this->json($anak?->toArray() ?? [
                    'success' => false,
                    'message' => 'Data anak tidak ditemukan.',
                ]);

            case 'kegiatan':
                return $this->json(Kegiatan::query()->orderBy('id')->pluck('nama')->all());

            case 'evaluasi':
                $tanggal = (string) ($request->query('tanggal') ?? date('Y-m-d'));

                if (! $jadwalService->isValidDate($tanggal)) {
                    throw new ApiException('Format tanggal tidak valid.');
                }

                return $this->json(
                    Evaluasi::query()
                        ->where('tanggal', $tanggal)
                        ->get(['anak_id', 'kegiatan_id', 'tanggal', 'nilai'])
                        ->toArray()
                );

            case 'settings':
                return $this->json($jadwalService->settings());

            case 'summary':
                return $this->json($ringkasanService->summary(
                    (string) ($request->query('id') ?? $this->defaultAnakId()),
                    (string) ($request->query('mulai') ?? ''),
                    (string) ($request->query('sampai') ?? ''),
                ));

            case 'report':
                return $this->json($raporService->report(
                    (string) ($request->query('id') ?? $this->defaultAnakId()),
                    (string) ($request->query('mulai') ?? ''),
                    (string) ($request->query('sampai') ?? ''),
                ));
        }

        return null;
    }

    /**
     * Body JSON seperti native; fallback ke form fields bila body kosong/bukan JSON.
     *
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $decoded = json_decode((string) $request->getContent(), true);

        if (! is_array($decoded) || $decoded === []) {
            return $request->request->all();
        }

        return $decoded;
    }

    private function defaultAnakId(): string
    {
        return (string) (Anak::query()->orderBy('nama')->value('id') ?? '');
    }

    private function json(mixed $data, int $status = 200): Response
    {
        return response(
            json_encode($data),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }
}
