@extends('layouts.dashboard')

@section('title', __('Wolfi Voices').' | '.__('site.meta.brand'))
@section('dashboard-title', __('Wolfi Voices'))
@section('dashboard-subtitle', __('Preview every available Wolfi voice, choose one, and save it for platform speech playback.'))

@section('content')
    @include('partials.wolfi-voices-panel')
@endsection
