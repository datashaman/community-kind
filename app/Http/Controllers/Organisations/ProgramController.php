<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Programs\BuildProgramReport;
use App\Actions\Programs\ExportPrograms;
use App\Actions\Programs\SearchPrograms;
use App\Actions\Programs\UpdateProgram;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\UpdateProgramRequest;
use App\Models\Organisation;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgramController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [Program::class, $currentOrganisation]);

        return Inertia::render('programs/index', [
            'programs' => Program::query()
                ->with(['stages', 'outcomeMeasures', 'taxonomies.values'])
                ->orderBy('name')
                ->get(['id', 'organisation_id', 'name', 'slug', 'request_label', 'case_label', 'configuration'])
                ->map(fn (Program $program): array => $this->serializeProgram($program) + [
                    'canUpdate' => Gate::allows('update', $program),
                ]),
        ]);
    }

    public function show(Organisation $organisation, string $program): JsonResponse
    {
        $program = $this->findProgram($program);
        Gate::authorize('view', $program);

        return response()->json($this->serializeProgram($program->load(['stages', 'outcomeMeasures', 'taxonomies.values'])));
    }

    public function update(UpdateProgramRequest $request, Organisation $organisation, string $program, UpdateProgram $updateProgram): JsonResponse|RedirectResponse
    {
        $program = $this->findProgram($program);
        Gate::authorize('update', $program);
        $program = $updateProgram->handle($program, $this->updateAttributes($request), $request->user());

        if ($request->header('X-Inertia') !== null) {
            return back();
        }

        return response()->json($this->serializeProgram($program));
    }

    public function search(Request $request, Organisation $organisation, SearchPrograms $searchPrograms): JsonResponse
    {
        $request->validate(['query' => ['required', 'string', 'max:100']]);
        Gate::authorize('viewAny', [Program::class, $organisation]);

        return response()->json($searchPrograms->handle($request->string('query')->toString()));
    }

    public function report(Organisation $organisation, BuildProgramReport $buildProgramReport): JsonResponse
    {
        Gate::authorize('viewAny', [Program::class, $organisation]);

        return response()->json($buildProgramReport->handle());
    }

    public function export(Organisation $organisation, ExportPrograms $exportPrograms): StreamedResponse
    {
        Gate::authorize('viewAny', [Program::class, $organisation]);

        return Storage::download($exportPrograms->handle());
    }

    private function findProgram(string $identifier): Program
    {
        return ctype_digit($identifier)
            ? Program::query()->findOrFail((int) $identifier)
            : Program::query()->where('slug', $identifier)->firstOrFail();
    }

    /**
     * @return array{
     *     name: string,
     *     slug: string,
     *     request_label?: string,
     *     case_label?: string,
     *     stages?: list<array{id: int|null, label: string, retired: bool}>,
     *     outcome_measures?: list<array{id: int|null, label: string, unit: string|null, retired: bool}>,
     *     taxonomies?: list<array{id: int|null, label: string, retired: bool, values: list<array{id: int|null, label: string, retired: bool}>}>,
     *     configuration?: array<string, mixed>
     * }
     */
    private function updateAttributes(UpdateProgramRequest $request): array
    {
        $configuration = $request->validated('configuration');
        $attributes = [
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
        ];

        if ($request->has('request_label')) {
            $attributes['request_label'] = $request->string('request_label')->toString();
        }

        if ($request->has('case_label')) {
            $attributes['case_label'] = $request->string('case_label')->toString();
        }

        if ($request->has('stages')) {
            /** @var list<array{id?: int|null, label: string, retired: bool}> $stages */
            $stages = $request->validated('stages');
            $attributes['stages'] = array_map(fn (array $stage): array => [
                'id' => isset($stage['id']) ? (int) $stage['id'] : null,
                'label' => (string) $stage['label'],
                'retired' => (bool) $stage['retired'],
            ], $stages);
        }

        if ($request->has('outcome_measures')) {
            /** @var list<array{id?: int|null, label: string, unit?: string|null, retired: bool}> $outcomeMeasures */
            $outcomeMeasures = $request->validated('outcome_measures');
            $attributes['outcome_measures'] = array_map(fn (array $measure): array => [
                'id' => isset($measure['id']) ? (int) $measure['id'] : null,
                'label' => $measure['label'],
                'unit' => $measure['unit'] ?? null,
                'retired' => $measure['retired'],
            ], $outcomeMeasures);
        }

        if ($request->has('taxonomies')) {
            /** @var list<array{id?: int|null, label: string, retired: bool, values: list<array{id?: int|null, label: string, retired: bool}>}> $taxonomies */
            $taxonomies = $request->validated('taxonomies');
            $attributes['taxonomies'] = array_map(fn (array $taxonomy): array => [
                'id' => isset($taxonomy['id']) ? (int) $taxonomy['id'] : null,
                'label' => $taxonomy['label'],
                'retired' => $taxonomy['retired'],
                'values' => array_map(fn (array $value): array => [
                    'id' => isset($value['id']) ? (int) $value['id'] : null,
                    'label' => $value['label'],
                    'retired' => $value['retired'],
                ], $taxonomy['values']),
            ], $taxonomies);
        }

        if ($request->has('configuration') && is_array($configuration)) {
            /** @var array<string, mixed> $configuration */
            $attributes['configuration'] = $configuration;
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function serializeProgram(Program $program): array
    {
        return [
            ...$program->only(['id', 'organisation_id', 'name', 'slug', 'request_label', 'case_label', 'configuration']),
            'stages' => $program->stages->map(fn ($stage): array => [
                'id' => $stage->id,
                'key' => $stage->key,
                'label' => $stage->label,
                'retired' => $stage->retired_at !== null,
            ])->values(),
            'outcome_measures' => $program->outcomeMeasures->map(fn ($measure): array => [
                'id' => $measure->id,
                'key' => $measure->key,
                'label' => $measure->label,
                'unit' => $measure->unit,
                'retired' => $measure->retired_at !== null,
            ])->values(),
            'taxonomies' => $program->taxonomies->map(fn ($taxonomy): array => [
                'id' => $taxonomy->id,
                'key' => $taxonomy->key,
                'label' => $taxonomy->label,
                'retired' => $taxonomy->retired_at !== null,
                'values' => $taxonomy->values->map(fn ($value): array => [
                    'id' => $value->id,
                    'key' => $value->key,
                    'label' => $value->label,
                    'retired' => $value->retired_at !== null,
                ])->values(),
            ])->values(),
        ];
    }
}
