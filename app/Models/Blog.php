<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'title',
        'description',
    ];

    public function getTranslation($field = '', $language = false)
    {
        $language = $language == false ? app()->getLocale() : $language;
        $translations = $this->translations->where('language', $language)->first();
        return $translations != null ? $translations->$field : $this->$field;
    }

    public function translations()
    {
        return $this->hasMany(BlogTranslation::class, 'blog_id', 'id');
    }
}
