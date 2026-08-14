<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Submission;
use App\Models\Problem;
use App\Models\Contest;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AdminSubmissionControllerTest extends TestCase
{
    public function test_admin_submission_status_fetches_from_redis(): void
    {
        $submission = new Submission();
        $submission->id = 99999;
        $submission->status = 'Judging';
        $submission->time = null;
        $submission->memory = null;

        // Mock or simulate Redis response
        Redis::shouldReceive('hgetall')
            ->with("judge:submission:99999")
            ->andReturn([
                'status' => 'OK',
                'time' => '15',
                'memory' => '1024',
                'test' => '3',
            ]);

        $controller = new \App\Http\Controllers\Admin\SubmissionController();
        $response = $controller->status(request(), $submission);
        $data = $response->getData(true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $data['status']);
        $this->assertEquals(15, $data['time']);
        $this->assertEquals(1024, $data['memory']);
        $this->assertEquals(3, $data['test']);
        $this->assertTrue($data['from_redis']);
    }

    public function test_admin_submission_status_not_found(): void
    {
        $controller = new \App\Http\Controllers\Admin\SubmissionController();
        $response = $controller->status(request(), 999999999);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
