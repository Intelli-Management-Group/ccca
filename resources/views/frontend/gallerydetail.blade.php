@extends('frontend.layout')

@section('content')
{{--    <section class="gallery-banner section-border">--}}
{{--        <div class="container-fluid">--}}
{{--            <div class="container">--}}
{{--                <div class="row justify-content-center">--}}
{{--                    <div class="col-md-12">--}}
{{--                        <div class="banner-container">--}}
{{--                            <h1>--}}
{{--                                Gallery--}}
{{--                            </h1>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </section>--}}
    <section class="section-pad section-border">
        <div class="container-fluid">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12 col-md-12 col-sm-12 ">
                        <h2>{{ $gallery_name }}</h2><hr/>
                        <div class="row gallery">
                            @foreach($images as $i)
                                <div class="col-sm-3 mb-2">
                                    <a href="{{ asset("uploads/albums/".$i->albumId."/".$i->fileName) }}">
                                        <img src="{{ asset("uploads/albums/".$i->albumId."/".$i->fileName) }}" class="img-thumbnail"
                                             alt="{{ $i->caption }}" title="{{ $i->credit }}">
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <link href="{{ asset('css/simple-lightbox.css') }}" rel="stylesheet">
    <script src="{{ asset('js/simple-lightbox.jquery.js') }}"></script>
    <script type="text/javascript">
        $(document).ready(function () {
            $('.gallery a').simpleLightbox();
        });
    </script>
@endsection
