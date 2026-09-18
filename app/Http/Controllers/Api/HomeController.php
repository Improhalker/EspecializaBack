<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $featuredCourses = Course::query()
            ->published()
            ->where('is_featured', true)
            ->with(['category', 'coverMedia'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->take(6)
            ->get();

        return response()->json([
            'featured_courses' => CourseResource::collection($featuredCourses)->resolve(),
            'faqs' => [
                ['id' => 'enrollment', 'question' => 'Como faço a matrícula?', 'answer' => 'Escolha o curso, fale com a equipe pelo WhatsApp e receba as orientações para concluir sua matrícula.'],
                ['id' => 'price', 'question' => 'Onde consulto os valores?', 'answer' => 'As condições podem variar conforme o curso e a modalidade. Nossa equipe informa tudo diretamente no atendimento.'],
                ['id' => 'modality', 'question' => 'Formação e Atualização são a mesma coisa?', 'answer' => 'São modalidades do mesmo curso, indicadas para momentos diferentes. Confira os requisitos ou peça ajuda para escolher.'],
            ],
        ]);
    }
}
