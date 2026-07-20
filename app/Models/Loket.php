<?php

namespace App\Models;

use Database\Factories\LoketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property string $name
 * @property string|null $officer_name
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Loket extends Model
{
    /** @use HasFactory<LoketFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_id',
        'name',
        'officer_name',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * The ticket currently being called or served at this loket.
     *
     * @return HasMany<Ticket, $this>
     */
    public function currentTicket(): HasMany
    {
        return $this->hasMany(Ticket::class)
            ->whereIn('status', [Ticket::STATUS_CALLED, Ticket::STATUS_SERVING])
            ->latest('called_at');
    }

    /**
     * @param  Builder<Loket>  $query
     * @return Builder<Loket>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
