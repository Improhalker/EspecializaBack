<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Setting;
use App\Models\SharedFaq;
use App\Services\PublicCourseCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewCourseCharacteristics extends Command
{
    protected $signature = 'courses:review-characteristics {--apply : Salvar somente os dados confirmados pelas fontes cadastradas}';

    protected $description = 'Revisa todos os cursos; por padrão apenas simula a organização das características já confirmadas.';

    private const LEGACY_FEATURES = [
        'Conteúdo disponibilizado na plataforma parceira',
        'Materiais digitais e avaliação conforme a modalidade',
        'Orientação de matrícula pelo WhatsApp',
    ];

    /** @var array<string, array{workload_question: string, content_question: string, content_answer: string, topics: list<string>}> */
    private const REVIEWED_COURSES = [
        'mopp' => [
            'workload_question' => 'Qual é a carga horária da Formação MOPP?',
            'content_question' => 'O que vou estudar no MOPP?',
            'content_answer' => 'Legislação e documentação, direção defensiva, movimentação de produtos perigosos e prevenção de incêndios fazem parte da formação.',
            'topics' => ['Legislação e documentação', 'Direção defensiva', 'Movimentação de produtos perigosos', 'Prevenção de incêndios'],
        ],
        'transporte-de-carga-indivisivel' => [
            'workload_question' => 'Qual é a carga horária da Formação em Carga Indivisível?',
            'content_question' => 'O que é estudado no curso de Carga Indivisível?',
            'content_answer' => 'Legislação do transporte especial, direção defensiva, sinalização, movimentação e fixação da carga fazem parte da formação.',
            'topics' => ['Legislação do transporte especial', 'Direção defensiva', 'Sinalização', 'Movimentação e fixação da carga'],
        ],
        'veiculos-de-emergencia' => [
            'workload_question' => 'Qual é a carga horária da Formação em Veículos de Emergência?',
            'content_question' => 'O que é estudado no curso de Veículos de Emergência?',
            'content_answer' => 'A formação inclui legislação, direção defensiva, atendimento inicial, comunicação e controle emocional em situações de urgência.',
            'topics' => ['Legislação', 'Direção defensiva', 'Atendimento inicial', 'Comunicação e controle emocional em situações de urgência'],
        ],
        'transporte-coletivo-de-passageiros' => [
            'workload_question' => 'Qual é a carga horária da Formação em Transporte Coletivo?',
            'content_question' => 'O que é estudado no curso de Transporte Coletivo?',
            'content_answer' => 'Legislação específica, direção defensiva, primeiros socorros e relacionamento com passageiros integram a formação.',
            'topics' => ['Legislação específica', 'Direção defensiva', 'Primeiros socorros', 'Relacionamento com passageiros'],
        ],
        'transporte-escolar' => [
            'workload_question' => 'Qual é a carga horária da Formação em Transporte Escolar?',
            'content_question' => 'O que é estudado no curso de Transporte Escolar?',
            'content_answer' => 'A formação aborda legislação do transporte escolar, direção defensiva, primeiros socorros e relacionamento com estudantes e responsáveis.',
            'topics' => ['Legislação do transporte escolar', 'Direção defensiva', 'Primeiros socorros', 'Relacionamento com estudantes e responsáveis'],
        ],
    ];

    public function handle(PublicCourseCache $cache): int
    {
        if (! Schema::hasColumn('course_modalities', 'characteristics')) {
            $this->components->error('Execute primeiro a migration de características das modalidades.');

            return self::FAILURE;
        }

        $globalFaqs = Setting::query()->where('key', 'global_faq')->value('value') ?? [];
        $courses = Course::query()->with(['modalities', 'faqs'])->orderBy('id')->get();
        $sharedFaqs = SharedFaq::query()->published()->with('courses:id')->get();
        $changes = [];

        foreach ($courses as $course) {
            $this->info("{$course->id}. {$course->name}");
            $definition = self::REVIEWED_COURSES[$course->slug] ?? null;
            $faqs = $sharedFaqs->filter(fn (SharedFaq $faq): bool => $faq->application_mode === SharedFaq::APPLICATION_ALL_COURSES || $faq->courses->contains('id', $course->id))
                ->concat($course->faqs)->keyBy('question');

            foreach ($course->modalities as $modality) {
                $attributes = [];
                $characteristics = $modality->characteristics ?? [];
                $verified = [];
                $isReviewed = $definition !== null && in_array($modality->name, ['Formação', 'Atualização'], true);
                $legacyFeatures = $modality->features === self::LEGACY_FEATURES;

                if ($isReviewed && $legacyFeatures) {
                    $verified['materials'] = 'Materiais digitais na plataforma parceira.';
                    $verified['assessment'] = 'Avaliação conforme a modalidade. Confirme as etapas aplicáveis antes da matrícula.';
                    $verified['support'] = 'Orientação de matrícula pelo WhatsApp.';
                }

                $onlineFaq = $faqs->get('Os cursos são online? Onde acesso as aulas?');
                if ($isReviewed && $onlineFaq?->answer === 'Sim. O conteúdo é disponibilizado na plataforma da IBAC Brasil, parceira da Especializa Condutor. Após a matrícula, você recebe orientação sobre o acesso e as etapas do curso.') {
                    $verified['format'] = 'Conteúdo online na plataforma da IBAC Brasil.';
                    $this->line("  Fonte de formato: FAQ compartilhada {$onlineFaq->id}.");
                }

                foreach ($globalFaqs as $faq) {
                    $answer = $faq['answer'] ?? '';
                    if (! $isReviewed) {
                        continue;
                    }
                    if ($answer === 'Sim, após cumprir as atividades e avaliações exigidas, o certificado é emitido pela instituição de ensino parceira IBAC Brasil. A Especializa Condutor divulga os cursos e acompanha a matrícula; não é a instituição emissora.') {
                        $verified['certificate'] = 'Emitido pela IBAC Brasil após cumprir as atividades e avaliações exigidas.';
                    }
                    if ($answer === 'Escolha o curso e fale com a atendente pelo WhatsApp. Ela confirma a modalidade, os requisitos e as condições, envia as instruções de pagamento via Pix e, após a confirmação, encaminha seu login para a plataforma pelo próprio WhatsApp.') {
                        $verified['access'] = 'Login enviado pelo WhatsApp após a confirmação do pagamento.';
                    }
                }

                foreach ($verified as $key => $value) {
                    if (! array_key_exists($key, $characteristics)) {
                        $characteristics[$key] = $value;
                    }
                }

                if ($characteristics !== ($modality->characteristics ?? [])) {
                    $attributes['characteristics'] = $characteristics;
                }

                if ($isReviewed && $modality->name === 'Formação') {
                    $workloadFaq = $faqs->get($definition['workload_question']);
                    if (in_array($modality->workload, [null, '', 'Formação completa'], true) && $workloadFaq && preg_match('/^A formação tem ([1-9][0-9]*) horas,/u', $workloadFaq->answer, $matches)) {
                        $attributes['workload'] = $matches[1].' horas';
                        $this->line("  Fonte de carga horária: FAQ {$workloadFaq->id} — {$attributes['workload']} somente na Formação.");
                    }

                    $contentFaq = $faqs->get($definition['content_question']);
                    if ($legacyFeatures && $contentFaq?->answer === $definition['content_answer']) {
                        $attributes['features'] = $definition['topics'];
                        $this->line("  Fonte de conteúdo: FAQ {$contentFaq->id} — temas específicos da Formação.");
                    }
                }

                if ($isReviewed && $modality->name === 'Atualização' && $modality->workload === 'Atualização do curso') {
                    $attributes['workload'] = null;
                }

                $this->line('  '.$modality->name.': '.(empty($attributes) ? 'nenhuma alteração' : implode(', ', array_keys($attributes))));
                $this->line('  Não confirmado: '.implode(', ', array_filter([
                    empty($attributes['workload'] ?? $modality->workload) || $modality->workload === 'Atualização do curso' ? 'carga horária' : null,
                    empty($characteristics['video_lessons_count']) ? 'quantidade de videoaulas' : null,
                    empty($characteristics['video_lessons_duration']) ? 'duração das videoaulas' : null,
                    ! str_contains(mb_strtolower($characteristics['materials'] ?? ''), 'pdf') ? 'material em PDF' : null,
                ])));

                if ($attributes !== []) {
                    $changes[] = ['modality' => $modality, 'attributes' => $attributes];
                }
            }
        }

        if (! $this->option('apply')) {
            $this->components->info(count($courses).' curso(s) revisado(s). Simulação: '.count($changes).' modalidade(s) com alterações propostas; nenhum dado foi salvo.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $change) {
                $modality = $change['modality'];
                $current = $modality->newQuery()->lockForUpdate()->findOrFail($modality->id);
                if ($current->getRawOriginal() !== $modality->getRawOriginal()) {
                    throw new \RuntimeException('Uma modalidade foi editada durante a revisão. Nada foi aplicado; execute a simulação novamente.');
                }
                $current->update($change['attributes']);
            }
        });

        if ($changes !== []) {
            $cache->invalidate();
        }

        $this->components->info(count($changes).' modalidade(s) atualizada(s). Preços, imagens, publicação, bônus e FAQs foram preservados.');

        return self::SUCCESS;
    }
}
