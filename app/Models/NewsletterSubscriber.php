<?php

namespace App\Models;

use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $unsubscribed_at Larastan doesn't infer casts declared via the casts(): array method syntax.
 */
#[Fillable(['email', 'unsubscribe_token', 'unsubscribed_at'])]
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function isUnsubscribed(): bool
    {
        return $this->unsubscribed_at !== null;
    }
}
