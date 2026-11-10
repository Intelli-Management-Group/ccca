<h4 class="sidebar-title">Recent News</h4>
<ul class="sidebar-list">
    @php
        use App\Models\News;
        use App\Models\Album;
    @endphp

    @php 
    
        $recent = News::orderBy('news_date', 'desc')->take(5)->get();
    
     @endphp
    @foreach($recent as $r)
        <li><a href="#">{{ $r->title }}</a></li>
    @endforeach
</ul>

<h4 class="sidebar-title mt-5">Archives</h4>
<ul class="sidebar-list">
    @php 
    
        $years = News::selectRaw("YEAR(news_date) as year")->groupByRaw("YEAR(news_date)")->orderByRaw("YEAR(news_date) DESC")->get();
        $year_array = array();
        if ($years) {
            foreach ($years as $y) {
                array_push($year_array, $y->year);
            }
        }

    @endphp
    @foreach(count($years) > 0 ? $years : array() as $y)
        <li><a href="{!! url('archives').'/'.$y !!}" target="_blank">{!! $y !!}</a></li>
    @endforeach
</ul>


<h4 class="sidebar-title mt-5">Gallery</h4>
<ul class="sidebar-list gallery-section">
    <?php $gallery = Album::orderBy('id', 'desc')->take(4)->get(); ?>
    <li class="row">
        @foreach($gallery as $g)
            <div class="col-sm-6">
                <div class="content mb-4">
                    <a href="{!! url('/gallery-detail') !!}/{{$g->id}}" target="_blank">
                        <div class="content-overlay"></div>
                        <img class="img-thumbnail" src="{{ asset('uploads/albums/'.$g->cover_image) }}" alt="{{$g->name}}">
                        <div class="content-details fadeIn-bottom">
                            <h5 class="content-title">{{ $g->name }}</h5>
                            <p class="content-text"><i class="fas fa-image"></i> {!! isset($g->Photos) ? count($g->Photos) : 0 !!} Images</p>
                        </div>
                    </a>
                </div>
            </div>
        @endforeach
    </li>
</ul>

