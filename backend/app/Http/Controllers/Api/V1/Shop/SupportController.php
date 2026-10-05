<?php

namespace App\Http\Controllers\Api\V1\Shop;

use App\Actions\Shop\GetSupportSectionsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupportSectionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SupportController extends Controller
{
    public function __invoke(GetSupportSectionsAction $getSupportSections): AnonymousResourceCollection
    {
        return SupportSectionResource::collection($getSupportSections->execute());
    }
}
