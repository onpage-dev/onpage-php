<?php

namespace Test;

use OnPage\File;
use OnPage\QueryBuilder;
use OnPage\Thing;

class MainTest extends \PHPUnit\Framework\TestCase
{
    private \OnPage\Api $api;

    public function setUp(): void
    {
        $this->api = new \OnPage\Api($_ENV['COMPANY'], $_ENV['TOKEN']);
    }

    public function testSchemaLoaded()
    {
        $this->assertSame(1, $this->api->getRequestCount());
        $this->assertTrue(mb_strlen($this->api->schema->label) > 0);
    }
    public function testPreloadedFilteredThings()
    {
        $query = $this->api->query('argomenti')
            ->with('prodotti');
        $this->assertSame(14, $query->first()->rel('prodotti')->count());

        $query->filterRelation('prodotti', function ($q) {
            $q->where('descrizione', 'like', '7w');
        });
        $this->assertSame(3, $query->first()->rel('prodotti')->count());
    }
    public function testSchemaStructure()
    {
        $res = $this->api->schema->resource('capitoli');
        foreach ($res->fields() as $field) {
            $this->assertIsString($field->getLabel());
            $this->assertIsString($field->getLabel('zh'));
            $this->assertIsString($field->name);
            $this->assertIsString($field->type);
            $this->assertIsString($field->unit ?? '');
            $this->assertIsBool($field->is_multiple);
            $this->assertIsBool($field->is_translatable);
        }
    }

    function testCountActivePrezzo()
    {
        $count = $this->api->query('prezzi')->count();
        $this->assertSame(1252, $count);
    }
    function testCountAllPrezzo()
    {
        $count = $this->api->query('prezzi')->withStatus('any')->count();
        $this->assertSame(1253, $count);
    }
    function testCountDeletedPrezzo()
    {
        $count = $this->api->query('prezzi')->withStatus('deleted')->count();
        $this->assertSame(1, $count);
    }
    function testGetDeletedItemShouldHaveData()
    {
        $prezzo = $this->api->query('prezzi')->withStatus('deleted')->first();
        $this->assertSame(238508, $prezzo?->id);
        $this->assertSame(18.8, $prezzo?->val('prezzo1'));
    }
    public function testMap()
    {
        $this->assertSame(1, $this->api->getRequestCount());

        $this->assertSame(
            [
                'Profili alluminio' => 236826,
                'Fari LED' => 236827,
                'Fari carrabiliiii' => 236828,
                'Proiettori LED' => 236829,
                'Sistemi a Plafone' => 236823,
                'Strip LED' => 236825,
                'Pannelli LED' => 236824,
                'Up & Down' => 236830,
                'Segnapasso LED' => 236831,
                'Sorgenti luminose LED' => 236832,
                'Accessori LED' => 236833,
                'Drivers' => 236834,
                'Sistemi di controllo' => 236835,
                'Componenti' => 236836,
                'Faretti in gesso' => 236837,
                'Downlight Led' => 236838,
                'Faretti Classici' => 236839,
                'Faretti LED' => 236840,
                'Faretti LED IP64-IP66' => 236841,
                'Profili LED Speciali' => 236842,
                'ARTICOLI FUORI STANDARD' => 236843,
            ],
            $this->api->query('capitoli')->map('descrizione', '_id')
        );
        $this->assertSame(
            [
                'Aluminium profiles' => 236826,
                'LED headlights' => 236827,
                'Drive-on headlights' => 236828,
                'LED projectors' => 236829,
                'Ceiling Systems' => 236823,
                'LED Strip' => 236825,
                'LED Panels' => 236824,
                'Up & Down' => 236830,
                'LED step markers' => 236831,
                'LED light sources' => 236832,
                'LED accessories' => 236833,
                'Drivers' => 236834,
                'Control systems' => 236835,
                'Components' => 236836,
                'Gypsum spotlights' => 236837,
                'Led Downlight' => 236838,
                'Classic Spotlights' => 236839,
                'LED Spotlights' => 236840,
                'IP64-IP66 LED spotlights' => 236841,
                'Special LED profiles' => 236842,
                'OUT OF STOCK ARTICLE' => 236843,
            ],
            $this->api->query('capitoli')->map('descrizione', '_id', 'gb')
        );
        $this->assertSame(3, $this->api->getRequestCount());
    }

    public function testGetFirstThing()
    {
        $cap = $this->api->query('capitoli')->first();
        $this->checkFirstChapter($cap);
        $cap_by_id = $this->api->query('capitoli')->where('_id', $cap->id)->first();
        $this->assertSame($cap->id, $cap_by_id->id);
    }

    public function testRelatedValues()
    {
        $thing = $this->api->query('capitoli')->with('argomenti')->find(236823);
        $this->assertSame(236823, $thing->id);
        $this->assertSame(236823, $thing->val('_id'));
        $this->assertSame([
            'Plafoniere LED',
            'Plafoniere'
        ], $thing->values('argomenti.intestazione')->all());
        $this->assertSame([
            236854,
            236855
        ], $thing->values('argomenti._id')->all());
    }

