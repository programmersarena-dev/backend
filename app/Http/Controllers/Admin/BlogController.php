<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Models\BlogTranslation;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Http\Requests\StoreBlogRequest;
use App\Http\Requests\UpdateBlogRequest;

class BlogController extends Controller
{
    public function index()
    {
        $blogs = Blog::orderByDesc('id')->paginate(10);
        return BlogResource::collection($blogs);
    }

    public function store(StoreBlogRequest $request)
    {
        $data = $request->validated();
        $blog = Blog::create([
            'title' => $data['title'],
            'description' => $data['description'],
        ]);

        $blogTranslationEN = BlogTranslation::create([
            'blog_id' => $blog->id,
            'language' => 'en',
            'title' => $data['title_en'],
            'description' => $data['description_en'],
        ]);

        $blogTranslationRU = BlogTranslation::create([
            'blog_id' => $blog->id,
            'language' => 'ru',
            'title' => $data['title_ru'],
            'description' => $data['description_ru'],
        ]);

        return new BlogResource($blog);
    }

    public function edit(Blog $blog)
    {
        return [
            "title" => $blog->title,
            "description" => $blog->description,
            "title_en" => $blog->getTranslation("title","en"),
            "description_en" => $blog->getTranslation("description","en"),
            "title_ru" => $blog->getTranslation("title","ru"),
            "description_ru" => $blog->getTranslation("description","ru"),
        ];
    }

    public function update(UpdateBlogRequest $request, Blog $blog)
    {
        $data = $request->validated();
        $blog->update([
            'title' => $data['title'],
            'description' => $data['description'],
        ]);

        $blog_en = BlogTranslation::where('blog_id',$blog->id)->where('language','en')->firstOrFail();
        $blog_en->update([
            'title' => $data['title_en'],
            'description' => $data['description_en'],
        ]);

        $blog_ru = BlogTranslation::where('blog_id',$blog->id)->where('language','ru')->firstOrFail();
        $blog_ru->update([
            'title' => $data['title_ru'],
            'description' => $data['description_ru'],
        ]);

        return response()->json([
            'message' => 'Üstünlikli üýtgedildi',
            'blog' => $blog,
        ], 202);
    }

    public function destroy(Blog $blog)
    {
        $blog_en = BlogTranslation::where('blog_id',$blog->id)->where('language','en')->firstOrFail();
        $blog_ru = BlogTranslation::where('blog_id',$blog->id)->where('language','ru')->firstOrFail();
        
        $blog->delete();
        $blog_en->delete();
        $blog_ru->delete();

        return response()->json(['message' => 'Üstünlikli pozuldy'], 202);
    }
}
