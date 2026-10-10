<?php
declare(strict_types=1);
require __DIR__ . '/../private/mcp-auth.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

check(mcp_redirect_allowed('https://chatgpt.com/connector_platform_oauth_redirect'), 'Stable ChatGPT redirect must be accepted.');
check(mcp_redirect_allowed('https://chatgpt.com/connector/oauth/callback_123-abc'), 'Callback-specific ChatGPT redirect must be accepted.');
check(!mcp_redirect_allowed('http://chatgpt.com/connector_platform_oauth_redirect'), 'HTTP redirect must be rejected.');
check(!mcp_redirect_allowed('https://chatgpt.com.evil.example/connector_platform_oauth_redirect'), 'Lookalike host must be rejected.');
check(!mcp_redirect_allowed('https://chatgpt.com/connector_platform_oauth_redirect?next=https://evil.example'), 'Redirect query strings must be rejected.');
check(!mcp_redirect_allowed('https://chatgpt.com/connector/oauth/callback#fragment'), 'Redirect fragments must be rejected.');
check(mcp_supported_scopes() === ['clients:read'], 'The MCP must expose read access only.');
$token=mcp_b64url(random_bytes(32));
check(preg_match('/^[A-Za-z0-9_-]{43}$/D',$token)===1, 'Opaque token encoding must be URL-safe and unpadded.');
echo "MCP OAuth smoke checks passed.\n";
