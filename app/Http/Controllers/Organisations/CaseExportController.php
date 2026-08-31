<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\CaseConfidentiality\ExportIdentifiableCases;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        Organisation $currentOrganisation,
        string $program,
        ExportIdentifiableCases $export,
    ): StreamedResponse {
        $program = Program::query()->findOrFail($program);
        abort_unless($program->organisation_id === $currentOrganisation->id, Response::HTTP_NOT_FOUND);

        try {
            $contents = $export->handle($program, $request->user());
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['export' => $exception->getMessage()]);
        }

        return response()->streamDownload(
            fn () => print $contents,
            "program-{$program->id}-cases.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
