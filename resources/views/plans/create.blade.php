<x-layouts.mobile heading="New plan" title="Finfolio · New plan" :back="route('plans.index')">
    <div class="app-pad lg:mx-auto lg:max-w-xl">
        @include('plans._form')
    </div>
</x-layouts.mobile>
