<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Standing extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'contest_id',
        'result',
    ];

    public function contest()
    {
        return $this->belongsTo(Contest::class);
    }

    public function addUserStanding($username, $contestType)
    {
        $contest = Contest::findOrFail($this->contest_id);
        $countProblems = $contest->problems()->count();
        $result = json_decode($this->result, true) ?? [];
        $result = collect($result);
        $userStanding = null;
        if ($contestType === 'Classic')
            $userStanding = $result->firstWhere('username', $username);
        elseif ($contestType === 'Duel') {
            $userStanding = $result->firstWhere('username', '=', $username[0]) ||
                $result->firstWhere('username2', '=', $username[0]);
        }
        if (!$userStanding) {
            if ($contest->hasSubtasks()) {
                if ($contestType === 'Classic') {
                    $userStanding = [
                        'username' => $username,
                        'problems' => array_fill(0, $countProblems, array_fill(0, 1, 0)),
                        'total_score' => 0,
                    ];
                } elseif ($contestType === 'Duel') {
                    $userStanding = [
                        'username' => $username[0],
                        'problems' => array_fill(0, $countProblems, array_fill(0, 1, 0)),
                        'total_score' => 0,
                        'username2' => $username[1],
                        'problems2' => array_fill(0, $countProblems, array_fill(0, 1, 0)),
                        'total_score2' => 0,
                    ];
                }
            } else {
                if ($contestType === 'Classic') {
                    $userStanding = [
                        'username' => $username,
                        'problems' => array_fill(0, $countProblems, ["score" => 0]),
                        'total_score' => 0,
                    ];
                } elseif ($contestType === 'Duel') {
                    $userStanding = [
                        'username' => $username[0],
                        'problems' => array_fill(0, $countProblems, ["score" => 0]),
                        'total_score' => 0,
                        'username2' => $username[1],
                        'problems2' => array_fill(0, $countProblems, ["score" => 0]),
                        'total_score2' => 0,
                    ];
                }
            }
            $result->push($userStanding);
        }
        return $this->sortResult($result);
    }

    public function removeUserStanding($username, $contestType)
    {
        $result = json_decode($this->result, true) ?? [];

        if ($contestType === 'Classic') {
            return array_filter($result, function ($item) use ($username) {
                return $item['username'] != $username;
            });
        } elseif ($contestType === 'Duel') {
            return array_filter($result, function ($item) use ($username) {
                return $item['username'] != $username && $item['username2'] != $username;
            });
        }

        return $this->sortResult($result);
    }

    public function userContestResult($username)
    {
        $users = json_decode($this->result);

        foreach ($users as $index => $user) {
            if ($user->username === $username) {
                $user->place = $index + 1;
                return $user;
            }
        }
    }

    public function sortResult($result = null)
    {
        if ($result) {
            $this->result = $result;
        }

        $resultsCollection = collect(
            is_string($this->result)
            ? json_decode($this->result, true)
            : $this->result
        );

        $sortedResults = $resultsCollection
            ->sortByDesc('total_score')
            ->values()
            ->toArray();

        $this->update([
            'result' => json_encode($sortedResults),
        ]);
        return $sortedResults;
    }
}
