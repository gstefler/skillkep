<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Question;

/**
 * Scores a submitted attempt. Answer formats (answer_value):
 *
 * - true_false: "true" or "false"
 * - write: free text, compared case-insensitively
 * - select_one: the chosen option id
 * - select_multiple: JSON list of the chosen option ids
 * - pair: JSON list of [option id, chosen match text]
 */
class GradingService
{
    public function grade(Attempt $attempt): void
    {
        $attempt->load(['test.questions.options', 'answers']);

        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($attempt->test->questions as $question) {
            $totalPoints += $question->points;

            /** @var AttemptAnswer|null $answer */
            $answer = $attempt->answers->firstWhere('question_id', $question->id);

            if ($answer === null) {
                continue;
            }

            $isCorrect = $this->isCorrect($question, (string) $answer->answer_value);
            $answer->update(['is_correct' => $isCorrect]);

            if ($isCorrect) {
                $earnedPoints += $question->points;
            }
        }

        $score = $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 0;

        $attempt->update([
            'score' => $score,
            'is_passed' => $score >= $attempt->test->pass_score,
        ]);
    }

    public function isCorrect(Question $question, string $value): bool
    {
        return match ($question->type) {
            QuestionType::TrueFalse, QuestionType::Write => $this->normalise($value) === $this->normalise((string) $question->correct_answer),
            QuestionType::SelectOne => $this->gradeSelectOne($question, $value),
            QuestionType::SelectMultiple => $this->gradeSelectMultiple($question, $value),
            QuestionType::Pair => $this->gradePair($question, $value),
        };
    }

    private function gradeSelectOne(Question $question, string $value): bool
    {
        $correct = $question->options->firstWhere('is_correct', true);

        return $correct !== null && (string) $correct->id === trim($value);
    }

    private function gradeSelectMultiple(Question $question, string $value): bool
    {
        $submitted = array_map('intval', (array) (json_decode($value, true) ?? []));
        $correct = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        sort($submitted);
        sort($correct);

        return $submitted === $correct;
    }

    private function gradePair(Question $question, string $value): bool
    {
        $submitted = json_decode($value, true);

        if (! is_array($submitted) || count($submitted) !== $question->options->count()) {
            return false;
        }

        $options = $question->options->keyBy('id');

        foreach ($submitted as $pair) {
            if (! is_array($pair) || count($pair) !== 2) {
                return false;
            }

            $option = $options->get((int) $pair[0]);

            if ($option === null || $this->normalise((string) $pair[1]) !== $this->normalise((string) $option->match)) {
                return false;
            }
        }

        return true;
    }

    private function normalise(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
