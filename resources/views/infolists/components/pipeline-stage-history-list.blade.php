<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry"
                     class="grid grid-cols-[--cols-default] fi-in-component-ctn gap-6">
    @foreach($getState() as $pipelineLog)
        <div class="mb-4">
            <div class="">
                <span class="font-bold">{{ $pipelineLog->user?->name ?? 'System' }}</span>, <span x-data="{}" x-tooltip="{
                        content: '{{ $pipelineLog->created_at }}',
                        theme: $store.theme,
                    }">{{ $pipelineLog->created_at->diffForHumans() }}</span>
            </div>
            <div class="">
                <span class="font-bold">Pipeline Stage:</span> {{ $pipelineLog->pipelineStage->name }}
            </div>
            @if($pipelineLog->notes)
                <div class="">
                    <span class="font-bold">Note:</span> {{ $pipelineLog->notes }}
                </div>
            @endif
        </div>
    @endforeach
</x-dynamic-component>
