<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\BlogResource;
use App\Models\Blog;

class BlogController extends Controller
{
    public function index()
    {
        return BlogResource::collection(Blog::paginate(10));
    }
}
