@extends('layouts.app')

@section('lang', 'en')
@section('title', 'Terms of Service')

@section('content')
    <article class="legal">
        <h1>Terms of Service</h1>
        <p class="muted">Last updated: 24 September 2026</p>

        <p>These terms govern the use of get-id (<a href="{{ url('/') }}">{{ url('/') }}</a>), a service operated by
            Ruslan Novikov that issues sequential numbers for ADRs and specifications in the software repositories of
            CAS. By signing in or calling the API you accept them.</p>

        <h2>Who may use it</h2>
        <p>get-id is for employees of CAS. You may sign in only with your own corporate Google account in the
            <code>{{ '@'.config('getid.allowed_email_domain') }}</code> domain. Your use is also governed by
            <a href="{{ route('privacy') }}">the Privacy Policy</a>.</p>

        <h2>Your account and tokens</h2>
        <ul>
            <li>You are responsible for everything done with your account and your access tokens.</li>
            <li>Keep tokens secret, do not share them and do not commit them to a repository. Revoke a token you no longer
                need, or one that may have leaked, on the token page.</li>
            <li>Requests are rate-limited per token; a client that exceeds the limit is refused until the next minute.</li>
        </ul>

        <h2>Numbers</h2>
        <ul>
            <li>A number is issued for a repository, a document type and a document title. Asking again with the same
                title returns the same number.</li>
            <li>An issued number is not issued again. The only exception: an administrator may withdraw the last number
                of a document type in a repository, and that number is then issued again.</li>
            <li>The document name is built by your repository from the number; get-id does not check whether the document
                exists.</li>
        </ul>

        <h2>Acceptable use</h2>
        <p>Use get-id only to number documents of CAS repositories. Do not try to get around authentication, access
            other people's accounts or tokens, or overload the service.</p>

        <h2>Availability and warranty</h2>
        <p>get-id is an internal tool provided as is, without warranties of any kind and without a guaranteed level of
            availability. The operator may change, suspend or stop the service.</p>

        <h2>Limitation of liability</h2>
        <p>To the extent permitted by law, the operator is not liable for any indirect or consequential loss arising
            from the use of get-id or from its unavailability.</p>

        <h2>Ending access</h2>
        <p>Access ends when you leave CAS or breach these terms: the account is deactivated and its tokens are revoked.
            Numbers already issued stay in the register.</p>

        <h2>Changes</h2>
        <p>If these terms change, the new version is published on this page with a new date.</p>

        <h2>Contact</h2>
        <p>Ruslan Novikov, <a href="mailto:ruslan.novikov@cas.ai">ruslan.novikov@cas.ai</a></p>
    </article>
@endsection
