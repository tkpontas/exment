<?php

namespace Exceedone\Exment\Notifications;

trait WebhookTrait
{
    /**
     * @var string|null
     */
    protected $webhook_url;

    /**
     * Get the value of the notifiable's primary key.
     *
     * @return string|null
     */
    public function getKey(): ?string
    {
        return $this->webhook_url;
    }

    /**
     * Get the webhook url.
     *
     * @return string|null
     */
    public function getWebhookUrl(): ?string
    {
        return $this->webhook_url;
    }
}
