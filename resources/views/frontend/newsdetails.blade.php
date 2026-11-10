@extends('frontend.layout')

@section('content')
    <section class="section-pad section-border">
        <div class="container-fluid">
            <div class="container">
                <div class="row">
                    <div class="col-lg-8 col-md-12 col-sm-12 ">
                            <div class="row mb-5">
                                <div class="col-sm-12">
                                    <div class="">
                                        @if($news->banner)
                                            <div class="news-banner">
                                                <img src="{{ asset('uploads/'.$news->banner) }}" width="100%" alt="{{$news->title}}">
                                            </div>
                                        @endif
                                        <div class="news-heading">
                                            <div class="news-title">
                                                <h3>{{ $news->title }}</h3>
                                            </div>
                                            <div class="news-date">
                                                {{ date('F d, Y', strtotime($news->news_date)) }}
                                            </div>
                                        </div>
                                        <div class="news-detail-body">
                                            {!! nl2br(e($news->description)) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                    <div class="col-lg-4 col-md-12 col-sm-12 ">
                        @include('frontend.sidebar_news')
                    </div>
                </div>
            </div>
        </div>

    </section>
@endsection