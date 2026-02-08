@props(['size' => 'md', 'showLabels' => false])

<div class="flex items-center {{ $showLabels ? 'flex-wrap gap-4' : 'gap-3' }}">
    <!-- YouTube -->
    <a href="https://www.youtube.com/@Qashierwisecom"
       target="_blank"
       rel="noopener noreferrer"
       class="{{ $showLabels ? 'flex items-center' : '' }} {{ $size === 'lg' ? 'w-10 h-10' : ($size === 'sm' ? 'w-8 h-8' : 'w-9 h-9') }} bg-red-600 hover:bg-red-700 text-white rounded-full flex items-center justify-center transition-colors"
       aria-label="Follow us on YouTube">
        <i class="fab fa-youtube {{ $size === 'lg' ? 'text-xl' : ($size === 'sm' ? 'text-base' : 'text-lg') }}"></i>
        @if($showLabels)
            <span class="ml-2 text-sm font-medium">YouTube</span>
        @endif
    </a>

    <!-- Threads -->
    <a href="https://www.threads.com/@qashierwisecom"
       target="_blank"
       rel="noopener noreferrer"
       class="{{ $showLabels ? 'flex items-center' : '' }} {{ $size === 'lg' ? 'w-10 h-10' : ($size === 'sm' ? 'w-8 h-8' : 'w-9 h-9') }} bg-gray-900 hover:bg-gray-800 text-white rounded-full flex items-center justify-center transition-colors"
       aria-label="Follow us on Threads">
        <i class="fab fa-threads {{ $size === 'lg' ? 'text-xl' : ($size === 'sm' ? 'text-base' : 'text-lg') }}"></i>
        @if($showLabels)
            <span class="ml-2 text-sm font-medium">Threads</span>
        @endif
    </a>

    <!-- Instagram -->
    <a href="https://www.instagram.com/qashierwisecom"
       target="_blank"
       rel="noopener noreferrer"
       class="{{ $showLabels ? 'flex items-center' : '' }} {{ $size === 'lg' ? 'w-10 h-10' : ($size === 'sm' ? 'w-8 h-8' : 'w-9 h-9') }} bg-gradient-to-br from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white rounded-full flex items-center justify-center transition-all"
       aria-label="Follow us on Instagram">
        <i class="fab fa-instagram {{ $size === 'lg' ? 'text-xl' : ($size === 'sm' ? 'text-base' : 'text-lg') }}"></i>
        @if($showLabels)
            <span class="ml-2 text-sm font-medium">Instagram</span>
        @endif
    </a>
</div>
