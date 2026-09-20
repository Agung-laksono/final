<?php

namespace Modules\Workspace\Livewire;

use Livewire\Component;
use Modules\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Auth;

class BoardList extends Component
{
    public $newWorkspaceName = '';
    public $newWorkspaceDescription = '';

    public function createWorkspace()
    {
        $this->validate([
            'newWorkspaceName' => 'required|string|max:255',
            'newWorkspaceDescription' => 'nullable|string'
        ]);

        $workspace = Workspace::create([
            'name' => $this->newWorkspaceName,
            'description' => $this->newWorkspaceDescription,
            'owner_id' => Auth::id() ?? 1 // Fallback to 1 if not logged in
        ]);
        
        // Add current user as member
        if (Auth::check()) {
            $workspace->users()->attach(Auth::id(), ['role' => 'admin']);
        }

        $this->reset(['newWorkspaceName', 'newWorkspaceDescription']);
        
        // Optional: create default columns for the new workspace
        $workspace->columns()->createMany([
            ['title' => 'Ide / Backlog', 'color' => 'gray', 'position' => 1],
            ['title' => 'In Progress', 'color' => 'blue', 'position' => 2],
            ['title' => 'Review', 'color' => 'yellow', 'position' => 3],
            ['title' => 'Done', 'color' => 'green', 'position' => 4],
        ]);
        
        $this->dispatch('workspace-created');
    }

    public function render()
    {
        // For now, let's just get all workspaces or workspaces owned by user
        $workspaces = Workspace::withCount('tasks')->get();
        
        return view('workspace::livewire.board-list', [
            'workspaces' => $workspaces
        ]);
    }
}
