@extends('layouts.app')
@section('content')

<div class="container">
    <div class="row">
        <div class="col-sm-4">
            <div class="album-img-container">
                <input type="file" id="imgupload" style="display:none"/>
                <a onclick="editAlbumThumbnail()" class="album-edit btn"><i class="fas fa-user-edit"></i></a>
                <img class="media-object pull-left" alt="{{$album->name}}" src="{{ asset('/uploads/albums/'.$album->cover_image) }}" width="350px">
            </div>
        </div>
        <div class="col-sm-8">
            <div class="row">
                <div class="col-sm-12">
                    <h2 class="media-heading" style="font-size: 26px;">Album Name:</h2>
                    <p>{{$album->name}}</p>
                </div>
                <div class="col-sm-12">
                    <h2 class="media-heading" style="font-size: 26px;">Album Description :</h2>
                    <p>{{$album->description}}<p>
                </div>
                <div class="col-sm-12">
                    <a href="{{URL::route('add_image',array('id'=>$album->id))}}"><button type="button"class="btn btn-primary btn-large">Add New Image to Album</button></a>
                    <form method="POST" action="{{ route('delete_album', ['id' => $album->id]) }}" style="display:inline" onsubmit="return confirm('Are you sure?')">@csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-large">Delete Album</button></form>
                </div>
            </div>
        </div>
    </div>

    <div class="row pt-5">
        @foreach($album->Photos as $photo)
            <div class="col-lg-3">
                <div class="thumbnail" style="max-height: 350px;min-height: 350px;">
                    <img alt="{{$album->name}}" src="{{ asset('/uploads/albums/'.$photo->image) }}">
                    <div class="caption">
                        <p>{{$photo->description}}</p>
                        <p>Created date:  {{ date("d F Y",strtotime($photo->created_at)) }}at {{ date("g:ha",strtotime($photo->created_at)) }}</p>
                        <form method="POST" action="{{ route('delete_image', ['id' => $photo->id]) }}" style="display:inline" onsubmit="return confirm('Are you sure?')">@csrf @method('DELETE')<button type="submit" class="btn btn-danger btn-small">Delete Image</button></form>
                        <p>Move image to another Album :</p>
                        <form name="movephoto" method="POST" action="{{URL::route('move_image')}}" enctype="multipart/form-data">
                            @csrf
                            <select name="new_album">
                                @foreach($albums as $others)
                                    <option value="{{$others->id}}">{{$others->name}}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="photo" value="{{$photo->id}}" />
                            <button type="submit" class="btn btn-small btn-info" onclick="return confirm('Are you sure?')">Move Image</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
