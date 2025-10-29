<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Carbon;
use Vinkla\Hashids\Facades\Hashids;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Trainer extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;

    /** @use HasFactory<\Database\Factories\TrainerFactory> */
    use HasFactory;
    use Notifiable, HasPushSubscriptions;

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
    protected $appends = ['name'];

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
        ];
    }

    /**
     * Get the trainer's name.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => "{$this->first_name} {$this->last_name}",
        );
    }

    /**
     * Get the trainer's birthday year.
     */
    protected function birthYear(): Attribute
    {
        return Attribute::make(
            get: fn () => isset($this->birthday) ? Carbon::parse($this->birthday)->year : null,
        );
    }

    /**
     * Get the trainer's name with initials.
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
     * Get trainer hash.
     */
    protected function hash(): Attribute
    {
        return Attribute::make(
            get: fn () => Hashids::connection('trainer_hash')->encode($this->id),
        );
    }

    /**
     * Get trainer unique account url.
     */
    protected function accountLink(): Attribute
    {
        return Attribute::make(
            get: fn () => route('trainers.dashboard', ['hash' => $this->hash]),
        );
    }

    /**
     * The athletes that belong to the trainer.
     */
    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(Athlete::class);
    }

    /**
     * The feedbacks that belong to the trainer.
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Get the notification preferences for the trainer.
     */
    public function notificationPreferences(): MorphMany
    {
        return $this->morphMany(NotificationPreference::class, 'notifiable');
    }
}
