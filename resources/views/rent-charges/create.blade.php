@extends('layouts.app')

@section('title', 'Nowy czynsz')
@section('heading', 'Czynsz poza naliczeniem')
@section('subheading', 'Dopisanie miesiąca ręcznie — zwykłe czynsze naliczają się same')

@section('content')
    <flux:card class="max-w-4xl">
        <form method="POST" action="{{ route('rent-charges.store') }}" class="space-y-6">
            @include('rent-charges.form')
        </form>
    </flux:card>
@endsection
