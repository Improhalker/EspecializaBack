<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCourseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_create_course_with_modalities_and_faqs(): void
    {
        $category = Category::factory()->create();
        $this->actingAsAdministrator();

        $response = $this->postJson('/api/admin/courses', $this->coursePayload($category->id));

        $response
            ->assertCreated()
            ->assertJsonPath('data.slug', 'curso-de-teste')
            ->assertJsonCount(2, 'data.modalities')
            ->assertJsonCount(1, 'data.faqs');

        $this->assertDatabaseHas('courses', ['slug' => 'curso-de-teste', 'category_id' => $category->id]);
        $this->assertDatabaseCount('course_modalities', 2);
        $this->assertDatabaseCount('course_faqs', 1);
    }

    public function test_administrator_can_update_publication_and_filter_courses_on_server(): void
    {
        $category = Category::factory()->create();
        $publishedCourse = Course::factory()->create([
            'category_id' => $category->id,
            'name' => 'MOPP Atualização',
            'slug' => 'mopp-atualizacao',
            'is_published' => true,
        ]);
        $draftCourse = Course::factory()->create([
            'category_id' => $category->id,
            'name' => 'Curso Rascunho',
            'slug' => 'curso-rascunho',
            'is_published' => false,
        ]);
        $publishedCourse->modalities()->create(['name' => 'Atualização', 'price_mode' => 'hidden']);
        $draftCourse->modalities()->create(['name' => 'Formação', 'price_mode' => 'hidden']);
        $this->actingAsAdministrator();

        $this->getJson('/api/admin/courses?status=published&search=mopp&modality=Atualização')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publishedCourse->id);

        $this->patchJson("/api/admin/courses/{$draftCourse->id}/publication", ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('data.is_published', true);

        $this->assertDatabaseHas('courses', ['id' => $draftCourse->id, 'is_published' => true]);
    }

    public function test_administrator_can_edit_a_course_without_replacing_its_existing_modality(): void
    {
        $category = Category::factory()->create();
        $course = Course::factory()->create(['category_id' => $category->id, 'slug' => 'curso-original']);
        $modality = $course->modalities()->create(['name' => 'Formação', 'workload' => '40 horas', 'price_mode' => 'hidden']);
        $this->actingAsAdministrator();

        $payload = $this->coursePayload($category->id);
        $payload['name'] = 'Curso revisado';
        $payload['slug'] = 'curso-revisado';
        $payload['modalities'] = [[
            'id' => $modality->id,
            'name' => 'Formação',
            'workload' => '50 horas-aula',
            'price_mode' => 'consult',
            'is_published' => true,
            'sort_order' => 1,
        ]];

        $this->putJson("/api/admin/courses/{$course->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'Curso revisado')
            ->assertJsonPath('data.modalities.0.workload', '50 horas-aula');

        $this->assertDatabaseHas('course_modalities', ['id' => $modality->id, 'workload' => '50 horas-aula']);
    }

    /**
     * @return array<string, mixed>
     */
    private function coursePayload(int $categoryId): array
    {
        return [
            'name' => 'Curso de teste',
            'short_name' => 'Teste',
            'slug' => 'curso-de-teste',
            'category_id' => $categoryId,
            'summary' => 'Resumo para validar a criação do curso.',
            'description' => 'Descrição completa do curso.',
            'requirements' => ['Ter documentação disponível.'],
            'is_featured' => true,
            'is_published' => false,
            'sort_order' => 1,
            'modalities' => [
                ['name' => 'Atualização', 'workload' => '16 horas-aula', 'price_mode' => 'consult', 'is_published' => true, 'sort_order' => 1],
                ['name' => 'Formação', 'workload' => '50 horas-aula', 'price_mode' => 'hidden', 'is_published' => false, 'sort_order' => 2],
            ],
            'faqs' => [
                ['question' => 'Como funciona?', 'answer' => 'O atendimento é feito pelo WhatsApp.', 'sort_order' => 1],
            ],
        ];
    }

    private function actingAsAdministrator(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'is_admin' => true,
            'must_change_password' => false,
        ]));
    }
}
