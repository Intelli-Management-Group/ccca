<?php

namespace App\Http\Controllers;

use App\Models\News;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Category;

class NewsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $news = News::all();
        return view('news.index',compact('news'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::all();
        return view('news.create',compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|array',
            'category.*' => 'string|max:255',
            'news_date' => 'nullable|date',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);


        if ($files = $request->file('image')) {
            $profileImage = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $files->guessExtension();
            $files->move(public_path('uploads'), $profileImage);
        }


//        $fileName = time().'.'.$request->file->extension();
//        $request->file->move(public_path('uploads'), $fileName);
        $news = new News();
        $news->title = $request->title;
        $news->description = $request->description;
        $news->news_date = Carbon::parse($request->news_date);
        $news->category = implode(',',$request->category);
        $news->banner = $profileImage;
        $news->save();
        return redirect('/news')->with('success','News created successfully!');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\News  $news
     * @return \Illuminate\Http\Response
     */
    public function show(News $news)
    {
        return view('news.show', compact('news'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\News  $news
     * @return \Illuminate\Http\Response
     */
    public function edit(News $news)
    {
        $categories = Category::all();
        return view('news.edit', compact('news','categories'));

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\News  $news
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, News $news)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|array',
            'category.*' => 'string|max:255',
            'news_date' => 'nullable|date',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
        if ($files = $request->file('image')) {
            $profileImage = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $files->guessExtension();
            $files->move(public_path('uploads'), $profileImage);
            $news->banner = $profileImage;
        }
        $news->title = $request->title;
        $news->description = $request->description;
        $news->news_date = Carbon::parse($request->news_date);
        $news->category = implode(',',$request->category);
        $news->save();
        return redirect('/news')->with('success','News updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\News  $news
     * @return \Illuminate\Http\Response
     */
    public function destroy(News $news)
    {
        $news->delete();
        return redirect('/news')->with('success','News deleted successfully!');
    }
}
