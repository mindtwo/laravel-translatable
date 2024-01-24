<?php

namespace mindtwo\LaravelTranslatable\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use mindtwo\LaravelAutoCreateUuid\AutoCreateUuid;

/**
 * @property ?int $id
 * @property ?string $uuid
 * @property ?string $translatable_type
 * @property ?int $translatable_id
 * @property ?string $key
 * @property ?string $locale
 * @property ?string $text
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Translatable extends Model
{
    use AutoCreateUuid;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'translatable';

    /**
     * Get the parent translatable model.
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
