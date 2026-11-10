


@extends('layouts.app')

@section('content')




<div class="container">
    <div class="row justify-content-center">
        <div class="col-12">
            @if(session()->has('success'))
                <div class="alert alert-success" id="successMessage">
                    {{ session('success') }}
                </div>
            @endif @if(session()->has('error'))
                <div class="alert alert-danger" id="errorMessage">
                    {{ session('error') }}
                </div>
            @endif
            <a href="{{URL::route('create_album_form')}}" class="btn btn-primary mb-2">Create New Album</a>
            <br>
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Gallery Name</th>
                    <th> Thumbnails </th>
                    <th> Images </th>
                    <th>Date</th>
                    <th colspan="2">Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach($albums as $album)
                    <tr>
                        <td>{{ $album->id }}</td>
                        <td>{{$album->name}}</td>
                        <td><img alt="{{$album->name}}" src="{{ asset('uploads/albums/'.$album->cover_image) }}" width="50"></td>
                        <td>{{count($album->Photos)}} image(s).</td>
                        <td>{{ date("d F Y",strtotime($album->created_at)) }} at {{date("g:ha",strtotime($album->created_at)) }}</td>
                        <td>
                            <a href="{{URL::route('show_album', array('id'=>$album->id))}}" class="btn btn-primary">Show Gallery</a>
                            <a href="{{URL::route('edit_album', array('id'=>$album->id))}}" class="btn btn-primary">Edit Gallery</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>




{{--<div class="container">--}}

{{--    <div class="starter-template">--}}

{{--        <div class="row">--}}
{{--            <div class="col-12">--}}
{{--                <a href="{{URL::route('create_album_form')}}" class="btn btn-primary mb-2">Create New Album</a>--}}
{{--            </div>--}}
{{--            @foreach($albums as $album)--}}
{{--                <div class="col-lg-3">--}}
{{--                    <div class="thumbnail" style="min-height: 514px;">--}}
{{--                        <img alt="{{$album->name}}" src="{{ asset('uploads/albums/'.$album->cover_image) }}">--}}
{{--                        <div class="caption">--}}
{{--                            <h3>{{$album->name}}</h3>--}}
{{--                            <p>{{$album->description}}</p>--}}
{{--                            <p>{{count($album->Photos)}} image(s).</p>--}}
{{--                            <p>Created date:  </p>--}}
{{--                            <p></p>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            @endforeach--}}
{{--        </div>--}}

{{--    </div><!-- /.container -->--}}
{{--</div>--}}
@endsection
{{--</body>--}}
{{--</html>--}}
