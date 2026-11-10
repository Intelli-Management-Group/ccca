<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumCategory;
use App\Models\AlbumImagesDetail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class AlbumController extends Controller
{
    private const IMAGE_RULE = 'image|mimes:jpeg,png,jpg,gif,webp|max:5120';

    public function getList(): View
    {
        $albums = Album::with('Photos')->get();
        return view('albums.index', compact('albums'));
    }

    public function getAlbum(int $id): View
    {
        $album = Album::with('Photos')->findOrFail($id);
        $albums = Album::all();
        return view('albums.album', compact('album', 'albums'));
    }

    public function editAlbum(int $id): View
    {
        $album = Album::findOrFail($id);
        $images = $album->Photos;
        $categories = AlbumCategory::all();
        return view('albums.editalbum', compact('album', 'images', 'categories'));
    }

    public function getForm(): View
    {
        $categories = AlbumCategory::all();
        return view('albums.createalbum', compact('categories'));
    }

    public function updateAlbum(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:albums,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|array',
            'category.*' => 'string|max:255',
            'cover_image' => 'nullable|' . self::IMAGE_RULE,
            'publishDate' => 'nullable|date',
            'deleted_id' => 'nullable|string',
            'sort_ids' => 'nullable|string',
            'allImageFiles' => 'nullable|array',
            'allImageFiles.*' => 'file|' . self::IMAGE_RULE,
            'allImageData' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $album = Album::findOrFail($request->input('id'));

        if ($file = $request->file('cover_image')) {
            $this->deleteFile(public_path('uploads/albums/' . basename((string) $album->cover_image)));
            $album->cover_image = $this->storeUpload($file, public_path('uploads/albums'), '_cover');
        }
        $album->name = $request->input('name');
        $album->description = $request->input('description');
        $album->category = implode(',', $request->input('category'));
        if ($request->filled('publishDate')) {
            $album->publishDate = Carbon::parse($request->input('publishDate'))->format('Y-m-d');
        }
        $album->save();

        $deleted_ids = $request->input('deleted_id');
        if ($deleted_ids) {
            foreach (array_filter(array_map('intval', explode(',', $deleted_ids))) as $d) {
                $image_file = AlbumImagesDetail::where('id', $d)->where('albumId', $album->id)->first();
                if ($image_file) {
                    if ($image_file->fileName) {
                        $this->deleteFile(public_path('uploads/albums/' . $album->id . '/' . basename($image_file->fileName)));
                    }
                    $image_file->delete();
                }
            }
        }

        $sort_ids = json_decode((string) $request->input('sort_ids'), true);
        if (is_array($sort_ids)) {
            foreach ($sort_ids as $id => $value) {
                $al_file = AlbumImagesDetail::where('id', (int) $id)->where('albumId', $album->id)->first();
                if ($al_file) {
                    $al_file->sortIndex = (int) $value;
                    $al_file->save();
                }
            }
        }

        $this->saveGalleryImages($request, $album);

        $request->session()->flash('success', 'Album updated successfully!');
        return response()->json(['list' => url('albums')]);
    }

    public function postCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|array',
            'category.*' => 'string|max:255',
            'cover_image' => 'required|' . self::IMAGE_RULE,
            'publishDate' => 'nullable|date',
            'allImageFiles' => 'nullable|array',
            'allImageFiles.*' => 'file|' . self::IMAGE_RULE,
            'allImageData' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $album = new Album();
        $album->name = $request->input('name');
        $album->description = $request->input('description');
        $album->category = implode(',', $request->input('category'));
        $album->cover_image = $this->storeUpload($request->file('cover_image'), public_path('uploads/albums'), '_cover');
        if ($request->filled('publishDate')) {
            $album->publishDate = Carbon::parse($request->input('publishDate'))->format('Y-m-d');
        }
        $album->save();

        $this->saveGalleryImages($request, $album);

        $request->session()->flash('success', 'Album created successfully!');
        return response()->json(['list' => url('albums')]);
    }

    public function getDelete(int $id): RedirectResponse
    {
        $album = Album::findOrFail($id);
        $this->deleteFile(public_path('uploads/albums/' . basename((string) $album->cover_image)));
        File::deleteDirectory(public_path('uploads/albums/' . $album->id));
        AlbumImagesDetail::where('albumId', $album->id)->delete();
        $album->delete();
        return redirect('/albums')->with('success', 'Album Deleted!');
    }

    private function saveGalleryImages(Request $request, Album $album): void
    {
        $album_images = $request->file('allImageFiles');
        $image_data = $request->input('allImageData', []);
        if (empty($album_images)) {
            return;
        }

        $imgPath = public_path('uploads/albums/' . $album->id);
        File::ensureDirectoryExists($imgPath, 0755);

        foreach ($album_images as $key => $file) {
            $meta = json_decode((string) ($image_data[$key] ?? ''), true);
            $meta = is_array($meta) ? $meta : [];

            $album_image = new AlbumImagesDetail();
            $album_image->albumId = $album->id;
            $album_image->sortIndex = (int) ($meta['sortIndex'] ?? 0);
            $album_image->caption = $meta['caption'] ?? null;
            $album_image->credit = $meta['credit'] ?? null;
            $album_image->save();

            $album_image->fileName = date('YmdHis') . '_' . $album->id . '_' . $album_image->id . '.' . $file->guessExtension();
            $album_image->save();
            $file->move($imgPath, $album_image->fileName);
        }
    }

    /** Store an upload under a server-generated name; the extension comes from file content, not the client. */
    private function storeUpload(UploadedFile $file, string $dir, string $suffix): string
    {
        File::ensureDirectoryExists($dir, 0755);
        $name = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . $suffix . '.' . $file->guessExtension();
        $file->move($dir, $name);
        return $name;
    }

    private function deleteFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
