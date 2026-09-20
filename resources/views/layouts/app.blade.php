<x-layouts.app :title="$__env->yieldContent('title', 'PM Property Manager')">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <div>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <flux:heading size="xl" level="1">@yield('heading', 'PM Property Manager')</flux:heading>
                    @hasSection('subheading')
                        <flux:subheading size="lg">@yield('subheading')</flux:subheading>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">@yield('actions')</div>
            </div>
            <flux:separator variant="subtle" class="mt-6" />
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" :heading="session('status')" />
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle" heading="Nie udało się zapisać">
                <flux:callout.text>
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
            </flux:callout>
        @endif

        @yield('content')
    </div>
</x-layouts.app>
