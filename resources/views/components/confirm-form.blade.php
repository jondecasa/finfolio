@props([
    'action',
    'method' => 'DELETE',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirm' => 'Delete',
    'trigger' => 'Delete',
    'triggerClass' => null,
    'tone' => 'danger', // danger | primary
])

@php
    $triggerClasses = $triggerClass ?? ($tone === 'danger'
        ? 'btn w-full !py-2.5 bg-loss text-white hover:bg-loss/90'
        : 'btn-primary w-full !py-2.5');
    $confirmClasses = $tone === 'danger'
        ? 'btn bg-loss text-white hover:bg-loss/90'
        : 'btn-primary';
@endphp

<div x-data>
    <button type="button" @click="$refs.dlg.showModal()" class="{{ $triggerClasses }}">
        {{ $trigger }}
    </button>

    <dialog x-ref="dlg" class="confirm-dialog"
            @click="if ($event.target === $refs.dlg) $refs.dlg.close()">
        <div class="w-full max-w-sm rounded-3xl bg-ink-800 p-6 text-white shadow-2xl shadow-black/60 ring-1 ring-white/10">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl {{ $tone === 'danger' ? 'bg-loss/15 text-loss' : 'bg-accent/15 text-accent' }}">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    @if ($tone === 'danger')
                        <path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14M10 11v6M14 11v6"/>
                    @else
                        <path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>
                    @endif
                </svg>
            </div>

            <h3 class="text-center text-lg font-bold">{{ $title }}</h3>
            <p class="mt-1.5 text-center text-sm text-muted">{{ $message }}</p>

            @isset($body)
                <div class="mt-4">{{ $body }}</div>
            @endisset

            <div class="mt-6 flex gap-3">
                <button type="button" class="btn-ghost flex-1" @click="$refs.dlg.close()">Cancel</button>
                <form method="POST" action="{{ $action }}" class="flex-1">
                    @csrf
                    @method($method)
                    {{ $slot }}
                    <button type="submit" class="{{ $confirmClasses }} w-full">{{ $confirm }}</button>
                </form>
            </div>
        </div>
    </dialog>
</div>
