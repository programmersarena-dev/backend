<?php

namespace App\Models;

use App\Models\Concerns\SanitizesRichText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogTranslation extends Model
{
    use HasFactory;
    use SanitizesRichText;

    protected $fillable = [
        'blog_id',
        'language',
        'title',
        'description',
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class, 'id', 'blog_id');
    }
}
