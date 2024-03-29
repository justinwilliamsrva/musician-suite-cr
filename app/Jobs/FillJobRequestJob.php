<?php

namespace App\Jobs;

use App\Mail\Host\FillJobRequest;
use App\Models\Job;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class FillJobRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $user_id;

    public Job $newJob;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($user_id, $newJob)
    {
        $this->user_id = $user_id;
        $this->newJob = $newJob;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = $this->newJob->gig->user;
        $applicant = User::find($this->user_id);
        Mail::to($user->email)->send(new FillJobRequest($applicant, $user, $this->newJob));
    }
}
