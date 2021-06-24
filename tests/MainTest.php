<?php

namespace Test;

use OnPage\File;
use OnPage\Thing;

class MainTest extends \PHPUnit\Framework\TestCase
{
    private \OnPage\Api $api;

    public function setUp(): void
    {
        $this->api = new \OnPage\Api($_ENV['COMPANY'], $_ENV['TOKEN']);
    }

    function testSchemaLoaded()
    {
        $this->assertSame(1, $this->api->getRequestCount());
        $this->assertTrue(mb_strlen($this->api->schema->label) > 0);
    }

    function testGetFirstThing()
    {
        $cap = $this->api->query('capitoli')->first();
        $this->checkFirstChapter($cap);
    }

    function testFiles()
    {
        $arg = $this->api->query('argomenti')->first();
        $img = $arg->val('disegno1');
        $this->assertInstanceOf(File::class, $img);
        $this->assertSame('https://lithos.onpage.it/api/storage/PMWJiNp8eYn2Hy3TevNU?name=shutterstock_36442114-ok-NEW.jpg', $img->link());
    }

    function testGetAllThings()
    {
        $caps = $this->api->query('capitoli')->all();
        $this->checkFirstChapter($caps->first());
        $this->assertSame(23, $caps->count());
    }
    function testFilters()
    {
        $thing = $this->api->query('capitoli')
            ->where('_id', 236823)
            ->first();
        $this->assertSame(236823, $thing?->id);

        $thing = $this->api->query('capitoli')
            ->where('descrizione', 'like', 'led')
            ->first();
        $this->assertSame(236827, $thing?->id);
    }

    function testOnDemandRelations()
    {
        $thing = $this->api->query('capitoli')->first();
        $this->api->resetRequestCount();
        $this->checkArgomenti($thing);
        $this->assertSame(1, $this->api->getRequestCount());
    }

    function testPreloadedThings()
    {
        $thing = $this->api->query('capitoli')->with('argomenti.prodotti')->first();
        $this->api->resetRequestCount();
        $this->checkArgomenti($thing);
        $this->assertSame(0, $this->api->getRequestCount());
    }

    private function checkFirstChapter(Thing $cap)
    {
        $this->assertNotNull($cap);
        $this->assertInstanceOf(\OnPage\Thing::class, $cap, 'Cannot pull first chapter');
        $this->assertSame(236826, $cap->id);
        $this->assertSame('Profili alluminio', $cap->val('descrizione')[0]);
    }

    function checkArgomenti(Thing $thing)
    {
        $this->assertCount(1, $thing->rel('argomenti'));
        $arg = $thing->rel('argomenti')->first();
        $this->assertSame('Architetturale;Domestico;Commerciale;Industriale;Arredamento;', $arg->val('nota10'));
        foreach ($thing->rel('argomenti') as $arg) {
            $arg->val('nota10');
        }
    }
}
