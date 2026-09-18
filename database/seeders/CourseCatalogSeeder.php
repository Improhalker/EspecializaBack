<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialized = Category::query()->updateOrCreate(
            ['slug' => 'especializacao'],
            ['name' => 'Cursos especializados', 'sort_order' => 1],
        );

        $courses = [
            ['name' => 'MOPP — Transporte de Produtos Perigosos', 'short_name' => 'MOPP', 'slug' => 'mopp', 'summary' => 'Prepare-se para atuar no transporte de produtos perigosos.', 'sort_order' => 1],
            ['name' => 'Transporte de Carga Indivisível', 'short_name' => 'Cargas indivisíveis', 'slug' => 'transporte-de-carga-indivisivel', 'summary' => 'Capacitação para quem busca atuar com cargas de dimensões especiais.', 'sort_order' => 2],
            ['name' => 'Veículos de Emergência', 'short_name' => 'Emergência', 'slug' => 'veiculos-de-emergencia', 'summary' => 'Especialização para condução profissional de veículos de emergência.', 'sort_order' => 3],
            ['name' => 'Transporte Coletivo de Passageiros', 'short_name' => 'Passageiros', 'slug' => 'transporte-coletivo-de-passageiros', 'summary' => 'Aprimore sua atuação no transporte coletivo de passageiros.', 'sort_order' => 4],
            ['name' => 'Transporte Escolar', 'short_name' => 'Escolar', 'slug' => 'transporte-escolar', 'summary' => 'Capacitação voltada à condução responsável no transporte escolar.', 'sort_order' => 5],
        ];

        foreach ($courses as $courseData) {
            $course = Course::query()->updateOrCreate(
                ['slug' => $courseData['slug']],
                [
                    ...$courseData,
                    'category_id' => $specialized->id,
                    'description' => 'Conheça as modalidades disponíveis e fale com a equipe para receber as condições de matrícula.',
                    'requirements' => [
                        'Consulte a equipe para confirmar os requisitos aplicáveis à sua situação.',
                        'Tenha seus documentos de habilitação disponíveis para o atendimento.',
                    ],
                    'is_featured' => true,
                    'is_published' => true,
                ],
            );

            $course->modalities()->updateOrCreate(
                ['name' => 'Atualização'],
                [
                    'workload' => 'Atualização do curso',
                    'description' => 'Modalidade destinada a quem precisa manter a capacitação em dia. A equipe confirma os detalhes antes da matrícula.',
                    'features' => ['Conteúdo disponibilizado na plataforma parceira', 'Materiais digitais e avaliação conforme a modalidade', 'Orientação de matrícula pelo WhatsApp'],
                    'bonuses' => ['Atendimento para tirar dúvidas sobre a matrícula'],
                    'price_mode' => 'consult',
                    'price_label' => 'Condições especiais para matrícula',
                    'whatsapp_message' => "Olá! Tenho interesse no curso {$course->short_name} — Atualização.",
                    'sort_order' => 1,
                    'is_published' => true,
                ],
            );

            $course->modalities()->updateOrCreate(
                ['name' => 'Formação'],
                [
                    'workload' => 'Formação completa',
                    'description' => 'Modalidade para quem busca a formação na especialização. A equipe orienta sobre os requisitos e as próximas etapas.',
                    'features' => ['Conteúdo disponibilizado na plataforma parceira', 'Materiais digitais e avaliação conforme a modalidade', 'Orientação de matrícula pelo WhatsApp'],
                    'bonuses' => ['Atendimento para tirar dúvidas sobre a matrícula'],
                    'price_mode' => 'consult',
                    'price_label' => 'Conheça as condições para matrícula',
                    'whatsapp_message' => "Olá! Tenho interesse no curso {$course->short_name} — Formação.",
                    'sort_order' => 2,
                    'is_published' => true,
                ],
            );

            $course->faqs()->updateOrCreate(
                ['question' => 'Como escolho entre Formação e Atualização?'],
                ['answer' => 'A equipe orienta você conforme sua necessidade e sua documentação.', 'sort_order' => 1],
            );
        }
    }
}
