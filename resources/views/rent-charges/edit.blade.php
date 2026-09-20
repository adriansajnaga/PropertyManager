@extends('layouts.app')

@section('title', 'Edycja czynszu')
@section('heading', $charge->unit->description.' — '.$charge->month->format('m.Y'))
@section('subheading', 'Numer faktury, termin płatności i zapłata')

@section('content')
    <flux:card class="max-w-4xl">
        <form method="POST" action="{{ route('rent-charges.update', $charge) }}" class="space-y-6">
            @method('PUT')
            @include('rent-charges.form')
        </form>
    </flux:card>
@endsection
