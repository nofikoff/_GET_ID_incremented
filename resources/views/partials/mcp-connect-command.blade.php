{{--
    The one place the connect command is built — the token cabinet (real token) and the help page (placeholder)
    include it, so the two cannot drift apart. --scope user: visible in every project, not only the current one.
--}}
<pre>claude mcp add --scope user --transport http get-id {{ url('/mcp') }} --header "Authorization: Bearer {{ $token }}"</pre>
