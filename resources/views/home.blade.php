@extends('layouts.app')

@section('lang', 'en')
@section('title', 'Sequential numbers for ADRs and specs')

@section('content')
    <div class="legal">
        <h1>get-id</h1>
        <p>get-id issues sequential numbers for architecture decision records (ADRs) and specifications in the
            software repositories of CAS. When several developers or AI coding assistants create documents in the
            same repository at the same time, each of them gets a different number, and asking again for the same
            document returns the number it already has.</p>

        <h2>How it works</h2>
        <ul>
            <li>An administrator registers a repository and enables the document types numbered in it.</li>
            <li>An employee signs in with their corporate Google account
                (<code>{{ '@'.config('getid.allowed_email_domain') }}</code>) and creates a personal access token.</li>
            <li>Claude Code or any REST client asks for the next number with that token, over MCP or the REST API.</li>
        </ul>

        <p>Sign-in is limited to corporate accounts. Google is used only to confirm who you are: the service reads
            your name, email address and profile picture and nothing else from your Google account. See the
            <a href="{{ route('privacy') }}">Privacy Policy</a> and the <a href="{{ route('terms') }}">Terms of Service</a>.</p>

        <p>@include('partials.google-sign-in', ['label' => 'Sign in with Google'])</p>
    </div>
@endsection
