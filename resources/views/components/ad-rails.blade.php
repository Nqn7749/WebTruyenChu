{{--
    Ads on the left and right edges of the page. Which ads depends on the kind of page; the rails are
    position: fixed in the empty space beside the content and only appear on very wide screens (ads.css).
    Each rail has a close button: closing hides both rails for the rest of the viewing session (this tab,
    until it is closed), remembered with sessionStorage. A new visit shows the ads again.
--}}
@php
    $adPage = match (true) {
        request()->routeIs('home') => 'home',
        request()->routeIs('stories.show') => 'story',
        request()->routeIs('chapters.show') => 'chapter',
        request()->routeIs('search', 'stories.index', 'categories.show', 'ranking') => 'list',
        default => null,
    };
    $adRails = $adPage ? [
        'left' => \App\Models\Ad::forPlacement($adPage.'_left'),
        'right' => \App\Models\Ad::forPlacement($adPage.'_right'),
    ] : [];
    $hasAds = collect($adRails)->contains(fn ($ads) => $ads->isNotEmpty());
@endphp

@foreach ($adRails as $side => $ads)
    @if ($ads->isNotEmpty())
        {{-- --ad-count: the ads of a rail share its height equally (see ads.css). --}}
        <aside class="ad-rail ad-rail-{{ $side }}" aria-label="Quảng cáo" style="--ad-count: {{ $ads->count() }}">
            <div class="ad-rail-head">
                <span class="ad-rail-label">Quảng cáo</span>
                <button type="button" class="ad-rail-close" data-ad-close title="Tắt quảng cáo" aria-label="Tắt quảng cáo">&times;</button>
            </div>
            @foreach ($ads as $ad)
                @if ($ad->type === 'image' && $ad->image_path)
                    <a href="{{ route('ads.click', $ad) }}" target="_blank" rel="sponsored nofollow noopener" class="ad-item">
                        <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->alt_text ?: $ad->name }}" loading="lazy">
                    </a>
                @elseif ($ad->type === 'code' && $ad->html_code)
                    {{-- Snippet pasted by an admin from an ad network: printed as it is, on purpose. --}}
                    <div class="ad-item ad-item-code">{!! $ad->html_code !!}</div>
                @endif
            @endforeach
        </aside>
    @endif
@endforeach

@if ($hasAds)
    <script>
    (() => {
        // A reader who closes the ads does not see them again until this viewing session ends
        // (sessionStorage: kept across pages of the same tab, gone when the tab or browser is closed).
        const KEY = 'ad_rails_closed';
        const rails = document.querySelectorAll('.ad-rail');
        const hide = () => rails.forEach((rail) => { rail.hidden = true; });

        try {
            if (sessionStorage.getItem(KEY) === '1') {
                hide();
            }
        } catch (e) { /* storage blocked: the ads simply stay */ }

        document.querySelectorAll('[data-ad-close]').forEach((button) => {
            button.addEventListener('click', () => {
                hide();
                try {
                    sessionStorage.setItem(KEY, '1');
                } catch (e) { /* ignore */ }
            });
        });
    })();
    </script>
@endif
