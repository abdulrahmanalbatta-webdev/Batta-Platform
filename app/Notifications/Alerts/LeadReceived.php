<?php

namespace App\Notifications\Alerts;

use App\Enums\AlertType;
use App\Models\Lead;

class LeadReceived extends TeamAlert
{
    public function __construct(public Lead $lead) {}

    public function type(): AlertType
    {
        return AlertType::Leads;
    }

    protected function title(): string
    {
        return 'طلب مشروع جديد من '.($this->lead->company ?: $this->lead->name);
    }

    protected function meta(): string
    {
        return $this->lead->service->label().' · ميزانية '.$this->money($this->lead->budget);
    }

    protected function page(): string
    {
        return 'leads';
    }

    protected function params(): array
    {
        return ['lead' => $this->lead->id];
    }
}
