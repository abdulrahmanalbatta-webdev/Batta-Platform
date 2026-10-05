<?php

namespace App\Enums;

enum LeadService: string
{
    case Websites = 'websites';
    case WebApps = 'web_apps';
    case Stores = 'stores';
    case Dashboards = 'dashboards';
    case Maintenance = 'maintenance';
    case Training = 'training';

    public function label(): string
    {
        return match ($this) {
            self::Websites => 'تطوير المواقع',
            self::WebApps => 'تطبيقات الويب',
            self::Stores => 'المتاجر الإلكترونية',
            self::Dashboards => 'لوحات التحكم والأنظمة',
            self::Maintenance => 'الصيانة والتطوير المستمر',
            self::Training => 'تدريب الفرق والجامعات',
        };
    }
}
