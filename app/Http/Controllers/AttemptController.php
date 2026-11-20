<?php

namespace App\Http\Controllers;

use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Services\CourseProgress;
use App\Services\GradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Taking a module test: start an attempt, save answers as the learner goes,
 * submit (or run out of time), then see the results.
 */
class AttemptController extends Controller
{
    public function store(Request $request, Test $test, GradingService $grader): RedirectResponse
    {
        $module = $test->module;
        $course = $module->course;
        Gate::authorize('view', $course);

        $course->load('modules');
        abort_if(CourseProgress::for($course, $request->user())->isLocked($course->modules->firstWhere('id', $module->id) ?? $module), 403);

        $existing = $request->user()->attempts()
            ->where('test_id', $test->id)
            ->whereNull('submitted_at')
            ->latest('started_at')
            ->get();

        foreach ($existing as $attempt) {
            if ($attempt->isInProgress()) {
                return to_route('attempts.show', $attempt);
            }

            $this->submit($attempt, $grader);
        }

        $attempt = $request->user()->attempts()->create([
            'test_id' => $test->id,
            'started_at' => now(),
            'time_limit_expires_at' => $test->time_limit_minutes ? now()->addMinutes($test->time_limit_minutes) : null,
        ]);

        return to_route('attempts.show', $attempt);
    }

    public function show(Request $request, Attempt $attempt, GradingService $grader): Response|RedirectResponse
    {
        $this->authorizeOwner($request, $attempt);

        if (! $attempt->isInProgress()) {
            $this->submit($attempt, $grader);

            return to_route('attempts.results', $attempt);
        }

        $test = $attempt->test->load(['questions.options', 'module.course:id,slug,title']);
        $saved = $attempt->answers()->pluck('answer_value', 'question_id');

        return Inertia::render('attempts/Show', [
            'attempt' => [
                'id' => $attempt->id,
                'expires_at' => $attempt->time_limit_expires_at?->toIso8601String(),
            ],
            'test' => [
                'title' => $test->title,
                'pass_score' => $test->pass_score,
            ],
            'course' => $test->module->course->only(['slug', 'title']),
            'module' => $test->module->only(['id', 'title']),
            'questions' => $test->questions->map(fn (Question $question) => [
                'id' => $question->id,
                'type' => $question->type->value,
                'prompt' => $question->prompt,
                'points' => $question->points,
                'options' => $question->options->map(fn (QuestionOption $option) => ['id' => $option->id, 'label' => $option->label]),
                // Pair questions: the matches, shuffled, are the choices for every row.
                'matches' => $question->type === QuestionType::Pair
                    ? $question->options->pluck('match')->shuffle()->values()
                    : [],
                'answer' => $saved[$question->id] ?? null,
            ]),
        ]);
    }

    public function answer(Request $request, Attempt $attempt): JsonResponse
    {
        $this->authorizeOwner($request, $attempt);
        abort_unless($attempt->isInProgress(), 409, 'A teszt már lezárult.');

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'answer_value' => ['nullable', 'string', 'max:10000'],
        ]);

        abort_unless($attempt->test->questions()->whereKey($data['question_id'])->exists(), 422);

        $attempt->answers()->updateOrCreate(
            ['question_id' => $data['question_id']],
            ['answer_value' => $data['answer_value']],
        );

        return response()->json(['saved' => true]);
    }

    public function finish(Request $request, Attempt $attempt, GradingService $grader): RedirectResponse
    {
        $this->authorizeOwner($request, $attempt);

        $this->submit($attempt, $grader);

        return to_route('attempts.results', $attempt);
    }

    public function results(Request $request, Attempt $attempt): Response|RedirectResponse
    {
        $this->authorizeOwner($request, $attempt);

        if ($attempt->submitted_at === null) {
            return to_route('attempts.show', $attempt);
        }

        $test = $attempt->test->load(['questions.options', 'module.course:id,slug,title']);
        $answers = $attempt->answers()->get()->keyBy('question_id');

        return Inertia::render('attempts/Results', [
            'attempt' => [
                'id' => $attempt->id,
                'score' => $attempt->score,
                'is_passed' => $attempt->is_passed,
                'submitted_at' => $attempt->submitted_at->format('Y. m. d. H:i'),
            ],
            'test' => [
                'id' => $test->id,
                'title' => $test->title,
                'pass_score' => $test->pass_score,
            ],
            'course' => $test->module->course->only(['slug', 'title']),
            'module' => $test->module->only(['id', 'title']),
            'questions' => $test->questions->map(function (Question $question) use ($answers) {
                /** @var AttemptAnswer|null $answer */
                $answer = $answers->get($question->id);

                return [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'points' => $question->points,
                    'is_correct' => (bool) $answer?->is_correct,
                    'given' => $this->describe($question, $answer?->answer_value),
                    'expected' => $this->describe($question, null, expected: true),
                ];
            }),
        ]);
    }

    private function submit(Attempt $attempt, GradingService $grader): void
    {
        if ($attempt->submitted_at !== null) {
            return;
        }

        $attempt->update(['submitted_at' => now()]);
        $grader->grade($attempt);
    }

    private function authorizeOwner(Request $request, Attempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);
    }

    /**
     * Readable form of an answer, or of the correct answer when $expected.
     *
     * @return list<string>
     */
    private function describe(Question $question, ?string $value, bool $expected = false): array
    {
        $options = $question->options;

        $labels = match ($question->type) {
            QuestionType::TrueFalse => [match ($expected ? $question->correct_answer : $value) {
                'true' => 'Igaz',
                'false' => 'Hamis',
                default => '–',
            }],
            QuestionType::Write => [($expected ? $question->correct_answer : $value) ?: '–'],
            QuestionType::SelectOne => $expected
                ? $options->where('is_correct', true)->pluck('label')->values()->all()
                : $options->where('id', (int) $value)->pluck('label')->values()->all(),
            QuestionType::SelectMultiple => $expected
                ? $options->where('is_correct', true)->pluck('label')->values()->all()
                : $options->whereIn('id', array_map('intval', (array) (json_decode((string) $value, true) ?? [])))->pluck('label')->values()->all(),
            QuestionType::Pair => $expected
                ? $options->map(fn (QuestionOption $o) => "{$o->label} → {$o->match}")->values()->all()
                : collect((array) (json_decode((string) $value, true) ?? []))
                    ->map(fn ($pair) => is_array($pair) && count($pair) === 2
                        ? ($options->firstWhere('id', (int) $pair[0])->label ?? '?').' → '.$pair[1]
                        : null)
                    ->filter()->values()->all(),
        };

        $labels = array_values(array_map(strval(...), $labels));

        return $labels === [] ? ['–'] : $labels;
    }
}
