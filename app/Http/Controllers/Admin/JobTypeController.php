<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobTypeController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = CurrentTenant::id();
        $groups = DB::table('job_groups')->where('tenant_id', $tenantId)->orderBy('sort')->orderBy('name')->get();
        $jobs = DB::table('job_types')->where('tenant_id', $tenantId)
            ->orderBy('code')->orderBy('name')->get(['id', 'job_group_id', 'code', 'name']);

        $selectedGroup = $request->filled('gruppe')
            ? $groups->firstWhere('id', $request->integer('gruppe'))
            : $groups->first();

        return view('admin.job-types.index', compact('groups', 'jobs', 'selectedGroup'));
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('job_groups', 'name')->where('tenant_id', $tenantId)],
        ]);
        $id = DB::table('job_groups')->insertGetId([
            'tenant_id' => $tenantId, 'name' => $data['name'],
            'sort' => 1 + (int) DB::table('job_groups')->where('tenant_id', $tenantId)->max('sort'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.jobtypen', ['gruppe' => $id])->with('status', __('Jobgruppe angelegt.'));
    }

    public function reorderGroups(Request $request): Response
    {
        $tenantId = CurrentTenant::id();
        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*' => ['required', 'integer', 'distinct'],
        ]);
        $ids = array_map('intval', $data['groups']);
        $existing = DB::table('job_groups')->where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($ids) !== count($existing) || array_diff($ids, $existing)) {
            abort(422);
        }

        DB::transaction(function () use ($tenantId, $ids) {
            foreach ($ids as $index => $id) {
                DB::table('job_groups')->where('tenant_id', $tenantId)->where('id', $id)->update(['sort' => $index]);
            }
        });

        return response()->noContent();
    }

    public function updateGroup(Request $request, int $jobGroup): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        abort_unless(DB::table('job_groups')->where('tenant_id', $tenantId)->where('id', $jobGroup)->exists(), 404);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('job_groups', 'name')->where('tenant_id', $tenantId)->ignore($jobGroup)],
        ]);
        DB::table('job_groups')->where('tenant_id', $tenantId)->where('id', $jobGroup)
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return redirect()->route('admin.jobtypen', ['gruppe' => $jobGroup])->with('status', __('Jobgruppe gespeichert.'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        $data = $this->validatedJob($request, $tenantId);

        DB::table('job_types')->insert([
            'tenant_id' => $tenantId,
            'job_group_id' => $data['job_group_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.jobtypen', ['gruppe' => $data['job_group_id']])->with('status', __('Jobtyp angelegt.'));
    }

    public function update(Request $request, int $jobType): RedirectResponse
    {
        $tenantId = CurrentTenant::id();
        abort_unless(DB::table('job_types')->where('tenant_id', $tenantId)->where('id', $jobType)->exists(), 404);
        $data = $this->validatedJob($request, $tenantId, $jobType);

        DB::table('job_types')->where('tenant_id', $tenantId)->where('id', $jobType)
            ->update(['job_group_id' => $data['job_group_id'], 'code' => $data['code'], 'name' => $data['name'], 'updated_at' => now()]);

        return redirect()->route('admin.jobtypen', ['gruppe' => $data['job_group_id']])->with('status', __('Jobtyp gespeichert.'));
    }

    private function validatedJob(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $request->merge([
            'code' => trim((string) $request->input('code')),
            'name' => trim((string) $request->input('name')),
        ]);

        return $request->validate([
            'job_group_id' => ['required', 'integer', Rule::exists('job_groups', 'id')->where('tenant_id', $tenantId)],
            'code' => ['required', 'string', 'max:30', Rule::unique('job_types', 'code')->where('tenant_id', $tenantId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
