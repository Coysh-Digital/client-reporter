<?php

declare(strict_types=1);

namespace App\Reporting\Blocks;

use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Support\ReportLang;

class WebsiteOverviewBlock extends BlockType
{
    public function type(): string
    {
        return 'website-overview';
    }

    public function label(): string
    {
        return ReportLang::get('website_overview.heading');
    }

    public function description(): string
    {
        return 'Key facts about the website: address, platform and environment.';
    }

    public function group(): string
    {
        return 'Website';
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $site = $context->site;

        return [
            'url' => $site->url,
            'host' => $site->host(),
            'cms' => $site->cms_type ? ucfirst($site->cms_type) : null,
            'environment' => ucfirst($site->environment),
        ];
    }

    public function icon(): string
    {
        return 'globe';
    }
}
