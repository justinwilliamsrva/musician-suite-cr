<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class Gig extends Model
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event_type',
        'street_address',
        'city',
        'state',
        'zip_code',
        'start_time',
        'end_time',
        'description',
        'user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Get the User who owns the Gig.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Jobs that belong to a Gig.
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function numberOfFilledJobs()
    {
        $numberOfJobs = $this->jobs()->count();
        $numberOfFilledJobs = $this->jobs()->whereRelation('users', function ($query) {
            $query->where('status', 'Booked');
        })->count();

        return $numberOfFilledJobs.'/'.$numberOfJobs;
    }

    public function numberOfUnfilledJobs()
    {
        $numberOfUnfilledJobs = $this->jobs()
            ->whereDoesntHave('users', function ($query) {
                $query->where('status', 'Booked');
            })
        ->count();

        return $numberOfUnfilledJobs;
    }

    public function getAllInstruments()
    {
        $instruments = '';

        foreach ($this->jobs as $job) {
            $instruments .= implode(', ', json_decode($job->instruments)).', ';
        }

        return rtrim($instruments, ', ');
    }

    public function getPaymentRange()
    {
        $maxPayment = $this->jobs->max('payment');
        $minPayment = $this->jobs->min('payment');

        if ($maxPayment != $minPayment) {
            return '$'.$minPayment.' - $'.$maxPayment;
        }

        return '$'.$maxPayment;
    }

    public function getEndTime()
    {
        if (strtotime(date_format($this->start_time, 'm/d/y')) != strtotime(date_format($this->end_time, 'm/d/y'))) {
            return ' - '.date_format($this->end_time, 'D, m/d/y g:i a');
        }

        return ' - '.date_format($this->end_time, 'g:i a');
    }

    public function getGoogleMapsLink()
    {
        return 'http://maps.google.com/?q='.$this->street_address.', '.$this->city.', '.$this->state.' '.$this->zip_code;
    }

    public function bookedMusicians()
    {
        $bookedEmails = [];
        foreach ($this->jobs as $job) {
            if ($job->users()->select(['users.*'])->wherePivot('status', 'Booked')->count() > 0) {
                $bookedEmails[] = $job->users()->select(['users.*'])->wherePivot('status', 'Booked')->first()->email;
            }
        }

        return $bookedEmails;
    }
}
