<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\News;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Category::latest()->get();
            return Datatables::of($data)
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


        return view('category.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

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

        $category = new Category();
        $category->name = $request->category;
        $category->save();
        return response()->json(['success'=>'Category Added.']);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function show(Category $category)
    {
//        return $category;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function edit(Category $category)
    {

//        return response()->json([
//            'data' => category
//        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Category $category)
    {
//        $request->validate([
//            'name' => 'required',
//        ]);
//        $category->name = $request->name();
//        $category->save();
//
//        return response()->json([
//            'message' => 'category updated!',
//            'success' => 'true'
//        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Category s $category
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        Category::findOrFail($id)->delete();


        return response()->json(['success'=>'Category deleted successfully.']);
//        $category->delete();
//        return response()->json([
//            'message' => 'category deleted'
//        ]);
    }
}
