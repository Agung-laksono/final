<?php

namespace Modules\Workspace\Livewire;

use Livewire\Component;
use Modules\Workspace\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BoardList extends Component
{
    // Create state
    public bool $showCreateModal = false;
    public bool $showGuideModal = false;
    public $newWorkspaceName = '';
    public $newWorkspaceDescription = '';
    public $cover_image = null;

    // Edit state
    public bool $showEditModal = false;
    public $editingWorkspaceId = null;
    public $editName = '';
    public $editDescription = '';
    public $editCoverImage = null;
    public $existingCoverImage = null;

    public function createWorkspace()
    {
        $this->validate([
            'newWorkspaceName' => 'required|string|max:255',
            'newWorkspaceDescription' => 'nullable|string'
        ]);

        $coverImagePath = $this->saveCoverImage($this->cover_image);

        $workspace = Workspace::create([
            'name' => $this->newWorkspaceName,
            'description' => $this->newWorkspaceDescription,
            'owner_id' => Auth::id() ?? 1,
            'cover_image' => $coverImagePath,
            'is_active' => true,
        ]);

        // Add current user as admin member
        if (Auth::check()) {
            $workspace->users()->attach(Auth::id(), ['role' => 'admin']);
        }

        $this->reset(['newWorkspaceName', 'newWorkspaceDescription', 'cover_image']);
        $this->dispatch('reset-cropper');
        $this->showCreateModal = false;
    }

    public function openEdit($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);

        // Only owner/admin can edit
        if (!$this->canManage($workspace)) return;

        $this->editingWorkspaceId = $workspaceId;
        $this->editName = $workspace->name;
        $this->editDescription = $workspace->description ?? '';
        $this->editCoverImage = null;
        $this->existingCoverImage = $workspace->cover_image
            ? Storage::url($workspace->cover_image)
            : null;

        $this->showEditModal = true;
    }

    public function updateWorkspace()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editDescription' => 'nullable|string',
        ]);

        $workspace = Workspace::findOrFail($this->editingWorkspaceId);

        if (!$this->canManage($workspace)) return;

        $data = [
            'name' => $this->editName,
            'description' => $this->editDescription,
        ];

        // If new image was uploaded
        if ($this->editCoverImage) {
            // Delete old image
            if ($workspace->cover_image) {
                Storage::disk('public')->delete($workspace->cover_image);
            }
            $data['cover_image'] = $this->saveCoverImage($this->editCoverImage);
        }

        $workspace->update($data);

        $this->reset(['editingWorkspaceId', 'editName', 'editDescription', 'editCoverImage', 'existingCoverImage']);
        $this->dispatch('reset-cropper');
        $this->showEditModal = false;
    }

    public function toggleActive($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);
        if (!$this->canManage($workspace)) return;

        $workspace->update(['is_active' => !$workspace->is_active]);
    }

    public function deleteWorkspace($workspaceId)
    {
        $workspace = Workspace::findOrFail($workspaceId);

        // Only owner can delete
        if ($workspace->owner_id !== Auth::id()) return;

        // Delete cover image file
        if ($workspace->cover_image) {
            Storage::disk('public')->delete($workspace->cover_image);
        }

        $workspace->delete();
    }

    private function saveCoverImage($base64): ?string
    {
        if (!$base64) return null;

        $imageParts = explode(";base64,", $base64);
        if (count($imageParts) !== 2) return null;

        $imageTypeAux = explode("image/", $imageParts[0]);
        $imageType = $imageTypeAux[1] ?? 'png';
        $imageBase64 = base64_decode($imageParts[1]);
        $fileName = 'workspace_' . time() . '_' . uniqid() . '.' . $imageType;
        Storage::disk('public')->put('workspaces/' . $fileName, $imageBase64);

        return 'workspaces/' . $fileName;
    }

    private function canManage(Workspace $workspace): bool
    {
        if ($workspace->owner_id === Auth::id()) return true;

        $pivot = $workspace->users()->where('user_id', Auth::id())->first()?->pivot;
        return $pivot && $pivot->role === 'admin';
    }

    public function render()
    {
        $userId = Auth::id();

        // Only show workspaces the current user is a member of (or owns)
        $workspaces = Workspace::withCount(['tasks', 'users', 'objectives', 'kpis'])
            ->with(['users' => function ($q) { $q->take(5); }])
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                      ->orWhereHas('users', fn($q) => $q->where('user_id', $userId));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('workspace::livewire.board-list', [
            'workspaces' => $workspaces,
        ]);
    }
}
