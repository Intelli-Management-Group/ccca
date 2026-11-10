<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Image;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImageController extends Controller
{
    public function getForm(int $id): View
    {
        $album = Album::findOrFail($id);
        return view('albums.addimage', compact('album'));
    }

    public function postAdd(Request $request): RedirectResponse
    {
        $request->validate([
            'album_id' => 'required|numeric|exists:albums,id',
            'description' => 'nullable|string|max:1000',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        $file = $request->file('image');
        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_album_image.' . $file->guessExtension();
        $file->move(public_path('uploads/albums'), $filename);

        $image = new Image();
        $image->description = $request->input('description');
        $image->image = $filename;
        $image->album_id = $request->input('album_id');
        $image->save();

        return redirect()->route('show_album', ['id' => $request->input('album_id')]);
    }

    public function getDelete(int $id): RedirectResponse
    {
        $image = Image::findOrFail($id);
        $path = public_path('uploads/albums/' . basename((string) $image->image));
        if (is_file($path)) {
            unlink($path);
        }
        $image->delete();
        return redirect()->route('show_album', ['id' => $image->album_id]);
    }

    public function postMove(Request $request): RedirectResponse
    {
        $request->validate([
            'new_album' => 'required|numeric|exists:albums,id',
            'photo' => 'required|numeric|exists:images,id',
        ]);

        $image = Image::findOrFail($request->input('photo'));
        $image->album_id = $request->input('new_album');
        $image->save();

        return redirect()->route('show_album', ['id' => $request->input('new_album')]);
    }
}
