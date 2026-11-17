@php
    use App\Filament\Admin\Resources\InboundMails\InboundMailResource;
    use App\Enums\InboundMailOutcome;
@endphp

<x-filament-widgets::widget :class="!$failed && $rows->isEmpty() ? 'hidden' : ''">
    @if ($failed)
        @include('filament.member.widgets.failed')
    @elseif ($rows->isNotEmpty())
        <x-filament::section :heading="$heading" :icon="$icon" :icon-color="$iconColor">
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($rows as $mail)
                    @php
                        $from = $mail->payload->from ?? null;
                        $fromName = is_object($from) && is_string($from->value[0]->name ?? null) && filled($from->value[0]->name)
                            ? $from->value[0]->name
                            : null;
                        $fromLine = $fromName
                            ?? $mail->from_address
                            ?? __('Unknown sender');
                        $when = $mail->created_at?->diffForHumans();
                        $detail = filled($mail->outcome_detail) ? $mail->outcome_detail : null;
                    @endphp
                    <li class="relative py-4 first:pt-0 last:pb-0" wire:key="inbound-{{ $mail->getKey() }}">
                        <a href="{{ InboundMailResource::getUrl('view', ['record' => $mail]) }}"
                            class="flex items-start justify-between gap-3 after:absolute after:inset-0 after:z-0 after:content-[''] hover:[&_.inbound-subject]:underline">
                            <div class="min-w-0 flex-1 space-y-1">
                                <p class="inbound-subject text-base font-semibold text-gray-950 dark:text-white">
                                    {{ $mail->subject ?: __('(No subject)') }}
                                </p>
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                                    <span>{{ $fromLine }}</span>
                                    @if ($mail->from_address && $fromName)
                                        <span class="text-gray-400 dark:text-gray-500">{{ $mail->from_address }}</span>
                                    @endif
                                </div>
                                @if ($detail)
                                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ $detail }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                @if ($when)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $when }}</p>
                                @endif
                                @if ($mail->outcome === InboundMailOutcome::Failed)
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-color-50 px-2.5 py-0.5 text-sm text-color-600 dark:bg-color-400/10 dark:text-color-400"
                                        @style([
                                            \Filament\Support\get_color_css_variables($mail->outcome->getColor(), shades: [50, 400, 600]),
                                        ])
                                    >
                                        {{ $mail->outcome->getLabel() }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($total > $previewLimit)
                <x-filament::link :href="$seeAllUrl" class="mt-3">{{ $seeAllLabel }}</x-filament::link>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
