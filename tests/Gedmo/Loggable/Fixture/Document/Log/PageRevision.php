<?php

namespace Loggable\Fixture\Document\Log;

use Gedmo\Loggable\Document\MappedSuperclass\AbstractLogEntry;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

/**
 * @ODM\Document(
 *     collection="test_page_revision_entries",
 *     repositoryClass="Gedmo\Loggable\Document\Repository\LogEntryRepository"
 * )
 */
class PageRevision extends AbstractLogEntry
{
}
