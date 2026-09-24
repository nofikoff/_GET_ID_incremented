@extends('layouts.app')

@section('lang', 'en')
@section('title', 'Privacy Policy')

{{-- Retention figures come from configuration so the policy cannot drift from what the service actually keeps. --}}
@php($logDays = in_array('daily', (array) config('logging.channels.stack.channels'), true) ? (int) config('logging.channels.daily.max_files') : null)

@section('content')
    <article class="legal">
        <h1>Privacy Policy</h1>
        <p class="muted">Last updated: 24 September 2026</p>

        <p>This policy explains what personal data get-id (<a href="{{ url('/') }}">{{ url('/') }}</a>) collects, including
            data received from Google, how it is used, stored and shared, and how to have it removed. get-id is an internal
            service that issues sequential numbers for ADRs and specifications to employees of CAS. It is operated by
            Ruslan Novikov, who is responsible for the data described here.</p>

        <h2>Data received from Google</h2>
        <p>You sign in with Google. get-id requests only the <code>openid</code>, <code>email</code> and
            <code>profile</code> scopes and receives:</p>
        <ul>
            <li>your email address and whether Google has verified it;</li>
            <li>your name;</li>
            <li>your Google account identifier;</li>
            <li>the address of your Google profile picture.</li>
        </ul>
        <p>get-id does not access your Gmail, Drive, Calendar, contacts or any other Google service, and it does not store
            the access token Google issues at sign-in.</p>

        <h2>How Google data is used</h2>
        <ul>
            <li>The email address decides whether you may sign in: only verified addresses in the
                <code>{{ '@'.config('getid.allowed_email_domain') }}</code> domain are accepted. It also identifies your
                account and is recorded with the numbers you issue and the changes you make.</li>
            <li>The Google account identifier links later sign-ins to the same account.</li>
            <li>The name labels your account. The profile picture address is stored with the account and is not shown
                anywhere or used for anything else.</li>
        </ul>
        <p>get-id's use and transfer of information received from Google APIs adheres to the
            <a href="https://developers.google.com/terms/api-services-user-data-policy">Google API Services User Data Policy</a>,
            including the Limited Use requirements. Google user data is not sold, not used for advertising, not used to
            train AI or machine-learning models, and not read by people except to operate, secure or troubleshoot the
            service or where the law requires it.</p>

        <h2>Other data get-id keeps</h2>
        <ul>
            <li><strong>Access tokens.</strong> A token is shown once, when you create it; get-id stores only a SHA-256 hash of
                it, together with its name and the time it was created and last used.</li>
            <li><strong>Issued numbers.</strong> For each number: the repository, the document type, the document title you
                sent, the number, the account that asked for it and the time. This register is the purpose of the
                service and is kept for as long as the service runs.</li>
            <li><strong>API request log.</strong> For each REST or MCP request: the account and token name, the method, the
                endpoint, the request parameters, the response status and the duration.
                Kept for {{ config('getid.api_log_retention_days') }} days.</li>
            <li><strong>Registry change trail.</strong> Changes to projects, document types and issued numbers made by an
                administrator, with that administrator's email address, are written to the application log.
                @if ($logDays)
                    Kept for {{ $logDays }} days.
                @else
                    Kept until the log is cleared by the operator.
                @endif
            </li>
            <li><strong>Sessions.</strong> While you are signed in, get-id stores your session together with your IP address
                and browser user agent. A session expires after {{ config('session.lifetime') }} minutes of inactivity.</li>
        </ul>

        <h2>Cookies</h2>
        <p>get-id sets only the cookies it needs to work: a session cookie and a cookie that protects forms against
            cross-site request forgery. There are no analytics, advertising or third-party tracking cookies.</p>

        <h2>Sharing</h2>
        <p>Personal data is not shared with, sold or transferred to third parties. The service runs on a server
            administered by the operator, and its traffic passes through Cloudflare, which delivers the site and protects
            it and processes requests only for that purpose. Inside the service, administrators can see which account
            issued each number and the API request log; the change trail is kept on the server and read only by the
            operator.</p>

        <h2>Security</h2>
        <p>All traffic is encrypted with HTTPS. Tokens are stored only as hashes and can be revoked at any time on the
            token page. Access is limited to corporate accounts, and administrative functions to administrators.</p>

        <h2>Retention and deletion</h2>
        <p>When an employee leaves or asks for it, the account is deactivated: every token is revoked immediately and the
            account can no longer sign in. The name and email address stay attached to the numbers that account issued,
            because the register exists to record who took which number. To have your account deactivated, or to ask
            what data get-id holds about you, write to the contact below.</p>

        <h2>Changes</h2>
        <p>If this policy changes, the new version is published on this page with a new date.</p>

        <h2>Contact</h2>
        <p>Ruslan Novikov, <a href="mailto:ruslan.novikov@cas.ai">ruslan.novikov@cas.ai</a></p>
    </article>
@endsection
