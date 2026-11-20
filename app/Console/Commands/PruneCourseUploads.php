<?php

namespace App\Console\Commands;

use App\Enums\CourseUploadStatus;
use App\Models\CourseUpload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('course-uploads:prune {--hours=72 : Age after which an unfinished upload is discarded}')]
#[Description('Discard abandoned course uploads and clean up stale upload records')]
class PruneCourseUploads extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));

        $abandoned = CourseUpload::query()
            ->where('status', CourseUploadStatus::Uploading)
            ->where('updated_at', '<', $cutoff)
            ->get();

        foreach ($abandoned as $upload) {
            $upload->deleteFiles();
            $upload->delete();
        }

        $interrupted = CourseUpload::query()
            ->where('status', CourseUploadStatus::Processing)
            ->where('updated_at', '<', now()->subHours(2))
            ->get();

        foreach ($interrupted as $upload) {
            $upload->forceFill([
                'status' => CourseUploadStatus::Failed,
                'stage' => null,
                'error' => 'A feldolgozás megszakadt.',
            ])->save();
            $upload->deleteFiles();
        }

        $old = CourseUpload::query()
            ->whereIn('status', [CourseUploadStatus::Completed, CourseUploadStatus::Failed])
            ->where('updated_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Discarded {$abandoned->count()} abandoned, failed {$interrupted->count()} interrupted, removed {$old} old upload records.");

        return self::SUCCESS;
    }
}
