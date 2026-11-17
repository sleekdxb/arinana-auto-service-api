<?php

namespace Tests;

use App\Models\Client;
use App\Models\Team;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Sends the next request as [client], or the first client created. */
    protected function asClient(?Client $client = null): static
    {
        $client ??= Client::orderBy('id')->firstOrFail();

        return $this->withToken($client->freshAccessToken());
    }

    /** Sends the next request as [member], or the first staff member created. */
    protected function asStaff(?Team $member = null): static
    {
        $member ??= Team::orderBy('id')->firstOrFail();

        return $this->withToken($member->freshAccessToken());
    }
}
