<x-layouts.mobile heading="Edit plan" title="Finfolio · Edit plan" :back="route('plans.show', $plan)">
    <div class="app-pad lg:mx-auto lg:max-w-xl">
        @include('plans._form', ['plan' => $plan])
    </div>
</x-layouts.mobile>
