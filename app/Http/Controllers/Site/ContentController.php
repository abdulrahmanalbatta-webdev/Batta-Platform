<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\SiteContent;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    /**
     * The site's own content edited on the dashboard (announcement, home, about, services, packages, case studies,
     * testimonials, FAQs) and the uploaded photo (null: the site keeps its bundled one); images as full URLs.
     */
    public function __invoke(SiteContent $content): JsonResponse
    {
        return response()->json(['data' => $content->forSite()]);
    }
}
