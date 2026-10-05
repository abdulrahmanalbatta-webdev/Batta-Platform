<?php

namespace App\Support;

/**
 * What a project request (lead) is about: the services on the site (محتوى الموقع ← الخدمات), by id.
 * Adding a service there makes it a choice on the site's quote form and on the dashboard's leads at once.
 */
class ProjectServices
{
    public function __construct(private SiteContent $content) {}

    /**
     * @return array<string, string> id => title, in the site's order
     */
    public function options(): array
    {
        return collect($this->content->all()['services'])->mapWithKeys(fn (array $service): array => [$service['id'] => $service['title']])->all();
    }

    /**
     * The service's title; a lead whose service was since removed from the site keeps showing its id.
     */
    public function label(?string $id): string
    {
        return $this->options()[$id] ?? (string) $id;
    }
}
