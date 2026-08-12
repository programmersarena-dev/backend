<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected $disk = 'local';

    /**
     * List all files and directories with their paths
     */
    public function index(Request $request)
    {
        // The directory path sent from React (e.g., "my-folder")
        $directory = $request->query('directory', '');

        // Get ONLY files in this specific folder (not subfolders)
        $files = Storage::disk($this->disk)->files($directory);

        // Get ONLY folders in this specific folder
        $directories = Storage::disk($this->disk)->directories($directory);

        return response()->json([
            'current_directory' => $directory,
            'directories' => $directories,
            'files' => $files,
        ]);
    }

    /**
     * Save a file to the directory
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'path' => 'nullable|string'
        ]);

        $targetPath = $request->input('path', '');

        if ($request->hasFile('file')) {
            // Saves to storage/app/{targetPath}
            $path = $request->file('file')->store($targetPath, $this->disk);

            return response()->json([
                'message' => 'File uploaded successfully',
                'path' => $path
            ]);
        }
    }

    public function makeDirectory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'path' => 'nullable|string' // The directory we are currently in
        ]);

        $currentDir = $request->input('path', '');
        // Combine current path with new folder name
        $newFolderPath = $currentDir ? $currentDir . '/' . $request->name : $request->name;

        if (Storage::disk($this->disk)->exists($newFolderPath)) {
            return response()->json(['error' => 'Directory already exists'], 422);
        }

        Storage::disk($this->disk)->makeDirectory($newFolderPath);

        return response()->json(['message' => 'Directory created successfully']);
    }

    /**
     * Delete a file from the directory
     */
    public function destroy(Request $request)
    {
        $path = $request->input('path');

        // Security check: ensure path is provided and exists on the local disk
        if ($path && Storage::disk($this->disk)->exists($path)) {
            Storage::disk($this->disk)->delete($path);
            return response()->json(['message' => 'File deleted']);
        }

        return response()->json(['error' => 'File not found'], 404);
    }
}
