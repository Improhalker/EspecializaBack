<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Http\Resources\HeroResource;
use App\Http\Resources\TestimonialResource;
use App\Models\Course;
use App\Models\PageAppearance;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class HomeController extends Controller
{
    /**
     * @var array<int, array<string, string>>
     */
    private const DEFAULT_FAQS = [
        ['question' => 'Como faço a matrícula?', 'answer' => 'Escolha o curso, fale com a equipe pelo WhatsApp e receba as orientações para concluir sua matrícula.'],
        ['question' => 'Onde consulto os valores?', 'answer' => 'As condições podem variar conforme o curso e a modalidade. Nossa equipe informa tudo diretamente no atendimento.'],
        ['question' => 'Formação e Atualização são a mesma coisa?', 'answer' => 'São modalidades do mesmo curso, indicadas para momentos diferentes. Confira os requisitos ou peça ajuda para escolher.'],
    ];

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

        $appearance = PageAppearance::query()->with(['heroMedia', 'heroMobileMedia'])->where('page_key', 'home')->first()
            ?? new PageAppearance(['page_key' => 'home']);

        $globalFaqs = Setting::query()->where('key', 'global_faq')->value('value');
        $faqs = ! empty($globalFaqs) ? $globalFaqs : self::DEFAULT_FAQS;

        return response()->json([
            'hero' => (new HeroResource($appearance))->resolve(),
            'featured_courses' => CourseResource::collection($featuredCourses)->resolve(),
            'testimonials' => TestimonialResource::collection($this->publishedTestimonials())->resolve(),
            'faqs' => collect($faqs)->values()->map(fn (array $faq, int $index) => [
                'id' => $index,
                'question' => $faq['question'],
                'answer' => $faq['answer'],
            ]),
        ]);
    }

    /**
     * @return Collection<int, Testimonial>
     */
    private function publishedTestimonials(): Collection
    {
        try {
            return Testimonial::query()
                ->published()
                ->with('avatarMedia')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        } catch (Throwable $exception) {
            Log::warning('Não foi possível carregar os depoimentos para a home.', ['exception' => $exception->getMessage()]);

            return new Collection;
        }
    }
}
