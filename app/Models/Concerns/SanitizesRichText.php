<?php

namespace App\Models\Concerns;

use App\Support\RichText;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait SanitizesRichText
{
    // Sanitize writes and legacy stored content when it is read by an API.
    protected function richTextAttribute(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => RichText::sanitize($value),
            set: fn ($value) => RichText::sanitize($value),
        )->shouldCache();
    }

    protected function description(): Attribute
    {
        return $this->richTextAttribute();
    }

    protected function input(): Attribute
    {
        return $this->richTextAttribute();
    }

    protected function output(): Attribute
    {
        return $this->richTextAttribute();
    }

    protected function note(): Attribute
    {
        return $this->richTextAttribute();
    }
}
