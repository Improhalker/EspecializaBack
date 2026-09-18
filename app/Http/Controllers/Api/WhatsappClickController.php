<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatsappClickRequest;
use App\Models\WhatsappClick;
use Illuminate\Http\JsonResponse;

class WhatsappClickController extends Controller
{
    public function store(StoreWhatsappClickRequest $request): JsonResponse
    {
        WhatsappClick::query()->create($request->validated());

        return response()->json([], 201);
    }
}
