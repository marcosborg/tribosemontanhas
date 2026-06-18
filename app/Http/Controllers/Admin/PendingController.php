<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PendingTask;
use App\Services\PendingItemsService;
use Gate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PendingController extends Controller
{
    public function index(PendingItemsService $pendingItemsService)
    {
        abort_if(Gate::denies('pending_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $documents = $pendingItemsService->documents();
        $tasks = $pendingItemsService->openTasks();

        return view('admin.pending.index', compact('documents', 'tasks'));
    }

    public function storeTask(Request $request)
    {
        abort_if(Gate::denies('pending_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        PendingTask::create($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]));

        return redirect()->route('admin.pendentes.index')->with('message', 'Tarefa criada com sucesso.');
    }

    public function completeTask(PendingTask $pendingTask)
    {
        abort_if(Gate::denies('pending_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $pendingTask->update([
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);

        return redirect()->route('admin.pendentes.index')->with('message', 'Tarefa concluida com sucesso.');
    }
}
