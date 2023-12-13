<?php

declare(strict_types=1);

namespace Kami\Cocktail\Models\Concerns;

use Illuminate\Support\Str;
use Kami\Cocktail\Models\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasImages
{
    /**
     * @return MorphMany<Image>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function getMainImageUrl(): ?string
    {
        return $this->getMainImage()?->getImageUrl();
    }

    public function getMainImage(): ?Image
    {
        return $this->images->sortBy('sort')->first() ?? null;
    }

    public function deleteImages(): void
    {
        foreach ($this->images as $image) {
            $image->delete();
        }
    }

    /**
     * @param Collection<int, Image> $images
     */
    public function attachImages(Collection $images): void
    {
        $disk = config('bar-assistant.use_s3_uploads') ? Storage::disk('uploads_s3') : Storage::disk('uploads');

        foreach ($images as $image) {
            if ($image->imageable_id !== null) {
                continue;
            }

            $oldFilePath = $image->file_path;
            $newFilePath = $this->getUploadPath() . $this->slug . '_' . Str::random(6) . '.' . $image->file_extension;

            if ($disk->exists($oldFilePath)) {
                $disk->move($oldFilePath, $newFilePath);

                $image->file_path = $newFilePath;
                $image->save();
            } else {
                $image->delete();
            }
        }

        $this->images()->saveMany($images);
    }
}
