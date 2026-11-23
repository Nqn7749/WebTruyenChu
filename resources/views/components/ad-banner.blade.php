@props(['page' => null])
@php
    $ad = null;
    if ($page) {
        $ads = \App\Models\Ad::forPlacement($page.'_right');
        if ($ads->isEmpty()) {
            $ads = \App\Models\Ad::forPlacement($page.'_left');
        }
        $ad = $ads->first();
    }
@endphp

@if ($ad)
    <div class="ad-inpage-banner my-4" aria-label="Quảng cáo tài trợ">
        <div class="ad-inpage-inner">
            <div class="ad-inpage-header">
                <span class="ad-inpage-tag">
                    <i class="bi bi-badge-ad me-1" aria-hidden="true"></i> Quảng cáo
                </span>
            </div>
            <div class="ad-inpage-content">
                @if ($ad->type === 'image' && $ad->image_path)
                    <a href="{{ route('ads.click', $ad) }}" target="_blank" rel="sponsored nofollow noopener" class="ad-inpage-link">
                        <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->alt_text ?: $ad->name }}" loading="lazy" class="ad-inpage-img">
                    </a>
                @elseif ($ad->type === 'code' && $ad->html_code)
                    <div class="ad-inpage-code">{!! $ad->html_code !!}</div>
                @endif
            </div>
        </div>
    </div>
@endif
