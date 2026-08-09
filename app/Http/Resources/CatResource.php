<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * description ships as {fr, en} (not the current-locale string
     * HasTranslations would normally expose) since the admin form needs
     * both languages at once to populate its FR/EN tabs.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cat = $this->resource;

        return [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'name' => $cat->name,
            'type' => $cat->type->value,
            'sex' => $cat->sex->value,
            'color_id' => $cat->color_id,
            'second_color_id' => $cat->second_color_id,
            'color' => $this->whenLoaded('color'),
            'second_color' => $this->whenLoaded('secondColor'),
            'description' => $cat->getTranslations('description'),
            'price' => $cat->price,
            'birth_date' => $cat->birth_date?->toDateString(),
            'eye_color' => $cat->eye_color,
            'available_at' => $cat->available_at?->toDateString(),
            'diet' => $cat->diet,
            'litter_trained' => $cat->litter_trained,
            'neutered' => $cat->neutered,
            'status' => $cat->status,
            // Only present when the controller eager-loads these counts
            // (see BreederCatController::index()) — the adoption side never
            // sets them, so whenCounted() omits both keys entirely there.
            'sire_litters_count' => $this->whenCounted('sireLitters'),
            'dam_litters_count' => $this->whenCounted('damLitters'),
            'photos' => $cat->getMedia('photos')->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
            ])->all(),
            // Only present on Admin\Cats\AdoptionCatController::edit() —
            // powers CatAdoptionPanel.vue's reservation status view.
            'deposits' => $this->whenLoaded('deposits', fn () => $cat->deposits->map(fn ($deposit) => [
                'id' => $deposit->id,
                'status' => $deposit->status,
                'amount' => $deposit->amount,
                'currency' => $deposit->currency,
                'payment_method' => $deposit->payment_method,
                'payment_link_url' => $deposit->payment_link_url,
                'paid_at' => $deposit->paid_at?->toIso8601String(),
                'finalized_at' => $deposit->finalized_at?->toIso8601String(),
                'owner' => $deposit->owner ? [
                    'id' => $deposit->owner->id,
                    'first_name' => $deposit->owner->first_name,
                    'last_name' => $deposit->owner->last_name,
                    'email' => $deposit->owner->email,
                    'phone' => $deposit->owner->phone,
                ] : null,
            ])),
        ];
    }
}
