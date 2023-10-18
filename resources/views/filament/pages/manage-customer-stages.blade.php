<x-filament-panels::page>
    <x-filament::card wire:ignore.self>
        <div>
            <div class="w-full h-full flex space-x-4 rtl:space-x-reverse overflow-x-auto">
                @foreach($statuses as $status)
                    <div class="h-full flex-1">
                        <div class="bg-primary-200 rounded px-2 flex flex-col h-full" id="{{ $status['id'] }}">
                            <div class="p-2 text-sm text-gray-900">
                                {{ $status['title'] }}
                            </div>
                            <div
                                    id="{{ $status['kanbanRecordsId'] }}"
                                    data-status-id="{{ $status['id'] }}"
                                    class="space-y-2 p-2 flex-1 overflow-y-auto">

                                @foreach($status['records'] as $record)
                                    <div
                                            id="{{ $record['id'] }}"
                                            class="shadow bg-white dark:bg-gray-800 p-2 rounded border">

                                        <p>
                                            {{ $record['title'] }}
                                        </p>

                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>


            <div wire:ignore>
                <script>
                    window.onload = () => {
                        @foreach($statuses as $status)
                        {{-- Space here is needed to fix Livewire issue where it adds comment block breaking JS scripts--}}
                        Sortable.create(document.getElementById('{{ $status['kanbanRecordsId'] }}'), {
                            group: '{{ $status['group'] }}',
                            animation: 0,
                            ghostClass: 'bg-warning-600',

                            setData: function (dataTransfer, dragEl) {
                                dataTransfer.setData('id', dragEl.id);
                            },

                            onEnd: function (evt) {
                                const sameContainer = evt.from === evt.to;
                                const orderChanged = evt.oldIndex !== evt.newIndex;

                                if (sameContainer && !orderChanged) {
                                    return;
                                }

                                const recordId = evt.item.id;
                                const toStatusId = evt.to.dataset.statusId;

                                @this.
                                dispatch('statusChangeEvent', {
                                    id: recordId,
                                    pipeline_stage_id: toStatusId
                                });
                            },
                        });
                        @endforeach
                    }
                </script>

            </div>
        </div>
    </x-filament::card>
</x-filament-panels::page>
