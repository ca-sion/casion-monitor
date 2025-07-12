<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\ValueObjects\Gender;
use Illuminate\Support\Carbon;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MichaelRavedoni\LaravelValueObjects\Casts\ValueObjectCast;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use App\Models\Injury;
use App\Models\RecoveryProtocol;
use App\Enums\InjuryStatus;

class Athlete extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;

    /** @use HasFactory<\Database\Factories\AthleteFactory> */
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        //
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['name', 'current_training_plan'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday'        => 'date',
            'last_connection' => 'datetime',
            'gender'          => ValueObjectCast::class.':'.Gender::class,
        ];
    }

    /**
     * Get the metrics for the athlete.
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    /**
     * The trainers that belong to the athlete.
     */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(Trainer::class);
    }

    /**
     * The training plans that belong to the athlete.
     */
    public function trainingPlans(): BelongsToMany
    {
        return $this->belongsToMany(TrainingPlan::class, 'assigned_training_plans', 'athlete_id', 'training_plan_id')
            ->withPivot('start_date', 'is_customized')
            ->with('weeks');
    }

    /**
     * The feedbacks that belong to the athlete.
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

   /**
    * Get the injuries for the athlete.
    */
   public function injuries(): HasMany
   {
       return $this->hasMany(Injury::class);
   }

   /**
    * Get the recovery protocols for the athlete.
    */
   public function recoveryProtocols(): HasMany
   {
       return $this->hasMany(RecoveryProtocol::class);
   }

   /**
    * Check if the athlete has any active injuries.
    */
   public function hasActiveInjuries(): bool
   {
       return $this->injuries()->whereNot('status', InjuryStatus::RESOLVED)->exists();
   }

    /**
     * Get the athlete's current training plan.
     */
    protected function currentTrainingPlan(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->trainingPlans()->orderByDesc('assigned_training_plans.start_date')->first(),
        );
    }

    /**
     * Get the athlete's name.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    /**
     * Get the athlete's birthday year.
     */
    protected function birthYear(): Attribute
    {
        return Attribute::make(
            get: fn () => isset($this->birthday) ? Carbon::parse($this->birthday)->year : null,
        );
    }

    /**
     * Get the athlete's name with initials.
     */
    protected function initials(): Attribute
    {
        $f = $this->first_name ? (str($this->first_name)->substr(0, 1)->ucfirst()) : null;
        $l = $this->last_name ? (str($this->last_name)->substr(0, 1)->ucfirst()) : null;

        return Attribute::make(
            get: fn () => $f.($l ?? null),
        );
    }

    /**
     * Get athlete hash.
     */
    protected function hash(): Attribute
    {
        return Attribute::make(
            get: fn () => Hashids::connection('athlete_hash')->encode($this->id),
        );
    }

    /**
     * Get athlete unique account url.
     */
    protected function accountLink(): Attribute
    {
        return Attribute::make(
            get: fn () => route('athletes.dashboard', ['hash' => $this->hash]),
        );
    }
}
