<?php

namespace Doctrine\ODM\OrientDB\Mapping\Driver;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\Mapping\Driver\AnnotationDriver as AbstractAnnotationDriver;
use Doctrine\ODM\OrientDB\Mapping\Annotations\ChangeTrackingPolicy;
use Doctrine\ODM\OrientDB\Mapping\Annotations\Document;
use Doctrine\ODM\OrientDB\Mapping\Annotations\Embedded;
use Doctrine\ODM\OrientDB\Mapping\Annotations\EmbeddedDocument;
use Doctrine\ODM\OrientDB\Mapping\Annotations\EmbeddedList;
use Doctrine\ODM\OrientDB\Mapping\Annotations\EmbeddedMap;
use Doctrine\ODM\OrientDB\Mapping\Annotations\EmbeddedPropertyBase;
use Doctrine\ODM\OrientDB\Mapping\Annotations\EmbeddedSet;
use Doctrine\ODM\OrientDB\Mapping\Annotations\Link;
use Doctrine\ODM\OrientDB\Mapping\Annotations\LinkList;
use Doctrine\ODM\OrientDB\Mapping\Annotations\LinkMap;
use Doctrine\ODM\OrientDB\Mapping\Annotations\LinkPropertyBase;
use Doctrine\ODM\OrientDB\Mapping\Annotations\LinkSet;
use Doctrine\ODM\OrientDB\Mapping\Annotations\MappedSuperclass;
use Doctrine\ODM\OrientDB\Mapping\Annotations\Property;
use Doctrine\ODM\OrientDB\Mapping\Annotations\PropertyBase;
use Doctrine\ODM\OrientDB\Mapping\Annotations\RID;
use Doctrine\ODM\OrientDB\Mapping\Annotations\Version;
use Doctrine\ODM\OrientDB\Mapping\MappingException;

class AnnotationDriver extends AbstractAnnotationDriver
{
    protected $entityAnnotationClasses = [
        Document::class         => 1,
        MappedSuperclass::class => 2,
        EmbeddedDocument::class => 3,
    ];

