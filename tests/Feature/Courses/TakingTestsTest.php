<?php

namespace Tests\Feature\Courses;

use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\Company;
use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Test;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TakingTestsTest extends TestCase
{
    use RefreshDatabase;

    private User $learner;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->learner = User::factory()->create(['company_id' => Company::factory()]);
        $this->course = Course::factory()->enrolling($this->learner)->create();
    }

    /**
     * A test with one question of every type. Returns [test, answers that are all correct].
     *
     * @return array{Test, array<int, string>}
     */
    private function createTestWithEveryQuestionType(Module $module): array
    {
        $test = Test::factory()->for($module)->create(['pass_score' => 60]);

        $trueFalse = Question::factory()->for($test)->create(['type' => QuestionType::TrueFalse, 'correct_answer' => 'true', 'order' => 1]);
        $write = Question::factory()->for($test)->create(['type' => QuestionType::Write, 'correct_answer' => 'Budapest', 'order' => 2]);

        $selectOne = Question::factory()->for($test)->create(['type' => QuestionType::SelectOne, 'correct_answer' => null, 'order' => 3]);
        $right = QuestionOption::factory()->for($selectOne)->create(['label' => 'Duna', 'is_correct' => true]);
        QuestionOption::factory()->for($selectOne)->create(['label' => 'Tisza', 'order' => 2]);

        $selectMultiple = Question::factory()->for($test)->create(['type' => QuestionType::SelectMultiple, 'correct_answer' => null, 'order' => 4]);
        $a = QuestionOption::factory()->for($selectMultiple)->create(['label' => 'piros', 'is_correct' => true]);
        $b = QuestionOption::factory()->for($selectMultiple)->create(['label' => 'fehér', 'is_correct' => true, 'order' => 2]);
        QuestionOption::factory()->for($selectMultiple)->create(['label' => 'kék', 'order' => 3]);

        $pair = Question::factory()->for($test)->create(['type' => QuestionType::Pair, 'correct_answer' => null, 'order' => 5]);
        $p1 = QuestionOption::factory()->for($pair)->create(['label' => 'Magyarország', 'match' => 'Budapest']);
        $p2 = QuestionOption::factory()->for($pair)->create(['label' => 'Ausztria', 'match' => 'Bécs', 'order' => 2]);

        return [$test, [
            $trueFalse->id => 'true',
            $write->id => '  budapest ',
            $selectOne->id => (string) $right->id,
            $selectMultiple->id => (string) json_encode([$b->id, $a->id]),
            $pair->id => (string) json_encode([[$p2->id, 'Bécs'], [$p1->id, 'Budapest']]),
        ]];
    }

    public function test_a_learner_takes_a_test_with_every_question_type_and_passes(): void
    {
        $module = Module::factory()->for($this->course)->create(['order' => 1]);
        [$test, $answers] = $this->createTestWithEveryQuestionType($module);

        $response = $this->actingAs($this->learner)->post(route('attempts.store', $test));
        $attempt = Attempt::query()->sole();
        $response->assertRedirect(route('attempts.show', $attempt));

        $this->get(route('attempts.show', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('attempts/Show')
                ->has('questions', 5)
                ->missing('questions.0.correct_answer'));

        foreach ($answers as $questionId => $value) {
            $this->putJson(route('attempts.answer', $attempt), ['question_id' => $questionId, 'answer_value' => $value])
                ->assertOk();
        }

        $this->post(route('attempts.finish', $attempt))->assertRedirect(route('attempts.results', $attempt));

        $attempt->refresh();
        $this->assertSame(100, $attempt->score);
        $this->assertTrue($attempt->is_passed);

        $this->get(route('attempts.results', $attempt))
            ->assertInertia(fn ($page) => $page->component('attempts/Results')->where('attempt.score', 100));
    }

    public function test_wrong_answers_fail_the_test_and_no_more_answers_are_accepted(): void
    {
        $module = Module::factory()->for($this->course)->create(['order' => 1]);
        [$test, $answers] = $this->createTestWithEveryQuestionType($module);
        $attempt = Attempt::factory()->for($this->learner)->for($test)->create();
        $firstQuestion = array_key_first($answers);

        $this->actingAs($this->learner)
            ->putJson(route('attempts.answer', $attempt), ['question_id' => $firstQuestion, 'answer_value' => 'false'])
            ->assertOk();
        $this->post(route('attempts.finish', $attempt));

        $attempt->refresh();
        $this->assertSame(0, $attempt->score);
        $this->assertFalse($attempt->is_passed);

        $this->putJson(route('attempts.answer', $attempt), ['question_id' => $firstQuestion, 'answer_value' => 'true'])
            ->assertConflict();
    }

    public function test_an_expired_attempt_is_submitted_when_opened(): void
    {
        $module = Module::factory()->for($this->course)->create(['order' => 1]);
        [$test] = $this->createTestWithEveryQuestionType($module);
        $attempt = Attempt::factory()->for($this->learner)->for($test)->create([
            'started_at' => now()->subHour(),
            'time_limit_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($this->learner)->get(route('attempts.show', $attempt))
            ->assertRedirect(route('attempts.results', $attempt));

        $this->assertNotNull($attempt->fresh()->submitted_at);
    }

    public function test_attempts_belong_to_their_learner(): void
    {
        $module = Module::factory()->for($this->course)->create(['order' => 1]);
        [$test] = $this->createTestWithEveryQuestionType($module);
        $attempt = Attempt::factory()->for($this->learner)->for($test)->create();

        $other = User::factory()->create(['company_id' => $this->learner->company_id]);

        $this->actingAs($other)->get(route('attempts.show', $attempt))->assertNotFound();
        $this->actingAs($other)->post(route('attempts.store', $test))->assertForbidden();
    }

    public function test_a_locked_module_opens_after_its_prerequisite_test_is_passed(): void
    {
        $first = Module::factory()->for($this->course)->create(['order' => 1]);
        $second = Module::factory()->for($this->course)->create(['order' => 2, 'prerequisite_module_id' => $first->id]);
        $firstTest = Test::factory()->for($first)->create();
        $secondTest = Test::factory()->for($second)->create();

        $this->actingAs($this->learner);

        $this->get(route('courses.show', $this->course))
            ->assertInertia(fn ($page) => $page
                ->where('modules.0.locked', false)
                ->where('modules.1.locked', true));
        $this->get(route('courses.modules.show', [$this->course, $second]))->assertForbidden();
        $this->post(route('attempts.store', $secondTest))->assertForbidden();

        Attempt::factory()->for($this->learner)->for($firstTest)->passed()->create();

        $this->get(route('courses.modules.show', [$this->course, $second]))->assertOk();
        $this->get(route('courses.show', $this->course))
            ->assertInertia(fn ($page) => $page
                ->where('modules.0.passed', true)
                ->where('progress.passed', 1)
                ->where('progress.tests', 2));
    }

    public function test_a_module_of_another_course_is_not_found(): void
    {
        $foreignModule = Module::factory()->create();

        $this->actingAs($this->learner)
            ->get(route('courses.modules.show', [$this->course, $foreignModule]))
            ->assertNotFound();
    }
}
