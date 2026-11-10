@extends('layouts.app')
@section('content')
    <form name="editalbum" id="editalbum" method="POST" action="{{URL::route('update_album')}}"
          enctype="multipart/form-data">
        @csrf
        <div class="container">
            <div class="row">
                <div class="col-sm-8">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="form-group">
                                <label for="name">Album Name</label>
                                <input name="name" type="text" class="form-control" placeholder="Album Name"
                                       value="{{$album->name}}">
                                <label class="text-danger" for="name"></label>
                            </div>
                            <div class="form-group">
                                <label for="description">Album Description</label>
                                <textarea name="description" type="text" class="form-control"
                                          placeholder="Album description" cols="30"
                                          rows="10">{{$album->description}}</textarea>
                            </div>
                            <div class="form-group">
                                <label for="">Publish At</label>
                                <input type="date" name="publishDate" class="form-control" @if($album->publishDate != "")value="{{ date('Y-m-d', strtotime($album->publishDate)) }}" @endif>
                            </div>
                            <label for="">Select Gallery Category</label>

                            <div class="form-group form-inline">
                                @php
                                    $category = explode(",","$album->category")
                                @endphp
                                @foreach($categories as $cat)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="category[]"
                                               value="{{ $cat->id }}" <?=(in_array($cat->id, $category)) ? "checked" : " ";?>>
                                        <label class="form-check-label" for="Checkbox">{{ $cat->name }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <label class="text-danger" for="category"></label>
                            <input type="hidden" name="id" id="album_id" value="{{$album->id}}">

                        </div>
                        <div class="col-sm-12">
                            <button type="button" class="btn btn-primary btn-large" data-toggle="modal"
                                    data-target="#imageUploadModal">Add New Image to Album
                            </button>
                            <button type="submit" form="delete-album-form" class="btn btn-danger btn-large">Delete Album</button>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="album-img-container">
                        <input type="file" name="cover_image" id="imgupload" style="display:none"
                               onchange="readURL(this)"/>
                        <a onclick="editAlbumThumbnail()" class="album-edit btn"><i class="fas fa-user-edit"></i></a>
                        <img class="media-object pull-left" alt="{{$album->name}}"
                             src="{{ asset('/uploads/albums/'.$album->cover_image) }}" width="350px">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-12 mt-5">
                    <button type="button" class="btn btn-primary" onclick="javascript:submitGallery()">Submit</button>
                </div>
            </div>
        </div>
    </form>
    <form id="delete-album-form" method="POST" action="{{ route('delete_album', ['id' => $album->id]) }}" style="display:none" onsubmit="return confirm('Are you sure?')">@csrf @method('DELETE')</form>
    @include("albums/imageModal",["images" => $images])
@endsection
