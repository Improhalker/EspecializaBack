<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustAnalyticsProxies extends TrustProxies
{
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        $this->proxies = config('analytics.trusted_proxies', []);
        parent::setTrustedProxyIpAddresses($request);
    }
}
