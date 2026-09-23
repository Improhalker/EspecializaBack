<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestimonialController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $testimonials = Testimonial::query()
            ->published()
            ->with('avatarMedia')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return TestimonialResource::collection($testimonials);
    }
}
