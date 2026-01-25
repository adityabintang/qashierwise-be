@props(['items' => []])

@if(count($items) > 0)
    <nav aria-label="Breadcrumb" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <ol class="flex items-center space-x-2 text-sm text-gray-600">
            @foreach($items as $index => $item)
                @if($index > 0)
                    <li class="flex items-center">
                        <i class="fas fa-chevron-right text-gray-400 text-xs mx-2"></i>
                    </li>
                @endif
                <li class="inline-flex items-center">
                    @if($loop->last)
                        <span class="font-semibold text-gray-900">{{ $item['label'] }}</span>
                    @else
                        <a href="{{ $item['url'] }}" class="hover:text-primary transition-colors">
                            {{ $item['label'] }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
