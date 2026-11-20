<?php

namespace App\Http\Controllers;

use App\Jobs\TranscodeVideo;
use App\Models\Material;
use App\Services\CourseProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Protected course files. PDFs are streamed through Laravel; for videos only
 * the HLS playlist and the AES key go through here (ADR-0004), the encrypted
 * segments are served statically from the hls disk.
 */
class MaterialController extends Controller
{
    public function pdf(Request $request, Material $material): StreamedResponse
    {
        $this->authorizeMaterial($request, $material);
        abort_unless($material->isPdf(), 404);

        $filename = str_replace('"', '', $material->title).'.pdf';

        return Storage::disk('local')->response($material->path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }

    public function playlist(Request $request, Material $material): Response
    {
        $this->authorizeMaterial($request, $material);
        abort_unless($material->isPlayable(), 404);

        $playlist = Storage::disk('local')->get($material->playlistPath());
        abort_if($playlist === null, 404);

        $segmentBase = rtrim(Storage::disk('hls')->url($material->hls_directory), '/').'/';
        $keyUrl = route('materials.key', $material);

        $lines = array_map(function (string $line) use ($segmentBase, $keyUrl): string {
            if (str_starts_with($line, '#EXT-X-KEY')) {
                return str_replace(TranscodeVideo::KEY_URI_PLACEHOLDER, $keyUrl, $line);
            }

            if ($line === '' || str_starts_with($line, '#')) {
                return $line;
            }

            return $segmentBase.basename($line);
        }, preg_split('/\r?\n/', $playlist) ?: []);

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function key(Request $request, Material $material): Response
    {
        $this->authorizeMaterial($request, $material);
        abort_unless($material->isPlayable(), 404);

        $key = Storage::disk('local')->get($material->keyPath());
        abort_if($key === null, 404);

        return response($key, 200, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** The course must be open to the user and the module unlocked. */
    private function authorizeMaterial(Request $request, Material $material): void
    {
        $module = $material->module;
        $course = $module->course;

        Gate::authorize('view', $course);

        $course->load('modules');
        $module = $course->modules->firstWhere('id', $module->id) ?? $module;

        abort_if(CourseProgress::for($course, $request->user())->isLocked($module), 403);
    }
}