    public function testWriteFirstThing()
    {
        // Get the resource
        $res_cap = $this->api->schema->resource('capitoli');

        // Find an element
        $cap = $res_cap->query()->first();
        $indice = $cap->val('indice');

        // Add edit to the updater
        $saved = $cap->editor()->set('indice', $indice + 1)->save();
        $this->assertEquals([$cap->id], $saved);

        $cap = $res_cap->query()->first();
        $nuovo_indice = $cap->val('indice');
        $this->assertSame($indice + 1, $nuovo_indice);
    }
    public function testCreateTwoThings()
    {
        // Get the resource
        $res_cap = $this->api->schema->resource('capitoli');

        $count = $res_cap->query()->all()->count();

        $updater = $res_cap->writer();
        $updater->createThing()->set('indice', -2)->set('dist', 3);
        $updater->createThing()->set('indice', -3)->set('dist', 4);
        $updater->save();

        $new_count = $res_cap->query()->all()->count();
        $this->assertSame($count + 2, $new_count);
    }
    public function testDeleteTwoThings()
    {
        // Get the resource
        $res_cap = $this->api->schema->resource('capitoli');

        $to_delete = $res_cap->query()->where('indice', '<', 0)->all();
        // $this->assertSame(2, $to_delete->count());

        $deleted_ids = $res_cap->query()->where('indice', '<', 0)->delete();
        $this->assertEquals($to_delete->pluck('id')->all(), $deleted_ids);

        $count = $res_cap->query()->where('indice', '<', 0)->all();
        $this->assertSame(0, $count->count());
    }

    function testGetNonExistingFirst()
    {
        $cap = $this->api->query('capitoli')->offset(99999)->first();
        $this->assertNull($cap);
    }

    public function testFiles()
    {
        $arg = $this->api->query('argomenti')->first();
        $img = $arg->val('disegno1');
        $this->assertInstanceOf(File::class, $img);
        $this->assertSame('https://lithos.onpage.it/api/storage/dd03bec8a725366c6e6327ceb0b91ffd587be553/shutterstock_36442114-ok-NEW.jpg', $img->link());
        $this->assertSame('https://lithos.onpage.it/api/storage/dd03bec8a725366c6e6327ceb0b91ffd587be553.png/shutterstock_36442114-ok-NEW.png', $img->link([
            'ext' => 'png',
        ]));
    }

    public function testGetAllThings()
    {
        $caps = $this->api->query('capitoli')->all();
        /** @var Thing */
        $first = $caps->first();
        $this->checkFirstChapter($first);
        $this->assertSame(21, $caps->count());
    }
    public function testFind()
    {
        $thing = $this->api->query('capitoli')
            ->where('_id', 236823)
            ->first();
        $this->assertSame(236823, $thing->id);
    }
    public function testWhere()
    {
        $thing = $this->api->query('capitoli')
            ->where('descrizione', 'like', 'led')
            ->first();
        $this->assertSame(236827, $thing->id);
    }
    public function testWhereHas()
    {
        $res = $this->api->query('capitoli')
            ->whereHas('argomenti', function (QueryBuilder $q) {
                $q->where('intestazione', 'like', 'pro');
            })
            ->map('descrizione');
        $this->assertSame([
            'Profili alluminio' => 236826,
            'Proiettori LED' => 236829,
            'Profili LED Speciali' => 236842,
        ], $res);
    }

    public function testRelationIds()
    {
        $thing = $this->api->query('capitoli')->loadFields(['argomenti'])->first();
        $this->assertSame([236849], $thing->values('argomenti')->all());
    }

    public function testOnDemandRelations()
    {
        $thing = $this->api->query('capitoli')->first();
        $this->api->resetRequestCount();
        $this->api->allow_dynamic_relations = true;
        $this->checkArgomenti($thing);
        $this->api->allow_dynamic_relations = false;
        $this->assertSame(1, $this->api->getRequestCount());
    }

    public function testOnDemandNestedRelations()
    {
        $thing = $this->api->query('capitoli')->first();
        $this->api->resetRequestCount();
        $this->api->allow_dynamic_relations = true;
        $arts = $thing->rel('argomenti.prodotti.articoli');
        $this->api->allow_dynamic_relations = false;
        $this->assertSame(1, $this->api->getRequestCount());
        $this->assertSame(76, $arts->count());
    }

    public function testPreloadedThings()
    {
        $thing = $this->api->query('capitoli')->with('argomenti.prodotti')->first();
        $this->api->resetRequestCount();
        $this->checkArgomenti($thing);
        $this->assertSame(0, $this->api->getRequestCount());

        $thing = $this->api->query('capitoli')->with('argomenti.prodotti.articoli')->first();
        $this->api->resetRequestCount();
        $arts = $thing->rel('argomenti.prodotti.articoli');
        $this->assertSame(0, $this->api->getRequestCount());
        $this->assertSame(76, $arts->count());
    }
    private function checkFirstChapter(Thing $cap)
    {
        $this->assertNotNull($cap);
        $this->assertInstanceOf(\OnPage\Thing::class, $cap, 'Cannot pull first chapter');
        $this->assertSame(236826, $cap->id);
        $this->assertSame('Profili alluminio', $cap->val('descrizione'));
        $this->assertSame('Perfiles de aluminio', $cap->val('descrizione', 'es'));
        
        // Test fallback lang
        $this->api->schema->lang = 'zh';
        $this->api->schema->setFallbackLang(null);
        $this->assertSame(null, $cap->val('descrizione'));
        $this->api->schema->setFallbackLang('gb');
        $this->assertSame('Aluminium profiles', $cap->val('descrizione'));
        $this->api->schema->setFallbackLang(null);
        $this->assertSame(null, $cap->val('descrizione'));
        $this->api->schema->lang = 'it';
    }

    public function checkArgomenti(Thing $thing)
    {
        $this->assertCount(1, $thing->rel('argomenti'));
        $arg = $thing->rel('argomenti')->first();
        $this->assertSame('Architetturale;Domestico;Commerciale;Industriale;Arredamento;', $arg->val('nota10'));
        $this->assertSame('Architetturale;Domestico;Commerciale;Industriale;Arredamento;', $thing->val('argomenti.nota10'));
        foreach ($thing->rel('argomenti') as $arg) {
            $arg->val('nota10');
        }
    }
}
