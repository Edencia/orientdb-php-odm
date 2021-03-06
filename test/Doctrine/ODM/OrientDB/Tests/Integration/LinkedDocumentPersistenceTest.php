<?php

namespace Doctrine\ODM\OrientDB\Tests\Integration;

use Doctrine\ODM\OrientDB\Tests\Models\Linked\EmailAddress as EmailAddress;
use Doctrine\ODM\OrientDB\Tests\Models\Linked\Person as Person;
use Doctrine\ODM\OrientDB\Tests\Models\Linked\Phone as Phone;

/**
 * @group integration
 */
class LinkedDocumentPersistenceTest extends AbstractIntegrationTest
{
    protected function setUp() {
        $this->useModelSet('linked');
        parent::setUp();
    }

    /**
     * @test
     */
    public function persist_with_link() {
        $d       = new Person();
        $d->name = "Sydney";

        $e        = new EmailAddress();
        $e->type  = 'work';
        $e->email = 'syd@gmail.com';

        $d->email = $e;

        $this->dm->persist($d);
        $this->dm->flush();
        $this->dm->clear();
        $this->assertNotNull($d->rid, 'Person RID is null');
        $this->assertNotNull($e->rid, 'Email RID is null');

        /** @var Person $d */
        $d = $this->dm->findByRid($d->rid);
        $this->assertNotNull($d, 'missing Person');
        $this->assertEquals('Sydney', $d->name);
        $this->assertNotNull($d->email, 'email is null');

        /** @var EmailAddress $e */
        $e = $this->dm->findByRid($e->rid);
        $this->assertNotNull($e);

        $this->assertSame($e, $d->email);

        $this->assertEquals('work', $d->email->type);
        $this->assertEquals('syd@gmail.com', $d->email->email);

        return $d->rid;
    }

    /**
     * @test
     * @depends persist_with_link
     *
     * @param string $rid
     *
     * @return string
     */
    public function update_top_level_and_linked_document($rid) {
        /** @var Person $d */
        $d               = $this->dm->findByRid($rid);
        $d->name         = 'Cameron';
        $d->email->email = 'cam@gmail.com';

        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Cameron', $d->name);
        $this->assertNotNull($d->email);
        $this->assertEquals('cam@gmail.com', $d->email->email);

        return $rid;
    }

    /**
     * @test
     * @depends update_top_level_and_linked_document
     *
     * @param string $rid
     */
    public function update_linked_document_to_null($rid) {
        /** @var Person $d */
        $d        = $this->dm->findByRid($rid);
        $erid     = $d->email->rid;
        $d->email = null;

        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Cameron', $d->name);
        $this->assertNull($d->email);

        $this->assertNull($this->dm->findByRid($erid));
    }

    /**
     * @test
     * @depends persist_with_link
     *
     * @param string $rid
     */
    public function delete_document_with_linked($rid) {
        $document = $this->dm->findByRid($rid);
        $this->dm->remove($document);
        $this->dm->flush();
        unset($document);
        $this->dm->clear();

        $this->assertNull($this->dm->findByRid($rid));
    }