    /**
     * Registers annotation classes to the common registry.
     *
     * This method should be called when bootstrapping your application.
     */
    public static function registerAnnotationClasses() {
        AnnotationRegistry::registerFile(__DIR__ . '/../Annotations/DoctrineAnnotations.php');
    }

    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @param string        $className
     * @param ClassMetadata $metadata
     *
     * @throws MappingException
     */
    public function loadMetadataForClass($className, ClassMetadata $metadata) {
        /** @var \Doctrine\ODM\OrientDB\Mapping\ClassMetadata $metadata */
        $classAnnotations = $this->reader->getClassAnnotations($metadata->getReflectionClass());
        if (count($classAnnotations) === 0) {
            throw MappingException::classIsNotAValidEntityOrMappedSuperClass($className);
        }

        $documentAnnots = [];
        foreach ($classAnnotations as $annot) {

            foreach ($this->entityAnnotationClasses as $annotClass => $i) {
                if ($annot instanceof $annotClass) {
                    $documentAnnots[$i] = $annot;
                    continue 2;
                }
            }

            switch (true) {
                case $annot instanceof ChangeTrackingPolicy:
                    $metadata->setChangeTrackingPolicy(constant('Doctrine\\ODM\\OrientDB\\Mapping\\ClassMetadata::CHANGETRACKING_' . $annot->value));
            }
        }

        // find the winning document annotation
        ksort($documentAnnots);
        $documentAnnot = reset($documentAnnots);

        if ($documentAnnot instanceof MappedSuperclass) {
            $metadata->isMappedSuperclass = true;
        } elseif ($documentAnnot instanceof EmbeddedDocument) {
            $metadata->isEmbeddedDocument = true;
        }

        if (isset($documentAnnot->class)) {
            $metadata->setOrientClass($documentAnnot->class);
            $metadata->isAbstract = $documentAnnot->abstract;
        }

        foreach ($metadata->reflClass->getProperties() as $property) {
            if (($metadata->isMappedSuperclass && !$property->isPrivate())
                ||
                $metadata->isInheritedField($property->name)
            ) {
                continue;
            }

            $pas = $this->reader->getPropertyAnnotations($property);
            foreach ($pas as $ann) {
                $mapping = [
                    'fieldName' => $property->getName(),
                    'nullable'  => false,
                ];

                if ($ann instanceof PropertyBase) {
                    if (!$ann->name) {
                        $ann->name = $property->getName();
                    }
                    $mapping['name'] = $ann->name;
                }

                switch (true) {
                    case $ann instanceof Property:
                        $mapping = $this->propertyToArray($property->getName(), $ann);
                        $metadata->mapField($mapping);
                        continue;

                    case $ann instanceof RID:
                        $metadata->mapRid($property->getName());
                        continue;

                    case $ann instanceof Version:
                        $metadata->mapVersion($property->getName());
                        continue;

                    case $ann instanceof Link:
                        $mapping             = $this->linkToArray($mapping, $ann);
                        $mapping['nullable'] = $ann->nullable;
                        $metadata->mapLink($mapping);
                        continue;

                    case $ann instanceof LinkList:
                        $mapping = $this->linkToArray($mapping, $ann);
                        $metadata->mapLinkList($mapping);
                        continue;

                    case $ann instanceof LinkSet:
                        $mapping = $this->linkToArray($mapping, $ann);
                        $metadata->mapLinkSet($mapping);
                        continue;

                    case $ann instanceof LinkMap:
                        $mapping = $this->linkToArray($mapping, $ann);
                        $metadata->mapLinkMap($mapping);
                        continue;

                    case $ann instanceof Embedded:
                        $mapping             = $this->embeddedToArray($mapping, $ann);
                        $mapping['nullable'] = $ann->nullable;
                        $metadata->mapEmbedded($mapping);
                        continue;

                    case $ann instanceof EmbeddedList:
                        $mapping = $this->embeddedToArray($mapping, $ann);
                        $metadata->mapEmbeddedList($mapping);
                        continue;

                    case $ann instanceof EmbeddedSet:
                        $mapping = $this->embeddedToArray($mapping, $ann);
                        $metadata->mapEmbeddedSet($mapping);
                        continue;

                    case $ann instanceof EmbeddedMap:
                        $mapping = $this->embeddedToArray($mapping, $ann);
                        $metadata->mapEmbeddedMap($mapping);
                        continue;
                }
            }
        }

        $isDocument = !($metadata->isEmbeddedDocument || $metadata->isMappedSuperclass || $metadata->isAbstract);

        if ($isDocument && empty($metadata->identifier)) {
            throw MappingException::missingRid($metadata->getName());
        }
    }

    public function &propertyToArray($fieldName, Property $prop) {
        $mapping = [
            'fieldName' => $fieldName,
            'name'      => $prop->name,
            'type'      => $prop->type,
            'nullable'  => $prop->nullable,
        ];

        return $mapping;
    }

    private function &linkToArray(array &$mapping, LinkPropertyBase $link) {
        $mapping['cascade']       = $link->cascade;
        $mapping['targetClass']   = $link->targetClass;
        $mapping['orphanRemoval'] = $link->orphanRemoval;

        if (!empty($link->parentProperty)) {
            $mapping['parentProperty'] = $link->parentProperty;
        }
        if (!empty($link->childProperty)) {
            $mapping['childProperty'] = $link->childProperty;
        }

        return $mapping;
    }

    private function &embeddedToArray(array &$mapping, EmbeddedPropertyBase $embed) {
        $mapping['targetClass'] = $embed->targetClass;

        return $mapping;
    }

    /**
     * Factory method for the Annotation Driver
     *
     * @param array|string $paths
     * @param Reader       $reader
     *
     * @return AnnotationDriver
     */
    public static function create($paths = [], Reader $reader = null) {
        if ($reader === null) {
            $reader = new AnnotationReader();
        }

        return new self($reader, $paths);
    }
}