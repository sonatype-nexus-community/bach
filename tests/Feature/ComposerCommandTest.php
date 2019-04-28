<?php

namespace Tests\Feature;

use Tests\TestCase;

class ComposerCommandTest extends TestCase
{
    /**
     * Test audit composer package manifests
     *
     * @return void
     */
    public function testComposerCommand()
    {
        $v = $this->artisan('composer')->expectsOutput('Simplicity is the ultimate sophistication.');
             
    }
}
