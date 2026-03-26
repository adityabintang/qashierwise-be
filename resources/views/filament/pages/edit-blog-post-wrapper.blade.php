<div x-data="{ saving: false }">
    <div
        x-show="saving"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="fixed top-0 left-0 right-0 z-50"
        style="display: none;"
    >
        <div class="h-1 bg-primary-600 animate-pulse"></div>
        <div class="bg-white border-b border-gray-200 px-4 py-2 shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-sm font-medium text-gray-700">Saving changes...</span>
            </div>
        </div>
    </div>

    {{ $slot }}
</div>

<script>
    document.addEventListener('livewire:init', () => {
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            if (commit.calls && commit.calls.some(c => c.method === 'save')) {
                const el = document.querySelector('[x-data]');
                if (el && el._x_dataStack) {
                    el._x_dataStack[0].saving = true;
                }
                succeed(({ snapshot, effect }) => {
                    setTimeout(() => {
                        const el = document.querySelector('[x-data]');
                        if (el && el._x_dataStack) {
                            el._x_dataStack[0].saving = false;
                        }
                    }, 600);
                });
                fail(() => {
                    const el = document.querySelector('[x-data]');
                    if (el && el._x_dataStack) {
                        el._x_dataStack[0].saving = false;
                    }
                });
            }
        });
    });
</script>
