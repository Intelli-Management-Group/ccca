<?php

namespace App\Http\Controllers;

use App\Models\AlbumCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class AlbumCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->ajax()){
            $data = AlbumCategory::latest()->get();
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('created_at', function ($row) {
                    //change over here
                    return date('d-m-Y', strtotime($row->created_at) );
                })
                ->addColumn('action', function($row){

                    $btn = ' <a href="javascript:void(0)" data-toggle="tooltip"  data-id="'.$row->id.'" data-original-title="Delete" class="btn btn-danger btn-sm deleteProduct">delete</a>';

                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('albums.albumscat');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
            'category' => 'required|string|max:255',
        ]);

        $category = new AlbumCategory();
        $category->name = $request->category;
        $category->save();
        return response()->json(['success'=>'Category Added.']);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\AlbumCategory  $albumCategory
     * @return \Illuminate\Http\Response
     */
    public function show(AlbumCategory $albumCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\AlbumCategory  $albumCategory
     * @return \Illuminate\Http\Response
     */
    public function edit(AlbumCategory $albumCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\AlbumCategory  $albumCategory
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, AlbumCategory $albumCategory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\AlbumCategory  $albumCategory
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        AlbumCategory::findOrFail($id)->delete();
        return response()->json(['success'=>'Category deleted successfully.']);

    }
}
