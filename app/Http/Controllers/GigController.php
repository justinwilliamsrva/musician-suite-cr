<?php

namespace App\Http\Controllers;

use App\Jobs\ChosenForJobJob;
use App\Jobs\NewJobAvailableJob;
use App\Models\Gig;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GigController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = User::find(Auth::id());

        $openJobs = Job::select('jobs.id as job_id', 'jobs.*', 'gigs.*')
        ->whereDoesntHave('users', function ($query) {
            $query->where('status', 'Booked');
        })
        ->join('gigs', 'jobs.gig_id', '=', 'gigs.id')
        ->where('gigs.start_time', '>', now())
        ->orderBy('gigs.start_time')
        ->paginate(10, ['*'], 'openJobs')
        ->fragment('openJobs');

        $openJobs->each(function ($job) {
            $job->id = $job->job_id;
        });

        $userJobs = Job::whereHas('users', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
        ->whereDoesntHave('users', function ($query) use ($user) {
            $query->where('user_id', '<>', $user->id)
                ->where('status', '=', 'booked');
        })
        ->with(['users' => function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->select('users.id', 'name', 'email', 'phone_number', 'instruments', 'admin', 'email_verified_at', 'status');
        }])
        ->join('gigs', 'jobs.gig_id', '=', 'gigs.id')
        ->select('jobs.*', 'gigs.start_time')
        ->where('gigs.start_time', '>', now())
        ->orderBy('gigs.start_time')
        ->paginate(4, ['*'], 'userJobs')
        ->fragment('userJobs');

        $userGigs = $user->gigs()
        ->with('jobs')
        ->where('start_time', '>', now())
        ->orderBy('start_time')
        ->paginate(4, ['*'], 'userGigs')
        ->fragment('userGigs');

        return view('musician-finder.dashboard', ['openJobs' => $openJobs, 'userJobs' => $userJobs, 'userGigs' => $userGigs]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('musician-finder.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_type' => 'required|string|min:3|max:50',
            'start_date_time' => 'required|date',
            'end_date_time' => 'required|date|after_or_equal:start_date_time',
            'street_address' => 'required|string|min:3|max:255',
            'city' => 'required|string|max:30',
            'musician-number' => 'numeric',
            'state' => ['required', Rule::in(config('gigs.states'))],
            'zip_code' => 'required|digits:5|integer',
            'description' => 'string|min:3|max:255|nullable',
            'musicians' => 'required|array|max:6',
            'musicians.*.fill_status' => 'required|string',
            'musicians.*.instruments' => ['required', 'array', 'min:1', 'max:10', Rule::in(config('gigs.instruments'))],
            'musicians.*.payment' => 'required|numeric|min:0',
            'musicians.*.extra_info' => 'string|min:3|max:255|nullable',
        ]);
        $gig = Gig::create([
            'event_type' => $validated['event_type'],
            'start_time' => $validated['start_date_time'],
            'end_time' => $validated['end_date_time'],
            'street_address' => $validated['street_address'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'zip_code' => $validated['zip_code'],
            'description' => $validated['description'] ?? '',
            'user_id' => Auth::id(),
        ]);

        foreach ($validated['musicians'] as $job) {
            $newJob = Job::create([
                'instruments' => json_encode($job['instruments']),
                'payment' => $job['payment'],
                'extra_info' => $job['extra_info'] ?? '',
                'gig_id' => $gig->id,
            ]);

            if ($job['fill_status'] == 'filled') {
                $newJob->users()->attach(1, ['status' => 'Booked']);
            }
        }

        NewJobAvailableJob::dispatch($gig);

        return redirect()->route('musician-finder.dashboard')->with('success', $gig->event_type.' Created Successfully');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Gig $gig)
    {
        $user = Auth::user();

        return view('musician-finder.show', ['gig' => $gig, 'user' => $user]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Gig $gig)
    {
        if (Auth::user()->admin != 1) {
            $this->authorize('update', $gig);
        }

        $jobsArray = $gig->jobs->toArray();

        foreach ($gig->jobs as $key => $job) {
            $isJobBooked = $job->jobHasBeenBooked($job);
            $userBooked = $job->users()->select(['users.*'])->wherePivot('status', 'Booked')->first()->name ?? '';
            $numberOfJobApplicants = $job->users()->count();
            $jobUsers = json_encode($job->users);

            $jobsArray[$key] = [
                'id' => $job->id,
                'payment' => $job->payment,
                'users' => $jobUsers,
                'extra_info' => $job->extra_info,
                'instruments' => json_decode($job->instruments),
                'isJobBooked' => $isJobBooked,
                'userBooked' => $userBooked,
                'numberOfJobApplicants' => $numberOfJobApplicants,
            ];
        }

        return view('musician-finder.edit', ['gig' => $gig, 'jobsArray' => $jobsArray]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Gig $gig)
    {
        if (Auth::user()->admin != 1) {
            $this->authorize('update', $gig);
        }

        $validated = $request->validate([
            'event_type' => 'required|string|min:3|max:50',
            'start_date_time' => 'required|date',
            'end_date_time' => 'required|date|after_or_equal:start_date_time',
            'street_address' => 'required|string|min:3|max:255',
            'city' => 'required|string|max:30',
            'musician-number' => 'numeric',
            'state' => ['required', Rule::in(config('gigs.states'))],
            'zip_code' => 'required|digits:5|integer',
            'description' => 'string|min:3|max:255|nullable',
            'musicians' => 'required|array|min:1|max:6',
        ]);

        $gig->fill([
            'event_type' => $validated['event_type'],
            'start_time' => $validated['start_date_time'],
            'end_time' => $validated['end_date_time'],
            'street_address' => $validated['street_address'],
            'city' => $validated['city'],
            'state' => $validated['state'],
            'zip_code' => $validated['zip_code'],
            'description' => $validated['description'] ?? '',
        ]);

        $gig->save();

        foreach ($validated['musicians'] as $key => $job) {
            // Delete Before Validation
            $status = $job['fill_status'] ?? $job['musician_picked'];
            if ($status == 'delete') {
                if (! isset($job['id'])) {
                    continue;
                }

                $jobToDelete = Job::find($job['id']);
                $jobToDelete->users()->detach();
                Job::destroy($jobToDelete->id);

                continue;
            }

            // Validate Current Musician
            $request->validate([
                'musicians.'.$key.'.id' => 'numeric|nullable',
                'musicians.'.$key.'.fill_status' => 'string|nullable',
                'musicians.'.$key.'.musician_picked' => 'max:15|nullable',
                'musicians.'.$key.'.instruments' => ['required', 'array', 'min:1', 'max:10', Rule::in(config('gigs.instruments'))],
                'musicians.'.$key.'.payment' => 'required|numeric|min:0',
                'musicians.'.$key.'.extra_info' => 'string|min:3|max:255|nullable',
            ]);

            // Create for update Jobs
            $newJob = Job::updateOrCreate([
                'id' => $job['id'] ?? Job::next(),
            ], [
                'instruments' => json_encode($job['instruments']),
                'payment' => $job['payment'],
                'extra_info' => $job['extra_info'] ?? '',
                'gig_id' => $gig->id,
            ]);

            $newJob->save();

            if ($newJob->wasRecentlyCreated) {
                NewJobAvailableJob::dispatch($gig, $newJob);
            }

            // Fill in pivot Table
            if ($status == 'filled') {
                $newJob->users()->attach(1, ['status' => 'Booked']);
            }
            if (is_numeric($status)) {
                $newJob->users()->updateExistingPivot($job['musician_picked'], ['status' => 'Booked']);
                if ($job['musician_picked'] != Auth::id()) {
                    ChosenForJobJob::dispatch($job['musician_picked'], $newJob);
                }
            }
        }

        return redirect()->back()->with('success', $gig->event_type.' Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Gig $gig)
    {
        $this->authorize('update', $gig);

        Job::where('gig_id', $gig->id)->each(function ($job) {
            $job->users()->detach();
            $job->delete();
        });

        $event_type = $gig->event_type;
        $gig->delete();

        return redirect()->route('musician-finder.dashboard')->with('success', $event_type.' Deleted Successfully');
    }

    public function applyToJob(Job $job)
    {
        $this->authorize('apply-to-job', $job);
        $job->users()->attach(Auth::id(), ['status' => 'Applied']);

        return redirect()->route('musician-finder.dashboard')->with('success', 'You\'ve applied to the Job Successfully');
    }

    public function applyToJobGet()
    {
        $job_id = request()->query('job');
        $job = Job::find($job_id);

        $user_id = request()->query('user');
        $user = User::find($user_id);

        if (! Gate::forUser($user)->allows('apply-to-job', $job)) {
            abort(403);
        }

        $job->users()->attach($user->id, ['status' => 'Applied']);

        return redirect()->route('musician-finder.dashboard')->with('success', 'You\'ve applied to the Job Successfully');
    }
}
