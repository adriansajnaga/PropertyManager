@extends('layouts.app')

@section('title', 'Edycja odczytu')
@section('heading', 'Edycja odczytu')

@section('content')
    @unless ($reading->isManual())
        <flux:callout variant="warning" icon="arrow-path" heading="Odczyt z synchronizacji ({{ $reading->source_table }})">
            <flux:callout.text>
                Ręczna zmiana zostanie nadpisana przy kolejnym przebiegu synchronizacji — popraw dane w źródle
                albo przypisz odczyt do właściwego licznika.
            </flux:callout.text>
        </flux:callout>
    @endunless

    <flux:card class="max-w-5xl">
        <form method="POST" action="{{ route('readings.update', $reading) }}" class="space-y-6">
            @method('PUT')
            @include('readings.form')
        </form>
    </flux:card>
@endsection
