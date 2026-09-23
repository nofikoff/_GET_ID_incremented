<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * One JSON-RPC message to POST /mcp, as a remote client sends it. Without `_meta` it is the pre-2026
     * envelope, which laravel/mcp serves without the MCP-* mirror headers.
     *
     * @param  array<string, mixed>  $params
     */
    protected function mcp(string $method, array $params = []): TestResponse
    {
        return $this->postJson('mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function mcpTool(string $tool, array $arguments = []): TestResponse
    {
        return $this->mcp('tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);
    }
}
