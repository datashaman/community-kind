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
                ->orderBy('name')
                ->get(['id', 'organisation_id', 'name', 'slug', 'configuration'])
                ->map(fn (Program $program): array => [
                    ...$program->only(['id', 'name', 'slug', 'configuration']),
                    'canUpdate' => Gate::allows('update', $program),
                ]),
        ]);
    }

    public function show(Organisation $organisation, string $program): JsonResponse
    {
        $program = $this->findProgram($program);
        Gate::authorize('view', $program);

        return response()->json($program->only(['id', 'organisation_id', 'name', 'slug', 'configuration']));
    }

    public function update(UpdateProgramRequest $request, Organisation $organisation, string $program, UpdateProgram $updateProgram): JsonResponse
    {
        $program = $this->findProgram($program);
        Gate::authorize('update', $program);
        $program = $updateProgram->handle($program, $this->updateAttributes($request), $request->user());

        return response()->json($program->only(['id', 'organisation_id', 'name', 'slug', 'configuration']));
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

    /** @return array{name: string, slug: string, configuration?: array<string, mixed>} */
    private function updateAttributes(UpdateProgramRequest $request): array
    {
        $configuration = $request->validated('configuration');

        return $request->has('configuration') && is_array($configuration)
            ? [
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
                'configuration' => $configuration,
            ]
            : [
                'name' => $request->string('name')->toString(),
                'slug' => $request->string('slug')->toString(),
            ];
    }
}
