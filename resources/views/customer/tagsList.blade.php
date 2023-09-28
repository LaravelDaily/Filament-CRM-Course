@foreach($tags as $tag)
    <div class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-1.5 min-w-[theme(spacing.5)] py-0.5 tracking-tight"
         style="background: {{ $tag->color }}; display: inline-block;">
        <span class="grid">
            <span class="truncate">{{ $tag->name }}</span>
        </span>
    </div>
@endforeach