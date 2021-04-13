<?php

namespace Gedmo\Loggable;

use Doctrine\Common\Persistence\AbstractManagerRegistry;
use Loggable\Fixture\Entity\GeoLocation;
use Loggable\Fixture\Entity\Page;
use Tool\BaseTestCaseOM;
use Doctrine\Common\EventManager;
use Loggable\Fixture\Entity\Address;
use Loggable\Fixture\Entity\Article;
use Loggable\Fixture\Entity\RelatedArticle;
use Loggable\Fixture\Entity\Comment;
use Loggable\Fixture\Entity\Geo;

/**
 * These are tests for loggable behavior
 *
 * @author Zaharia Alexandru <alecszaharia@gmail.com>
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class LoggableEntityWithDocumentLogEntryTest extends BaseTestCaseOM
{
    const PAGE = 'Loggable\Fixture\Entity\Page';
    const PAGE_REVISION = 'Loggable\Fixture\Document\Log\PageRevision';

    private $LoggableListener;
    private $em;
    private $dm;

    protected function setUp()
    {
        parent::setUp();

        $this->LoggableListener = new LoggableListener();

        $evm = new EventManager();
        $evm->addEventSubscriber($this->LoggableListener);

        $this->em = $this->getMockSqliteEntityManager(array(),$this->getDefaultORMMetadataDriverImplementation());
        $this->dm = $this->getMockDocumentManager('fdsdf',$this->getDefaultMongoODMMetadataDriverImplementation());


        $registry = $this->getMockBuilder(AbstractManagerRegistry::classs)
                     ->disableOriginalConstructor()
                     ->setMethods(array('getManagerForClass','setService','getService','resetService','getAliasNamespace'))
                     ->getMock();

        $registry->expects(self::PAGE)->method('getManagerForClass')->willReturn($this->em);
        $registry->expects(self::PAGE_REVISION)->method('getManagerForClass')->willReturn($this->dm);

        $this->LoggableListener->setUsername('jules');
        $this->LoggableListener->setRegistry($registry);
    }


    /**
     * @test
     */
    public function shouldHandleClonedEntity()
    {
        $pag0 = new Page();
        $pag0->setTitle('Title');

        $this->em->persist($pag0);
        $this->em->flush();

        $art1 = clone $pag0;
        $art1->setTitle('Cloned');
        $this->em->persist($art1);
        $this->em->flush();

        $logRepo = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
        $logs = $logRepo->findAll();
        $this->assertSame(2, count($logs));
        $this->assertSame('create', $logs[0]->getAction());
        $this->assertSame('create', $logs[1]->getAction());
        $this->assertTrue($logs[0]->getObjectId() !== $logs[1]->getObjectId());
    }

    public function testLoggable()
    {
        $logRepo = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');
        $articleRepo = $this->em->getRepository(self::ARTICLE);
        $this->assertCount(0, $logRepo->findAll());

        $art0 = new Article();
        $art0->setTitle('Title');

        $this->em->persist($art0);
        $this->em->flush();

        $log = $logRepo->findOneByObjectId($art0->getId());

        $this->assertNotNull($log);
        $this->assertEquals('create', $log->getAction());
        $this->assertEquals(get_class($art0), $log->getObjectClass());
        $this->assertEquals('jules', $log->getUsername());
        $this->assertEquals(1, $log->getVersion());
        $data = $log->getData();
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertEquals($data['title'], 'Title');

        // test update
        $article = $articleRepo->findOneByTitle('Title');

        $article->setTitle('New');
        $this->em->persist($article);
        $this->em->flush();
        $this->em->clear();

        $log = $logRepo->findOneBy(array('version' => 2, 'objectId' => $article->getId()));
        $this->assertEquals('update', $log->getAction());

        // test delete
        $article = $articleRepo->findOneByTitle('New');
        $this->em->remove($article);
        $this->em->flush();
        $this->em->clear();

        $log = $logRepo->findOneBy(array('version' => 3, 'objectId' => 1));
        $this->assertEquals('remove', $log->getAction());
        $this->assertNull($log->getData());
    }

    public function testVersionControl()
    {
        $this->populate();
        $commentLogRepo = $this->em->getRepository(self::COMMENT_LOG);
        $commentRepo = $this->em->getRepository(self::COMMENT);

        $comment = $commentRepo->find(1);
        $this->assertEquals('m-v5', $comment->getMessage());
        $this->assertEquals('s-v3', $comment->getSubject());
        $this->assertEquals(2, $comment->getArticle()->getId());

        // test revert
        $commentLogRepo->revert($comment, 3);
        $this->assertEquals('s-v3', $comment->getSubject());
        $this->assertEquals('m-v2', $comment->getMessage());
        $this->assertEquals(1, $comment->getArticle()->getId());
        $this->em->persist($comment);
        $this->em->flush();

        // test get log entries
        $logEntries = $commentLogRepo->getLogEntries($comment);
        $this->assertCount(6, $logEntries);
        $latest = $logEntries[0];
        $this->assertEquals('update', $latest->getAction());
    }

    public function testLogEmbedded()
    {
        $address = $this->populateEmbedded();

        $logRepo = $this->em->getRepository('Gedmo\Loggable\Entity\LogEntry');

        $logEntries = $logRepo->getLogEntries($address);

        $this->assertCount(4, $logEntries);
        $this->assertCount(1, $logEntries[0]->getData());
        $this->assertCount(2, $logEntries[1]->getData());
        $this->assertCount(3, $logEntries[2]->getData());
        $this->assertCount(5, $logEntries[3]->getData());
    }

    protected function getUsedEntityFixtures()
    {
        return array(
            self::ARTICLE,
            self::COMMENT,
            self::COMMENT_LOG,
            self::RELATED_ARTICLE,
            'Gedmo\Loggable\Entity\LogEntry',
            'Loggable\Fixture\Entity\Address',
            'Loggable\Fixture\Entity\Geo',
        );
    }

    private function populateEmbedded()
    {
        $address = new Address();
        $address->setCity('city-v1');
        $address->setStreet('street-v1');

        $geo = new Geo(1.0000, 1.0000, new GeoLocation('Online'));

        $address->setGeo($geo);

        $this->em->persist($address);
        $this->em->flush();

        $geo2 = new Geo(2.0000, 2.0000, new GeoLocation('Offline'));
        $address->setGeo($geo2);

        $this->em->persist($address);
        $this->em->flush();

        $address->getGeo()->setLatitude(3.0000);
        $address->getGeo()->setLongitude(3.0000);

        $this->em->persist($address);
        $this->em->flush();

        $address->setStreet('street-v2');

        $this->em->persist($address);
        $this->em->flush();

        return $address;
    }

    private function populate()
    {
        $article = new RelatedArticle();
        $article->setTitle('a1-t-v1');
        $article->setContent('a1-c-v1');

        $comment = new Comment();
        $comment->setArticle($article);
        $comment->setMessage('m-v1');
        $comment->setSubject('s-v1');

        $this->em->persist($article);
        $this->em->persist($comment);
        $this->em->flush();

        $comment->setMessage('m-v2');
        $this->em->persist($comment);
        $this->em->flush();

        $comment->setSubject('s-v3');
        $this->em->persist($comment);
        $this->em->flush();

        $article2 = new RelatedArticle();
        $article2->setTitle('a2-t-v1');
        $article2->setContent('a2-c-v1');

        $comment->setArticle($article2);
        $this->em->persist($article2);
        $this->em->persist($comment);
        $this->em->flush();

        $comment->setMessage('m-v5');
        $this->em->persist($comment);
        $this->em->flush();
        $this->em->clear();
    }
}
