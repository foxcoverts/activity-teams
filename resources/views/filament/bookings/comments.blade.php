<div>
    @if ($showPanel)
        <x-filament::section class="booking-comments-panel" :heading="__('Comments')" :icon="\Filament\Support\Icons\Heroicon::OutlinedChatBubbleLeftRight" :has-content-el="false">
            @if ($hasOlder && !$showOlder)
                <div class="comment-toolbar">
                    <x-filament::button color="gray" outlined size="sm" wire:click="showOlderComments">
                        {{ __('Show older comments') }}
                    </x-filament::button>
                </div>
            @endif

            @foreach ($rows as $row)
                <div id="comment-{{ $row['id'] }}"
                    class="comment-row{{ $row['trashed'] ? ' comment-row-ghosted' : '' }}"
                    wire:key="comment-{{ $row['id'] }}"
                    @if ($row['highlight']) x-data
                        x-init="$el.scrollIntoView({ block: 'center' })" @endif>
                    <x-filament-panels::avatar.user :user="$row['authorUser']" size="lg" class="shrink-0" />

                    <div class="comment-main">
                        <div class="comment-header">
                            <div class="comment-meta">
                                <span class="comment-author">{{ $row['author'] }}</span>

                                @if ($row['is_manager'])
                                    <x-filament::badge :color="\App\Support\Palette::manager()" :icon="\Filament\Support\Icons\Heroicon::Star">
                                        {{ __('Manager') }}
                                    </x-filament::badge>
                                @endif

                                @if ($row['is_lead'])
                                    <x-filament::badge :color="\App\Support\Palette::informative()" :icon="\Filament\Support\Icons\Heroicon::OutlinedFlag">
                                        {{ __('Lead') }}
                                    </x-filament::badge>
                                @endif

                                @if (filled($row['when']))
                                    <span class="comment-dot" aria-hidden="true">&middot;</span>
                                    <time class="comment-when" datetime="{{ $row['when_iso'] }}" x-data
                                        x-tooltip.raw="{{ $row['when_full'] }}">{{ $row['when'] }}</time>
                                @endif

                                @if (filled($row['state']))
                                    <span class="comment-dot" aria-hidden="true">&middot;</span>
                                    <span class="comment-state">{{ $row['state'] }}</span>
                                @endif
                            </div>

                            @if (!$row['editing'] && ($row['can_edit'] || $row['can_remove'] || $row['can_restore']))
                                <div class="comment-menu">
                                    <x-filament::dropdown placement="bottom-end"
                                        wire:key="comment-menu-{{ $row['id'] }}">
                                        <x-slot name="trigger">
                                            <x-filament::icon-button color="gray" size="sm" :icon="\Filament\Support\Icons\Heroicon::OutlinedEllipsisHorizontal"
                                                :label="__('Comment actions')" />
                                        </x-slot>

                                        <x-filament::dropdown.list>
                                            @if ($row['can_edit'])
                                                <x-filament::dropdown.list.item :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare"
                                                    wire:click="startEdit('{{ $row['id'] }}')">
                                                    {{ __('Edit') }}
                                                </x-filament::dropdown.list.item>
                                            @endif

                                            @if ($row['can_restore'])
                                                <x-filament::dropdown.list.item :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft"
                                                    wire:click="restore('{{ $row['id'] }}')">
                                                    {{ __('Restore') }}
                                                </x-filament::dropdown.list.item>
                                            @endif

                                            @if ($row['can_remove'])
                                                <x-filament::dropdown.list.item color="danger" :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
                                                    wire:click="remove('{{ $row['id'] }}')"
                                                    wire:confirm="{{ __('Are you sure you would like to remove this comment?') }}">
                                                    {{ __('Remove') }}
                                                </x-filament::dropdown.list.item>
                                            @endif
                                        </x-filament::dropdown.list>
                                    </x-filament::dropdown>
                                </div>
                            @endif
                        </div>

                        @if ($row['editing'])
                            <form wire:submit="saveEdit" class="comment-compose" x-data>
                                <textarea x-ref="edit" wire:model="editBody" rows="1" class="comment-input"
                                    aria-label="{{ __('Edit comment') }}" x-init="$el.style.height = '0px';
                                    $el.style.height = $el.scrollHeight + 'px'"
                                    x-on:input="$el.style.height = '0px'; $el.style.height = $el.scrollHeight + 'px'"
                                    x-on:keydown.enter="if ($event.shiftKey || $event.isComposing) { return }; $event.preventDefault(); if (! $el.value.trim()) { return }; $wire.set('editBody', $el.value).then(() => $wire.saveEdit())"
                                    x-on:keydown.escape.prevent="$wire.cancelEdit()"></textarea>
                                <button type="submit" class="comment-submit">
                                    {{ __('Save') }}
                                </button>
                            </form>
                        @else
                            <p class="comment-body">{!! str($row['body'])->inlineMarkdown([
                                'html_input' => 'strip',
                                'allow_unsafe_links' => false,
                                'renderer' => ['soft_break' => "<br />\n"],
                            ]) !!}</p>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($canPost)
                <form wire:submit="post" class="comment-row comment-composer" x-data="{ canPost: false }">
                    <x-filament-panels::avatar.user :user="$viewer" size="lg" class="shrink-0" />

                    <div class="comment-main">
                        <div class="comment-meta">
                            <span class="comment-author">{{ $viewer->name }}</span>
                        </div>

                        <div class="comment-compose">
                            <textarea id="booking-comment-body" wire:model="body" rows="1" class="comment-input"
                                aria-label="{{ __('Add a comment') }}" placeholder="{{ __('Add a comment...') }}" x-init="$el.style.height = '0px';
                                $el.style.height = $el.scrollHeight + 'px'"
                                x-on:input="canPost = $el.value.trim() !== ''; $el.style.height = '0px'; $el.style.height = $el.scrollHeight + 'px'"
                                x-on:keydown.enter="if ($event.shiftKey || $event.isComposing) { return }; $event.preventDefault(); if (! $el.value.trim()) { return }; $wire.set('body', $el.value).then(() => $wire.post())"></textarea>
                            <button type="submit" class="comment-submit" x-bind:disabled="!canPost">
                                {{ __('Post') }}
                            </button>
                        </div>

                        @error('body')
                            <p class="comment-error">{{ $message }}</p>
                        @enderror
                    </div>
                </form>
            @endif
        </x-filament::section>
    @endif
</div>
