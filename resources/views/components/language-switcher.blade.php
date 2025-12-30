<!-- Language Switcher Component -->
<div x-data="{ open: false }" class="relative">
    <button 
        @click="open = !open" 
        class="flex items-center gap-2 px-3 py-2 rounded-lg border-2 border-gray-200 hover:border-primary hover:bg-primary/5 transition-all duration-200 bg-white shadow-sm hover:shadow-md"
        aria-label="Switch language"
    >
        <i class="fas fa-globe text-lg text-primary"></i>
        <span class="text-sm font-medium text-gray-700">{{ app()->getLocale() === 'id' ? 'ID' : 'EN' }}</span>
        <i class="fas fa-chevron-down text-xs text-gray-500 transition-transform duration-200" :class="{ 'rotate-180': open }"></i>
    </button>

    <!-- Language Dropdown -->
    <div 
        x-show="open" 
        x-cloak
        @click.away="open = false" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
        class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-50"
    >
        <div class="py-2">
            <a 
                href="{{ route('language.switch', 'en') }}" 
                class="flex items-center justify-between px-4 py-3 text-sm hover:bg-primary/10 transition-all duration-200 {{ app()->getLocale() === 'en' ? 'bg-primary/5 border-l-4 border-primary' : '' }}"
            >
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🇬🇧</span>
                    <span class="font-medium {{ app()->getLocale() === 'en' ? 'text-primary' : 'text-gray-700' }}">English</span>
                </div>
                @if(app()->getLocale() === 'en')
                    <i class="fas fa-check text-primary font-bold"></i>
                @endif
            </a>
            <a 
                href="{{ route('language.switch', 'id') }}" 
                class="flex items-center justify-between px-4 py-3 text-sm hover:bg-primary/10 transition-all duration-200 {{ app()->getLocale() === 'id' ? 'bg-primary/5 border-l-4 border-primary' : '' }}"
            >
                <div class="flex items-center gap-3">
                    <span class="text-2xl">🇮🇩</span>
                    <span class="font-medium {{ app()->getLocale() === 'id' ? 'text-primary' : 'text-gray-700' }}">Bahasa Indonesia</span>
                </div>
                @if(app()->getLocale() === 'id')
                    <i class="fas fa-check text-primary font-bold"></i>
                @endif
            </a>
        </div>
    </div>
</div>
