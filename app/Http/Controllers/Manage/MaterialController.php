<?php

namespace App\Http\Controllers\Manage;

use App\Enums\VideoStatus;
use App\Http\Controllers\Concerns\FlashesGeneratedPassword;
use App\Http\Controllers\Controller;
use App\Jobs\TranscodeVideo;
use App\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MaterialController extends Controller
{
    use FlashesGeneratedPassword;

    /** Queue the HLS transcoding of a video again, e.g. after a failure. */
    public function transcode(Material $material): RedirectResponse
    {
        Gate::authorize('administer', $material->module->course);
        abort_unless($material->isVideo(), 404);

        if ($material->video_status === VideoStatus::Processing) {
            $this->flashToast('A videó feldolgozása már folyamatban van.', 'info');

            return back();
        }

        $material->update(['video_status' => VideoStatus::Pending, 'video_error' => null]);
        TranscodeVideo::dispatch($material);

        $this->flashToast("{$material->title}: a videó feldolgozása újraindult.");

        return back();
    }
}
