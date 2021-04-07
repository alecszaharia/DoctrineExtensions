<?php

namespace Gedmo\Loggable;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\AbstractManagerRegistry;
use Doctrine\Persistence\ManagerRegistry;
use Loggable\Fixture\Document\Log\PageRevision;
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

    protected function setUp() :void
    {
        parent::setUp();

        $this->LoggableListener = new LoggableListener();

        $evm = new EventManager();
        $evm->addEventSubscriber($this->LoggableListener);

        $this->em = $this->getMockSqliteEntityManager(array(),$this->getDefaultORMMetadataDriverImplementation());
        $this->dm = $this->getMockDocumentManager('log_mongo_revisions',$this->getDefaultMongoODMMetadataDriverImplementation());

        $registry = $this->getMockBuilder(Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $registry->method('getManagerForClass')->with(PageRevision::class)->willReturn($this->dm);
        //$registry->method('getManagerForClass')->with(Page::class)->willReturn($this->em);

        $this->LoggableListener->setUsername('jules');
        $this->LoggableListener->setRegistry($registry);

        $this->em->getEventManager()->addEventSubscriber($this->LoggableListener);
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


        $pageRepo = $this->em->getRepository('Loggable\Fixture\Entity\Page');
        $pages = $pageRepo->findAll();
        $this->assertSame(2, count($pages));

        $pag0->setTitle('Title 2');
        $this->em->persist($pag0);
        $this->em->flush();

        $logRepo = $this->dm->getRepository('Loggable\Fixture\Document\Log\PageRevision');
        $logs = $logRepo->findAll();
        $this->assertSame(3, count($logs));
    }
}
