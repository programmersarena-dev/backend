<?php

namespace Tests\Feature;

use App\Jobs\GradeSubmissionJob;
use App\Jobs\NotifyContestUsers;
use App\Models\Contest;
use App\Models\ContestRating;
use App\Models\ContestType;
use App\Models\Problem;
use App\Models\Submission;
use App\Models\User;
use App\Services\JudgeService;
use App\Support\TestCaseBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArenaImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private function problem(bool $activeContest = false): Problem
    {
        $type = ContestType::firstOrCreate(['name' => 'Classic']);
        $contest = Contest::create(['type_id' => $type->id, 'name' => 'Practice', 'start_date' => $activeContest ? now()->subMinutes(5) : now()->subDays(2),
            'duration_minutes' => 60, 'active' => true]);

        return Problem::create(['contest_id' => $contest->id, 'name' => 'Sum', 'slug' => 'sum-'.$contest->id,
            'code' => 'SUM-'.$contest->id, 'time_limit' => 1000, 'memory_limit' => 256]);
    }

    private function submission(User $user, Problem $problem, string $status = 'AC'): Submission
    {
        return Submission::create(['user_id' => $user->id, 'problem_id' => $problem->id,
            'contest_id' => $problem->contest_id, 'language' => 'gcc-10', 'code' => 'int main() {}', 'status' => $status]);
    }

    public function test_profile_returns_real_rating_and_counts_distinct_solved_problems(): void
    {
        $user = User::factory()->create(['current_rating' => 1450]);
        $problem = $this->problem();
        $this->submission($user, $problem);
        $this->submission($user, $problem);
        ContestRating::create(['user_id' => $user->id, 'contest_id' => $problem->contest_id,
            'rank' => 2, 'solved' => 3, 'old_rating' => 1400, 'new_rating' => 1450]);
        $this->getJson('/api/v1/profile/'.$user->handle)->assertOk()
            ->assertJsonPath('data.current_rating', 1450)->assertJsonPath('data.accepted_problems_count', 1);
        $this->getJson('/api/v1/profile/'.$user->handle.'/ratings')->assertOk()
            ->assertJsonPath('current_rating', 1450)->assertJsonPath('contest_ratings.0.rating', 50)
            ->assertJsonPath('contest_ratings.0.solved', 3);
    }

    public function test_batch_status_authorizes_each_submission_and_handles_queued_state(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $problem = $this->problem(true);
        $mine = $this->submission($owner, $problem, 'Queued');
        $theirs = $this->submission($other, $problem, 'Queued');
        Sanctum::actingAs($owner);
        Redis::shouldReceive('hgetall')->andReturn([]);
        Redis::shouldReceive('get')->with("judge:submission:{$mine->id}:queue-state")->once()->andReturn('In Queue');
        $this->getJson('/api/v1/submissions/statuses?'.http_build_query(['ids' => [$mine->id, $theirs->id]]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.status', 'In Queue');
    }

    public function test_intermediate_ioi_test_result_does_not_look_final(): void
    {
        $user = User::factory()->create();
        $problem = $this->problem();
        $submission = $this->submission($user, $problem, 'Judging');
        Sanctum::actingAs($user);
        Redis::shouldReceive('hgetall')->once()->andReturn(['status' => 'OK', 'finished' => '0', 'test' => '1']);
        $this->getJson("/api/v1/submissions/submission/{$submission->id}/status")
            ->assertOk()->assertJsonPath('status', 'Judging');
    }

    public function test_pending_scope_does_not_escape_user_filter(): void
    {
        $owner = User::factory()->create();
        $problem = $this->problem();
        $mine = $this->submission($owner, $problem, 'Queued');
        $this->submission(User::factory()->create(), $problem, 'Judging');
        $this->assertSame([$mine->id], Submission::where('user_id', $owner->id)->pending()->pluck('id')->all());
    }

    public function test_submission_list_caps_page_size_and_hides_future_contests(): void
    {
        $user = User::factory()->create();
        $problem = $this->problem();
        $this->submission($user, $problem);
        $problem->contest->update(['start_date' => now()->addDay()]);
        $this->getJson('/api/v1/submissions?per_page=1000')->assertOk()
            ->assertJsonPath('meta.per_page', 50)->assertJsonCount(0, 'data');
    }

    public function test_repeated_submission_is_rejected_without_dispatching_another_job(): void
    {
        $user = User::factory()->create();
        $problem = $this->problem();
        Sanctum::actingAs($user);
        Bus::fake();
        $this->mock(JudgeService::class)->shouldReceive('getAvailableLanguages')->andReturn(['gcc-10']);
        config(['languages.dockerLanguages.gcc.versions' => ['10']]);
        $payload = ['language' => 'gcc-10', 'code' => 'int main() {}'];
        $this->postJson("/api/v1/submissions/problem/{$problem->code}/submit", $payload)->assertCreated();
        $this->postJson("/api/v1/submissions/problem/{$problem->code}/submit", $payload)->assertStatus(422);
        Bus::assertDispatchedTimes(GradeSubmissionJob::class, 1);
    }

    public function test_notification_request_dispatches_background_job(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $problem = $this->problem();
        $problem->contest->update(['start_date' => now()->addDay()]);
        Bus::fake();
        $this->postJson("/api/v1/admin/contest/{$problem->contest_id}/notify")->assertStatus(202);
        Bus::assertDispatched(NotifyContestUsers::class, fn ($job) => $job->connection === 'database');
    }

    public function test_test_references_are_small_and_path_traversal_is_rejected(): void
    {
        $this->assertSame(['input_file' => 'tests/1-01.in', 'expected_output_file' => 'tests/1-01.out'],
            TestCaseBundle::test('tests/', '1-01'));
        $this->expectException(\RuntimeException::class);
        TestCaseBundle::test('tests/', '../../secret');
    }

    public function test_grade_job_enqueues_file_references_instead_of_test_contents(): void
    {
        $problem = $this->problem();
        $problem->update(['test_cases_path' => 'bundles/sum']);
        $submission = $this->submission(User::factory()->create(), $problem, 'Queued');
        $submission->update(['code' => '"A literal string, not JSON-encoded source"']);
        $originalStorage = $this->app->storagePath();
        $temporaryStorage = sys_get_temp_dir().'/arena-job-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($temporaryStorage.'/app/public/bundles/sum/tests', 0755, true);
        $this->app->useStoragePath($temporaryStorage);
        try {
            File::put($temporaryStorage.'/app/public/bundles/sum/tests/1.in', str_repeat('123456789\n', 10000));
            File::put($temporaryStorage.'/app/public/bundles/sum/tests/1.out', '42');
            $queued = null;
            $transaction = \Mockery::mock();
            $transaction->shouldReceive('setex')->once();
            $transaction->shouldReceive('del')->once();
            $transaction->shouldReceive('lpush')->once()->andReturnUsing(function ($queue, $payload) use (&$queued) {
                $this->assertSame('judge:jobs', $queue);
                $this->assertLessThan(4096, strlen($payload));
                $queued = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
            });
            Redis::shouldReceive('transaction')->once()->andReturnUsing(fn ($callback) => $callback($transaction));

            (new GradeSubmissionJob($submission->id, 'gcc', '10', 1500))->handle();
            $this->assertSame('Classic', $queued['grading_type']);
            $this->assertSame($submission->code, $queued['files']['submission.cpp']);
            $this->assertSame(1500, $queued['time_limit']);
            $this->assertSame('ms', $queued['time_limit_unit']);
            $this->assertSame(['input_file' => 'tests/1.in', 'expected_output_file' => 'tests/1.out'], $queued['subtasks'][0]['tests'][0]);
            $this->assertSame('In Queue', $submission->fresh()->status);
        } finally {
            $this->app->useStoragePath($originalStorage);
            File::deleteDirectory($temporaryStorage);
        }
    }

    public function test_admin_user_search_is_bounded_and_uses_unique_handles(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        User::factory()->count(25)->sequence(fn ($sequence) => ['handle' => 'coder'.$sequence->index, 'name' => 'Same name'])->create();
        $response = $this->getJson('/api/v1/admin/users?q=coder')->assertOk()->assertJsonCount(20);
        $this->assertSame(['id', 'handle', 'name'], array_keys($response->json()[0]));
        $this->getJson('/api/v1/admin/users')->assertOk()->assertExactJson([]);
    }

    public function test_failed_grade_job_does_not_leave_submission_pending(): void
    {
        $submission = $this->submission(User::factory()->create(), $this->problem(), 'In Queue');
        (new GradeSubmissionJob($submission->id, 'gcc', '10'))->failed(new \RuntimeException('Invalid test bundle'));
        $this->assertSame('Judge Error', $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->judged_at);
    }

    public function test_contest_selections_use_handles_when_display_names_match(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        ContestType::firstOrCreate(['name' => 'Classic']);
        $author = User::factory()->create(['name' => 'Same display name', 'handle' => 'author']);
        $participant = User::factory()->create(['name' => 'Same display name', 'handle' => 'participant']);

        $response = $this->postJson('/api/v1/admin/contest/add', [
            'type' => 'Classic', 'name' => 'Handle selection',
            'authors' => [$author->handle], 'start_date' => now()->addDay()->toIso8601String(),
            'duration' => '02:00', 'official' => true, 'active' => true,
            'participants' => ['official' => [$participant->handle], 'unofficial' => []],
        ])->assertStatus(202)->assertJsonPath('contest.authors.0', 'author')
            ->assertJsonPath('contest.participants.official.0', 'participant');

        $contest = Contest::findOrFail($response->json('contest.id'));
        $this->assertSame([$author->id], $contest->authors()->pluck('users.id')->all());
        $this->assertSame([$participant->id], $contest->participants()->pluck('users.id')->all());
    }
}
