<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Category;
use App\Models\News;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $news = News::orderBy('news_date', 'desc')->limit(2)->get();
        return view('frontend.home', compact('news'));
    }

    public function newsList(): View
    {
        $news = News::paginate(3);
        return view('frontend.newslist', compact('news'));
    }

    public function getArchiveNews(int $year): View
    {
        $news = News::whereYear('news_date', $year)->paginate(3);
        return view('frontend.newslist', compact('news'));
    }

    public function newsDetail(int $id): View
    {
        $news = News::findOrFail($id);
        $recent = News::orderBy('id', 'desc')->take(5)->get();
        $news_cat = Category::get();
        return view('frontend.newsdetails', compact('news', 'recent', 'news_cat'));
    }

    public function galleryList(): View
    {
        $gallery = Album::paginate(8);
        return view('frontend.gallerylist', compact('gallery'));
    }

    public function galleryDetail(int $id): View
    {
        $gallery_detail = Album::findOrFail($id);
        $gallery = Album::paginate(9);
        $images = $gallery_detail->Photos;
        $gallery_name = $gallery_detail->name;
        return view('frontend.gallerydetail', compact('gallery', 'images', 'gallery_name'));
    }
}
