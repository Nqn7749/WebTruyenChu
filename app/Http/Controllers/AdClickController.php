<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Support\BotDetector;
use Illuminate\Http\Request;

/**
 * Counts a click on an image ad and sends the visitor to the ad's own link. It only ever redirects to the
 * URL the admin stored (https, validated on save), never to a URL taken from the request.
 */
class AdClickController extends Controller
{
    public function __invoke(Request $request, Ad $ad)
    {
        abort_unless($ad->type === 'image' && $ad->link_url && Ad::query()->running()->whereKey($ad->id)->exists(), 404);

        if (! BotDetector::isBot($request->userAgent())) {
            Ad::query()->whereKey($ad->id)->increment('clicks');
        }

        return redirect()->away($ad->link_url)->header('Cache-Control', 'no-store');
    }
}
