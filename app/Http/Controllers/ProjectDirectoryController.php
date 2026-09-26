<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectDirectoryLocator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProjectDirectoryController extends Controller
{
    public function __construct(private readonly ProjectDirectoryLocator $locator) {}

    public function show(Request $request, Project $project): View
    {
        abort_unless($request->user()->can('project.view'), 403);

        // AV ist Standard, das SV nur mit Recht project.directory.locked
        // (Ralf, 2026-09-26) - ohne Recht existiert der SV-Reiter nicht.
        $sources = $this->locator->visibleSources($project->tenant_id, $request->user());
        abort_if($sources === [], 403);

        $requested = $request->query('quelle');
        $source = in_array($requested, $sources, true) ? $requested : $sources[0];

        $status = $this->locator->statusForProject($project, $source);
        $contents = $status['status'] === 'found' ? $this->locator->listContents($status['path']) : [];

        return view('projekte.partials.directory-content', [
            'project' => $project,
            'sources' => $sources,
            'source' => $source,
            'status' => $status,
            'contents' => $contents,
            'suggestedFolderName' => $this->locator->suggestedFolderName($project),
        ]);
    }

    public function store(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.edit'), 403);

        $validated = $request->validate([
            'folder_name' => ['required', 'string', 'max:200'],
            'quelle' => ['nullable', 'in:'.ProjectDirectoryLocator::SOURCE_AV.','.ProjectDirectoryLocator::SOURCE_SV],
        ]);
        $source = $validated['quelle'] ?? ProjectDirectoryLocator::SOURCE_SV;

        abort_unless(in_array($source, $this->locator->visibleSources($project->tenant_id, $request->user()), true), 403);

        $status = $this->locator->statusForProject($project, $source);
        abort_if($status['status'] !== 'not_found', 409);

        $basePath = $this->locator->basePathFor($project->tenant_id, $source);

        $folderName = $project->source_pn.'_'.$this->locator->sanitizeFolderName(
            preg_replace('/^\d{6}_/', '', $validated['folder_name'])
        );

        abort_if(is_dir($basePath.DIRECTORY_SEPARATOR.$folderName), 409, 'Ein Verzeichnis mit diesem Namen existiert bereits.');

        $this->locator->create($basePath, $folderName);

        return response()->noContent();
    }
}
