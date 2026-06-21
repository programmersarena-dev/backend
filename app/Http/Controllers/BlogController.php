<?php

namespace App\Http\Controllers;

use App\Http\Resources\User\BlogResource;
use App\Models\Blog;

class BlogController extends Controller
{
    public function index()
    {
        return BlogResource::collection(Blog::paginate(10));
    }
}
