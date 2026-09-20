<div class="p-6 h-full flex flex-col bg-gray-50/50">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-blue-600"></i>
                Workspaces
            </h1>
            <p class="text-sm text-gray-500 mt-1">Manage your project boards and tasks</p>
        </div>
    </div>

    <!-- Workspace Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
        @foreach($workspaces as $workspace)
            <a href="/workspaces/{{ $workspace->id }}" class="group block bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-xl hover:border-blue-200 transition-all duration-300 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-blue-500 to-indigo-600 transform origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-300"></div>
                
                <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600 transition-colors">{{ $workspace->name }}</h3>
                <p class="text-sm text-gray-500 mt-2 line-clamp-2 h-10">{{ $workspace->description ?? 'No description provided' }}</p>
                
                <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between">
                    <div class="flex -space-x-2">
                        <!-- Placeholder avatars -->
                        <div class="w-8 h-8 rounded-full bg-blue-100 border-2 border-white flex items-center justify-center text-xs font-bold text-blue-600">A</div>
                        <div class="w-8 h-8 rounded-full bg-indigo-100 border-2 border-white flex items-center justify-center text-xs font-bold text-indigo-600">B</div>
                    </div>
                    <div class="flex items-center gap-1.5 text-xs font-medium text-gray-500 bg-gray-50 px-2 py-1 rounded-md">
                        <i class="fa-solid fa-list-check"></i>
                        {{ $workspace->tasks_count }} Tasks
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    <!-- Create New Workspace Form -->
    <div class="bg-white rounded-2xl border border-gray-200 p-6 max-w-xl shadow-sm">
        <h2 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-plus-circle text-gray-400"></i> Create New Workspace
        </h2>
        
        <form wire:submit="createWorkspace" class="space-y-4">
            <div>
                <flux:input wire:model="newWorkspaceName" label="Workspace Name" placeholder="e.g. Produksi Video, Kampanye Q3" />
            </div>
            
            <div>
                <flux:textarea wire:model="newWorkspaceDescription" label="Description (Optional)" rows="3" placeholder="What is this workspace for?" />
            </div>
            
            <flux:button type="submit" variant="primary" class="w-full">
                Create Workspace
                <div wire:loading wire:target="createWorkspace" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin ml-2"></div>
            </flux:button>
        </form>
    </div>
</div>
