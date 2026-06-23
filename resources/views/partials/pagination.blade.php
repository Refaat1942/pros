@if ($paginator->hasPages())
    <nav class="dash-pagination" role="navigation" aria-label="تصفح الصفحات">
        <div class="dash-pagination-info">
            عرض
            <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            من <strong>{{ $paginator->total() }}</strong>
        </div>
        <ul class="dash-pagination-list">
            {{-- السابق --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="dash-page-btn disabled" aria-disabled="true">‹ السابق</span>
                @else
                    <a class="dash-page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ السابق</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="dash-page-ellipsis">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="dash-page-btn active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="dash-page-btn" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- التالي --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a class="dash-page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">التالي ›</a>
                @else
                    <span class="dash-page-btn disabled" aria-disabled="true">التالي ›</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
