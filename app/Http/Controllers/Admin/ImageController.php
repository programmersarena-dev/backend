<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImageController extends Controller
{
    /**
     * Display a listing of the images.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $images = Storage::disk('public')->files('images');

        $imageUrls = array_map(fn($image) => Storage::url($image), $images);

        return response()->json([
            'success' => true,
            'images' => $imageUrls,
        ]);
    }

    /**
     * Store newly uploaded images.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:20048',
        ]);

        $uploadedPaths = [];
        foreach ($request->file('images') as $image) {
            $path = $image->store('images', 'public');
            $uploadedPaths[] = Storage::url($path);
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'paths' => $uploadedPaths,
        ]);
    }

    /**
     * Remove the specified image from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = str_replace(Storage::url(''), '', $request->input('path'));

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Image not found',
        ], 404);
    }
}