    /**
     * @test
     */
    public function persist_with_linked_list_document() {
        $d       = new Person();
        $d->name = "Sydney";

        $e1        = new EmailAddress();
        $e1->type  = 'work';
        $e1->email = 'syd-work@gmail.com';

        $e2        = new EmailAddress();
        $e2->type  = 'home';
        $e2->email = 'syd-home@gmail.com';

        $d->emails = [$e1, $e2];

        $this->dm->persist($d);
        $this->dm->flush();
        $this->dm->clear();
        $this->assertNotNull($d->rid);

        /** @var Person $d */
        $d = $this->dm->findByRid($d->rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertCount(2, $d->emails);

        return $d->rid;
    }

    /**
     * @test
     * @depends persist_with_linked_list_document
     *
     * @param string $rid
     *
     * @return string
     */
    public function update_with_linked_list_document($rid) {
        /** @var Person $d */
        $d                   = $this->dm->findByRid($rid);
        $d->emails[0]->email = 'cam@gmail.com';

        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertCount(2, $d->emails);
        $this->assertEquals('cam@gmail.com', $d->emails[0]->email);

        return $rid;
    }

    /**
     * @test
     * @depends persist_with_linked_list_document
     *
     * @param string $rid
     *
     * @return string
     */
    public function remove_item_from_linked_list_document($rid) {
        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        unset($d->emails[0]);

        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertCount(1, $d->emails);
        $this->assertEquals('syd-home@gmail.com', $d->emails[0]->email);

        return $rid;
    }

    /**
     * @test
     * @depends remove_item_from_linked_list_document
     *
     * @param string $rid
     *
     * @return string
     */
    public function clear_linked_list_document($rid) {
        /** @var Person $d */
        $d    = $this->dm->findByRid($rid);
        $erid = $d->emails->first()->rid;
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $d->emails->clear();

        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertCount(0, $d->emails);

        $this->assertNull($this->dm->findByRid($erid));

        return $rid;
    }

    /**
     * @test
     * @depends persist_with_linked_list_document
     *
     * @param string $rid
     */
    public function delete_with_linked_list_document($rid) {
        $d = $this->dm->findByRid($rid);
        $this->dm->remove($d);
        $this->dm->flush();
        unset($d);
        $this->dm->clear();

        $this->assertNull($this->dm->findByRid($rid));
    }

    /**
     * @test
     */
    public function persist_with_linked_map_document() {
        $d       = new Person();
        $d->name = "Sydney";

        $e1        = new Phone();
        $e1->phone = '4804441999';

        $e2        = new Phone();
        $e2->phone = '5554443333';

        $d->phones = [
            'home' => $e1,
            'work' => $e2,
        ];

        $this->dm->persist($d);
        $this->dm->flush();
        $this->dm->clear();
        $this->assertNotNull($d->rid);

        /** @var Person $d */
        $d = $this->dm->findByRid($d->rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertCount(2, $d->phones);
        $actual = $d->phones->getKeys();
        sort($actual);
        $this->assertEquals(['home', 'work'], $actual);

        return $d->rid;
    }

    /**
     * @test
     * @depends persist_with_linked_map_document
     */
    public function update_existing_item_in_linked_map_document($rid) {
        /** @var Person $d */
        $d                        = $this->dm->findByRid($rid);
        $d->phones['home']->phone = '4804442000';

        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertArrayHasKey('home', $d->phones);
        $this->assertEquals('4804442000', $d->phones['home']->phone);
    }

    /**
     * @test
     * @depends persist_with_linked_map_document
     */
    public function update_add_new_key_to_existing_linked_map_document($rid) {
        /** @var Person $d */
        $d                   = $this->dm->findByRid($rid);
        $p                   = new Phone();
        $p->phone            = '4801112222';
        $d->phones['mobile'] = $p;

        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertCount(3, $d->phones);
        $this->assertArrayHasKey('mobile', $d->phones);
        $this->assertEquals('4801112222', $d->phones['mobile']->phone);

        return $rid;
    }

    /**
     * @test
     * @depends update_add_new_key_to_existing_linked_map_document
     */
    public function update_remove_existing_key_from_existing_linked_map_document($rid) {
        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        unset($d->phones['mobile']);
        unset($d);
        $this->dm->flush();
        $this->dm->clear();

        /** @var Person $d */
        $d = $this->dm->findByRid($rid);
        $this->assertEquals('Sydney', $d->name);
        $this->assertCount(2, $d->phones);
        $this->assertArrayNotHasKey('mobile', $d->phones);
    }

    /**
     * @test
     * @depends persist_with_linked_map_document
     *
     * @param $rid
     */
    public function delete_with_linked_map_document($rid) {
        $d = $this->dm->findByRid($rid);
        $this->dm->remove($d);
        $this->dm->flush();
        unset($d);
        $this->dm->clear();

        $this->assertNull($this->dm->findByRid($rid));
    }
}