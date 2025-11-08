<?php

namespace App\Models;

use App\Enums\ProfessionalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Professional extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type'   => ProfessionalType::class,
        ];
    }

    public function healthEvents(): HasMany
    {
        return $this->hasMany(HealthEvent::class);
    }
    
    /**
     * Définit le mutateur pour l'attribut 'name'.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => [
                'name' => $this->first_name . ' ' . $this->last_name,
            ],
        );
    }
}
