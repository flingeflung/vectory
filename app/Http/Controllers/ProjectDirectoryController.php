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

        $status = $this->locator->statusForProject($project);
        $contents = $status['status'] === 'found' ? $this->locator->listContents($status['path']) : [];

        return view('projekte.partials.directory-content', [
            'project' => $project,
            'status' => $status,
            'contents' => $contents,
        ]);
    }

    public function store(Request $request, Project $project): Response
    {
        abort_unless($request->user()->can('project.edit'), 403);

        $status = $this->locator->statusForProject($project);
        abort_if($status['status'] !== 'not_found', 409);

        $basePath = $this->locator->basePath($project->tenant_id);

        $validated = $request->validate([
            'folder_name' => ['required', 'string', 'max:200'],
        ]);

        $folderName = $project->source_pn.'_'.$this->locator->sanitizeFolderName(
            preg_replace('/^\d{6}_/', '', $validated['folder_name'])
        );

        abort_if(is_dir($basePath.DIRECTORY_SEPARATOR.$folderName), 409, 'Ein Verzeichnis mit diesem Namen existiert bereits.');

        $this->locator->create($basePath, $folderName);

        return response()->noContent();
    }
}
