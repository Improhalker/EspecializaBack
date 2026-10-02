<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseModality;
use App\Models\Setting;
use App\Models\SharedFaq;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseCharacteristicsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_create_and_edit_characteristics_per_modality(): void
    {
        $this->administrator();
        $payload = $this->payload();
        $payload['modalities'] = [
            ['name' => 'Formação', 'price_mode' => 'consult', 'is_published' => true, 'characteristics' => ['format' => 'Online', 'materials' => 'PDF confirmado', 'video_lessons_count' => 12, 'video_lessons_duration' => '3 horas no total']],
            ['name' => 'Atualização', 'price_mode' => 'hidden', 'is_published' => true, 'characteristics' => ['format' => 'Presencial']],
        ];

        $created = $this->postJson('/api/admin/courses', $payload)->assertCreated()
            ->assertJsonPath('data.modalities.0.characteristics.video_lessons_count', 12)
            ->assertJsonPath('data.modalities.1.characteristics.format', 'Presencial');
        $courseId = $created->json('data.id');
        $this->getJson('/api/courses/curso-revisado')->assertOk()
            ->assertJsonPath('data.modalities.0.characteristics.materials', 'PDF confirmado')
            ->assertJsonMissingPath('data.modalities.1.characteristics.video_lessons_count');

        $payload['modalities'][0]['id'] = $created->json('data.modalities.0.id');
        $payload['modalities'][1]['id'] = $created->json('data.modalities.1.id');
        $payload['modalities'][0]['characteristics']['materials'] = null;
        $payload['modalities'][0]['characteristics']['video_lessons_count'] = null;
        $this->putJson("/api/admin/courses/{$courseId}", $payload)->assertOk();
        $this->getJson('/api/courses/curso-revisado')->assertOk()
            ->assertJsonPath('data.modalities.0.characteristics.materials', null)
            ->assertJsonPath('data.modalities.0.characteristics.video_lessons_count', null)
            ->assertJsonPath('data.modalities.1.characteristics.format', 'Presencial');
        $this->assertDatabaseCount('course_modalities', 2);
    }

    public function test_invalid_or_unknown_characteristics_are_rejected_in_creation_and_update(): void
    {
        $this->administrator();
        $course = Course::factory()->create(['slug' => 'curso-revisado']);
        $payload = $this->payload();
        $payload['modalities'] = [['name' => 'Formação', 'price_mode' => 'hidden', 'characteristics' => ['video_lessons_count' => 0, 'materials' => str_repeat('a', 301), 'unknown_claim' => 'não permitido']]];

        foreach (['POST', 'PUT'] as $method) {
            if ($method === 'POST') {
                $payload['slug'] = 'outro-curso';
            } else {
                $payload['slug'] = 'curso-revisado';
            }
            $this->json($method, $method === 'POST' ? '/api/admin/courses' : "/api/admin/courses/{$course->id}", $payload)
                ->assertUnprocessable()->assertJsonValidationErrors(['modalities.0.characteristics', 'modalities.0.characteristics.video_lessons_count', 'modalities.0.characteristics.materials']);
        }
        $this->assertDatabaseCount('course_modalities', 0);
    }

    public function test_characteristics_require_administrator_access_and_cannot_publish_a_draft(): void
    {
        $this->postJson('/api/admin/courses', $this->payload())->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'must_change_password' => false]));
        $this->postJson('/api/admin/courses', $this->payload())->assertForbidden();
        $course = Course::factory()->create(['slug' => 'rascunho', 'is_published' => false]);
        CourseModality::factory()->for($course)->create(['characteristics' => ['format' => 'Online']]);
        $this->getJson('/api/courses/rascunho')->assertNotFound();
    }

    public function test_old_admin_payloads_preserve_characteristics_and_absent_values_remain_absent(): void
    {
        $course = Course::factory()->create(['slug' => 'curso-revisado']);
        $modality = CourseModality::factory()->for($course)->create(['characteristics' => ['support' => 'Canal específico']]);
        $this->administrator();
        $payload = $this->payload();
        $payload['modalities'] = [['id' => $modality->id, 'name' => 'Formação', 'price_mode' => 'consult', 'is_published' => true]];
        $this->putJson("/api/admin/courses/{$course->id}", $payload)->assertOk()
            ->assertJsonPath('data.modalities.0.characteristics.support', 'Canal específico');
        $this->getJson('/api/courses/curso-revisado')->assertOk()
            ->assertJsonMissingPath('data.modalities.0.characteristics.video_lessons_count')
            ->assertJsonMissingPath('data.modalities.0.characteristics.certificate');
    }

    public function test_review_command_is_read_only_by_default_and_only_uses_applicable_course_sources(): void
    {
        [$course, $formation, $update] = $this->reviewableMopp();
        $this->artisan('courses:review-characteristics')->assertSuccessful();
        $this->assertSame(['format' => 'Online confirmado por mim'], $formation->fresh()->characteristics);
        $this->assertSame('Formação completa', $formation->fresh()->workload);

        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame('50 horas', $formation->fresh()->workload);
        $this->assertSame('Online confirmado por mim', $formation->fresh()->characteristics['format'] ?? null);
        $this->assertNull($update->fresh()->workload);
        $this->assertArrayNotHasKey('video_lessons_count', $update->fresh()->characteristics);
        $this->assertSame(['Legislação e documentação', 'Direção defensiva', 'Movimentação de produtos perigosos', 'Prevenção de incêndios'], $formation->fresh()->features);
        $this->assertSame($this->legacyFeatures(), $update->fresh()->features);
        $this->assertSame('consult', $formation->fresh()->price_mode);
        $this->assertSame('290.00', $formation->fresh()->price);
        $this->assertSame(['Benefício existente'], $formation->fresh()->bonuses);
        $this->assertTrue($course->fresh()->is_published);
        $this->assertDatabaseCount('shared_faqs', 2);
        $this->assertDatabaseCount('course_modalities', 2);
    }

    public function test_review_does_not_take_workload_from_another_course_or_unpublished_faq(): void
    {
        $course = Course::factory()->create(['slug' => 'mopp']);
        $formation = CourseModality::factory()->for($course)->create(['features' => $this->legacyFeatures()]);
        $other = Course::factory()->create(['slug' => 'outro']);
        $faq = SharedFaq::factory()->create(['question' => 'Qual é a carga horária da Formação MOPP?', 'answer' => 'A formação tem 50 horas, conforme a Portaria SENATRAN nº 923/2025.', 'application_mode' => 'selected_courses']);
        $faq->courses()->attach($other);
        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame('Formação completa', $formation->fresh()->workload);

        $faq->courses()->attach($course);
        $faq->update(['is_published' => false]);
        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame('Formação completa', $formation->fresh()->workload);
    }

    public function test_review_preserves_admin_overrides_and_can_be_repeated_without_changes(): void
    {
        [$course, $formation] = $this->reviewableMopp();
        $formation->update(['workload' => '52 horas confirmadas', 'characteristics' => ['format' => 'Formato personalizado', 'support' => null], 'features' => ['Conteúdo personalizado']]);
        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame('Formato personalizado', $formation->fresh()->characteristics['format']);
        $this->assertNull($formation->fresh()->characteristics['support']);
        $this->assertSame(['Conteúdo personalizado'], $formation->fresh()->features);
        $this->assertSame('52 horas confirmadas', $formation->fresh()->workload);
        $before = $formation->fresh()->getRawOriginal();
        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame($before, $formation->fresh()->getRawOriginal());
    }

    public function test_review_rejects_changed_source_text_and_does_not_add_facts_to_new_courses(): void
    {
        [$course, $formation] = $this->reviewableMopp();
        SharedFaq::query()->where('question', 'O que vou estudar no MOPP?')->update(['answer' => 'Novo conteúdo ainda não revisado.']);
        $newCourse = Course::factory()->create(['slug' => 'nova-especializacao']);
        $newModality = CourseModality::factory()->for($newCourse)->create();
        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame($this->legacyFeatures(), $formation->fresh()->features);
        $this->assertNull($newModality->fresh()->characteristics);
    }

    public function test_review_uses_published_global_format_and_real_certificate_and_access_sources(): void
    {
        [$course, $formation, $update] = $this->reviewableMopp();
        $online = SharedFaq::factory()->create([
            'question' => 'Os cursos são online? Onde acesso as aulas?',
            'answer' => 'Sim. O conteúdo é disponibilizado na plataforma da IBAC Brasil, parceira da Especializa Condutor. Após a matrícula, você recebe orientação sobre o acesso e as etapas do curso.',
            'application_mode' => 'all_courses',
        ]);
        Setting::factory()->create(['key' => 'global_faq', 'value' => [
            ['question' => 'Recebo certificado?', 'answer' => 'Sim, após cumprir as atividades e avaliações exigidas, o certificado é emitido pela instituição de ensino parceira IBAC Brasil. A Especializa Condutor divulga os cursos e acompanha a matrícula; não é a instituição emissora.'],
            ['question' => 'Como recebo o acesso?', 'answer' => 'Escolha o curso e fale com a atendente pelo WhatsApp. Ela confirma a modalidade, os requisitos e as condições, envia as instruções de pagamento via Pix e, após a confirmação, encaminha seu login para a plataforma pelo próprio WhatsApp.'],
        ]]);

        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        $this->assertSame('Conteúdo online na plataforma da IBAC Brasil.', $update->fresh()->characteristics['format']);
        $this->assertSame('Emitido pela IBAC Brasil após cumprir as atividades e avaliações exigidas.', $formation->fresh()->characteristics['certificate']);
        $this->assertSame('Login enviado pelo WhatsApp após a confirmação do pagamento.', $formation->fresh()->characteristics['access']);
    }

    public function test_all_five_courses_receive_only_their_own_confirmed_formation_topics(): void
    {
        $definitions = [
            ['mopp', 'Qual é a carga horária da Formação MOPP?', 'O que vou estudar no MOPP?', 'Legislação e documentação, direção defensiva, movimentação de produtos perigosos e prevenção de incêndios fazem parte da formação.', 'Movimentação de produtos perigosos'],
            ['transporte-de-carga-indivisivel', 'Qual é a carga horária da Formação em Carga Indivisível?', 'O que é estudado no curso de Carga Indivisível?', 'Legislação do transporte especial, direção defensiva, sinalização, movimentação e fixação da carga fazem parte da formação.', 'Movimentação e fixação da carga'],
            ['veiculos-de-emergencia', 'Qual é a carga horária da Formação em Veículos de Emergência?', 'O que é estudado no curso de Veículos de Emergência?', 'A formação inclui legislação, direção defensiva, atendimento inicial, comunicação e controle emocional em situações de urgência.', 'Comunicação e controle emocional em situações de urgência'],
            ['transporte-coletivo-de-passageiros', 'Qual é a carga horária da Formação em Transporte Coletivo?', 'O que é estudado no curso de Transporte Coletivo?', 'Legislação específica, direção defensiva, primeiros socorros e relacionamento com passageiros integram a formação.', 'Relacionamento com passageiros'],
            ['transporte-escolar', 'Qual é a carga horária da Formação em Transporte Escolar?', 'O que é estudado no curso de Transporte Escolar?', 'A formação aborda legislação do transporte escolar, direção defensiva, primeiros socorros e relacionamento com estudantes e responsáveis.', 'Relacionamento com estudantes e responsáveis'],
        ];
        $pairs = [];
        foreach ($definitions as [$slug, $workloadQuestion, $contentQuestion, $contentAnswer, $specificTopic]) {
            $course = Course::factory()->create(['slug' => $slug]);
            $formation = CourseModality::factory()->for($course)->create(['features' => $this->legacyFeatures()]);
            $update = CourseModality::factory()->for($course)->create(['name' => 'Atualização', 'workload' => 'Atualização do curso', 'features' => $this->legacyFeatures()]);
            foreach ([['question' => $workloadQuestion, 'answer' => 'A formação tem 50 horas, conforme a Portaria SENATRAN nº 923/2025.'], ['question' => $contentQuestion, 'answer' => $contentAnswer]] as $data) {
                $faq = SharedFaq::factory()->create([...$data, 'application_mode' => 'selected_courses']);
                $faq->courses()->attach($course);
            }
            $pairs[] = [$formation, $update, $specificTopic];
        }

        $this->artisan('courses:review-characteristics', ['--apply' => true])->assertSuccessful();
        foreach ($pairs as [$formation, $update, $specificTopic]) {
            $this->assertSame('50 horas', $formation->fresh()->workload);
            $this->assertContains($specificTopic, $formation->fresh()->features);
            $this->assertNull($update->fresh()->workload);
            $this->assertSame($this->legacyFeatures(), $update->fresh()->features);
            $this->assertArrayNotHasKey('video_lessons_count', $formation->fresh()->characteristics);
        }
    }

    private function administrator(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_admin' => true, 'must_change_password' => false]));
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return ['name' => 'Curso revisado', 'slug' => 'curso-revisado', 'summary' => 'Características confirmadas.', 'is_published' => true];
    }

    /** @return list<string> */
    private function legacyFeatures(): array
    {
        return ['Conteúdo disponibilizado na plataforma parceira', 'Materiais digitais e avaliação conforme a modalidade', 'Orientação de matrícula pelo WhatsApp'];
    }

    /** @return array{Course, CourseModality, CourseModality} */
    private function reviewableMopp(): array
    {
        $course = Course::factory()->create(['slug' => 'mopp']);
        $formation = CourseModality::factory()->for($course)->create(['features' => $this->legacyFeatures(), 'characteristics' => ['format' => 'Online confirmado por mim'], 'price_mode' => 'consult', 'price' => 290, 'bonuses' => ['Benefício existente']]);
        $update = CourseModality::factory()->for($course)->create(['name' => 'Atualização', 'workload' => 'Atualização do curso', 'features' => $this->legacyFeatures()]);
        foreach ([
            ['question' => 'Qual é a carga horária da Formação MOPP?', 'answer' => 'A formação tem 50 horas, conforme a Portaria SENATRAN nº 923/2025. A atualização é uma modalidade diferente; confirme com a equipe qual se aplica a você.'],
            ['question' => 'O que vou estudar no MOPP?', 'answer' => 'Legislação e documentação, direção defensiva, movimentação de produtos perigosos e prevenção de incêndios fazem parte da formação.'],
        ] as $data) {
            $faq = SharedFaq::factory()->create([...$data, 'application_mode' => 'selected_courses']);
            $faq->courses()->attach($course);
        }

        return [$course, $formation, $update];
    }
}
