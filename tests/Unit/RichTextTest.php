<?php

namespace Tests\Unit;

use App\Models\Blog;
use App\Support\RichText;
use PHPUnit\Framework\TestCase;

class RichTextTest extends TestCase
{
    public function test_removes_executable_html_and_preserves_problem_markup(): void
    {
        $html = RichText::sanitize('<p><strong>Sum</strong></p><pre><code>a &lt; b</code></pre>'
            .'<img src="/storage/example.png" onerror="alert(1)"><a href="javascript:alert(1)">link</a>'
            .'<script>alert(1)</script><iframe src="https://example.com"></iframe>');
        $this->assertStringContainsString('<strong>Sum</strong>', $html);
        $this->assertStringContainsString('a &lt; b', $html);
        $this->assertStringContainsString('/storage/example.png', $html);
        foreach (['onerror', 'javascript:', '<script', '<iframe'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
    }

    public function test_legacy_content_is_sanitized_on_read_and_new_content_on_write(): void
    {
        $blog = new Blog;
        $blog->setRawAttributes(['description' => '<p onclick="alert(1)">Old</p>']);
        $this->assertSame('<p>Old</p>', $blog->description);
        $blog->description = '<p>New</p><script>bad()</script>';
        $this->assertSame('<p>New</p>', $blog->getAttributes()['description']);
    }
}
