<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdController extends Controller
{
    public function index()
    {
        $ads = Ad::query()->orderBy('placement')->orderBy('sort_order')->orderBy('id')->paginate(30);

        // How many ads are running in each placement, for the placement map.
        $running = Ad::query()->running()->selectRaw('placement, COUNT(*) as total')->groupBy('placement')->pluck('total', 'placement');

        return view('admin.ads.index', [
            'ads' => $ads,
            'placements' => Ad::PLACEMENTS,
            'running' => $running,
            'maxPerPlacement' => Ad::MAX_PER_PLACEMENT,
        ]);
    }

    public function create()
    {
        return view('admin.ads.create', ['ad' => new Ad(['type' => 'image', 'is_active' => true, 'sort_order' => 0]), 'placements' => Ad::PLACEMENTS]);
    }

    public function store(Request $request, AdminActivityLogger $logger)
    {
        $data = $this->validated($request);
        $data['image_path'] = $request->file('image')?->store('ads', 'public');

        $ad = Ad::create($this->normalize($data));

        $logger->record($request, 'ad.created', $ad, 'Thêm quảng cáo "'.$ad->name.'" ('.$ad->placementLabel().')');

        return redirect()->route('admin.ads.index')->with('success', 'Đã thêm quảng cáo.');
    }

    public function edit(Ad $ad)
    {
        return view('admin.ads.edit', ['ad' => $ad, 'placements' => Ad::PLACEMENTS]);
    }

    public function update(Request $request, Ad $ad, AdminActivityLogger $logger)
    {
        $data = $this->validated($request, $ad);

        $newImage = $request->file('image')?->store('ads', 'public');
        $oldImage = $ad->image_path;
        $data['image_path'] = $newImage ?? $oldImage;

        $data = $this->normalize($data);
        $ad->update($data);

        // Drop the previous file once it is no longer referenced (replaced, or the ad became a code ad).
        if ($oldImage && $oldImage !== $ad->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        $logger->record($request, 'ad.updated', $ad, 'Sửa quảng cáo "'.$ad->name.'" ('.$ad->placementLabel().')');

        return redirect()->route('admin.ads.index')->with('success', 'Đã cập nhật quảng cáo.');
    }

    public function toggle(Request $request, Ad $ad, AdminActivityLogger $logger)
    {
        $ad->update(['is_active' => ! $ad->is_active]);

        $logger->record($request, $ad->is_active ? 'ad.enabled' : 'ad.disabled', $ad, ($ad->is_active ? 'Bật' : 'Tắt').' quảng cáo "'.$ad->name.'"');

        return back()->with('success', $ad->is_active ? 'Đã bật quảng cáo.' : 'Đã tắt quảng cáo.');
    }

    public function destroy(Request $request, Ad $ad, AdminActivityLogger $logger)
    {
        $name = $ad->name;
        $image = $ad->image_path;

        $ad->delete();

        if ($image) {
            Storage::disk('public')->delete($image);
        }

        $logger->record($request, 'ad.deleted', null, 'Xóa quảng cáo "'.$name.'"');

        return redirect()->route('admin.ads.index')->with('success', 'Đã xóa quảng cáo.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Ad $ad = null): array
    {
        $isImage = $request->input('type') === 'image';
        // A new image ad needs a banner; editing keeps the current one unless a new file is sent.
        $needsImage = $isImage && ! ($ad?->image_path);

        return $request->validate([
            'name' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'placement' => ['required', Rule::in(array_keys(Ad::PLACEMENTS))],
            'type' => ['required', Rule::in(['image', 'code'])],
            // Raster banners only: SVG can carry script and the file is served from the public disk.
            'image' => [Rule::requiredIf($needsImage), 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
            'link_url' => [Rule::requiredIf($isImage), 'nullable', 'url', 'max:2048', 'starts_with:https://'],
            'alt_text' => ['nullable', 'string', 'max:150', 'not_regex:/[<>]/'],
            // Pasted as-is on every page of the placement: only from an ad network you trust.
            'html_code' => [Rule::requiredIf($request->input('type') === 'code'), 'nullable', 'string', 'max:5000'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'image.required' => 'Hãy chọn ảnh banner cho quảng cáo dạng ảnh.',
            'image.mimes' => 'Ảnh phải là JPG, PNG, WebP hoặc GIF.',
            'image.max' => 'Ảnh tối đa 1 MB.',
            'link_url.required' => 'Hãy nhập đường dẫn khi bấm vào quảng cáo.',
            'link_url.starts_with' => 'Đường dẫn phải bắt đầu bằng https://',
            'html_code.required' => 'Hãy dán mã quảng cáo.',
            'ends_at.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ]);
    }

    /**
     * Keep only the fields that belong to the chosen type.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        unset($data['image']);

        if ($data['type'] === 'code') {
            $data['image_path'] = null;
            $data['link_url'] = null;
            $data['alt_text'] = null;
        } else {
            $data['html_code'] = null;
        }

        return $data;
    }
}
