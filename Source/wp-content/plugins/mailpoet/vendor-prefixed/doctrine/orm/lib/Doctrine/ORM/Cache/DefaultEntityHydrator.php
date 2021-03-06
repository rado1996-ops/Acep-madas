<?php

/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */
namespace MailPoetVendor\Doctrine\ORM\Cache;

if (!defined('ABSPATH')) exit;


use MailPoetVendor\Doctrine\Common\Util\ClassUtils;
use MailPoetVendor\Doctrine\ORM\Query;
use MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata;
use MailPoetVendor\Doctrine\ORM\EntityManagerInterface;
use MailPoetVendor\Doctrine\ORM\Utility\IdentifierFlattener;
/**
 * Default hydrator cache for entities
 *
 * @since   2.5
 * @author  Fabio B. Silva <fabio.bat.silva@gmail.com>
 */
class DefaultEntityHydrator implements \MailPoetVendor\Doctrine\ORM\Cache\EntityHydrator
{
    /**
     * @var \MailPoetVendor\Doctrine\ORM\EntityManager
     */
    private $em;
    /**
     * @var \MailPoetVendor\Doctrine\ORM\UnitOfWork
     */
    private $uow;
    /**
     * The IdentifierFlattener used for manipulating identifiers
     *
     * @var \MailPoetVendor\Doctrine\ORM\Utility\IdentifierFlattener
     */
    private $identifierFlattener;
    /**
     * @var array
     */
    private static $hints = array(\MailPoetVendor\Doctrine\ORM\Query::HINT_CACHE_ENABLED => \true);
    /**
     * @param \MailPoetVendor\Doctrine\ORM\EntityManagerInterface $em The entity manager.
     */
    public function __construct(\MailPoetVendor\Doctrine\ORM\EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->uow = $em->getUnitOfWork();
        $this->identifierFlattener = new \MailPoetVendor\Doctrine\ORM\Utility\IdentifierFlattener($em->getUnitOfWork(), $em->getMetadataFactory());
    }
    /**
     * {@inheritdoc}
     */
    public function buildCacheEntry(\MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata $metadata, \MailPoetVendor\Doctrine\ORM\Cache\EntityCacheKey $key, $entity)
    {
        $data = $this->uow->getOriginalEntityData($entity);
        $data = \array_merge($data, $metadata->getIdentifierValues($entity));
        // why update has no identifier values ?
        foreach ($metadata->associationMappings as $name => $assoc) {
            if (!isset($data[$name])) {
                continue;
            }
            if (!($assoc['type'] & \MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata::TO_ONE)) {
                unset($data[$name]);
                continue;
            }
            if (!isset($assoc['cache'])) {
                $targetClassMetadata = $this->em->getClassMetadata($assoc['targetEntity']);
                $associationIds = $this->identifierFlattener->flattenIdentifier($targetClassMetadata, $targetClassMetadata->getIdentifierValues($data[$name]));
                unset($data[$name]);
                foreach ($associationIds as $fieldName => $fieldValue) {
                    if (isset($targetClassMetadata->associationMappings[$fieldName])) {
                        $targetAssoc = $targetClassMetadata->associationMappings[$fieldName];
                        foreach ($assoc['targetToSourceKeyColumns'] as $referencedColumn => $localColumn) {
                            if (isset($targetAssoc['sourceToTargetKeyColumns'][$referencedColumn])) {
                                $data[$localColumn] = $fieldValue;
                            }
                        }
                    } else {
                        $data[$assoc['targetToSourceKeyColumns'][$targetClassMetadata->columnNames[$fieldName]]] = $fieldValue;
                    }
                }
                continue;
            }
            if (!isset($assoc['id'])) {
                $targetClass = \MailPoetVendor\Doctrine\Common\Util\ClassUtils::getClass($data[$name]);
                $targetId = $this->uow->getEntityIdentifier($data[$name]);
                $data[$name] = new \MailPoetVendor\Doctrine\ORM\Cache\AssociationCacheEntry($targetClass, $targetId);
                continue;
            }
            // handle association identifier
            $targetId = \is_object($data[$name]) && $this->uow->isInIdentityMap($data[$name]) ? $this->uow->getEntityIdentifier($data[$name]) : $data[$name];
            // @TODO - fix it !
            // handle UnitOfWork#createEntity hash generation
            if (!\is_array($targetId)) {
                $data[\reset($assoc['joinColumnFieldNames'])] = $targetId;
                $targetEntity = $this->em->getClassMetadata($assoc['targetEntity']);
                $targetId = array($targetEntity->identifier[0] => $targetId);
            }
            $data[$name] = new \MailPoetVendor\Doctrine\ORM\Cache\AssociationCacheEntry($assoc['targetEntity'], $targetId);
        }
        return new \MailPoetVendor\Doctrine\ORM\Cache\EntityCacheEntry($metadata->name, $data);
    }
    /**
     * {@inheritdoc}
     */
    public function loadCacheEntry(\MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata $metadata, \MailPoetVendor\Doctrine\ORM\Cache\EntityCacheKey $key, \MailPoetVendor\Doctrine\ORM\Cache\EntityCacheEntry $entry, $entity = null)
    {
        $data = $entry->data;
        $hints = self::$hints;
        if ($entity !== null) {
            $hints[\MailPoetVendor\Doctrine\ORM\Query::HINT_REFRESH] = \true;
            $hints[\MailPoetVendor\Doctrine\ORM\Query::HINT_REFRESH_ENTITY] = $entity;
        }
        foreach ($metadata->associationMappings as $name => $assoc) {
            if (!isset($assoc['cache']) || !isset($data[$name])) {
                continue;
            }
            $assocClass = $data[$name]->class;
            $assocId = $data[$name]->identifier;
            $isEagerLoad = $assoc['fetch'] === \MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata::FETCH_EAGER || $assoc['type'] === \MailPoetVendor\Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_ONE && !$assoc['isOwningSide'];
            if (!$isEagerLoad) {
                $data[$name] = $this->em->getReference($assocClass, $assocId);
                continue;
            }
            $assocKey = new \MailPoetVendor\Doctrine\ORM\Cache\EntityCacheKey($assoc['targetEntity'], $assocId);
            $assocPersister = $this->uow->getEntityPersister($assoc['targetEntity']);
            $assocRegion = $assocPersister->getCacheRegion();
            $assocEntry = $assocRegion->get($assocKey);
            if ($assocEntry === null) {
                return null;
            }
            $data[$name] = $this->uow->createEntity($assocEntry->class, $assocEntry->resolveAssociationEntries($this->em), $hints);
        }
        if ($entity !== null) {
            $this->uow->registerManaged($entity, $key->identifier, $data);
        }
        $result = $this->uow->createEntity($entry->class, $data, $hints);
        $this->uow->hydrationComplete();
        return $result;
    }
}
