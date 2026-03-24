<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'topic',
        'keywords',
        'tone',
        'audience',
        'depth',
        'title',
        'slug',
        'excerpt',
        'intro',
        'sections',
        'takeaway',
        'tags',
        'reading_time',
        'model',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'tags' => 'array',
        ];
    }

    /**
     * Get the user who generated the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Use slugs in generated URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
